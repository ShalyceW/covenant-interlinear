<?php

use App\Models\BiblicalReference;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\HebrewWord;
use App\Models\Lemma;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function scriptureChapter(bool $published = true): Chapter
{
    $book = Book::create(['slug' => '1nefi', 'name' => '1 Nephi', 'hebrew_name' => 'נְפִי א', 'position' => 1]);
    $chapter = $book->chapters()->create([
        'number' => 1,
        'source_url' => 'https://brithamashiach.org/books/1nefi/chapters/1',
        'imported_at' => now(),
        'published_at' => $published ? now() : null,
    ]);
    $passage = $chapter->passages()->create([
        'reference' => '1 Nephi 1:1',
        'position' => 1,
        'hebrew_text' => 'אָנֹכִי נְפִי',
        'english_text' => 'I, Nephi',
    ]);
    $lemma = Lemma::create(['hebrew' => 'נְפִי', 'normalized' => 'נפי', 'transliteration' => 'nefi', 'definition' => 'Nephi']);
    $passage->hebrewWords()->createMany([
        ['position' => 1, 'hebrew' => 'אָנֹכִי', 'normalized' => 'אנכי'],
        ['position' => 2, 'hebrew' => 'נְפִי', 'normalized' => 'נפי', 'lemma_id' => $lemma->id, 'english_gloss' => 'Nephi'],
    ]);

    return $chapter;
}

test('the public reader returns the selected published chapter in source order', function () {
    scriptureChapter();

    $this->get('/library/1nefi/1')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Scripture/Show')
        ->where('chapter.passages.0.english_text', 'I, Nephi')
        ->where('chapter.passages.0.words.0.hebrew', 'אָנֹכִי')
        ->where('chapter.passages.0.words.1.english_gloss', 'Nephi'));
});

test('editorial proposal metadata stays out of reader word rows', function () {
    scriptureChapter();
    $word = HebrewWord::query()->where('normalized', 'נפי')->firstOrFail();
    $word->update(['enrichment' => [
        'lemma' => ['status' => 'proposed', 'candidates' => [[
            'strongs_number' => 'H5030', 'headword' => 'נָבִיא', 'definition' => 'prophet',
        ]]],
        'gloss' => ['status' => 'proposed'],
    ]]);

    $this->get('/library/1nefi/1')->assertInertia(fn (Assert $page) => $page
        ->missing('chapter.passages.0.words.1.review'));

    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    $this->actingAs($admin)->get('/library/1nefi/1')->assertInertia(fn (Assert $page) => $page
        ->missing('chapter.passages.0.words.1.review'));
});

test('only administrators can approve proposed glosses from the reader', function () {
    scriptureChapter();
    $word = HebrewWord::query()->where('normalized', 'אנכי')->firstOrFail();
    $ordinary = User::factory()->create(['email_verified_at' => now()]);
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

    $this->actingAs($ordinary)->patch(route('words.review', $word), [
        'field' => 'gloss', 'value' => 'I',
    ])->assertForbidden();

    $this->actingAs($admin)->patch(route('words.review', $word), [
        'field' => 'gloss', 'value' => 'I',
    ])->assertRedirect();

    expect($word->fresh()->english_gloss)->toBe('I')
        ->and($word->fresh()->enrichment['gloss']['status'])->toBe('confirmed')
        ->and($word->fresh()->enrichment['gloss']['source'])->toBe('editorial');
});

test('word study exposes gloss dropdown options only to administrators', function () {
    scriptureChapter();
    $word = HebrewWord::query()->where('normalized', 'נפי')->firstOrFail();
    $word->update([
        'english_gloss' => null,
        'enrichment' => [
            'lemma' => ['status' => 'proposed', 'candidates' => [[
                'strongs_number' => 'H5030', 'headword' => 'נָבִיא', 'definition' => 'prophet, spokesman',
            ]]],
            'gloss' => ['status' => 'unresolved'],
        ],
    ]);

    $this->getJson(route('word-study.show', $word))
        ->assertOk()
        ->assertJsonPath('review', null);

    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    $this->actingAs($admin)->getJson(route('word-study.show', $word))
        ->assertOk()
        ->assertJsonPath('review.gloss_status', 'unresolved')
        ->assertJsonPath('review.gloss_options.0', 'prophet');
});

test('unpublished chapters are excluded from the reader and concordance', function () {
    scriptureChapter(false);

    $this->get('/library/1nefi/1')->assertNotFound();
    $this->get(route('concordance.index', ['q' => 'נפי']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Concordance/Index')->has('results', 0));
});

test('concordance search and word study count published occurrences', function () {
    scriptureChapter();
    $word = HebrewWord::query()->where('normalized', 'נפי')->firstOrFail();

    $this->get(route('concordance.index', ['q' => 'נפי']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('results', 1)
        ->where('results.0.occurrence_count', 1));

    $this->getJson("/word-study/{$word->id}")->assertOk()
        ->assertJsonPath('occurrence_count', 1)
        ->assertJsonPath('lemma.definition', 'Nephi');
});

test('word study counts every occurrence before paginating the displayed contexts', function () {
    $chapter = scriptureChapter();
    $passage = $chapter->passages()->firstOrFail();
    $lemma = Lemma::query()->firstOrFail();
    $selected = HebrewWord::query()->where('normalized', 'נפי')->firstOrFail();
    $selected->update(['enrichment' => ['lemma' => ['status' => 'confirmed', 'source' => 'editorial']]]);
    foreach (range(3, 302) as $position) {
        $passage->hebrewWords()->create([
            'position' => $position,
            'hebrew' => 'נְפִי',
            'normalized' => 'נפי',
            'lemma_id' => $lemma->id,
            'enrichment' => ['lemma' => ['status' => 'confirmed', 'source' => 'editorial']],
        ]);
    }

    $this->getJson("/word-study/{$selected->id}")
        ->assertOk()
        ->assertJsonPath('occurrence_count', 301)
        ->assertJsonPath('pagination.last_page', 7)
        ->assertJsonCount(50, 'occurrences');
});

test('tentative lemma links remain exact-form concordance groups', function () {
    scriptureChapter();
    $word = HebrewWord::query()->where('normalized', 'נפי')->firstOrFail();
    $word->update(['enrichment' => ['lemma' => ['status' => 'proposed', 'source' => 'generated']]]);

    $this->get(route('concordance.index', ['q' => 'נפי']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('results.0.match_type', 'exact_written_form'));
});

test('print view uses the same passage and gloss data', function () {
    scriptureChapter();

    $this->get('/library/1nefi/1/print')->assertOk()
        ->assertSee('I, Nephi')
        ->assertSee('Nephi');
});

test('word study keeps Covenant and Biblical occurrence counts separate', function () {
    scriptureChapter();
    $word = HebrewWord::query()->where('normalized', 'נפי')->firstOrFail();
    $word->update(['enrichment' => ['lemma' => ['status' => 'confirmed', 'source' => 'editorial']]]);
    BiblicalReference::create([
        'lemma_id' => $word->lemma_id,
        'source' => 'Brit HaMashiach morphology',
        'raw_reference' => '1Sam.9',
        'normalized_reference' => '1Sam.9',
        'book' => '1 Samuel',
        'source_position' => 1,
        'source_url' => 'https://brithamashiach.org/api/volume/morphology/test',
    ]);
    BiblicalReference::create([
        'lemma_id' => $word->lemma_id,
        'source' => 'Brit HaMashiach morphology',
        'raw_reference' => '1Sam.9',
        'normalized_reference' => '1Sam.9',
        'book' => '1 Samuel',
        'source_position' => 2,
    ]);

    $this->getJson("/word-study/{$word->id}")->assertOk()
        ->assertJsonPath('occurrence_count', 1)
        ->assertJsonPath('biblical_reference_count', 2)
        ->assertJsonPath('biblical_unique_location_count', 1)
        ->assertJsonPath('biblical_references.0.raw_reference', '1Sam.9')
        ->assertJsonPath('biblical_references.0.chapter', null)
        ->assertJsonPath('occurrences.0.book_slug', '1nefi');
});

<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\HebrewWord;
use App\Models\Lemma;
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

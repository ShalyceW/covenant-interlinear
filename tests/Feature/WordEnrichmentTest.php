<?php

use App\Models\BiblicalReference;
use App\Models\Book;
use App\Models\Lemma;
use App\Services\HebrewTransliterator;
use App\Services\WordEnrichmentService;
use Illuminate\Support\Facades\Http;

test('surface transliteration handles vowel letters prefixes overrides and suffixes', function () {
    $transliterator = app(HebrewTransliterator::class);

    expect($transliterator->transliterate('יוֹם'))->toBe('yom')
        ->and($transliterator->transliterate('יְמֵי'))->toBe('yemei')
        ->and($transliterator->transliterate('וּבִימֵי'))->toBe('u-vi-mei')
        ->and($transliterator->transliterate('אֱלֹהִים'))->toBe('elohim')
        ->and($transliterator->transliterate('הָרִאשׁוֹן'))->toBe('ha-rishon')
        ->and($transliterator->transliterate('נְפִי'))->toBe('Nefi')
        ->and($transliterator->transliterate('וּנְפִי'))->toBe('u-Nefi');
});

test('chapter enrichment is contextual idempotent and preserves editorial corrections', function () {
    Http::fake([
        'https://brithamashiach.org/api/volume/morphology/*' => Http::response(['parses' => [[
            'lemma' => '3117', 'baseStrongs' => '3117', 'root' => 'יוֹם',
            'definition' => '1) day, time', 'morphology' => 'HNcmpc',
            'morphologyDescription' => 'Hebrew noun, masculine plural construct',
        ]]]),
    ]);
    $book = Book::create(['slug' => '1nefi', 'name' => '1 Nephi', 'position' => 1]);
    $chapter = $book->chapters()->create(['number' => 1, 'source_url' => 'https://brithamashiach.org/books/1nefi/chapters/1']);
    $passage = $chapter->passages()->create([
        'reference' => '1 Nephi 1:1', 'position' => 1,
        'hebrew_text' => 'יְמֵי נְפִי', 'english_text' => 'In the days of Nephi',
    ]);
    $manualLemma = Lemma::create(['hebrew' => 'נְפִי', 'normalized' => 'נפי', 'definition' => 'Nephi']);
    $days = $passage->hebrewWords()->create([
        'position' => 1, 'hebrew' => 'יְמֵי', 'normalized' => 'ימי',
        'morphology' => ['found_in_hebrew_bible' => true],
    ]);
    $name = $passage->hebrewWords()->create([
        'position' => 2, 'hebrew' => 'נְפִי', 'normalized' => 'נפי', 'lemma_id' => $manualLemma->id,
        'transliteration' => 'Nefi-manual', 'english_gloss' => 'Nephi (editorial)',
        'morphology' => ['proper_name' => true, 'found_in_hebrew_bible' => false],
        'enrichment' => [
            'lemma' => ['status' => 'confirmed', 'source' => 'editorial'],
            'transliteration' => ['status' => 'confirmed', 'source' => 'editorial'],
            'gloss' => ['status' => 'confirmed', 'source' => 'editorial'],
        ],
    ]);

    $first = app(WordEnrichmentService::class)->enrich($chapter);
    $second = app(WordEnrichmentService::class)->enrich($chapter);

    expect($days->fresh()->lemma->hebrew)->toBe('יוֹם')
        ->and($days->fresh()->transliteration)->toBe('yemei')
        ->and($days->fresh()->english_gloss)->toBe('days-of')
        ->and($days->fresh()->enrichment['gloss']['status'])->toBe('proposed')
        ->and($name->fresh()->lemma_id)->toBe($manualLemma->id)
        ->and($name->fresh()->transliteration)->toBe('Nefi-manual')
        ->and($name->fresh()->english_gloss)->toBe('Nephi (editorial)')
        ->and($first['after'])->toBe($second['after']);
});

test('dry run reports enrichment without modifying records', function () {
    Http::fake(['https://brithamashiach.org/api/volume/morphology/*' => Http::response([], 404)]);
    $book = Book::create(['slug' => '1nefi', 'name' => '1 Nephi', 'position' => 1]);
    $chapter = $book->chapters()->create(['number' => 1, 'source_url' => 'source']);
    $passage = $chapter->passages()->create(['reference' => '1 Nephi 1:1', 'position' => 1, 'hebrew_text' => 'יוֹם', 'english_text' => 'day']);
    $word = $passage->hebrewWords()->create(['position' => 1, 'hebrew' => 'יוֹם', 'normalized' => 'יום']);

    $result = app(WordEnrichmentService::class)->enrich($chapter, true);

    expect($result['after']['transliterations'])->toBe(1)
        ->and($word->fresh()->transliteration)->toBeNull();
});

test('enrichment replaces stale generated glosses with contextual and grammatical proposals', function () {
    Http::fake();
    $book = Book::create(['slug' => '1nefi', 'name' => '1 Nephi', 'position' => 1]);
    $chapter = $book->chapters()->create(['number' => 1, 'source_url' => 'source']);
    $summary = $chapter->passages()->create([
        'reference' => '1 Nephi 0e', 'position' => 1,
        'hebrew_text' => 'אָמַר', 'english_text' => 'The Lord warns Lehi.',
    ]);
    $verse = $chapter->passages()->create([
        'reference' => '1 Nephi 1:1', 'position' => 2,
        'hebrew_text' => 'אָמַר נָשִׁים', 'english_text' => 'He said women.',
    ]);
    $say = Lemma::create([
        'hebrew' => 'אָמַר', 'normalized' => 'אמר', 'definition' => 'to say',
        'strongs_number' => 'H559', 'source_reference' => 'https://brithamashiach.org/api/volume/morphology/אמר',
    ]);
    $woman = Lemma::create([
        'hebrew' => 'אִשָּׁה', 'normalized' => 'אשה', 'definition' => 'woman',
        'strongs_number' => 'H802', 'source_reference' => 'https://brithamashiach.org/api/volume/morphology/נשים',
    ]);
    $send = Lemma::create([
        'hebrew' => 'שָׁלַח', 'normalized' => 'שלח', 'definition' => 'to send',
        'strongs_number' => 'H7971', 'source_reference' => 'https://brithamashiach.org/api/volume/morphology/שלח',
    ]);
    $unsupported = Lemma::create([
        'hebrew' => 'אוֹי', 'normalized' => 'אוי', 'definition' => 'interj',
        'strongs_number' => 'H188', 'source_reference' => 'https://brithamashiach.org/api/volume/morphology/אוי',
    ]);
    $generated = [
        'lemma' => ['status' => 'confirmed', 'source' => 'brithamashiach-morphology'],
        'gloss' => ['status' => 'proposed', 'source' => 'dictionary-morphology-context-rule-v1'],
    ];
    $summaryWord = $summary->hebrewWords()->create([
        'position' => 1, 'hebrew' => 'אָמַר', 'normalized' => 'אמר', 'lemma_id' => $say->id,
        'english_gloss' => 'v', 'morphology' => ['code' => 'HVqp3ms'], 'enrichment' => $generated,
    ]);
    $finiteVerb = $verse->hebrewWords()->create([
        'position' => 1, 'hebrew' => 'אָמַר', 'normalized' => 'אמר', 'lemma_id' => $say->id,
        'english_gloss' => 'say', 'morphology' => ['code' => 'HVqp3ms'], 'enrichment' => $generated,
    ]);
    $plural = $verse->hebrewWords()->create([
        'position' => 2, 'hebrew' => 'נָשִׁים', 'normalized' => 'נשים', 'lemma_id' => $woman->id,
        'english_gloss' => 'womans', 'morphology' => ['code' => 'HNcfp'], 'enrichment' => $generated,
    ]);
    $sent = $verse->hebrewWords()->create([
        'position' => 3, 'hebrew' => 'וַיְשַׁלְּחֵהוּ', 'normalized' => 'וישלחהו', 'lemma_id' => $send->id,
        'english_gloss' => 'and-he-sended-him', 'morphology' => ['code' => 'HC/Vpw3ms/Sp3ms'], 'enrichment' => $generated,
    ]);
    $uncertain = $verse->hebrewWords()->create([
        'position' => 4, 'hebrew' => 'אוֹי', 'normalized' => 'אוי', 'lemma_id' => $unsupported->id,
        'english_gloss' => 'interj-me', 'morphology' => ['code' => 'HTj'], 'enrichment' => $generated,
    ]);

    app(WordEnrichmentService::class)->enrich($chapter);

    expect($summaryWord->fresh()->english_gloss)->toBe('he-said')
        ->and($summaryWord->fresh()->enrichment['gloss']['source'])->toBe('chapter-context-proposal')
        ->and($finiteVerb->fresh()->english_gloss)->toBe('he-said')
        ->and($plural->fresh()->english_gloss)->toBe('women')
        ->and($sent->fresh()->english_gloss)->toBe('and-he-sent-him')
        ->and($uncertain->fresh()->english_gloss)->toBe('woe')
        ->and($uncertain->fresh()->enrichment['gloss']['source'])->toBe('dictionary-morphology-context-rule-v2');
});

test('legacy placeholder roots are cleared and lemma transliterations are repaired', function () {
    Http::fake();
    $book = Book::create(['slug' => '1nefi', 'name' => '1 Nephi', 'position' => 1]);
    $chapter = $book->chapters()->create(['number' => 1, 'source_url' => 'source']);
    $lemma = Lemma::create([
        'hebrew' => 'אֲשֶׁר', 'normalized' => 'אשר', 'definition' => 'N/A',
        'transliteration' => null, 'root' => 'N/A', 'strongs_number' => 'H834a',
    ]);

    app(WordEnrichmentService::class)->enrich($chapter);

    expect($lemma->fresh()->root)->toBeNull()
        ->and($lemma->fresh()->transliteration)->toBe('asher')
        ->and($lemma->fresh()->definition)->toBe('who, which, that');
});

test('extended Strong identifiers use the local dictionary and import opaque biblical references idempotently', function () {
    Http::fake([
        'https://brithamashiach.org/api/volume/morphology/*' => Http::response(['parses' => [[
            'lemma' => 'd/5892 b',
            'baseStrongs' => '5892 b',
            'root' => 'N/A',
            'definition' => 'N/A',
            'morphology' => 'HTd/Ncfsa',
            'morphologyDescription' => 'Hebrew definite article + feminine singular noun',
            'occurrences' => ['1Chr.41', '1Chr.8', '1Chr.8'],
        ]]]),
    ]);
    $dictionary = Lemma::create([
        'hebrew' => 'עִיר', 'normalized' => 'עיר', 'transliteration' => 'ir',
        'definition' => 'city, town', 'strongs_number' => 'H5892b',
        'base_strongs_number' => 'H5892', 'source_reference' => 'STEPBible TBESH',
    ]);
    $book = Book::create(['slug' => '1nefi', 'name' => '1 Nephi', 'position' => 1]);
    $chapter = $book->chapters()->create(['number' => 1, 'source_url' => 'source']);
    $passage = $chapter->passages()->create([
        'reference' => 'Test 1:1', 'position' => 1, 'hebrew_text' => 'הָעִיר', 'english_text' => 'the city',
    ]);
    $word = $passage->hebrewWords()->create([
        'position' => 1, 'hebrew' => 'הָעִיר', 'normalized' => 'העיר',
        'morphology' => ['found_in_hebrew_bible' => true],
    ]);

    app(WordEnrichmentService::class)->enrich($chapter);
    app(WordEnrichmentService::class)->enrich($chapter, retryUnresolved: true);

    expect($word->fresh()->lemma_id)->toBe($dictionary->id)
        ->and($dictionary->fresh()->strongs_number)->toBe('H5892b')
        ->and($dictionary->fresh()->base_strongs_number)->toBe('H5892')
        ->and($dictionary->fresh()->source_lexical_id)->toBe('d/5892 b')
        ->and(BiblicalReference::count())->toBe(3)
        ->and(BiblicalReference::query()->where('raw_reference', '1Chr.8')->count())->toBe(2)
        ->and(BiblicalReference::first()->book)->toBe('1 Chronicles')
        ->and(BiblicalReference::first()->chapter)->toBeNull()
        ->and(BiblicalReference::first()->verse)->toBeNull();
});

test('ambiguous parses remain proposed and do not import references from unselected candidates', function () {
    Http::fake([
        'https://brithamashiach.org/api/volume/morphology/*' => Http::response(['parses' => [
            [
                'lemma' => '123', 'baseStrongs' => '123', 'root' => 'אָב',
                'definition' => 'alpha', 'morphology' => 'HNcmsa', 'occurrences' => ['Gen.1'],
            ],
            [
                'lemma' => '456', 'baseStrongs' => '456', 'root' => 'אוֹר',
                'definition' => 'beta', 'morphology' => 'HNcmsa', 'occurrences' => ['Exod.2'],
            ],
        ]]),
    ]);
    $book = Book::create(['slug' => 'test', 'name' => 'Test', 'position' => 1]);
    $chapter = $book->chapters()->create(['number' => 1, 'source_url' => 'source']);
    $passage = $chapter->passages()->create([
        'reference' => 'Test 1:1', 'position' => 1, 'hebrew_text' => 'דָּבָר', 'english_text' => 'unrelated context',
    ]);
    $word = $passage->hebrewWords()->create([
        'position' => 1, 'hebrew' => 'דָּבָר', 'normalized' => 'דבר',
        'morphology' => ['found_in_hebrew_bible' => true],
    ]);

    app(WordEnrichmentService::class)->enrich($chapter);

    expect($word->fresh()->lemma->strongs_number)->toBe('H123')
        ->and($word->fresh()->enrichment['lemma']['status'])->toBe('proposed')
        ->and($word->fresh()->english_gloss)->toBe('alpha')
        ->and($word->fresh()->enrichment['lemma']['candidates'])->toHaveCount(2)
        ->and(BiblicalReference::count())->toBe(0);
});

test('a failed morphology refresh retains verified assignments and imported references', function () {
    Http::fake(['https://brithamashiach.org/api/volume/morphology/*' => Http::response([], 503)]);
    $book = Book::create(['slug' => 'test', 'name' => 'Test', 'position' => 1]);
    $chapter = $book->chapters()->create(['number' => 1, 'source_url' => 'source']);
    $passage = $chapter->passages()->create([
        'reference' => 'Test 1:1', 'position' => 1, 'hebrew_text' => 'יוֹם', 'english_text' => 'day',
    ]);
    $lemma = Lemma::create([
        'hebrew' => 'יוֹם', 'normalized' => 'יום', 'strongs_number' => 'H3117',
    ]);
    $word = $passage->hebrewWords()->create([
        'position' => 1, 'hebrew' => 'יוֹם', 'normalized' => 'יום', 'lemma_id' => $lemma->id,
        'morphology' => ['found_in_hebrew_bible' => true],
        'enrichment' => ['lemma' => ['status' => 'confirmed', 'source' => 'morphology-source-unavailable']],
    ]);
    BiblicalReference::create([
        'lemma_id' => $lemma->id, 'source' => 'Brit HaMashiach morphology',
        'raw_reference' => 'Gen.1', 'normalized_reference' => 'Gen.1',
        'book' => 'Genesis', 'source_position' => 1,
    ]);

    app(WordEnrichmentService::class)->enrich($chapter, retryUnresolved: true);

    expect($word->fresh()->lemma_id)->toBe($lemma->id)
        ->and($word->fresh()->enrichment['lemma']['status'])->toBe('confirmed')
        ->and($word->fresh()->enrichment['lemma'])->toHaveKey('refresh_failure')
        ->and(BiblicalReference::count())->toBe(1);
});

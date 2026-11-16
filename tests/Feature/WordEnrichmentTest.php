<?php

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
        ->and($uncertain->fresh()->english_gloss)->toBeNull()
        ->and($uncertain->fresh()->enrichment['gloss']['source'])->toBe('insufficient-contextual-evidence');
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

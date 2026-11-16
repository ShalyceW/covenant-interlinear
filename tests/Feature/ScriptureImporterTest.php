<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\HebrewWord;
use App\Models\Passage;
use App\Services\ScriptureImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function fakeScriptureSource(bool $morphologyFound = true): void
{
    Http::fake([
        'https://brithamashiach.org/api/volume/books' => Http::response([[
            'name' => '1nefi',
            'hebrewName' => 'נְפִי א',
            'ccBoMName' => '1 Nephi',
            'chapters' => [['chapterNum' => 1, 'hebChapterNum' => 'א']],
        ]]),
        'https://brithamashiach.org/api/volume/books/1nefi/chapters/1' => Http::response([
            'chapterNum' => 1,
            'hebChapterNum' => 'א',
            'parts' => [[
                'paraNum' => 1,
                'type' => 'TextParagraph',
                'sentences' => [[
                    'sentenceRef' => '1a',
                    'ldsRef' => '1:1',
                    'text' => [
                        'hebText' => 'אָנֹכִי נְפִי מִכָּל־לֶקַח׃',
                        'hebNoNiqqudText' => 'אנכי נפי מכל־לקח׃',
                        'ccText' => 'I Nephi received every teaching.',
                    ],
                    'annotatedHebWords' => [
                        '1' => ['isPN' => false, 'inHB' => true],
                        '2' => ['isPN' => true, 'inHB' => false],
                        '3' => ['isPN' => false, 'inHB' => true],
                    ],
                ]],
            ]],
        ]),
        'https://brithamashiach.org/api/volume/morphology/*' => $morphologyFound
            ? Http::response([
                'parses' => [[
                    'baseStrongs' => '595',
                    'morphology' => 'HPp1cs',
                    'morphologyDescription' => 'Hebrew Pronoun (personal), 1st person, common singular',
                    'root' => 'אָנֹכִי',
                    'definition' => 'I (first person singular)',
                ]],
            ])
            : Http::response(['message' => 'No morphology entry found for surface form.'], 404),
    ]);
}

test('a chapter import preserves exact source text and logical Hebrew word order', function () {
    fakeScriptureSource();

    $result = app(ScriptureImporter::class)->importChapter('1nefi', 1);

    expect($result['passages_created'])->toBe(1)
        ->and($result['words_created'])->toBe(3)
        ->and(Passage::first()->hebrew_text)->toBe('אָנֹכִי נְפִי מִכָּל־לֶקַח׃')
        ->and(Passage::first()->english_text)->toBe('I Nephi received every teaching.')
        ->and(Passage::first()->hebrewWords->pluck('hebrew')->all())
        ->toBe(['אָנֹכִי', 'נְפִי', 'מִכָּל־לֶקַח׃']);
});

test('duplicate imports do not duplicate records or discard annotations', function () {
    fakeScriptureSource();
    $importer = app(ScriptureImporter::class);
    $importer->importChapter('1nefi', 1);
    HebrewWord::query()->where('position', 2)->update(['english_gloss' => 'Nephi']);

    $result = $importer->importChapter('1nefi', 1);

    expect(Chapter::count())->toBe(1)
        ->and(Passage::count())->toBe(1)
        ->and(HebrewWord::count())->toBe(3)
        ->and(HebrewWord::query()->where('position', 2)->value('english_gloss'))->toBe('Nephi')
        ->and($result['words_created'])->toBe(0);
});

test('an entire verified book imports one chapter at a time', function () {
    fakeScriptureSource();

    $results = app(ScriptureImporter::class)->importBook('1nefi');

    expect($results)->toHaveCount(1)
        ->and($results[0])->not->toHaveKey('error')
        ->and(Chapter::count())->toBe(1)
        ->and(Passage::count())->toBe(1);
});

test('a local JSON chapter can import for an existing verified book without remote access', function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    Book::create(['slug' => '1nefi', 'name' => '1 Nephi', 'hebrew_name' => 'נְפִי א', 'position' => 1]);
    $payload = [
        'chapterNum' => 1,
        'parts' => [['sentences' => [[
            'sentenceRef' => '1a',
            'ldsRef' => '1:1',
            'text' => ['hebText' => 'אָנֹכִי נְפִי', 'ccText' => 'I, Nephi'],
            'annotatedHebWords' => [],
        ]]]],
    ];
    Storage::disk('local')->put('chapter.json', json_encode($payload, JSON_THROW_ON_ERROR));

    $result = app(ScriptureImporter::class)->importChapter(
        '1nefi',
        1,
        Storage::disk('local')->path('chapter.json'),
    );

    expect($result['passages_created'])->toBe(1)
        ->and($result['words_created'])->toBe(2);
});

test('verified lexicon lookup links a lemma without inventing a contextual gloss', function () {
    fakeScriptureSource();
    $importer = app(ScriptureImporter::class);
    $importer->importChapter('1nefi', 1);
    $word = HebrewWord::query()->where('position', 1)->firstOrFail();

    expect($importer->lookupLexicon($word))->toBeTrue()
        ->and($word->fresh()->lemma->strongs_number)->toBe('H595')
        ->and($word->fresh()->lemma->definition)->toBe('I (first person singular)')
        ->and($word->fresh()->morphology['code'])->toBe('HPp1cs')
        ->and($word->fresh()->english_gloss)->toBeNull();
});

test('a missing remote morphology entry returns a normal not-found result', function () {
    fakeScriptureSource(false);
    $importer = app(ScriptureImporter::class);
    $importer->importChapter('1nefi', 1);
    $word = HebrewWord::query()->where('position', 2)->firstOrFail();
    expect($importer->lookupLexicon($word))->toBeFalse()
        ->and($word->fresh()->lemma_id)->toBeNull();
});

test('lexicon lookup preserves ambiguous candidates instead of choosing the first parse', function () {
    fakeScriptureSource();
    app(ScriptureImporter::class)->importChapter('1nefi', 1);
    $word = HebrewWord::query()->where('position', 1)->firstOrFail();
    $payload = ['parses' => [
        ['lemma' => '1697', 'baseStrongs' => '1697', 'root' => 'דָּבָר', 'morphology' => 'HNcmsa'],
        ['lemma' => '1696', 'baseStrongs' => '1696', 'root' => 'דָבַר', 'morphology' => 'HVqr'],
    ]];

    expect(app(ScriptureImporter::class)->applyLexiconPayload($word, $payload))->toBeFalse()
        ->and($word->fresh()->lemma_id)->toBeNull()
        ->and($word->fresh()->enrichment['lemma']['status'])->toBe('proposed')
        ->and($word->fresh()->enrichment['lemma']['candidates'])->toHaveCount(2);
});

test('verified fallbacks resolve a missing source headword and preserve lexical suffixes', function () {
    fakeScriptureSource();
    app(ScriptureImporter::class)->importChapter('1nefi', 1);
    $word = HebrewWord::query()->where('position', 1)->firstOrFail();
    $payload = ['parses' => [[
        'lemma' => '5612 a', 'baseStrongs' => '5612 a', 'root' => 'N/A',
        'definition' => 'N/A', 'morphology' => 'HNcmsc',
    ]]];

    expect(app(ScriptureImporter::class)->applyLexiconPayload($word, $payload))->toBeTrue()
        ->and($word->fresh()->lemma->strongs_number)->toBe('H5612a')
        ->and($word->fresh()->lemma->hebrew)->toBe('סֵפֶר')
        ->and($word->fresh()->lemma->root)->toBeNull();
});

<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\HebrewWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScriptureController extends Controller
{
    public function home(): Response|RedirectResponse
    {
        $chapter = Chapter::query()->published()->with('book')->orderBy('book_id')->orderBy('number')->first();

        return $chapter
            ? to_route('scripture.show', [$chapter->book->slug, $chapter->number])
            : Inertia::render('Welcome');
    }

    public function show(Book $book, int $chapter): Response
    {
        $selected = $book->chapters()->published()->where('number', $chapter)->firstOrFail();
        $selected->load(['passages.hebrewWords.lemma']);

        return Inertia::render('Scripture/Show', [
            'book' => $book->only('id', 'slug', 'name', 'hebrew_name'),
            'chapter' => [
                'id' => $selected->id,
                'number' => $selected->number,
                'passages' => $selected->passages->map(fn ($passage) => [
                    'id' => $passage->id,
                    'reference' => $passage->reference,
                    'hebrew_text' => $passage->hebrew_text,
                    'english_text' => $passage->english_text,
                    'words' => $passage->hebrewWords->map(fn ($word) => [
                        'id' => $word->id,
                        'position' => $word->position,
                        'hebrew' => $word->hebrew,
                        'normalized' => $word->normalized,
                        'transliteration' => $word->transliteration,
                        'english_gloss' => $word->english_gloss,
                        'has_lemma' => $word->lemma !== null,
                    ]),
                ]),
            ],
            'books' => Book::query()->whereHas('publishedChapters')->with(['publishedChapters:id,book_id,number'])
                ->orderBy('position')->get(['id', 'slug', 'name']),
            'chapters' => $book->publishedChapters()->get(['id', 'number']),
        ]);
    }

    /** @return array<string, mixed> */
    private function reviewData(HebrewWord $word): array
    {
        $candidates = collect((array) data_get($word->enrichment, 'lemma.candidates', []))
            ->filter(fn (mixed $candidate): bool => is_array($candidate) && filled($candidate['headword'] ?? null))
            ->unique('strongs_number')
            ->map(fn (array $candidate): array => [
                'value' => (string) $candidate['strongs_number'],
                'label' => trim((string) $candidate['headword'].' · '.(string) $candidate['strongs_number']
                    .(filled($candidate['definition'] ?? null) ? ' · '.$this->shortDefinition((string) $candidate['definition']) : '')),
            ])->values();

        $glosses = collect();
        foreach ((array) data_get($word->enrichment, 'lemma.candidates', []) as $candidate) {
            if (is_array($candidate) && filled($candidate['definition'] ?? null)) {
                $glosses = $glosses->concat($this->definitionGlossOptions((string) $candidate['definition']));
            }
        }
        $configured = $word->lemma?->strongs_number === null
            ? null
            : config("hebrew.lexical_glosses.{$word->lemma->strongs_number}");
        $glosses = $glosses->concat(is_array($configured) ? $configured : [$configured]);
        $glosses = $glosses->concat($this->definitionGlossOptions($word->lemma?->definition));
        foreach ((array) $word->lemma?->alternate_definitions as $definition) {
            if (filled($definition['gloss'] ?? null)) {
                $glosses = $glosses->concat(preg_split('/\s*:\s*/', (string) $definition['gloss']) ?: []);
            }
            $glosses = $glosses->concat($this->definitionGlossOptions($definition['definition'] ?? null));
        }

        return [
            'lemma_status' => data_get($word->enrichment, 'lemma.status', 'unresolved'),
            'gloss_status' => data_get($word->enrichment, 'gloss.status', 'unresolved'),
            'selected_lemma' => $word->lemma?->strongs_number,
            'lemma_options' => $candidates->all(),
            'gloss_options' => $glosses->filter(fn ($gloss) => is_string($gloss) && trim($gloss) !== '')
                ->map(fn (string $gloss): string => str_replace(' ', '-', trim($gloss)))
                ->unique()->take(20)->values()->all(),
        ];
    }

    /** @return list<string> */
    private function definitionGlossOptions(?string $definition): array
    {
        if (blank($definition) || strtoupper(trim((string) $definition)) === 'N/A') {
            return [];
        }

        $definition = preg_replace('/\b\d+[a-z]?\d*\)\s*/i', ';', (string) $definition) ?? (string) $definition;
        $definition = preg_replace('/\([^)]*\)/', '', $definition) ?? $definition;
        $parts = preg_split('/[,;|\n]+/', $definition, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(collect($parts)
            ->map(fn (string $part): string => trim(preg_replace('/^\s*:?\s*(?:to\s+)?/i', '', $part) ?? $part, " \t\n\r\0\x0B.-"))
            ->filter(fn (string $part): bool => $part !== ''
                && str_word_count($part) <= 5
                && preg_match('/(?:also means|[HG]\d|["=])/i', $part) !== 1
                && preg_match('/^(?:noun|verb|adjective|adverb|prep|pronoun|particle)$/i', $part) !== 1)
            ->values()->all());
    }

    private function shortDefinition(string $definition): string
    {
        $definition = preg_replace('/^\s*:?\s*|\b\d+[a-z]?\d*\)\s*/i', '', $definition) ?? $definition;
        $sense = trim(explode(';', explode("\n", $definition)[0])[0]);
        $sense = trim(explode(',', $sense)[0]);

        return mb_substr($sense, 0, 80);
    }

    public function wordStudy(Request $request, HebrewWord $word): JsonResponse
    {
        $word->load(['lemma', 'passage.chapter']);
        $confirmedLemma = $word->hasConfirmedLemma();
        $query = HebrewWord::query()
            ->with(['passage.chapter.book', 'passage.hebrewWords'])
            ->whereHas('passage.chapter', fn ($query) => $query->whereNotNull('published_at'))
            ->when(
                $confirmedLemma,
                fn ($query) => $query->where('lemma_id', $word->lemma_id)->where('enrichment->lemma->status', 'confirmed'),
                fn ($query) => $query->where('normalized', $word->normalized),
            )
            ->orderBy('passage_id')
            ->orderBy('position');
        $occurrenceCount = (clone $query)->count();
        $page = max(1, $request->integer('page', 1));
        $perPage = 50;
        $occurrences = $query->forPage($page, $perPage)->get();
        $biblicalPage = max(1, $request->integer('bible_page', 1));
        $biblicalPerPage = 50;
        $biblicalQuery = $word->lemma?->biblicalReferences()->getQuery();
        $biblicalCount = $biblicalQuery === null ? 0 : (clone $biblicalQuery)->count();
        $uniqueBiblicalLocations = $biblicalQuery === null
            ? 0
            : (clone $biblicalQuery)->distinct()->count('raw_reference');
        $biblicalReferences = $biblicalQuery === null
            ? collect()
            : $biblicalQuery->forPage($biblicalPage, $biblicalPerPage)->get();

        return response()->json([
            'word' => $word->only('id', 'hebrew', 'normalized', 'transliteration', 'english_gloss', 'morphology', 'enrichment'),
            'review' => (bool) $request->user()?->is_admin ? $this->reviewData($word) : null,
            'lemma' => $word->lemma?->only(
                'id', 'hebrew', 'normalized', 'transliteration', 'definition', 'alternate_definitions',
                'root', 'strongs_number', 'base_strongs_number', 'source_lexical_id',
                'lexicon_morphology', 'source_reference',
            ),
            'match_type' => $confirmedLemma ? 'confirmed_lemma' : ($word->lemma_id === null ? 'unresolved_form' : 'exact_written_form'),
            'occurrence_count' => $occurrenceCount,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($occurrenceCount / $perPage)),
            ],
            'occurrences' => $occurrences->map(fn ($occurrence) => [
                'id' => $occurrence->id,
                'passage_id' => $occurrence->passage_id,
                'reference' => $occurrence->passage->reference,
                'hebrew' => $occurrence->passage->hebrew_text,
                'english' => $occurrence->passage->english_text,
                'book_slug' => $occurrence->passage->chapter->book->slug,
                'chapter' => $occurrence->passage->chapter->number,
                'word' => $occurrence->hebrew,
                'words' => $occurrence->passage->hebrewWords->map(fn ($contextWord) => [
                    'id' => $contextWord->id,
                    'hebrew' => $contextWord->hebrew,
                    'is_match' => $contextWord->id === $occurrence->id,
                ])->values(),
            ]),
            'biblical_reference_count' => $biblicalCount,
            'biblical_unique_location_count' => $uniqueBiblicalLocations,
            'biblical_pagination' => [
                'page' => $biblicalPage,
                'per_page' => $biblicalPerPage,
                'last_page' => max(1, (int) ceil($biblicalCount / $biblicalPerPage)),
            ],
            'biblical_references' => $biblicalReferences->map(fn ($reference) => $reference->only(
                'id', 'source', 'raw_reference', 'normalized_reference', 'book',
                'chapter', 'verse', 'source_position', 'source_url',
            )),
            'sources' => [
                'hebrew_scripture' => data_get($word->enrichment, 'sources.hebrew_scripture') ?? $word->passage->chapter->source_url,
                'english_context' => $word->passage->reference,
                'morphology' => data_get($word->enrichment, 'lemma.source') === 'brithamashiach-morphology'
                    ? rtrim((string) config('services.brit_hamashiach.url'), '/').'/api/volume/morphology/'.rawurlencode($word->hebrew)
                    : null,
                'supporting_lexicon' => $word->lemma?->source_reference,
                'annotation' => data_get($word->enrichment, 'sources.annotation'),
            ],
        ]);
    }
}

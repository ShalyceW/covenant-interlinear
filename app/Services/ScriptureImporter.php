<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\HebrewWord;
use App\Models\Lemma;
use App\Models\Passage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ScriptureImporter
{
    public const SOURCE_HOST = 'brithamashiach.org';

    public function __construct(private readonly HebrewTokenizer $tokenizer) {}

    /** @return list<array<string, mixed>> */
    public function availableBooks(): array
    {
        $books = $this->client()->get('/api/volume/books')->throw()->json();

        if (! is_array($books) || ! array_is_list($books)) {
            throw new RuntimeException('The scripture source returned an invalid book index.');
        }

        return $books;
    }

    /** @return array<string, mixed> */
    public function fetchChapter(string $bookSlug, int $chapterNumber): array
    {
        $this->validateSlug($bookSlug);

        $payload = $this->client()
            ->get("/api/volume/books/{$bookSlug}/chapters/{$chapterNumber}")
            ->throw()
            ->json();

        return $this->validatePayload($payload);
    }

    /** @return array<string, mixed> */
    public function readLocalJson(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['file' => 'The JSON file cannot be read.']);
        }

        if (filesize($path) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'The JSON file may not exceed 5 MB.']);
        }

        $payload = json_decode(file_get_contents($path) ?: '', true, flags: JSON_THROW_ON_ERROR);

        return $this->validatePayload(Arr::get($payload, 'chapter', $payload));
    }

    /** @return array<string, mixed> */
    public function preview(string $bookSlug, int $chapterNumber, ?string $localPath = null): array
    {
        $payload = $localPath ? $this->readLocalJson($localPath) : $this->fetchChapter($bookSlug, $chapterNumber);
        $book = $localPath ? $this->localBookMetadata($bookSlug) : $this->bookMetadata($bookSlug);
        $passages = $this->extractPassages($payload, $book['ccBoMName'] ?? $bookSlug);

        return [
            'book' => $book,
            'chapter' => (int) $payload['chapterNum'],
            'passage_count' => count($passages),
            'word_count' => array_sum(array_map(
                fn (array $passage): int => count($this->tokenizer->tokenize($passage['hebrew_text'])),
                $passages,
            )),
            'hebrew_preview' => array_slice(array_column($passages, 'hebrew_text'), 0, 3),
            'english_preview' => array_slice(array_column($passages, 'english_text'), 0, 3),
            'existing' => Chapter::query()->whereHas('book', fn ($query) => $query->where('slug', $bookSlug))
                ->where('number', $chapterNumber)->exists(),
        ];
    }

    /** @return array<string, int|string> */
    public function importChapter(
        string $bookSlug,
        int $chapterNumber,
        ?string $localPath = null,
        bool $publish = true,
    ): array {
        $payload = $localPath ? $this->readLocalJson($localPath) : $this->fetchChapter($bookSlug, $chapterNumber);
        if ((int) $payload['chapterNum'] !== $chapterNumber) {
            throw ValidationException::withMessages(['chapter' => 'The selected chapter number does not match the source payload.']);
        }

        $bookData = $localPath ? $this->localBookMetadata($bookSlug) : $this->bookMetadata($bookSlug);
        $sourceUrl = $localPath ? 'local-json://'.basename($localPath) : $this->chapterUrl($bookSlug, $chapterNumber);

        return DB::transaction(function () use ($payload, $bookData, $bookSlug, $sourceUrl, $publish): array {
            $book = Book::query()->firstOrCreate(
                ['slug' => $bookSlug],
                [
                    'name' => $bookData['ccBoMName'] ?? $bookSlug,
                    'hebrew_name' => $bookData['hebrewName'] ?? null,
                    'position' => $bookData['_position'] ?? (Book::max('position') + 1),
                    'source_url' => $this->bookUrl($bookSlug),
                ],
            );

            $chapter = Chapter::query()->firstOrNew([
                'book_id' => $book->id,
                'number' => (int) $payload['chapterNum'],
            ]);
            $chapter->source_url = $sourceUrl;
            $chapter->imported_at = now();
            if ($publish && $chapter->published_at === null) {
                $chapter->published_at = now();
            }
            $chapter->save();

            $createdPassages = 0;
            $updatedPassages = 0;
            $createdWords = 0;
            $skipped = 0;
            $passages = $this->extractPassages($payload, $book->name);

            foreach ($passages as $data) {
                $hash = hash('sha256', $data['hebrew_text']."\0".$data['english_text']);
                $passage = Passage::query()->firstOrNew([
                    'chapter_id' => $chapter->id,
                    'position' => $data['position'],
                ]);
                $isNew = ! $passage->exists;

                if (! $isNew && $passage->manually_edited) {
                    $skipped++;

                    continue;
                }

                $sourceChanged = ! $isNew && $passage->source_hash !== null && $passage->source_hash !== $hash;
                $passage->fill($data + ['source_hash' => $hash]);
                $passage->save();
                $isNew ? $createdPassages++ : $updatedPassages++;

                $tokens = $this->tokenizer->tokenize($passage->hebrew_text);
                $existingWords = $passage->hebrewWords()->get();
                $existingForms = $existingWords->pluck('hebrew')->all();
                $newForms = array_column($tokens, 'hebrew');
                $hasAnnotations = $existingWords->contains(fn ($word): bool => filled($word->lemma_id) || filled($word->transliteration) || filled($word->english_gloss) || filled($word->morphology) || filled($word->enrichment)
                );

                if ($sourceChanged && $existingWords->isNotEmpty() && $existingForms !== $newForms && $hasAnnotations) {
                    $passage->update(['needs_retokenization' => true]);

                    continue;
                }

                if ($existingForms !== $newForms) {
                    $passage->hebrewWords()->delete();
                    foreach ($tokens as $index => $token) {
                        $passage->hebrewWords()->create($token + [
                            'position' => $index + 1,
                            'morphology' => $this->sourceMorphology($data['annotations'][$index + 1] ?? null),
                        ]);
                        $createdWords++;
                    }
                    $passage->update(['needs_retokenization' => false]);
                }
            }

            return [
                'book' => $book->name,
                'chapter' => $chapter->number,
                'passages_created' => $createdPassages,
                'passages_updated' => $updatedPassages,
                'words_created' => $createdWords,
                'passages_skipped' => $skipped,
            ];
        });
    }

    /** @return list<array<string, int|string>> */
    public function importBook(string $bookSlug, bool $publish = true, ?callable $progress = null): array
    {
        $book = $this->bookMetadata($bookSlug);
        $chapters = $book['chapters'] ?? [];

        if (! is_array($chapters) || $chapters === []) {
            throw new RuntimeException("No verified chapters are available for {$bookSlug}.");
        }

        $results = [];
        foreach ($chapters as $chapter) {
            $number = (int) ($chapter['chapterNum'] ?? 0);
            try {
                $result = $this->importChapter($bookSlug, $number, publish: $publish);
                $results[] = $result;
                if ($progress !== null) {
                    $progress($number, $result, null);
                }
            } catch (\Throwable $exception) {
                $result = ['book' => $bookSlug, 'chapter' => $number, 'error' => $exception->getMessage()];
                $results[] = $result;
                if ($progress !== null) {
                    $progress($number, $result, $exception);
                }
            }
        }

        return $results;
    }

    public function retokenize(Passage $passage): int
    {
        return DB::transaction(function () use ($passage): int {
            $existing = $passage->hebrewWords()->get()->keyBy('position');
            $tokens = $this->tokenizer->tokenize($passage->hebrew_text);
            $passage->hebrewWords()->delete();

            foreach ($tokens as $index => $token) {
                $position = $index + 1;
                $previous = $existing->get($position);
                $preserve = $previous !== null && $previous->hebrew === $token['hebrew'];
                $passage->hebrewWords()->create($token + [
                    'position' => $position,
                    'lemma_id' => $preserve ? $previous->lemma_id : null,
                    'transliteration' => $preserve ? $previous->transliteration : null,
                    'english_gloss' => $preserve ? $previous->english_gloss : null,
                    'morphology' => $preserve ? $previous->morphology : null,
                    'enrichment' => $preserve ? $previous->enrichment : null,
                ]);
            }

            $passage->update(['needs_retokenization' => false]);

            return count($tokens);
        });
    }

    public function lookupLexicon(HebrewWord $word): bool
    {
        return $this->applyLexiconPayload($word, $this->fetchMorphology($word->hebrew));
    }

    /** @return array<string, mixed>|null */
    public function fetchMorphology(string $surfaceForm): ?array
    {
        $lookupForm = $this->tokenizer->lookupForm($surfaceForm);
        $response = $this->client()->get('/api/volume/morphology/'.rawurlencode($lookupForm));
        if ($response->status() === 404) {
            return null;
        }

        $payload = $response->throw()->json();

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  list<string>  $surfaceForms
     * @return array<string, array<string, mixed>|null>
     */
    public function fetchMorphologies(array $surfaceForms): array
    {
        $results = [];
        foreach (array_chunk(array_values(array_unique($surfaceForms)), 5) as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk): array {
                $baseUrl = rtrim((string) config('services.brit_hamashiach.url'), '/');

                return array_map(
                    fn (string $form) => $pool->as(base64_encode($form))->baseUrl($baseUrl)
                        ->acceptJson()->timeout(25)->retry(3, 1000, throw: false)
                        ->get('/api/volume/morphology/'.rawurlencode($this->tokenizer->lookupForm($form))),
                    $chunk,
                );
            });

            foreach ($chunk as $form) {
                $response = $responses[base64_encode($form)] ?? null;
                if (! $response instanceof Response || $response->status() === 404) {
                    $results[$form] = null;

                    continue;
                }

                if ($response->failed()) {
                    $results[$form] = ['_error' => 'source-unavailable', '_status' => $response->status()];

                    continue;
                }

                $payload = $response->throw()->json();
                $results[$form] = is_array($payload) ? $payload : null;
            }
        }

        return $results;
    }

    /** @param array<string, mixed>|null $payload */
    public function applyLexiconPayload(HebrewWord $word, ?array $payload): bool
    {
        $parses = is_array($payload) && is_array($payload['parses'] ?? null) ? $payload['parses'] : [];
        $candidates = collect($parses)
            ->filter(fn (mixed $parse): bool => is_array($parse) && filled($parse['baseStrongs'] ?? null))
            ->map(function (array $parse): array {
                $strongs = $this->normalizeStrongs((string) $parse['baseStrongs']);
                $fallback = $this->strongsFallback($strongs);
                $sourceHeadword = $this->validHeadword($parse['root'] ?? null);

                return [
                    'lexical_id' => (string) ($parse['lemma'] ?? $parse['baseStrongs']),
                    'strongs_number' => $strongs,
                    'headword' => $fallback['hebrew'] ?? $sourceHeadword,
                    'source_root' => $sourceHeadword,
                    'morphology' => $parse['morphology'] ?? null,
                    'morphology_description' => $parse['morphologyDescription'] ?? null,
                    'definition' => $fallback['definition'] ?? $parse['definition'] ?? null,
                ];
            })->values();

        $enrichment = $word->enrichment ?? [];
        $enrichment['lemma']['candidates'] = $candidates->all();
        $enrichment['lemma']['source'] = ($payload['_error'] ?? null) === 'source-unavailable'
            ? 'morphology-source-unavailable'
            : 'brithamashiach-morphology';
        $enrichment['lemma']['source_surface_form'] = $payload['surfaceForm'] ?? null;
        $enrichment['lemma']['source_surface_form_with_breaks'] = $payload['surfaceFormWithBreaks'] ?? null;

        if ($candidates->isEmpty()) {
            $enrichment['lemma']['status'] = 'unresolved';
            $word->update(['enrichment' => $enrichment]);

            return false;
        }

        $strongsNumbers = $candidates->pluck('strongs_number')->filter()->unique()->values();
        $selected = null;
        if ($strongsNumbers->count() === 1) {
            $selected = $candidates->firstWhere('strongs_number', $strongsNumbers->first());
        } elseif ($word->lemma !== null && $strongsNumbers->contains($word->lemma->strongs_number)) {
            $selected = $candidates->firstWhere('strongs_number', $word->lemma->strongs_number);
        } elseif (($preferred = $this->preferredStrongs($word, $candidates->values()->all())) !== null) {
            $selected = $candidates->firstWhere('strongs_number', $preferred);
        }

        if (! is_array($selected) || blank($selected['headword'] ?? null)) {
            $enrichment['lemma']['status'] = $candidates->count() > 1 ? 'proposed' : 'unresolved';
            $word->update(['enrichment' => $enrichment]);

            return false;
        }

        $strongs = (string) $selected['strongs_number'];
        $hebrew = (string) $selected['headword'];
        $sourceUrl = rtrim((string) config('services.brit_hamashiach.url'), '/')
            .'/api/volume/morphology/'.rawurlencode($this->tokenizer->lookupForm($word->hebrew));

        return DB::transaction(function () use ($word, $candidates, $selected, $strongs, $hebrew, $sourceUrl, $enrichment): bool {
            $lemma = Lemma::query()->firstOrCreate(
                ['strongs_number' => $strongs],
                ['hebrew' => $hebrew, 'normalized' => $this->tokenizer->normalize($hebrew)],
            );
            if (in_array($lemma->hebrew, ['', 'N/A'], true) || $lemma->normalized === '') {
                $lemma->hebrew = $hebrew;
                $lemma->normalized = $this->tokenizer->normalize($hebrew);
            }
            foreach ([
                'transliteration' => data_get($this->strongsFallback($strongs), 'transliteration'),
                'definition' => $selected['definition'] ?? null,
                'root' => $selected['source_root'] ?? null,
                'source_reference' => $sourceUrl,
            ] as $field => $value) {
                if (blank($lemma->{$field}) && filled($value)) {
                    $lemma->{$field} = $value;
                }
            }
            $lemma->save();

            $morphology = $word->morphology ?? [];
            $matchingParses = $candidates->where('strongs_number', $strongs)
                ->unique(fn (array $candidate): string => (string) $candidate['morphology'].'|'.(string) $candidate['morphology_description'])
                ->values();
            if ($matchingParses->count() === 1) {
                $morphology['code'] = $selected['morphology'] ?? null;
                $morphology['description'] = $selected['morphology_description'] ?? null;
                $morphology['status'] = 'confirmed';
                unset($morphology['candidates']);
            } else {
                unset($morphology['code'], $morphology['description']);
                $morphology['status'] = 'unresolved';
                $morphology['candidates'] = $matchingParses->map(fn (array $candidate): array => array_filter([
                    'code' => $candidate['morphology'],
                    'description' => $candidate['morphology_description'],
                ]))->values()->all();
            }

            $enrichment['lemma'] = array_merge($enrichment['lemma'], [
                'status' => 'confirmed',
                'lexical_id' => $selected['lexical_id'],
                'strongs_number' => $strongs,
                'headword_source' => $this->strongsFallback($strongs) !== null ? 'verified-fallback' : 'source-root-field',
            ]);
            $word->update(['lemma_id' => $lemma->id, 'morphology' => array_filter($morphology, fn ($value) => $value !== null), 'enrichment' => $enrichment]);

            return true;
        });
    }

    private function normalizeStrongs(string $strongs): string
    {
        $strongs = trim($strongs);
        if (preg_match('/^H?(\d+)\s*([a-z])?$/i', $strongs, $matches) === 1) {
            return 'H'.$matches[1].strtolower($matches[2] ?? '');
        }

        return $strongs;
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     */
    private function preferredStrongs(HebrewWord $word, array $candidates): ?string
    {
        $available = collect($candidates)->pluck('strongs_number')->filter()->unique();
        $configured = config("hebrew.surface_lemma_preferences.{$word->normalized}");
        if (is_string($configured) && $available->contains($configured)) {
            return $configured;
        }

        $confirmedPeers = HebrewWord::query()
            ->where('normalized', $word->normalized)
            ->whereNotNull('lemma_id')
            ->where('enrichment->lemma->status', 'confirmed')
            ->with('lemma:id,strongs_number')
            ->get()
            ->pluck('lemma.strongs_number')
            ->filter()
            ->unique()
            ->values();
        if ($confirmedPeers->count() === 1 && $available->contains($confirmedPeers->first())) {
            return (string) $confirmedPeers->first();
        }

        $scored = collect($candidates)
            ->groupBy('strongs_number')
            ->map(fn ($group): int => collect($group)->max(fn (array $candidate): int => $this->candidateEnglishScore($candidate, $word->passage->english_text)) ?? 0)
            ->sortDesc();
        if ($scored->isEmpty() || (int) $scored->first() < 2) {
            return null;
        }

        $best = (int) $scored->first();
        $winners = $scored->filter(fn (int $score): bool => $score === $best);

        return $winners->count() === 1 ? (string) $winners->keys()->first() : null;
    }

    /** @param array<string, mixed> $candidate */
    private function candidateEnglishScore(array $candidate, string $english): int
    {
        $definition = mb_strtolower((string) ($candidate['definition'] ?? ''));
        if ($definition === '' || strtoupper($definition) === 'N/A') {
            return 0;
        }

        $definition = preg_replace('/\d+[a-z]?\d*\)|\([^)]*\)/i', ' ', $definition) ?? $definition;
        $terms = preg_split('/[^a-z]+/i', $definition, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['the', 'and', 'with', 'from', 'that', 'this', 'have', 'has', 'into', 'upon', 'also', 'one', 'used', 'name'];
        $english = mb_strtolower($english);
        $score = 0;
        foreach (array_unique($terms) as $term) {
            if (mb_strlen($term) < 3 || in_array($term, $stop, true)) {
                continue;
            }
            $stem = mb_substr($term, 0, min(5, mb_strlen($term)));
            if (preg_match('/\b'.preg_quote($stem, '/').'[a-z]*\b/i', $english) === 1) {
                $score += mb_strlen($term) >= 5 ? 3 : 2;
            }
        }

        return $score;
    }

    /** @return array<string, string>|null */
    private function strongsFallback(string $strongs): ?array
    {
        $fallback = config("hebrew.strongs_fallbacks.{$strongs}");

        return is_array($fallback) ? $fallback : null;
    }

    private function validHeadword(mixed $value): ?string
    {
        return is_string($value) && $value !== '' && strtoupper($value) !== 'N/A' ? $value : null;
    }

    /** @return array<string, mixed> */
    private function bookMetadata(string $bookSlug): array
    {
        $this->validateSlug($bookSlug);
        foreach ($this->availableBooks() as $position => $book) {
            if (($book['name'] ?? null) === $bookSlug) {
                return $book + ['_position' => $position + 1];
            }
        }

        throw ValidationException::withMessages(['book' => "Unknown source book: {$bookSlug}."]);
    }

    /** @return array<string, mixed> */
    private function localBookMetadata(string $bookSlug): array
    {
        $this->validateSlug($bookSlug);
        $book = Book::query()->where('slug', $bookSlug)->first();
        if ($book !== null) {
            return [
                'name' => $book->slug,
                'ccBoMName' => $book->name,
                'hebrewName' => $book->hebrew_name,
                '_position' => $book->position,
            ];
        }

        return $this->bookMetadata($bookSlug);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function extractPassages(array $payload, string $bookName): array
    {
        $passages = [];
        foreach ($payload['parts'] as $part) {
            foreach (($part['sentences'] ?? []) as $sentence) {
                $hebrew = Arr::get($sentence, 'text.hebText');
                $english = Arr::get($sentence, 'text.ccText');
                if (! is_string($hebrew) || ! is_string($english) || $hebrew === '' || $english === '') {
                    continue;
                }

                $sourceRef = (string) ($sentence['ldsRef'] ?: $sentence['sentenceRef'] ?? count($passages) + 1);
                $passages[] = [
                    'reference' => trim("{$bookName} {$sourceRef}"),
                    'position' => count($passages) + 1,
                    'hebrew_text' => $hebrew,
                    'english_text' => $english,
                    'annotations' => $sentence['annotatedHebWords'] ?? [],
                ];
            }
        }

        if ($passages === []) {
            throw new RuntimeException('The chapter contains no corresponding Hebrew and Covenant of Christ English passages.');
        }

        return $passages;
    }

    /** @return array<string, mixed> */
    private function validatePayload(mixed $payload): array
    {
        if (! is_array($payload) || ! isset($payload['chapterNum'], $payload['parts']) || ! is_array($payload['parts'])) {
            throw ValidationException::withMessages(['source' => 'The file is not a supported Brit HaMashiach chapter payload.']);
        }

        return $payload;
    }

    private function validateSlug(string $bookSlug): void
    {
        if (! preg_match('/\A[a-z0-9-]+\z/', $bookSlug)) {
            throw ValidationException::withMessages(['book' => 'The source book identifier is invalid.']);
        }
    }

    /** @return array<string, bool>|null */
    private function sourceMorphology(mixed $annotation): ?array
    {
        if (! is_array($annotation)) {
            return null;
        }

        return [
            'proper_name' => (bool) ($annotation['isPN'] ?? false),
            'found_in_hebrew_bible' => (bool) ($annotation['inHB'] ?? false),
        ];
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.brit_hamashiach.url'), '/');
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if ($host !== self::SOURCE_HOST) {
            throw new RuntimeException('Remote scripture imports are restricted to the approved source domain.');
        }

        return Http::baseUrl($baseUrl)->acceptJson()->timeout(25)->retry(2, 500, throw: false);
    }

    private function chapterUrl(string $slug, int $chapter): string
    {
        return rtrim((string) config('services.brit_hamashiach.url'), '/')."/books/{$slug}/chapters/{$chapter}?showCols=heb&showCols=cc";
    }

    private function bookUrl(string $slug): string
    {
        return rtrim((string) config('services.brit_hamashiach.url'), '/')."/books/{$slug}";
    }
}

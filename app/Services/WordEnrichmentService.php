<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\HebrewWord;
use App\Models\Lemma;
use Illuminate\Support\Facades\DB;

class WordEnrichmentService
{
    public function __construct(
        private readonly ScriptureImporter $importer,
        private readonly HebrewTokenizer $tokenizer,
        private readonly HebrewTransliterator $transliterator,
    ) {}

    /** @return array<string, mixed> */
    public function enrich(Chapter $chapter, bool $dryRun = false): array
    {
        $chapter->loadMissing('book');
        $before = $this->coverage($chapter);
        $words = HebrewWord::query()
            ->with(['lemma', 'passage.chapter'])
            ->whereHas('passage', fn ($query) => $query->where('chapter_id', $chapter->id))
            ->orderBy('passage_id')->orderBy('position')->get();
        foreach ($words as $word) {
            $word->normalized = $this->tokenizer->normalize($word->hebrew);
        }
        $remoteForms = array_values($words
            ->filter(fn (HebrewWord $word): bool => $this->needsRemoteLookup($word))
            ->filter(fn (HebrewWord $word): bool => (bool) data_get($word->morphology, 'found_in_hebrew_bible'))
            ->map(fn (HebrewWord $word): string => $word->hebrew)->unique()->all());
        $payloads = $this->importer->fetchMorphologies($remoteForms);

        DB::beginTransaction();
        try {
            $repairedLemmas = $this->repairLegacyLemmas();
            $changed = 0;

            foreach ($words as $word) {
                $original = $word->getAttributes();
                $enrichment = $word->enrichment ?? [];

                if (! filled($word->transliteration) || ! $this->isManual($word, 'transliteration')) {
                    $word->transliteration = $this->transliterator->transliterate($word->hebrew);
                    $enrichment['transliteration'] = [
                        'status' => $this->hasTransliterationOverride($word) ? 'confirmed' : 'proposed',
                        'source' => $this->hasTransliterationOverride($word) ? 'config-override' : 'deterministic-surface-form-v2',
                    ];
                }

                if (! $this->hasManualLemma($word)) {
                    if (($local = $this->localLemma($word)) !== null) {
                        $strongs = $local['strongs_number'] ?? null;
                        $identity = is_string($strongs)
                            ? ['strongs_number' => $strongs]
                            : ['normalized' => $this->tokenizer->normalize($local['hebrew']), 'strongs_number' => null];
                        $lemma = Lemma::query()->firstOrCreate($identity, [
                            'hebrew' => $local['hebrew'],
                            'normalized' => $this->tokenizer->normalize($local['hebrew']),
                            'transliteration' => $local['transliteration'],
                            'definition' => $local['gloss'],
                            'source_reference' => $local['source_reference'] ?? 'local-config://hebrew.local_lemmas',
                        ]);
                        $word->lemma_id = $lemma->id;
                        if (blank($lemma->transliteration)) {
                            $lemma->transliteration = $local['transliteration'] ?? $this->transliterator->transliterate($lemma->hebrew);
                            $lemma->save();
                        }
                        $word->setRelation('lemma', $lemma);
                        $lemmaSource = is_string($strongs) ? 'verified-local-lexicon-config' : 'covenant-proper-name-config';
                        $enrichment['lemma'] = [
                            'status' => 'confirmed',
                            'source' => $lemmaSource,
                            'evidence' => is_string($strongs)
                                ? 'Verified OSHB lexical mapping and matching Covenant of Christ English context.'
                                : 'Source proper-name annotation and matching Covenant of Christ English context.',
                        ];
                        $word->morphology = array_merge($word->morphology ?? [], [
                            'description' => 'Covenant of Christ proper name',
                            'status' => 'confirmed',
                        ]);
                    } elseif ($this->needsRemoteLookup($word) && array_key_exists($word->hebrew, $payloads)) {
                        $word->enrichment = $enrichment;
                        $word->save();
                        $this->importer->applyLexiconPayload($word, $payloads[$word->hebrew]);
                        $word->refresh()->load('lemma');
                        $enrichment = $word->enrichment ?? [];
                    } elseif (blank(data_get($enrichment, 'lemma.status'))) {
                        $enrichment['lemma'] = array_merge($enrichment['lemma'] ?? [], [
                            'status' => 'unresolved',
                            'source' => data_get($word->morphology, 'found_in_hebrew_bible') ? 'morphology-source-no-match' : 'source-not-in-hebrew-bible',
                        ]);
                    }
                }

                if (! filled($word->english_gloss) || ! $this->isManual($word, 'gloss')) {
                    $word->enrichment = $enrichment;
                    $word->load('lemma');
                    $word->english_gloss = null;
                    $override = $this->contextualGlossOverride($word);
                    $gloss = $this->contextualGloss($word);
                    if ($gloss !== null) {
                        $word->english_gloss = $gloss;
                        $confirmedGloss = in_array(data_get($enrichment, 'lemma.source'), ['covenant-proper-name-config', 'verified-local-lexicon-config'], true)
                            || $this->isVerifiedProperNameGloss($word, $gloss);
                        $enrichment['gloss'] = [
                            'status' => $confirmedGloss ? 'confirmed' : 'proposed',
                            'source' => $confirmedGloss
                                ? (data_get($enrichment, 'lemma.source') === 'brithamashiach-morphology'
                                    ? 'verified-proper-name-context'
                                    : data_get($enrichment, 'lemma.source'))
                                : ($override !== null ? 'chapter-context-proposal' : 'dictionary-morphology-context-rule-v2'),
                            'context_reference' => $word->passage->reference,
                        ];
                    } else {
                        $enrichment['gloss'] = [
                            'status' => 'unresolved',
                            'source' => 'insufficient-contextual-evidence',
                            'context_reference' => $word->passage->reference,
                        ];
                    }
                }

                $enrichment['sources'] = array_filter([
                    'hebrew_scripture' => $chapter->source_url,
                    'english_context' => $word->passage->reference,
                    'supporting_lexicon' => $word->lemma?->source_reference,
                    'annotation' => data_get($enrichment, 'gloss.source'),
                ]);
                $word->enrichment = $enrichment;
                $word->save();
                if ($word->getAttributes() !== $original) {
                    $changed++;
                }
            }

            $after = $this->coverage($chapter);
            $result = [
                'book' => $chapter->book->name,
                'chapter' => $chapter->number,
                'dry_run' => $dryRun,
                'records_changed' => $changed,
                'lemma_records_corrected' => $repairedLemmas,
                'before' => $before,
                'after' => $after,
            ];

            $dryRun ? DB::rollBack() : DB::commit();

            return $result;
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    /** @return array<string, int> */
    public function coverage(Chapter $chapter): array
    {
        $query = HebrewWord::query()->whereHas('passage', fn ($query) => $query->where('chapter_id', $chapter->id));

        return [
            'total_words' => (clone $query)->count(),
            'transliterations' => (clone $query)->whereNotNull('transliteration')->where('transliteration', '!=', '')->count(),
            'contextual_glosses' => (clone $query)->whereNotNull('english_gloss')->where('english_gloss', '!=', '')->count(),
            'confirmed_lemmas' => (clone $query)->whereNotNull('lemma_id')->where('enrichment->lemma->status', 'confirmed')->count(),
            'proposed_lemmas' => (clone $query)->where('enrichment->lemma->status', 'proposed')->count(),
            'unresolved_words' => (clone $query)->where(function ($query): void {
                $query->whereNull('enrichment->lemma->status')
                    ->orWhere('enrichment->lemma->status', 'unresolved');
            })->count(),
            'words_requiring_review' => (clone $query)->where(function ($query): void {
                $query->whereNull('enrichment->lemma->status')
                    ->orWhereNull('enrichment->gloss->status')
                    ->orWhereNull('enrichment->transliteration->status')
                    ->orWhereIn('enrichment->lemma->status', ['proposed', 'unresolved'])
                    ->orWhereIn('enrichment->gloss->status', ['proposed', 'unresolved'])
                    ->orWhereIn('enrichment->transliteration->status', ['proposed', 'unresolved']);
            })->count(),
        ];
    }

    private function repairLegacyLemmas(): int
    {
        $corrected = 0;
        foreach (Lemma::query()->whereNotNull('strongs_number')->get() as $lemma) {
            $normalizedStrongs = $this->normalizeStrongs($lemma->strongs_number);
            $fallback = config("hebrew.strongs_fallbacks.{$normalizedStrongs}");
            $dirty = false;
            if (strtoupper(trim((string) $lemma->root)) === 'N/A') {
                $lemma->root = null;
                $dirty = true;
            }
            if ($lemma->strongs_number !== $normalizedStrongs && ! Lemma::query()->whereKeyNot($lemma->id)->where('strongs_number', $normalizedStrongs)->exists()) {
                $lemma->strongs_number = $normalizedStrongs;
                $dirty = true;
            }
            if (is_array($fallback)) {
                if (in_array($lemma->hebrew, ['', 'N/A'], true) || $lemma->normalized === '') {
                    $lemma->hebrew = $fallback['hebrew'];
                    $lemma->normalized = $this->tokenizer->normalize($fallback['hebrew']);
                    $dirty = true;
                }
                if (blank($lemma->transliteration)) {
                    $lemma->transliteration = $fallback['transliteration'] ?? $this->transliterator->transliterate($lemma->hebrew);
                    $dirty = true;
                }
                if (blank($lemma->definition) || strtoupper(trim((string) $lemma->definition)) === 'N/A') {
                    $lemma->definition = $fallback['definition'] ?? null;
                    $dirty = true;
                }
            } elseif (blank($lemma->transliteration)) {
                $lemma->transliteration = $this->transliterator->transliterate($lemma->hebrew);
                $dirty = true;
            }
            if ($dirty) {
                $lemma->save();
                $corrected++;
            }
        }

        return $corrected;
    }

    /** @return array<string, string>|null */
    private function localLemma(HebrewWord $word): ?array
    {
        foreach ((array) config('hebrew.local_lemmas', []) as $key => $lemma) {
            if (! str_ends_with($word->normalized, (string) $key)) {
                continue;
            }
            $gloss = $lemma['gloss'] ?? null;
            $english = $word->passage->english_text;
            $sourceSaysProperName = (bool) data_get($word->morphology, 'proper_name');
            if (isset($lemma['strongs_number']) || $sourceSaysProperName || (is_string($gloss) && preg_match('/\b'.preg_quote($gloss, '/').'\b/i', $english) === 1)) {
                return $lemma;
            }
        }

        return null;
    }

    private function contextualGloss(HebrewWord $word): ?string
    {
        if (($override = $this->contextualGlossOverride($word)) !== null) {
            return $override;
        }

        if (($local = $this->localLemma($word)) !== null) {
            return $this->localNamePrefixGloss($word, $local).$local['gloss'];
        }

        if (! $word->hasConfirmedLemma() || $word->lemma === null) {
            return null;
        }

        $strongs = $word->lemma->strongs_number;
        $configured = is_string($strongs) ? config("hebrew.lexical_glosses.{$strongs}") : null;
        $base = $this->configuredGloss($configured, $word->passage->english_text);
        $base ??= is_string($strongs) ? data_get(config("hebrew.strongs_fallbacks.{$strongs}"), 'gloss') : null;
        $base ??= $this->definitionGloss($word->lemma->definition, $word->passage->english_text);
        if (! is_string($base) || $base === '') {
            return null;
        }

        $code = (string) data_get($word->morphology, 'code', '');
        if ($strongs === 'H1931') {
            $base = str_contains($code, '3fs') ? 'she' : (str_contains($code, '3mp') ? 'they' : 'he');
        }
        $gloss = $base;
        $gloss = $this->finiteVerbGloss($gloss, $code);
        $gloss = $this->participleGloss($gloss, $code);
        if (preg_match('/V.c/', $code) === 1 && in_array(mb_substr($word->normalized, 0, 1), ['ב', 'כ'], true)) {
            $gloss = $this->gerund($base);
        }
        if ($strongs !== 'H430' && preg_match('/N..p/', $code) === 1 && ! str_ends_with($gloss, 's')) {
            $gloss = $this->pluralize($gloss);
        }
        $suffixes = [
            'Sp1cs' => ['my', 'me'], 'Sp1cp' => ['our', 'us'],
            'Sp2ms' => ['your', 'you'], 'Sp2fs' => ['your', 'you'],
            'Sp2mp' => ['your', 'you'], 'Sp2fp' => ['your', 'you'],
            'Sp3ms' => ['his', 'him'], 'Sp3fs' => ['her', 'her'],
            'Sp3mp' => ['their', 'them'], 'Sp3fp' => ['their', 'them'],
        ];
        $hasSuffix = false;
        foreach ($suffixes as $needle => [$possessive, $object]) {
            if (str_contains($code, $needle)) {
                if (str_starts_with($code, 'HTn/')) {
                    $gloss = $this->negativeParticleGloss($needle);
                } elseif ($strongs === 'H853') {
                    $gloss = $object;
                } elseif (str_starts_with($code, 'HAc') && $base === 'two') {
                    $gloss = 'both-of-'.$object;
                } elseif (preg_match('/V.c/', $code) === 1) {
                    $gloss = $possessive.'-'.$this->gerund($base);
                } elseif (preg_match('/V.r/', $code) === 1) {
                    $gloss = $possessive.'-'.$gloss;
                } else {
                    $gloss = str_contains($code, 'N')
                        ? $possessive.'-'.$gloss
                        : $this->appendObject($gloss, $object);
                }
                $hasSuffix = true;
                break;
            }
        }
        if (! $hasSuffix && preg_match('/N...c(?:\/|$)/', $code) === 1) {
            $gloss .= '-of';
        }
        if (str_contains($code, 'Td/') && ! str_contains($code, '/P')) {
            $gloss = 'the-'.$gloss;
        }
        if (str_starts_with($code, 'HR/') && ! str_starts_with($code, 'HR/Sp')) {
            $gloss = $this->prepositionGloss($word->normalized).'-'.$gloss;
        }
        if (str_starts_with($code, 'HC/R/')) {
            $gloss = $this->prepositionGloss($word->normalized).'-'.$gloss;
        }
        if (str_starts_with($code, 'HC/')) {
            $gloss = 'and-'.$gloss;
        }

        return $gloss;
    }

    private function definitionGloss(?string $definition, string $english): ?string
    {
        if (blank($definition) || strtoupper(trim((string) $definition)) === 'N/A') {
            return null;
        }
        $clean = preg_replace('/\b\d+[a-z]?\d*\)\s*/i', '', (string) $definition) ?? (string) $definition;
        $firstSense = trim(explode(';', $clean)[0]);
        $choices = array_values(array_filter(array_map(fn (string $value): string => trim(preg_replace('/\([^)]*\)/', '', $value) ?? $value), explode(',', $firstSense))));
        foreach ($choices as $choice) {
            $candidate = preg_replace('/^to\s+/i', '', $choice) ?? $choice;
            if ($this->isReadableSense($candidate) && str_word_count($candidate) <= 2 && preg_match('/\b'.preg_quote($candidate, '/').'\w*\b/i', $english) === 1) {
                return str_replace(' ', '-', mb_strtolower($candidate));
            }
        }

        return null;
    }

    private function contextualGlossOverride(HebrewWord $word): ?string
    {
        $overrides = (array) config('hebrew.contextual_gloss_overrides', []);
        $passage = $overrides[$word->passage->reference] ?? null;
        $gloss = is_array($passage) ? ($passage[$word->hebrew] ?? null) : null;

        return is_string($gloss) && $gloss !== '' ? $gloss : null;
    }

    private function configuredGloss(mixed $configured, string $english): ?string
    {
        if (is_string($configured)) {
            return $configured;
        }
        if (! is_array($configured)) {
            return null;
        }

        foreach ($configured as $choice) {
            if (! is_string($choice)) {
                continue;
            }
            $forms = array_unique([$choice, $this->pluralize($choice)]);
            foreach ($forms as $form) {
                if (preg_match('/\b'.preg_quote($form, '/').'\b/i', $english) === 1) {
                    return $choice;
                }
            }
        }

        return is_string($configured[0] ?? null) ? $configured[0] : null;
    }

    private function isReadableSense(string $sense): bool
    {
        $sense = trim($sense);
        if ($sense === '' || str_contains($sense, '=') || str_contains($sense, '"')) {
            return false;
        }
        if (preg_match('/(?:^|[-\s])(adj|adv|v|verb|prep|subst|pron|interj|interr|partic|particle)(?:$|[-\s])/i', $sense) === 1) {
            return false;
        }

        return preg_match('/^(?:a|an|the)\s+/i', $sense) !== 1;
    }

    private function pluralize(string $word): string
    {
        return match (mb_strtolower($word)) {
            'woman' => 'women', 'man' => 'men', 'child' => 'children',
            'person' => 'people', 'family' => 'families', 'wife' => 'wives',
            'life' => 'lives',
            default => preg_match('/[^aeiou]y$/i', $word) === 1
                ? mb_substr($word, 0, -1).'ies'
                : (preg_match('/(?:s|x|z|ch|sh)$/i', $word) === 1 ? $word.'es' : $word.'s'),
        };
    }

    private function finiteVerbGloss(string $base, string $code): string
    {
        if (preg_match('/V.[pwiv](1cs|1cp|2ms|2fs|2mp|2fp|3ms|3fs|3mp|3fp|3cp)/', $code, $matches) !== 1) {
            return $base;
        }

        $conjugation = $matches[0][2] ?? '';
        if ($conjugation === 'v') {
            return $base.'!';
        }

        $subject = match ($matches[1]) {
            '1cs' => 'I', '1cp' => 'we',
            '2ms', '2fs', '2mp', '2fp' => 'you',
            '3ms' => 'he', '3fs' => 'she',
            default => 'they',
        };
        if ($conjugation === 'i') {
            return $subject.'-will-'.$base;
        }

        return $subject.'-'.$this->pastTense($base, $subject);
    }

    private function pastTense(string $verb, string $subject): string
    {
        if (str_starts_with($verb, 'be-')) {
            $copula = in_array($subject, ['they', 'we', 'you'], true) ? 'were' : 'was';

            return $copula.'-'.mb_substr($verb, 3);
        }

        return match (mb_strtolower($verb)) {
            'say' => 'said', 'go' => 'went', 'walk' => 'walked', 'come' => 'came', 'take' => 'took',
            'send' => 'sent', 'bring-out' => 'brought-out', 'go-out' => 'went-out', 'dwell' => 'dwelt',
            'go-up' => 'went-up', 'go-down' => 'went-down', 'go-through' => 'went-through',
            'build' => 'built', 'keep' => 'kept', 'hit' => 'hit', 'sell' => 'sold',
            'stop' => 'stopped', 'think' => 'thought', 'tear-away' => 'tore-away',
            'snatch-away' => 'snatched-away', 'resort-to' => 'resorted-to',
            'hear' => 'heard', 'tell' => 'told', 'make-clear' => 'made-clear',
            'do-well' => 'did-well',
            'seek' => 'sought', 'see' => 'saw', 'know' => 'knew', 'find' => 'found',
            'speak' => 'spoke', 'give' => 'gave', 'fall' => 'fell', 'stand' => 'stood',
            'read' => 'read', 'write' => 'wrote', 'choose' => 'chose', 'flee' => 'fled',
            'swear' => 'swore', 'die' => 'died', 'be' => $subject === 'they' || $subject === 'we' || $subject === 'you' ? 'were' : 'was',
            'do' => 'did', 'make' => 'made', 'return' => 'returned', 'rebel' => 'rebelled',
            'be-angry' => $subject === 'they' || $subject === 'we' || $subject === 'you' ? 'were-angry' : 'was-angry',
            'be-displeased' => $subject === 'they' || $subject === 'we' || $subject === 'you' ? 'were-displeased' : 'was-displeased',
            default => str_ends_with($verb, 'e') ? $verb.'d'
                : (preg_match('/[^aeiou]y$/i', $verb) === 1 ? mb_substr($verb, 0, -1).'ied' : $verb.'ed'),
        };
    }

    private function gerund(string $verb): string
    {
        if (str_contains($verb, '-')) {
            [$first, $remainder] = explode('-', $verb, 2);

            return $this->gerund($first).'-'.$remainder;
        }

        return match (mb_strtolower($verb)) {
            'be' => 'being', 'die' => 'dying', 'lie' => 'lying',
            default => str_ends_with($verb, 'e') && ! str_ends_with($verb, 'ee')
                ? mb_substr($verb, 0, -1).'ing'
                : $verb.'ing',
        };
    }

    private function participleGloss(string $gloss, string $code): string
    {
        if (preg_match('/V.r([mfc])p/', $code) !== 1) {
            return $gloss;
        }
        if (str_ends_with($gloss, 'ant')) {
            return $this->pluralize($gloss);
        }

        return match ($gloss) {
            'seek' => 'seekers', 'dwell', 'live' => 'inhabitants',
            default => $this->gerund($gloss),
        };
    }

    private function negativeParticleGloss(string $suffix): string
    {
        return match ($suffix) {
            'Sp1cs' => 'I-am-not', 'Sp1cp' => 'we-are-not',
            'Sp2ms', 'Sp2fs', 'Sp2mp', 'Sp2fp' => 'you-are-not',
            'Sp3ms' => 'he-is-not', 'Sp3fs' => 'she-is-not',
            default => 'they-are-not',
        };
    }

    private function appendObject(string $gloss, string $object): string
    {
        if (str_ends_with($gloss, '-out')) {
            return mb_substr($gloss, 0, -4).'-'.$object.'-out';
        }

        return $gloss.'-'.$object;
    }

    private function prepositionGloss(string $normalized): string
    {
        if (str_starts_with($normalized, 'ו')) {
            $normalized = mb_substr($normalized, 1);
        }

        return match (mb_substr($normalized, 0, 1)) {
            'ב' => 'in', 'ל' => 'to', 'כ' => 'as', 'מ' => 'from',
            default => 'with',
        };
    }

    /** @param array<string, string> $local */
    private function localNamePrefixGloss(HebrewWord $word, array $local): string
    {
        $key = $this->tokenizer->normalize($local['hebrew']);
        $prefix = mb_substr($word->normalized, 0, max(0, mb_strlen($word->normalized) - mb_strlen($key)));
        $prefix = str_replace('־', '', $prefix);
        if ($prefix === '') {
            return '';
        }

        $parts = [];
        if (str_starts_with($prefix, 'ו')) {
            $parts[] = 'and';
            $prefix = mb_substr($prefix, 1);
        }
        if (str_contains($prefix, 'אל')) {
            $parts[] = 'to';
        } else {
            foreach (mb_str_split($prefix) as $letter) {
                $parts[] = match ($letter) {
                    'ל' => 'to', 'ב' => 'in', 'מ' => 'from', 'כ' => 'as',
                    default => '',
                };
            }
        }

        $parts = array_values(array_filter($parts));

        return $parts === [] ? '' : implode('-', $parts).'-';
    }

    private function hasManualLemma(HebrewWord $word): bool
    {
        if ($word->lemma_id === null) {
            return false;
        }

        $source = data_get($word->enrichment, 'lemma.source');
        if (in_array($source, ['manual', 'editorial'], true)) {
            return true;
        }

        return $source === null && $word->lemma !== null
            && ! str_contains((string) $word->lemma->source_reference, '/api/volume/morphology/');
    }

    private function needsRemoteLookup(HebrewWord $word): bool
    {
        if ($this->hasManualLemma($word) || $this->localLemma($word) !== null) {
            return false;
        }

        $source = data_get($word->enrichment, 'lemma.source');
        $status = data_get($word->enrichment, 'lemma.status');

        if ($word->lemma_id === null && in_array($status, ['proposed', 'unresolved'], true)) {
            return true;
        }

        return $source === null
            || $source === 'morphology-source-unavailable'
            || $source === 'morphology-source-no-match'
            || $source === 'covenant-proper-name-config';
    }

    private function isManual(HebrewWord $word, string $field): bool
    {
        $source = data_get($word->enrichment, "{$field}.source");

        return in_array($source, ['manual', 'editorial'], true)
            || ($source === null && filled($field === 'gloss' ? $word->english_gloss : $word->transliteration));
    }

    private function hasTransliterationOverride(HebrewWord $word): bool
    {
        if (config("hebrew.transliteration_overrides.{$word->normalized}") !== null) {
            return true;
        }

        return collect(array_keys((array) config('hebrew.local_lemmas', [])))
            ->contains(fn (string $key): bool => str_ends_with($word->normalized, $key));
    }

    private function isVerifiedProperNameGloss(HebrewWord $word, string $gloss): bool
    {
        if (! str_contains((string) data_get($word->morphology, 'code'), 'Np')) {
            return false;
        }

        $name = preg_replace('/^(?:and|to|in|from|as)-/', '', $gloss) ?? $gloss;

        return preg_match('/\b'.preg_quote($name, '/').'\b/i', $word->passage->english_text) === 1;
    }

    private function normalizeStrongs(string $strongs): string
    {
        if (preg_match('/^H?(\d+)\s*([a-z])?$/i', trim($strongs), $matches) === 1) {
            return 'H'.$matches[1].strtolower($matches[2] ?? '');
        }

        return trim($strongs);
    }
}

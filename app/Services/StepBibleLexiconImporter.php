<?php

namespace App\Services;

use App\Models\Lemma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StepBibleLexiconImporter
{
    public const SOURCE = 'https://github.com/STEPBible/STEPBible-Data/tree/master/Lexicons';

    public function __construct(private readonly HebrewTokenizer $tokenizer) {}

    /** @return array{read: int, created: int, updated: int, preserved: int} */
    public function import(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw ValidationException::withMessages(['file' => 'The TBESH lexicon file cannot be read.']);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'The TBESH lexicon file cannot be opened.']);
        }

        $headerFound = false;
        $grouped = [];
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if (! $headerFound) {
                $headerFound = str_starts_with(ltrim($line, "\xEF\xBB\xBF"), "eStrong#\tdStrong\tuStrong\tHebrew\t");

                continue;
            }

            $fields = explode("\t", $line);
            if (count($fields) < 8 || preg_match('/^H\d{4}(?:[a-z])?$/i', $fields[0]) !== 1) {
                continue;
            }

            [$extended, $disambiguated, $unified, $hebrew, $transliteration, $morphology, $gloss, $meaning] = array_pad($fields, 8, '');
            $extended = $this->normalizeExtendedStrongs($extended);
            $grouped[$extended][] = [
                'source_lexical_id' => trim(strtok($disambiguated, ' ') ?: $disambiguated),
                'unified_strongs' => trim($unified),
                'hebrew' => trim($hebrew),
                'transliteration' => trim($transliteration),
                'morphology' => trim($morphology),
                'gloss' => trim($gloss),
                'definition' => $this->plainText($meaning),
            ];
        }
        fclose($handle);

        if (! $headerFound || $grouped === []) {
            throw ValidationException::withMessages(['file' => 'The file does not contain the published TBESH field header and records.']);
        }

        $stats = ['read' => count($grouped), 'created' => 0, 'updated' => 0, 'preserved' => 0];
        DB::transaction(function () use ($grouped, &$stats): void {
            foreach ($grouped as $strongs => $entries) {
                $primary = $entries[0];
                $lemma = Lemma::query()->firstOrNew(['strongs_number' => $strongs]);
                $created = ! $lemma->exists;
                $dirty = false;

                $values = [
                    'hebrew' => $primary['hebrew'],
                    'normalized' => $this->tokenizer->normalize($primary['hebrew']),
                    'transliteration' => str_replace('.', '', $primary['transliteration']),
                    'definition' => $primary['definition'] !== '' ? $primary['definition'] : $primary['gloss'],
                    'base_strongs_number' => $this->baseStrongs($strongs),
                    'source_lexical_id' => $primary['source_lexical_id'],
                    'lexicon_morphology' => $primary['morphology'],
                    'alternate_definitions' => array_map(fn (array $entry): array => array_filter([
                        'source_lexical_id' => $entry['source_lexical_id'],
                        'unified_strongs' => $entry['unified_strongs'],
                        'gloss' => $entry['gloss'],
                        'definition' => $entry['definition'],
                    ]), $entries),
                    'source_reference' => self::SOURCE,
                ];

                foreach ($values as $field => $value) {
                    if ($field === 'alternate_definitions') {
                        if ($lemma->{$field} === null) {
                            $lemma->{$field} = $value;
                            $dirty = true;
                        }

                        continue;
                    }

                    if (blank($lemma->{$field}) || in_array(strtoupper(trim((string) $lemma->{$field})), ['N/A', 'NA'], true)) {
                        $lemma->{$field} = $value;
                        $dirty = true;
                    }
                }

                if ($created || $dirty) {
                    $lemma->save();
                    $created ? $stats['created']++ : $stats['updated']++;
                } else {
                    $stats['preserved']++;
                }
            }
        });

        return $stats;
    }

    private function normalizeExtendedStrongs(string $value): string
    {
        preg_match('/^H0*(\d+)([a-z])?$/i', trim($value), $matches);

        return 'H'.($matches[1] ?? $value).strtolower($matches[2] ?? '');
    }

    private function baseStrongs(string $value): string
    {
        preg_match('/^H0*(\d+)/i', $value, $matches);

        return 'H'.($matches[1] ?? ltrim($value, 'H'));
    }

    private function plainText(string $value): string
    {
        $value = preg_replace('/<br\s*\/?>/i', "\n", $value) ?? $value;

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

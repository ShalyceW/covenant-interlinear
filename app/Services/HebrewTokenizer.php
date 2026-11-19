<?php

namespace App\Services;

use Normalizer;

class HebrewTokenizer
{
    /** @return list<array{hebrew: string, normalized: string}> */
    public function tokenize(string $text): array
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $token): array => [
            'hebrew' => $this->normalizeDisplay($token),
            'normalized' => $this->normalize($token),
        ], $tokens);
    }

    /**
     * Replace Hebrew presentation-form ligatures with ordinary Hebrew letters
     * and combining marks. The compatibility glyphs are absent from several
     * otherwise capable Hebrew fonts and render as boxes in the reader.
     */
    public function normalizeDisplay(string $text): string
    {
        if (! class_exists(Normalizer::class)) {
            return $text;
        }

        return implode('', array_map(
            fn (string $character): string => Normalizer::normalize($character, Normalizer::FORM_KC) ?: $character,
            mb_str_split($text),
        ));
    }

    public function normalize(string $text): string
    {
        $text = $this->normalizeDisplay($text);

        $text = preg_replace('/\p{Mn}+/u', '', $text) ?? $text;
        // \p{Hebrew} includes punctuation such as maqaf and sof pasuq. A
        // lexical comparison key must contain letters only.
        $text = preg_replace('/[^\x{05D0}-\x{05EA}]+/u', '', $text) ?? $text;

        return trim($text);
    }

    public function lookupForm(string $text): string
    {
        // Punctuation at a token boundary is not part of the surface form, but
        // an internal maqaf represents a source-defined compound and is kept.
        return preg_replace('/^[\s.,;:!?\x{05C3}\x{05F3}\x{05F4}]+|[\s.,;:!?\x{05C3}\x{05F3}\x{05F4}]+$/u', '', $text) ?? $text;
    }
}

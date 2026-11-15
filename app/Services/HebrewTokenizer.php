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
            'hebrew' => $token,
            'normalized' => $this->normalize($token),
        ], $tokens);
    }

    public function normalize(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            // Compatibility decomposition also normalizes Hebrew presentation
            // forms (for example the shin ligature found in the source text).
            $text = Normalizer::normalize($text, Normalizer::FORM_KD) ?: $text;
        }

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

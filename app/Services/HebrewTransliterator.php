<?php

namespace App\Services;

use Normalizer;

class HebrewTransliterator
{
    /** @var array<string, string> */
    private const CONSONANTS = [
        'א' => '', 'ב' => 'v', 'ג' => 'g', 'ד' => 'd', 'ה' => 'h', 'ו' => 'v',
        'ז' => 'z', 'ח' => 'ch', 'ט' => 't', 'י' => 'y', 'כ' => 'kh', 'ך' => 'kh',
        'ל' => 'l', 'מ' => 'm', 'ם' => 'm', 'נ' => 'n', 'ן' => 'n', 'ס' => 's',
        'ע' => '', 'פ' => 'f', 'ף' => 'f', 'צ' => 'ts', 'ץ' => 'ts', 'ק' => 'q',
        'ר' => 'r', 'ש' => 'sh', 'ת' => 't',
    ];

    /** @var array<string, string> */
    private const VOWELS = [
        "\u{05B1}" => 'e', "\u{05B2}" => 'a', "\u{05B3}" => 'o',
        "\u{05B4}" => 'i', "\u{05B5}" => 'e', "\u{05B6}" => 'e', "\u{05B7}" => 'a',
        "\u{05B8}" => 'a', "\u{05B9}" => 'o', "\u{05BA}" => 'o', "\u{05BB}" => 'u',
        "\u{05C7}" => 'o',
    ];

    public function __construct(private readonly HebrewTokenizer $tokenizer) {}

    public function transliterate(string $hebrew): string
    {
        $lookup = $this->tokenizer->lookupForm($hebrew);
        $normalized = $this->tokenizer->normalize($lookup);

        if (($properName = $this->properNameOverride($lookup, $normalized)) !== null) {
            return $properName;
        }

        $override = config("hebrew.transliteration_overrides.{$normalized}");
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $parts = preg_split('/\x{05BE}/u', $lookup) ?: [$lookup];

        return implode('-', array_values(array_filter(array_map(
            fn (string $part): string => $this->transliteratePart($part),
            $parts,
        ))));
    }

    private function transliteratePart(string $hebrew): string
    {
        $text = class_exists(Normalizer::class)
            ? (Normalizer::normalize($hebrew, Normalizer::FORM_D) ?: $hebrew)
            : $hebrew;

        preg_match_all('/([\x{05D0}-\x{05EA}])([\p{Mn}]*)/u', $text, $matches, PREG_SET_ORDER);
        $clusters = array_map(fn (array $match): array => ['letter' => $match[1], 'marks' => $match[2]], $matches);
        $output = '';
        $count = count($clusters);

        foreach ($clusters as $index => $cluster) {
            $letter = $cluster['letter'];
            $marks = $cluster['marks'];
            $hasDagesh = str_contains($marks, "\u{05BC}");
            $vowel = $this->vowel($marks, $index, $clusters);
            $consonant = self::CONSONANTS[$letter] ?? '';

            if ($letter === 'ב' && $hasDagesh) {
                $consonant = 'b';
            } elseif (($letter === 'כ' || $letter === 'ך') && $hasDagesh) {
                $consonant = 'k';
            } elseif (($letter === 'פ' || $letter === 'ף') && $hasDagesh) {
                $consonant = 'p';
            } elseif ($letter === 'ש') {
                $consonant = str_contains($marks, "\u{05C2}") && ! str_contains($marks, "\u{05C1}") ? 's' : 'sh';
            }

            if ($letter === 'ו' && $hasDagesh && $vowel === '') {
                $consonant = '';
                $vowel = 'u';
            } elseif ($letter === 'ו' && $vowel === 'o') {
                $consonant = '';
            }

            if ($letter === 'י' && $vowel === '' && $index > 0) {
                $previousMarks = $clusters[$index - 1]['marks'];
                if (str_contains($previousMarks, "\u{05B4}")) {
                    $consonant = '';
                } elseif ($index === $count - 1 && str_contains($previousMarks, "\u{05B5}")) {
                    $consonant = 'i';
                } elseif ($this->endsInVowel($output)) {
                    $consonant = '';
                }
            }

            if ($index === $count - 1 && $vowel === '' && in_array($letter, ['א', 'ה'], true)) {
                $consonant = $letter === 'ה' && ! $this->endsInVowel($output) ? 'h' : '';
            }

            $output .= $consonant.$vowel;
        }

        return trim($this->addReadablePrefixBreaks($text, $output), '- ');
    }

    /** @param list<array{letter: string, marks: string}> $clusters */
    private function vowel(string $marks, int $index, array $clusters): string
    {
        if (str_contains($marks, "\u{05B0}")) {
            if ($index === 0 || ($index > 0 && $this->explicitVowel($clusters[$index - 1]['marks']) === '')) {
                return 'e';
            }

            return '';
        }

        return $this->explicitVowel($marks);
    }

    private function explicitVowel(string $marks): string
    {
        foreach (self::VOWELS as $mark => $sound) {
            if (str_contains($marks, $mark)) {
                return $sound;
            }
        }

        return '';
    }

    private function addReadablePrefixBreaks(string $hebrew, string $output): string
    {
        if (preg_match('/^ו[\x{05B0}\x{05B7}\x{05BC}]/u', $hebrew) === 1) {
            $prefix = str_contains(mb_substr($hebrew, 0, 2), "\u{05BC}") ? 'u' : (str_contains($hebrew, "\u{05B7}") ? 'va' : 've');
            if (str_starts_with($output, $prefix) && mb_strlen($output) > mb_strlen($prefix)) {
                $output = $prefix.'-'.mb_substr($output, mb_strlen($prefix));
            }
        }

        if (preg_match('/^ה[\x{05B7}\x{05B8}]/u', $hebrew) === 1 && str_starts_with($output, 'ha') && mb_strlen($output) > 2) {
            $output = 'ha-'.mb_substr($output, 2);
        }

        if (preg_match('/^(?:וּ)?בִי/u', $hebrew) === 1) {
            $output = preg_replace('/^(u-)?vi(?=m)/', '$1vi-', $output) ?? $output;
        }

        return $output;
    }

    private function properNameOverride(string $hebrew, string $normalized): ?string
    {
        foreach ((array) config('hebrew.local_lemmas', []) as $key => $lemma) {
            if (! str_ends_with($normalized, (string) $key)) {
                continue;
            }

            $name = $lemma['transliteration'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $prefixLength = mb_strlen($normalized) - mb_strlen((string) $key);
            if ($prefixLength === 0) {
                return $name;
            }

            preg_match_all('/[\x{05D0}-\x{05EA}][\p{Mn}]*/u', $hebrew, $clusters);
            $prefix = implode('', array_slice($clusters[0], 0, $prefixLength));
            $rendered = $this->transliteratePart($prefix);

            return $rendered === '' ? $name : $rendered.'-'.$name;
        }

        return null;
    }

    private function endsInVowel(string $text): bool
    {
        return preg_match('/[aeiou]$/', $text) === 1;
    }
}

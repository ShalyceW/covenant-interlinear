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
        "\u{05B0}" => 'e', "\u{05B1}" => 'e', "\u{05B2}" => 'a', "\u{05B3}" => 'o',
        "\u{05B4}" => 'i', "\u{05B5}" => 'e', "\u{05B6}" => 'e', "\u{05B7}" => 'a',
        "\u{05B8}" => 'a', "\u{05B9}" => 'o', "\u{05BA}" => 'o', "\u{05BB}" => 'u',
        "\u{05C7}" => 'o',
    ];

    public function transliterate(string $hebrew): string
    {
        $normalizedKey = app(HebrewTokenizer::class)->normalize($hebrew);
        $override = config("hebrew.transliteration_overrides.{$normalizedKey}");
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $text = class_exists(Normalizer::class)
            ? (Normalizer::normalize($hebrew, Normalizer::FORM_D) ?: $hebrew)
            : $hebrew;

        preg_match_all('/([\x{05D0}-\x{05EA}])([\p{Mn}]*)|([\x{05BE}\x{05C3}\s.,;:!?]+)/u', $text, $matches, PREG_SET_ORDER);

        $output = '';
        $letterIndex = 0;
        foreach ($matches as $match) {
            if (($match[3] ?? '') !== '') {
                if (str_contains($match[3], "\u{05BE}")) {
                    $output = rtrim($output, '-').'-';
                    $letterIndex = 0;
                }

                continue;
            }

            $letter = $match[1];
            $marks = $match[2] ?? '';
            $hasDagesh = str_contains($marks, "\u{05BC}");
            $hasShinDot = str_contains($marks, "\u{05C1}");
            $hasSinDot = str_contains($marks, "\u{05C2}");
            $vowel = '';
            foreach (self::VOWELS as $mark => $sound) {
                if (str_contains($marks, $mark)) {
                    $vowel = $sound;
                    break;
                }
            }

            // A medial sheva is normally silent in this deliberately readable
            // convention; initial sheva is rendered as e (Nefi, bereshit).
            if ($vowel === 'e' && str_contains($marks, "\u{05B0}") && $letterIndex > 0) {
                $vowel = '';
            }

            $consonant = self::CONSONANTS[$letter] ?? '';
            if ($letter === 'ב' && $hasDagesh) {
                $consonant = 'b';
            } elseif (($letter === 'כ' || $letter === 'ך') && $hasDagesh) {
                $consonant = 'k';
            } elseif (($letter === 'פ' || $letter === 'ף') && $hasDagesh) {
                $consonant = 'p';
            } elseif ($letter === 'ש') {
                $consonant = $hasSinDot && ! $hasShinDot ? 's' : 'sh';
            }

            // Shureq and holam male are vowels, not consonantal vav.
            if ($letter === 'ו' && $hasDagesh && $vowel === '') {
                $consonant = '';
                $vowel = 'u';
            } elseif ($letter === 'ו' && $vowel === 'o') {
                $consonant = '';
            }

            // Unpointed aleph and a final unpointed yod commonly serve as
            // vowel letters. Omitting them keeps the result readable.
            if ($letter === 'י' && $vowel === '' && $this->previousEndsInVowel($output)) {
                $consonant = '';
            }

            $output .= $consonant.$vowel;
            $letterIndex++;
        }

        return trim(preg_replace('/-+/u', '-', $output) ?? $output, '- ');
    }

    private function previousEndsInVowel(string $text): bool
    {
        return preg_match('/[aeiou]$/', $text) === 1;
    }
}

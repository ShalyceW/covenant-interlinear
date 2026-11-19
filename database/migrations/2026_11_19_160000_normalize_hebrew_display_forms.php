<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(Normalizer::class)) {
            return;
        }

        $normalize = static fn (string $text): string => implode('', array_map(
            static fn (string $character): string => Normalizer::normalize($character, Normalizer::FORM_KC) ?: $character,
            mb_str_split($text),
        ));

        DB::table('hebrew_words')->orderBy('id')->eachById(function (object $word) use ($normalize): void {
            $hebrew = $normalize((string) $word->hebrew);
            if ($hebrew !== $word->hebrew) {
                DB::table('hebrew_words')->where('id', $word->id)->update(['hebrew' => $hebrew]);
            }
        });

        DB::table('lemmas')->orderBy('id')->eachById(function (object $lemma) use ($normalize): void {
            $hebrew = $normalize((string) $lemma->hebrew);
            if ($hebrew !== $lemma->hebrew) {
                DB::table('lemmas')->where('id', $lemma->id)->update(['hebrew' => $hebrew]);
            }
        });

        DB::table('passages')->orderBy('id')->eachById(function (object $passage) use ($normalize): void {
            $hebrew = $normalize((string) $passage->hebrew_text);
            if ($hebrew !== $passage->hebrew_text) {
                DB::table('passages')->where('id', $passage->id)->update(['hebrew_text' => $hebrew]);
            }
        });
    }

    public function down(): void
    {
        // Compatibility normalization is intentionally irreversible: both
        // forms are canonically equivalent, while the normalized form renders.
    }
};

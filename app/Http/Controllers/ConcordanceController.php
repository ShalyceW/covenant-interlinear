<?php

namespace App\Http\Controllers;

use App\Models\HebrewWord;
use App\Services\HebrewTokenizer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConcordanceController extends Controller
{
    public function __invoke(Request $request, HebrewTokenizer $tokenizer): Response
    {
        $search = trim((string) $request->string('q'));
        $normalized = $tokenizer->normalize($search);
        $words = collect();

        if ($search !== '') {
            $words = HebrewWord::query()
                ->with('lemma')
                ->whereHas('passage.chapter', fn ($query) => $query->whereNotNull('published_at'))
                ->where(function ($query) use ($search, $normalized): void {
                    $query->where('hebrew', 'like', "%{$search}%")
                        ->orWhere('normalized', 'like', "%{$normalized}%")
                        ->orWhere('transliteration', 'like', "%{$search}%")
                        ->orWhere('english_gloss', 'like', "%{$search}%")
                        ->orWhereHas('lemma', fn ($lemma) => $lemma
                            ->where('hebrew', 'like', "%{$search}%")
                            ->orWhere('normalized', 'like', "%{$normalized}%")
                            ->orWhere('transliteration', 'like', "%{$search}%")
                            ->orWhere('definition', 'like', "%{$search}%")
                            ->orWhere('strongs_number', 'like', "%{$search}%"));
                })
                ->limit(500)
                ->get();
        }

        $results = $words->groupBy(fn ($word) => $word->lemma_id ? "lemma:{$word->lemma_id}" : "form:{$word->normalized}")
            ->map(function ($group): array {
                $word = $group->first();
                $hasLemma = $word->lemma_id !== null && $word->lemma !== null;

                return [
                    'word_id' => $word->id,
                    'hebrew' => $hasLemma ? $word->lemma->hebrew : $word->hebrew,
                    'normalized' => $hasLemma ? $word->lemma->normalized : $word->normalized,
                    'transliteration' => $hasLemma ? $word->lemma->transliteration : $word->transliteration,
                    'definition' => $hasLemma ? $word->lemma->definition : $word->english_gloss,
                    'strongs_number' => $hasLemma ? $word->lemma->strongs_number : null,
                    'occurrence_count' => $group->count(),
                ];
            })->values()->take(100);

        return Inertia::render('Concordance/Index', [
            'query' => $search,
            'results' => $results,
        ]);
    }
}

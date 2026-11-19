<?php

namespace App\Http\Controllers;

use App\Models\HebrewWord;
use App\Models\Lemma;
use App\Services\HebrewTokenizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WordReviewController extends Controller
{
    public function update(Request $request, HebrewWord $word, HebrewTokenizer $tokenizer): RedirectResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $data = $request->validate([
            'field' => ['required', Rule::in(['gloss', 'lemma'])],
            'value' => ['required', 'string', 'max:255'],
        ]);
        $enrichment = $word->enrichment ?? [];

        if ($data['field'] === 'gloss') {
            $word->english_gloss = $data['value'];
            $enrichment['gloss'] = array_merge($enrichment['gloss'] ?? [], [
                'status' => 'confirmed',
                'source' => 'editorial',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now()->toIso8601String(),
            ]);
        } else {
            $candidate = collect((array) data_get($enrichment, 'lemma.candidates', []))
                ->firstWhere('strongs_number', $data['value']);
            abort_unless(is_array($candidate) && filled($candidate['headword'] ?? null), 422);

            $strongs = (string) $candidate['strongs_number'];
            $hebrew = $tokenizer->normalizeDisplay((string) $candidate['headword']);
            $lemma = Lemma::query()->firstOrCreate(
                ['strongs_number' => $strongs],
                [
                    'hebrew' => $hebrew,
                    'normalized' => $tokenizer->normalize($hebrew),
                    'base_strongs_number' => $candidate['base_strongs_number'] ?? null,
                    'source_lexical_id' => $candidate['lexical_id'] ?? null,
                    'definition' => $candidate['definition'] ?? null,
                    'source_reference' => rtrim((string) config('services.brit_hamashiach.url'), '/')
                        .'/api/volume/morphology/'.rawurlencode($tokenizer->lookupForm($word->hebrew)),
                ],
            );
            $word->lemma_id = $lemma->id;
            $enrichment['lemma'] = array_merge($enrichment['lemma'] ?? [], [
                'status' => 'confirmed',
                'source' => 'editorial',
                'strongs_number' => $strongs,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now()->toIso8601String(),
            ]);
        }

        $word->enrichment = $enrichment;
        $word->save();

        return back();
    }
}

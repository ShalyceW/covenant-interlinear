<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\HebrewWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScriptureController extends Controller
{
    public function home(): Response|RedirectResponse
    {
        $chapter = Chapter::query()->published()->with('book')->orderBy('book_id')->orderBy('number')->first();

        return $chapter
            ? to_route('scripture.show', [$chapter->book->slug, $chapter->number])
            : Inertia::render('Welcome');
    }

    public function show(Book $book, int $chapter): Response
    {
        $selected = $book->chapters()->published()->where('number', $chapter)->firstOrFail();
        $selected->load(['passages.hebrewWords.lemma']);

        return Inertia::render('Scripture/Show', [
            'book' => $book->only('id', 'slug', 'name', 'hebrew_name'),
            'chapter' => [
                'id' => $selected->id,
                'number' => $selected->number,
                'passages' => $selected->passages->map(fn ($passage) => [
                    'id' => $passage->id,
                    'reference' => $passage->reference,
                    'hebrew_text' => $passage->hebrew_text,
                    'english_text' => $passage->english_text,
                    'words' => $passage->hebrewWords->map(fn ($word) => [
                        'id' => $word->id,
                        'position' => $word->position,
                        'hebrew' => $word->hebrew,
                        'normalized' => $word->normalized,
                        'transliteration' => $word->transliteration,
                        'english_gloss' => $word->english_gloss,
                        'has_lemma' => $word->lemma !== null,
                    ]),
                ]),
            ],
            'books' => Book::query()->whereHas('publishedChapters')->with(['publishedChapters:id,book_id,number'])
                ->orderBy('position')->get(['id', 'slug', 'name']),
            'chapters' => $book->publishedChapters()->get(['id', 'number']),
        ]);
    }

    public function wordStudy(Request $request, HebrewWord $word): JsonResponse
    {
        $word->load(['lemma']);
        $confirmedLemma = $word->hasConfirmedLemma();
        $query = HebrewWord::query()
            ->with(['passage.chapter.book'])
            ->whereHas('passage.chapter', fn ($query) => $query->whereNotNull('published_at'))
            ->when(
                $confirmedLemma,
                fn ($query) => $query->where('lemma_id', $word->lemma_id)->where('enrichment->lemma->status', 'confirmed'),
                fn ($query) => $query->where('normalized', $word->normalized),
            )
            ->orderBy('passage_id')
            ->orderBy('position');
        $occurrenceCount = (clone $query)->count();
        $page = max(1, $request->integer('page', 1));
        $perPage = 50;
        $occurrences = $query->forPage($page, $perPage)->get();

        return response()->json([
            'word' => $word->only('id', 'hebrew', 'normalized', 'transliteration', 'english_gloss', 'morphology', 'enrichment'),
            'lemma' => $word->lemma?->only('id', 'hebrew', 'normalized', 'transliteration', 'definition', 'root', 'strongs_number', 'source_reference'),
            'match_type' => $confirmedLemma ? 'confirmed_lemma' : ($word->lemma_id === null ? 'unresolved_form' : 'exact_written_form'),
            'occurrence_count' => $occurrenceCount,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($occurrenceCount / $perPage)),
            ],
            'occurrences' => $occurrences->map(fn ($occurrence) => [
                'id' => $occurrence->id,
                'passage_id' => $occurrence->passage_id,
                'reference' => $occurrence->passage->reference,
                'hebrew' => $occurrence->passage->hebrew_text,
                'english' => $occurrence->passage->english_text,
                'book_slug' => $occurrence->passage->chapter->book->slug,
                'chapter' => $occurrence->passage->chapter->number,
                'word' => $occurrence->hebrew,
            ]),
            'sources' => [
                'hebrew_scripture' => data_get($word->enrichment, 'sources.hebrew_scripture') ?? $word->passage->chapter->source_url,
                'english_context' => $word->passage->reference,
                'supporting_lexicon' => $word->lemma?->source_reference,
                'annotation' => data_get($word->enrichment, 'sources.annotation'),
            ],
        ]);
    }
}

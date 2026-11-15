<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\HebrewWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function wordStudy(HebrewWord $word): JsonResponse
    {
        $word->load(['lemma']);
        $occurrences = HebrewWord::query()
            ->with(['passage.chapter.book'])
            ->whereHas('passage.chapter', fn ($query) => $query->whereNotNull('published_at'))
            ->when(
                $word->lemma_id,
                fn ($query) => $query->where('lemma_id', $word->lemma_id),
                fn ($query) => $query->where('normalized', $word->normalized),
            )
            ->limit(250)
            ->get();

        return response()->json([
            'word' => $word->only('id', 'hebrew', 'normalized', 'transliteration', 'english_gloss', 'morphology'),
            'lemma' => $word->lemma?->only('id', 'hebrew', 'normalized', 'transliteration', 'definition', 'root', 'strongs_number', 'source_reference'),
            'occurrence_count' => $occurrences->count(),
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
        ]);
    }
}

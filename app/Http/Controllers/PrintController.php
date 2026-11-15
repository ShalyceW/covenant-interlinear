<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;

class PrintController extends Controller
{
    public function show(Book $book, int $chapter): View
    {
        $selected = $book->chapters()->published()->where('number', $chapter)->firstOrFail();
        $selected->load('passages.hebrewWords');

        return view('print.chapter', ['book' => $book, 'chapter' => $selected]);
    }
}

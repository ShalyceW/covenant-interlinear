<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $book_id
 * @property int $number
 * @property string $source_url
 * @property CarbonInterface|null $imported_at
 * @property CarbonInterface|null $published_at
 * @property Book $book
 */
class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory;

    protected $fillable = ['book_id', 'number', 'source_url', 'imported_at', 'published_at'];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime', 'published_at' => 'datetime'];
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** @return HasMany<Passage, $this> */
    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class)->orderBy('position');
    }

    /**
     * @param  Builder<Chapter>  $query
     * @return Builder<Chapter>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }
}

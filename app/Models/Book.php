<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $hebrew_name
 * @property int $position
 * @property string|null $source_url
 */
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'name', 'hebrew_name', 'position', 'source_url'];

    /** @return HasMany<Chapter, $this> */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('number');
    }

    /** @return HasMany<Chapter, $this> */
    public function publishedChapters(): HasMany
    {
        return $this->chapters()->whereNotNull('published_at');
    }
}

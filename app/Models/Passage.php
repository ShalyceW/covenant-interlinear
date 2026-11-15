<?php

namespace App\Models;

use Database\Factories\PassageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $chapter_id
 * @property string $reference
 * @property int $position
 * @property string $hebrew_text
 * @property string $english_text
 * @property string|null $source_hash
 * @property bool $manually_edited
 * @property bool $needs_retokenization
 * @property Chapter $chapter
 */
class Passage extends Model
{
    /** @use HasFactory<PassageFactory> */
    use HasFactory;

    protected $fillable = [
        'chapter_id', 'reference', 'position', 'hebrew_text', 'english_text',
        'source_hash', 'manually_edited', 'needs_retokenization',
    ];

    protected function casts(): array
    {
        return ['manually_edited' => 'boolean', 'needs_retokenization' => 'boolean'];
    }

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /** @return HasMany<HebrewWord, $this> */
    public function hebrewWords(): HasMany
    {
        return $this->hasMany(HebrewWord::class)->orderBy('position');
    }
}

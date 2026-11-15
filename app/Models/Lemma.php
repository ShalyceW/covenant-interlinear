<?php

namespace App\Models;

use Database\Factories\LemmaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $hebrew
 * @property string $normalized
 * @property string|null $transliteration
 * @property string|null $definition
 * @property string|null $root
 * @property string|null $strongs_number
 * @property string|null $source_reference
 */
class Lemma extends Model
{
    /** @use HasFactory<LemmaFactory> */
    use HasFactory;

    protected $fillable = [
        'hebrew', 'normalized', 'transliteration', 'definition', 'root',
        'strongs_number', 'source_reference',
    ];

    /** @return HasMany<HebrewWord, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(HebrewWord::class);
    }
}

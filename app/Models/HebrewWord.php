<?php

namespace App\Models;

use Database\Factories\HebrewWordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $passage_id
 * @property int|null $lemma_id
 * @property int $position
 * @property string $hebrew
 * @property string $normalized
 * @property string|null $transliteration
 * @property string|null $english_gloss
 * @property array<string, mixed>|null $morphology
 * @property array<string, mixed>|null $enrichment
 * @property Passage $passage
 * @property Lemma|null $lemma
 */
class HebrewWord extends Model
{
    /** @use HasFactory<HebrewWordFactory> */
    use HasFactory;

    protected $fillable = [
        'passage_id', 'lemma_id', 'position', 'hebrew', 'normalized',
        'transliteration', 'english_gloss', 'morphology', 'enrichment',
    ];

    protected function casts(): array
    {
        return ['morphology' => 'array', 'enrichment' => 'array'];
    }

    /** @return BelongsTo<Passage, $this> */
    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class);
    }

    /** @return BelongsTo<Lemma, $this> */
    public function lemma(): BelongsTo
    {
        return $this->belongsTo(Lemma::class);
    }

    public function hasConfirmedLemma(): bool
    {
        return $this->lemma_id !== null
            && data_get($this->enrichment, 'lemma.status') === 'confirmed';
    }
}

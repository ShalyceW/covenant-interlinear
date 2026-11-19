<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiblicalReference extends Model
{
    protected $fillable = [
        'lemma_id', 'source', 'raw_reference', 'normalized_reference',
        'book', 'chapter', 'verse', 'source_position', 'source_url',
    ];

    /** @return BelongsTo<Lemma, $this> */
    public function lemma(): BelongsTo
    {
        return $this->belongsTo(Lemma::class);
    }
}

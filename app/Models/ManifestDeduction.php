<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestDeduction extends Model
{
    protected $fillable = [
        'manifest_id',
        'deduction_type',
        'title',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class, 'manifest_id');
    }
}


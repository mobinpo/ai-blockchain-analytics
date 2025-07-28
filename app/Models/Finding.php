<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Finding extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_id',
        'severity',
        'title',
        'description',
        'line',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function analysis()
    {
        return $this->belongsTo(Analysis::class);
    }
} 
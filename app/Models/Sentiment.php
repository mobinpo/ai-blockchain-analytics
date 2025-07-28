<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sentiment extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_id',
        'score',
        'magnitude',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function analysis()
    {
        return $this->belongsTo(Analysis::class);
    }
} 
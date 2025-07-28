<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'engine',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function findings()
    {
        return $this->hasMany(Finding::class);
    }

    public function sentiments()
    {
        return $this->hasMany(Sentiment::class);
    }
} 
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    use HasFactory;

   protected $fillable = [
        'external_id',
        'title',
        'genre',
        'year',
        'plot',
        'poster_url',
        'imdb_id',
    ];

    public function watchlists()
    {
        return $this->hasMany(Watchlist::class);
    }
}
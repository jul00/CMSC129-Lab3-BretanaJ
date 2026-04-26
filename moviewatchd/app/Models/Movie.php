<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Movie extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title','genre','release_year','rating','comment','watched_at','category_id','poster'
    ];

    public function category(){
        return $this->belongsTo(Category::class);
    }
    
}

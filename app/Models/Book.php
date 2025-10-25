<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'book_title',
        'book_author',
        'book_description',
        'category_name',
        'publish_date',
        'file_size_info',
        'file_ext',
        'cover_url',
        'using_drm',
        'borrowed',
        'path',
        'language',
        'publisher',
    ];
}

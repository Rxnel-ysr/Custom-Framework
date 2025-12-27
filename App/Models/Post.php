<?php

namespace App\Models;

use App\Foundation\Model;

class Post extends Model
{
    protected $table = 'posts';
    protected $primary = 'id';

    protected $fillable = [
        'name',
        'email',
        'password'
    ];

    public function hello()
    {
        return 'hello I am is ' . $this->name;
    }


    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id',
        );
    }

    public function comments()
    {
        return $this->hasMany(Comments::class);
    }
}

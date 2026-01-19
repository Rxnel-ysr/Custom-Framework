<?php

namespace App\Models;

use App\Foundation\Model;

class User extends Model
{
    protected $hidden = [
        'password'
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'updated_at'
    ];

    public $timestamps = false;

    public function hello()
    {
        return 'hello I am is ' . $this->name;
    }

    public function posts()
    {
        return $this->hasMany(
            Post::class,
            'user_id',
            'id',
        );
    }

    public function comments()
    {
        return $this->hasMany(Comments::class);
    }
}

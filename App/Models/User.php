<?php

namespace App\Models;

use App\Foundation\Database\Model;
use App\Foundation\Database\Traits\HasUuid;
use App\Foundation\Guard\Traits\HasApiToken;

class User extends Model
{
    use HasApiToken;

    protected $hidden = [
        'password',
        'updated_at',
        'created_at',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public $timestamps = true;

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

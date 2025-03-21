<?php

namespace App\Models;

use App\Utils\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password'
    ];

    public function hello()
    {
        return 'hello I am is ' . $this->name;
    }
}

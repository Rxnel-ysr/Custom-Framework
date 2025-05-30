<?php

namespace App\Models;

use App\Foundation\Model;

class User extends Model
{
    protected $table = 'users';
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
}

<?php

namespace App\Models;

use App\Foundation\Model;

class Testable extends Model
{
    protected $table = 'testable';
    protected $primary = 'id';

    protected $fillable = [
        'nama',
    ];

    protected $casts = [
        'jeson' => 'json',
        'arrr' => 'array',
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function sayHi()
    {
        return "Hello, my name is {$this->nama}. And I was created_at {$this->created_at}";
    }
}

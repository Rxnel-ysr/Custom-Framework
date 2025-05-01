<?php

namespace App\Models;

use App\Foundation\Model;

class Test extends Model
{
    protected $table = 'test';
    protected $fillable = [
        'id',
        'name',
        'created_at',
        'updated_at'
    ];
}

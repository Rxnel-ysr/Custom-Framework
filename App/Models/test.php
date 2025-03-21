<?php

namespace App\Models;

use App\Utils\Model;

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

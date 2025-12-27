<?php

namespace App\Models;

use App\Foundation\Model;

class Student extends Model
{
    protected $table = 'murid';
    protected $primary = 'id';

    protected $fillable = [
        'nama',
        'nisn',
        'status',
        'nyawa',
        'jenis_kelamin',
        'nik'
    ];
}

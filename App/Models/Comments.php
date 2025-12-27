<?php

namespace App\Models;

use App\Foundation\Model;

class Comments extends Model
{
    protected $table = 'comments';
    protected $primary = 'id';

    protected $fillable = [
        'name',
        'email',
        'password'
    ];

    protected $hidden = [
        'created_at'
    ];

    public function hello()
    {
        return 'hello I am is ' . $this->name;
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
}

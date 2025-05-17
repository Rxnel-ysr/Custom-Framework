<?php
namespace App\Http\Middlewares;

function auk(){
    if(1 == 1){
        echo 'Got u';
        return false;
    }
    return true;
}
<?php


namespace App\Contracts\Implementation;

use App\Contracts\Interfaces\TestInterface;

class TestImplementation implements TestInterface{
    public function died(): void
    {
        echo "Does this works?";
        die();
    }
}
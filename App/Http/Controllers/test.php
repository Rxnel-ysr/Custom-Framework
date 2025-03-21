<?php

namespace App\Http\Controllers;

use App\Utils\Http\Controller;


class test extends Controller
{
    public function test()
    {
       $response1 = response();
       $response2 = response();
       var_dump($response1 == $response2);
    }
}

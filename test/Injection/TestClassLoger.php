<?php

namespace Test;

use Setup;

#[Setup(['type' => 'k'])]
class Logger
{
    public function __construct(public string $type) {}
    public function log($msg)
    {
        echo "[LOG] $msg\n";
    }
}

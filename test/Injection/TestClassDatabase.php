<?php

namespace Test;

use Setup;

#[Setup]
class Database
{
    public function connect()
    {
        echo "Connected to database.\n";
    }
}

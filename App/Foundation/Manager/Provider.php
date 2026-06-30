<?php

namespace App\Foundation\Manager;

use App\App;

interface Provider {
    public function boot(App $app);
    public function register(App $app);
}
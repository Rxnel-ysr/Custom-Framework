<?php

namespace App\Reactive;

use App\Foundation\Reactive\Reactive;

class Oka extends Reactive
{
    public function sync($name)
    {
        $this->states['name'] = $name;
    }

    public function view(): string
    {
        return 'components.home';
    }
}

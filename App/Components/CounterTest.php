<?php

namespace App\Reactive;

use App\Foundation\Reactive\Reactive;

class CounterTest extends Reactive
{

    public function increment()
    {
        return $this->states['count']++;
    }

    public function view(): string
    {
        return 'components.CounterTest';
    }
}

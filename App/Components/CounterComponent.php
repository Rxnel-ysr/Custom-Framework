<?php

namespace App\Reactive;

use App\Foundation\Reactive\Reactive;

class CounterComponent extends Reactive
{

    public function increment($count)
    {
        return $this->states['count'] = $count;
    }

    public function decrement($count)
    {
        return $this->states['count'] = $count;
    }

    public function view(): string
    {
        return 'components.counter';
    }
}

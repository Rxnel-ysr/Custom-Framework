<?php

namespace App\Reactive;

use App\Foundation\Reactive\Reactive;

class Oka extends Reactive
{
    public function switch()
    {
        $this->states['home'] = !$this->states['home'];
        $this->setView(($this->states['home'] ? 'components.home' : 'components.about'));
    }

    public function view(): string
    {
        return 'components.home';
    }
}

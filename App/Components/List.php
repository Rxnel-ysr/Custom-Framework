<?php

namespace App\Reactive;

use App\Foundation\Reactive\Reactive;

class Lists extends Reactive
{
    
    public function update($temp)
    {
        $this->states['temp'] = $temp;
    }
    
    public function add($temp){
        $this->states['lists'][] = $temp;
        $this->states['temp'] = '';
    }

    public function view(): string
    {
        return 'components.List';
    }
}

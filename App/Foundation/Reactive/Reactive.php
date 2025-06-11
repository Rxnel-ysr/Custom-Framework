<?php

namespace App\Foundation\Reactive;

use App\Foundation\Compiler\Compile;
use App\Foundation\Http\Request;
use App\Foundation\Http\Route;
use App\Support\Facades\Rx;
use Exception;

class Reactive
{
    public string $id;
    protected static string $baseView;
    protected string $view;
    protected array $states;
    protected static $initialized;

    public function __construct(array $initialState = [])
    {
        $this->id = static::class . '_' . spl_object_id($this);
        $this->view = str_replace('.', DIRECTORY_SEPARATOR, $this->view());
        $this->states = $initialState;
    }

    public function render()
    {
        // try {
        ob_start();
        Compile::compile($this->view, ['id' => $this->id, 'currentStates' => $this->states, ...$this->states]);
        return ob_get_clean();
        // } catch (\Throwable $e) {
        //     return "<!-- render error: {$e->getMessage()} -->";
        // }

    }

    public function view(): string
    {
        throw new Exception(static::class . ': Must define view for component');
    }

    public function handle(string $_action, mixed ...$args)
    {
        if (!method_exists($this, $_action)) {
            throw new BadMethodCallException("Method {$_action} not found");
        }

        $this->$_action(...$args);

        // return response()->json($this->states);

        return response()->json([
            'id' => $this->id,
            'html' => $this->render(),
            'state' => $this->states
        ]);
    }
}


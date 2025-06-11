<?php

namespace App\Foundation\Reactive;

use App\Foundation\Compiler\Compile;
use App\Foundation\Http\Request;
use App\Foundation\Http\Route;
use App\Support\Facades\Rx;
use Exception;

class ReactiveHandler
{
    public static array $components;

    public static function init()
    {
        self::$components = [];
    }

    public static function handle($id, $action, ...$args)
    {
        self::$components[$id]->handle($action, ...$args);
    }

    public static function state($id, $name)
    {
        return self::$components[$id]->states[$name] ?? '';
    }

    public static function register($id, $obj)
    {
        return self::$components[$id] = $obj;
    }

    public static function hasComponent($id): bool
    {
        return isset(self::$components[$id]);
    }

    public static function getALlComponents()
    {
        return self::$components;
    }
}

class Reactive
{
    public string $id;
    protected static string $baseView;
    protected string $view;
    public array $states;
    protected static $initialized;

    public function __construct(array $initialState = [])
    {
        $this->id = static::class;
        $this->view = str_replace('.', DIRECTORY_SEPARATOR, $this->view());
        $this->states = $initialState;
    }

    public function render()
    {
        ob_start();
        Compile::compile($this->view, ['id' => $this->id, 'currentStates' => $this->states, ...$this->states]);
        return ob_get_clean();
    }

    public function view(): string
    {
        throw new Exception(static::class . ': Must define view for component');
    }

    public function handle(string $name, mixed ...$args)
    {
        if (!method_exists($this, $name)) {
            throw new BadMethodCallException("Method {$name} not found");
        }

        $this->$name(...$args);

        // return response()->json($this->states);

        return response()->json([
            'id' => $this->id,
            'html' => $this->render(),
            'state' => $this->states
        ]);
    }
}

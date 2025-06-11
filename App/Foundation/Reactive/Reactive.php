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

/**
 * Register Rx directive
 */
Rx::register('reactive', function ($component, $state): void {
    $component = 'App\Reactive\\' . $component;
    $instance = new $component($state);
    echo $instance->render();
});


/**
 * Route registration
 */
Route::post('/__reactive', function (Request $request) {
    // 1. Get raw JSON data (since the frontend sends `application/json`)
    $data = $request->json();

    // 2. Extract reactive metadata
    $componentName = $data['__component_name'] ?? null;
    $action = $data['__component_action'] ?? null;
    $states = $data['__component_states'] ?? [];
    $params = array_diff_key($data, [
        '__component_name' => true,
        '__component_action' => true,
        '__component_states' => true,
    ]);

    // 3. Debugging (optional)

    // return response()->json($data);
    // return response()->json(compact('componentName', 'action', 'states', 'params'));

    // 4. Handle the component logic
    if (!class_exists($componentName)) {
        return response()->json([
            'message' => 'Component not found',
            'component' => $componentName,
        ], 404);
    }

    $component = new $componentName($states);
    // return response()->json($component->states);
    // $component->
    return $component->handle($action, ...$params);
});
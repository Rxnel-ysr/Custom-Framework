<?php

namespace App\Foundation\Reactive;

use App\Foundation\Compiler\Compile;
use App\Foundation\Http\Request;
use App\Foundation\Http\Route;
use App\Support\Facades\Rx;
use Exception;

class ReactiveHandler
{
    protected static bool $initialized = false;

    public static function init()
    {
        self::$initialized = true;
    }

    public static function isInitialized()
    {
        return self::$initialized;
    }
}

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
        $reativejs = asset('js/reactive.js');

        $injector = <<<HTML
        <script>
        (function() {
            window.addEventListener('load', () => {
                const s = document.createElement('script');
                s.src = "{$reativejs}";
                s.type = "text/javascript";
                s.onload = () => Reactive?.init?.();
                s.onerror = () => alert('Failed to load reactive.js');
                document.body.appendChild(s);
                
                const self = document.currentScript;
                self?.parentNode?.removeChild(self);
            });
        })();
        </script>
        HTML;

        ob_start();
        Compile::compile($this->view, ['id' => $this->id, 'currentStates' => $this->states, ...$this->states]);
        $res = ob_get_clean() . (ReactiveHandler::isInitialized() ? '' : $injector);
        ReactiveHandler::init();
        return $res;
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

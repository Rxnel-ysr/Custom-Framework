<?php

namespace App\Foundation\Reactive;

use App\Foundation\Compiler\Compile;
use App\Foundation\Http\Request;
use App\Foundation\Http\Route;
use App\Support\Facades\DI;
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
        $this->id = static::class . '_' . uniqid('', true);
        $this->view = str_replace('.', DIRECTORY_SEPARATOR, $this->view());
        $this->states = $initialState;
    }

    public function render(bool $reactiveLoader = true)
    {
        $reactivejs = asset('js/reactive.js');
        $nonce = $_ENV['CSP'] === true ? DI::get('nonce') : '';

        $injector = <<<HTML
        <script id="reactive_loader" nonce="{$nonce}">
        (function() {
            const existingLoader = document.getElementById('reactive_loader');
            if (existingLoader) existingLoader.remove();

            if (typeof Reactive !== 'undefined') {
                Reactive?.init?.();
                return;
            }

            window.addEventListener('DOMContentLoaded', () => {
                if (typeof Reactive !== 'undefined') {
                    Reactive?.init?.();
                    document.getElementById('reactive_loader')?.remove();
                    return;
                }

                if (document.getElementById('reactive_js')) {
                    return;
                }

                const script = document.createElement('script');
                script.id = 'reactive_js';
                script.nonce = "{$nonce}";
                script.src = "{$reactivejs}";
                script.onload = () => {
                    Reactive?.init?.();
                    document.getElementById('reactive_loader')?.remove();
                };
                script.onerror = () => {
                    console.error('Failed to load reactive.js');
                    document.getElementById('reactive_loader')?.remove();
                };
                document.body.appendChild(script);
            });
        })();
        </script>
        HTML;

        ob_start();
        Compile::compile($this->view, ['id' => $this->id, 'currentStates' => $this->states, ...$this->states]);
        $content = ob_get_clean();

        return $content . ($reactiveLoader ? $injector : '');
    }

    public function setView($name)
    {
        $this->view = str_replace('.', DIRECTORY_SEPARATOR, $name);
    }

    public function view(): string
    {
        throw new Exception(static::class . ': Must define view for component');
    }

    public function handle(string $_action, mixed ...$args)
    {
        if (!method_exists($this, $_action)) {
            return [
                'found' => false,
                'component' => $this->id,
                'message' => 'Method not found: ' . $_action,
            ];
        }

        $this->$_action(...$args);

        return [
            'found' => true,
            'id' => $this->id,
            'html' => $this->render(false),
            'state' => $this->states,
        ];
    }
}

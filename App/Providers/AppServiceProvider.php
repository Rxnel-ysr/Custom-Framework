<?php

namespace App\Foundation\Providers;

use App\Foundation\Http\Request;
use App\Foundation\Http\Route;
use App\Support\Facades\Rx;

class AppServiceProvider
{

    public static function register(): void
    {
        // Register
        Rx::register('say', function (string $message): void {
            echo $message;
        });

        /**
         * Route registration
         */
        Route::post('/__reactive', function (Request $request) {
            $data = $request->json();

            $componentName = strtok($data['__component_name'] ?? '', '_');
            $action = $data['__component_action'] ?? null;
            $states = $data['__component_states'] ?? [];
            $params = array_diff_key($data, [
                '__component_name' => true,
                '__component_action' => true,
                '__component_states' => true,
            ]);

            if (!class_exists($componentName)) {
                return response()->json([
                    'message' => 'Component not found',
                    'component' => $componentName,
                ], 404);
            }

            $component = new $componentName($states);
            return $component->handle($action, ...$params);
        });

    }

    public static function boot(): void
    {
        // Boot 
    }
}

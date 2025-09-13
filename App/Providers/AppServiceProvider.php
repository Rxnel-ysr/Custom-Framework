<?php

namespace App\Foundation\Providers;

use App\Foundation\Http\Request;
use App\Foundation\Http\Route;
use App\Support\Facades\Rx;

class AppServiceProvider
{

    public function register(): void
    {
        // Register
        Rx::register('say', function (string $message): string {
            return $message;
        });

        Rx::register('jparam', function (...$param): void {
            // $temp = compact(...$param);

            echo json_encode($param);
        });

        /**
         * Route registration
         */
        Route::post('/__reactive', function (Request $request) {
            if ($request->header('X-Component-Request') === null) {
                return response(404)->json([
                    'message' => 'Not found'
                ]);
            }

            $data = $request->json();

            $componentName = strtok($data['__component_name'] ?? '', '_');
            $action = $data['__component_action'] ?? null;
            $states = $data['__component_states'] ?? [];
            $params = $data['params'];

            if (!class_exists($componentName)) {
                return response(404)->json([
                    'message' => 'Component not found',
                    'component' => $componentName,
                ]);
            }

            $component = new $componentName($states);
            $newComp = $component->handle($action, ...$params);

            if (! $newComp['found']) {
                return response(404)->json($newComp);
            }

            return response()->json($newComp);
        });
    }

    public function boot(): void
    {
        // Boot 
    }
}

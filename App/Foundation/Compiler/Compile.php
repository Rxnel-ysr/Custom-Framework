<?php

namespace App\Foundation\Compiler;

require_once __DIR__ . '/Rx.php';

use Exception;
use ReflectionFunction;

class Compile
{
    private static string $cache_dir;
    private static string $views_dir;
    private static array $directive = [
        '/\{\{\s*(.*?)\s*\}\}/' =>  '<?= htmlspecialchars($1) ?>',
        '/\{\!\!\s*(.*?)\s*\!\!\}/' =>  '<?= $1 ?>',
        '/@csrf/' => '<?= \\App\\Foundation\\Guard\\CSRF::csrf() ?>',
        '/@if\s*\((.*?)\)/s' => '<?php if ($1): ?>',
        '/@elseif\s*\((.*?)\s*\)/s' => '<?php elseif ($1): ?>',
        '/@else\b/' => '<?php else: ?>',
        '/@endif\b/' => '<?php endif; ?>',
        '/@foreach\s*\((.*?)\)/s' => '<?php foreach($1): ?>',
        '/@endforeach/' => '<?php endforeach;?>',
        '/@method\s*\((.*?)\)/s' => '<?= method($1) ?>',
        '/@include\s*\((.*?)\)/s' => '<?php view($1) ?>',
        '/@extends\s*\((.*?)\)/s' => '<?php rx_extends($1) ?>',
        '/@section\s*\((.*?)\)/s' => '<?php rx_start_section($1) ?>',
        '/@yield\s*\((.*?)\)/s' => '<?php rx_yield($1) ?>',
        '/@endsection/' => '<?php rx_end_section() ?>',
    ];

    private static array $user_directive = [];

    private static array $user_callbacks = [];

    public static function init($views_dir, $cache_dir)
    {
        self::$views_dir = $views_dir;
        self::$cache_dir = $cache_dir;
    }


    public static function dispatch(string $name, mixed ...$args)
    {
        return (self::$user_callbacks[$name][0])(...$args);
    }

    public static function register(string $name, callable $func)
    {

        $reflection = new ReflectionFunction($func);
        if (!$type = $reflection->getReturnType()) {
            throw new Exception('Rx template callbacks must specify return type using \':\'');
        }


        $hasArgs =  $reflection->getNumberOfParameters() > 0;
        $directive = $hasArgs ?  '/@' . $name . '\s*\((.*?)\)/s' : '/@' . $name . '/';
        self::$user_directive[$name] = [$directive, $hasArgs];
        self::$user_callbacks[$name] = [$func, $type !== 'void'];
    }

    public static function compile($path)
    {
        if (!file_exists($viewPath = self::$views_dir . DIRECTORY_SEPARATOR . $path)) {
            throw new Exception('View not found [' . $$viewPath . ']');
        }

        $cachePath = self::$cache_dir . DIRECTORY_SEPARATOR . $path;

        // Ensure the cache directory exists
        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        if (file_exists($cachePath) && filemtime($cachePath) >= filemtime($viewPath)) {
            return require_once $cachePath;
        }

        $template = file_get_contents($viewPath);

        $user_directive = [];
        foreach (self::$user_directive as $name => $directive) {
            $res = self::$user_callbacks[$name];
            $callback = $res[0];
            $hasReturn = $res[1];
            $callback = $directive[1] ? 'App\\Foundation\\Compiler\\Compile::dispatch(\'' . $name . '\',$1)' : 'App\\Foundation\\Compiler\\Compile::dispatch(\'' . $name . '\')';
            $user_directive[$directive[0]] = $hasReturn ? '<?= ' . $callback . ' ?>' : '<?php ' . $callback . ' ?>';
        }

        $combined = array_merge(self::$directive, $user_directive);


        $compiled = preg_replace(array_keys($combined), array_values($combined), $template);

        file_put_contents($cachePath, $compiled);

        return require_once $cachePath;
    }
}

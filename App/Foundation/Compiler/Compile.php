<?php

namespace App\Foundation\Compiler;

require_once __DIR__ . '/Rx.php';

use App\Support\Facades\DI;
use Exception;
use ReflectionFunction;

class Compile
{
    private static string $ext;
    private static string $cache_dir;
    private static string $views_dir;
    private static array $directive = [
        // Comments
        '/\{\{--\s*(.*?)\s*--\}\}/s' => '<?= \'<!-- $1 -->\' ?>',

        // Echo statements
        '/\{\{\s*(.*?)\s*\}\}/s' => '<?= htmlspecialchars($1) ?>',
        '/\{\!\!\s*(.*?)\s*\!\!\}/s' => '<?= $1 ?>',

        // CSRF
        '/@csrf\b/' => '<?= \\App\\Foundation\\Guard\\CSRF::csrf() ?>',

        // Control structures
        '/@if\s*\((.*?)\)/s' => '<?php if ($1): ?>',
        '/@elseif\s*\((.*?)\)/s' => '<?php elseif ($1): ?>',
        '/@else\b/' => '<?php else: ?>',
        '/@endif\b/' => '<?php endif; ?>',

        // Loops
        '/@foreach\s*\((.*?)\)/s' => '<?php foreach($1): ?>',
        '/@endforeach\b/' => '<?php endforeach; ?>',
        '/@for\s*\((.*?)\)/s' => '<?php for($1): ?>',
        '/@endfor\b/' => '<?php endfor; ?>',
        '/@while\s*\((.*?)\)/s' => '<?php while($1): ?>',
        '/@endwhile\b/' => '<?php endwhile; ?>',

        // Special loops
        '/@forelse\s*\((.*?)\)/s' => '<?php if (!empty($1)): foreach($1): ?>',
        '/@empty\b/' => '<?php endforeach; else: ?>',
        '/@endforelse\b/' => '<?php endif; ?>',

        // Switch
        '/@switch\s*\((.*?)\)/s' => '<?php switch($1): ?>',
        '/@case\s*\((.*?)\)/s' => '<?php case $1: ?>',
        '/@default\b/' => '<?php default: ?>',
        '/@endswitch\b/' => '<?php endswitch; ?>',

        // Flow control
        '/@break\b/' => '<?php break; ?>',
        '/@continue\b/' => '<?php continue; ?>',

        // Conditionals
        '/@isset\s*\((.*?)\)/s' => '<?php if (isset($1)): ?>',
        '/@endisset\b/' => '<?php endif; ?>',
        '/@empty\s*\((.*?)\)/s' => '<?php if (empty($1)): ?>',
        '/@endempty\b/' => '<?php endif; ?>',

        // PHP blocks
        '/@php\b/' => "<?php\n",
        '/@endphp\b/' => "?>",

        // Includes and components
        '/@include\s*\((.*?)\)/s' => '<?php view($1) ?>',
        '/@extends\s*\((.*?)\)/s' => '<?php rx_extends($1) ?>',

        // Sections and stacks
        '/@section\s*\((.*?)\)/s' => '<?php rx_start_section($1) ?>',
        '/@yield\s*\((.*?)\)/s' => '<?php rx_yield($1) ?>',
        '/@endsection\b/' => '<?php rx_end_section() ?>',
        '/@push\s*\((.*?)\)/s' => '<?php rx_append_stack($1) ?>',
        '/@stacks\s*\((.*?)\)/s' => '<?php rx_stacks($1) ?>',
        '/@endpush\b/' => '<?php rx_end_stack() ?>',

        // Data handling
        '/@json\s*\((.*?)\)/s' => '<?= json_encode($1) ?>',
        '/@method\s*\((.*?)\)/s' => '<?= method($1) ?>',
        '/@reactive\s*\((.*?)\)/s' => '<?= rx_reactive($1) ?>',

        // Generic directive fallback (must be last)
        /* '/@(\w+)\s*\((.*?)\)/s' => '<?= $1($2) ?>' */
    ];

    private static array $user_directive = [];

    private static array $user_callbacks = [];

    public static function init($views_dir, $cache_dir, $file_ext = '.rx.php')
    {
        self::$ext = $file_ext;
        self::$views_dir = $views_dir;
        self::$cache_dir = $cache_dir;
    }

    public static function getExt()
    {
        return self::$ext;
    }


    public static function dispatch(string $name, mixed ...$args)
    {
        return (self::$user_callbacks[$name][0])(...$args);
    }

    public static function register(string $name, callable $func)
    {
        $reflection = new ReflectionFunction($func);
        if (!$type = $reflection->getReturnType()) {
            throw new CompilerException('Rx template callbacks must specify return type using \': returnType\'');
        }


        $hasArgs =  $reflection->getNumberOfParameters() > 0;
        $directive = $hasArgs ?  '/@' . $name . '\s*\((.+)\)/s' : '/@' . $name . '\b/';
        self::$user_directive[$name] = [$directive, $hasArgs];
        self::$user_callbacks[$name] = [$func, ! in_array($type, ['void', 'never'])];
    }

    /**
     * Compile view on given path
     *
     * @param string $path Path to file to be compiled
     * @param array $data Data to be extracted inside compiled file
     * @param bool $return Decide whether to return compiled as string or directly echo it
     * @return ($return is false ? void : string )
     * @throws CompilerException if file was not exist
     */
    public static function compile(string $path, array $_extractedData, bool $return = false)
    {
        // echo $path;
        $viewPath = self::$views_dir . DIRECTORY_SEPARATOR . $path . self::$ext;
        if (!file_exists($viewPath)) {
            throw new CompilerException('View not found [ ' . str_replace([self::$views_dir . DIRECTORY_SEPARATOR, self::$ext], ['', ''], $viewPath) . ' ], Are you sure its ends with ' . self::$ext . ' ?');
        }

        $_currentFile = self::$cache_dir . DIRECTORY_SEPARATOR . md5($path);

        // Create cache directory if needed, yeah
        $cacheDir = dirname($_currentFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        $_nonce = DI::get('nonce');

        // Recompile if needed
        if (!file_exists($_currentFile) || filemtime($_currentFile) < filemtime($viewPath)) {
            $template = file_get_contents($viewPath);

            $user_directive = [];
            foreach (self::$user_directive as $name => $directive) {
                $res = self::$user_callbacks[$name];
                $hasReturn = $res[1];
                $callback = $directive[1]
                    ? 'App\\Foundation\\Compiler\\Compile::dispatch(\'' . $name . '\',$1)'
                    : 'App\\Foundation\\Compiler\\Compile::dispatch(\'' . $name . '\')';
                $user_directive[$directive[0]] = $hasReturn
                    ? '<?= ' . $callback . ' ?>'
                    : '<?php ' . $callback . ' ?>';
            }

            $compiled = preg_replace(
                array_keys([...self::$directive, ...$user_directive]),
                array_values([...self::$directive, ...$user_directive]),
                $template
            );

            file_put_contents($_currentFile, $compiled);
        }

        // Isolated scope with output control, well, cant be 100% but I'l treat it as feature hahaha...
        $render = function () use ($_currentFile, $_extractedData, $_nonce) {
            extract($_extractedData);
            unset($_extractedData);
            ob_start();
            try {
                require $_currentFile;
                return ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        };

        $output = $render();

        if ($return) {
            return $output;
        }

        echo $output;
    }
}

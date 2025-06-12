#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Debug\Debugger;
use App\Foundation\CLI\Command;
use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\Helpers\Env;

if (PHP_SAPI !== 'cli') {
    return die('Must run on CLI');
}

// define('ROOT', __DIR__);

// require_once __DIR__ . '/App/Core/definitions.php';
require_once __DIR__ . '/App/Foundation/Manager/ClassManager_EXPE.php';
require_once __DIR__ . '/App/Foundation/CLI/Command.php';
require_once __DIR__ . '/App/Foundation/Helpers/Env.php';
require_once __DIR__ . '/App/Foundation/Helpers/Utility.php';
require_once __DIR__ . '/App/Foundation/Helpers/Helpers.php';
require_once __DIR__ . '/App/Core/bootstrap.php';
// require_once ROOT . 'test/memory.php';
Env::load(__DIR__ . '/config/.env');

safe(
    function () {
        require_once __DIR__ . '/App/CLI/Commands.php';
        Command::standBy();
    },
    [],
    $res,
    false,
    true
);



// define('BASE_PATH', __DIR__);

// use App\Foundation\Database\Blueprint;
// use App\Foundation\Database\Migration;
// use App\Foundation\Database\Schema;
// use App\Foundation\Env;
// use App\Foundation\Guard\CSRF;

// require_once __DIR__ . '/App/Core/definitions.php';
// require_once FOUNDATION .'Utility.php';
// require_once FOUNDATION .'Helpers.php';
// require_once FOUNDATION .'Env.php';
// Env::load(ROOT . 'config/.env');

// function cleanupFiles($dir, $pattern, $maxAge)
// {
//     $count = 0;
//     foreach (glob("$dir/$pattern") as $file) {
//         if (filemtime($file) < time() - $maxAge) {
//             unlink($file);
//             $count++;
//         }
//     }
//     return $count;
// }

// if ($argv[1] == 'start') {
//     $ipPort = $argv[2] ?? 'localhost:8000';
//     shell_exec('php -S ' . $ipPort . ' index.php');
//     // . ' index.php'
//     // . ' ' . BASE_PATH . '/App/Core/bootstrap.php'
// }

// if ($argv[1] == 'clean:ratelimit') {
//     $dir = ROOT . '/storage/cache/rate-limiter';
//     $total = cleanupFiles($dir, "rate_limit_*.log", 3600);
//     $total += cleanupFiles($dir, "ban_*.log", 86400);
//     echo "Cleanup done. Deleted $total file/s.\n";
// }

// if ($argv[1] == 'clean:csrf') {
//     session_start();

//     unset($_SESSION['csrf_secret'], $_SESSION['csrf_tokens']);

//     foreach ($_COOKIE as $name => $value) {
//         echo $name;
//         if (fnmatch('CSRF-TOKEN-*', $name)) {
//             CSRF::expireCookie($name);
//         }
//     }

//     echo "CSRF tokens cleared.";
// }
// if ($argv[1] == 'migrate') {
//     require_once FOUNDATION .'Connection.php';
//     require_once FOUNDATION .'Migration.php';

//     $files = glob(MIGRATIONS . '*.php');
//     $records = Migration::getRecord();

//     if ($records['current'] > -1) {
//         $filenames = array_map(fn($file) => basename($file), $files);
//         $current_migrations = array_keys($records[$records['current']]);

//         $new_migrations = arrayNonIntersect($current_migrations, $filenames);

//         if (empty($new_migrations)) {
//             echo 'Nothing to migrate.' . PHP_EOL;
//             return 0;
//         }

//         $old_migrations = arrayIntersectOnly($current_migrations, $filenames);

//         $files = array_map(function ($file) use ($new_migrations, $old_migrations) {
//             if (in_array(basename($file), $new_migrations)) {
//                 return ['file' => $file, 'status' => 'pending'];
//             };

//             if (in_array(basename($file), $old_migrations)) {
//                 return ['file' => $file, 'status' => 'done'];
//             }
//             return null;
//         }, $files);

//         foreach ($files as $f) {
//             if (!$f) continue;
//             $basename = basename($f['file']);

//             Migration::addMigrationOnRecord($records['current'] + 1, getContent($f['file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, [1]), $basename);

//             if ($f['status'] != 'done') {
//                 echo 'Running migration: ' . $basename . PHP_EOL;
//                 $class = require $f['file'];
//                 $class->up();
//             }
//         }
//     } else {
//         foreach ($files as $f) {
//             $basename = basename($f);
//             Migration::addMigrationOnRecord($records['current'] + 1, getContent($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, [1]), $basename);
//             echo 'Running migration: ' . $basename . PHP_EOL;
//             $class = require $f;
//             $class->up();
//         }
//     }

//     Migration::goToNextMigration();
// }

// if ($argv[1] == 'migrate:fresh') {
//     require_once FOUNDATION .'Connection.php';
//     require_once FOUNDATION .'Migration.php';
//     $files = glob(MIGRATIONS . '*.php');

//     foreach ($files as $f) {
//         echo 'Dropping migration: ' . basename($f) . PHP_EOL;
//         $class = require $f;
//         $class->down();
//     }
//     foreach ($files as $f) {
//         echo 'Running migration: ' . basename($f) . PHP_EOL;
//         $class = require $f;
//         $class->run();
//     }
// }

// if ($argv[1] == 'migrate:rollback') {
//     require_once FOUNDATION .'Connection.php';
//     require_once FOUNDATION .'Migration.php';

//     Migration::goToPrevMigrationsAndUnset();
// }

// if ($argv[1] == 'make:controller') {
//     $content = "<?php\nnamespace App\Http\Controllers;\n\nuse App\Foundation\Http\Controller;\n\nclass $argv[2] extends Controller\n{\n    //\n}\n";
//     $filename = CONTROLLERS . $argv[2] . '.php';
//     file_put_contents($filename, $content);

//     echo "Created new controller [$filename]\n";
// }

// if ($argv[1] == 'make:model') {
//     $content = "<?php\nnamespace App\Models;\n\nuse App\Foundation\Model;\n\nclass $argv[2] extends Model\n{\n    // \n}\n";
//     $filename = MODELS . $argv[2] . '.php';
//     file_put_contents($filename, $content);

//     echo "Created new model [$filename]\n";
// }

// if ($argv[1] == 'make:migration') {
//     $content = "<?php\n\nuse App\Foundation\Database\Blueprint;\nuse App\Foundation\Database\Migration;\nuse App\Foundation\Database\Schema;\n\nreturn new class extends Migration {\n\n    public function up() {\n        Schema::create(\"$argv[2]\", function (Blueprint \$table) {\n            \$table->id();\n            \$table->timestamps();\n        });\n    }\n\n    public function down() {\n        Schema::dropIfExists(\"$argv[2]\");\n    }\n\n};";
//     $filename = MIGRATIONS . date('Y_m_d') . '_' . $argv[2] . '.php';
//     file_put_contents($filename, $content);

//     echo "Created new migration [$filename]\n";
// }

// if ($argv[1] == 'test') {
//     var_dump(convertArraySyntax(var_export([[1, 2, 3], [4, 5, 6]], true)));

// }

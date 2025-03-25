#!/usr/bin/env php
<?php

use App\CLI\Command;
use App\Utils\Manager\ClassManager;

if (PHP_SAPI !== 'cli') {
    return die('Must run on CLI');
}

require_once __DIR__ . '/App/Core/definitions.php';
require_once UTILS_PATH . 'ClassManager.php';
require_once UTILS_PATH . 'Command.php';

ClassManager::init(true);
ClassManager::initAutoloader(true);

require_once CLI . 'Commands.php';

Command::standBy();

// define('BASE_PATH', __DIR__);

// use App\Utils\Database\Blueprint;
// use App\Utils\Database\Migration;
// use App\Utils\Database\Schema;
// use App\Utils\Env;
// use App\utils\Guard\CSRF;

// require_once __DIR__ . '/App/Core/definitions.php';
// require_once UTILS_PATH . 'Utility.php';
// require_once UTILS_PATH . 'Helpers.php';
// require_once UTILS_PATH . 'Env.php';
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
//     require_once UTILS_PATH . 'Connection.php';
//     require_once UTILS_PATH . 'Migration.php';

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
//     require_once UTILS_PATH . 'Connection.php';
//     require_once UTILS_PATH . 'Migration.php';
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
//     require_once UTILS_PATH . 'Connection.php';
//     require_once UTILS_PATH . 'Migration.php';

//     Migration::goToPrevMigrationsAndUnset();
// }

// if ($argv[1] == 'make:controller') {
//     $content = "<?php\nnamespace App\Http\Controllers;\n\nuse App\Utils\Http\Controller;\n\nclass $argv[2] extends Controller\n{\n    //\n}\n";
//     $filename = CONTROLLERS . $argv[2] . '.php';
//     file_put_contents($filename, $content);

//     echo "Created new controller [$filename]\n";
// }

// if ($argv[1] == 'make:model') {
//     $content = "<?php\nnamespace App\Models;\n\nuse App\Utils\Model;\n\nclass $argv[2] extends Model\n{\n    // \n}\n";
//     $filename = MODELS . $argv[2] . '.php';
//     file_put_contents($filename, $content);

//     echo "Created new model [$filename]\n";
// }

// if ($argv[1] == 'make:migration') {
//     $content = "<?php\n\nuse App\Utils\Database\Blueprint;\nuse App\Utils\Database\Migration;\nuse App\Utils\Database\Schema;\n\nreturn new class extends Migration {\n\n    public function up() {\n        Schema::create(\"$argv[2]\", function (Blueprint \$table) {\n            \$table->id();\n            \$table->timestamps();\n        });\n    }\n\n    public function down() {\n        Schema::dropIfExists(\"$argv[2]\");\n    }\n\n};";
//     $filename = MIGRATIONS . date('Y_m_d') . '_' . $argv[2] . '.php';
//     file_put_contents($filename, $content);

//     echo "Created new migration [$filename]\n";
// }

// if ($argv[1] == 'test') {
//     var_dump(convertArraySyntax(var_export([[1, 2, 3], [4, 5, 6]], true)));

// }

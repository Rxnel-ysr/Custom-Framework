<?php

use App\CLI\Command;

require_once './App/Core/definitions.php';
require_once UTILS_PATH . 'Command.php';

$required = [];
$required['migration'] = ['Migration' => \App\Utils\Database\Migration::class, \App\Utils\Database\Connection::class];

error_reporting(E_ALL & ~E_WARNING);

Command::register('help', fn() => Command::showHelp(), [], [], 'Show help message then exit.');

Command::register('start', function () {
    $ipPort = Command::parameter(2) ?? 'localhost:8000';
    shell_exec('php -S ' . $ipPort . ' index.php');
}, [], [], 'Start the server, if no ip and port where provided, defaulted to localhost:8000');

Command::register('migrate', function () {
    $files = glob(MIGRATIONS . '*.php');
    $records = Migration::getRecord();

    if ($records['current'] > -1) {
        $filenames = array_map(fn($file) => basename($file), $files);
        $current_migrations = array_keys($records[$records['current']]);

        $new_migrations = arrayNonIntersect($current_migrations, $filenames);

        if (empty($new_migrations)) {
            echo 'Nothing to migrate.' . PHP_EOL;
            return 0;
        }

        $old_migrations = arrayIntersectOnly($current_migrations, $filenames);

        $files = array_map(function ($file) use ($new_migrations, $old_migrations) {
            if (in_array(basename($file), $new_migrations)) {
                return ['file' => $file, 'status' => 'pending'];
            };

            if (in_array(basename($file), $old_migrations)) {
                return ['file' => $file, 'status' => 'done'];
            }
            return null;
        }, $files);

        foreach ($files as $f) {
            if (!$f)
                continue;
            $basename = basename($f['file']);

            Migration::addMigrationOnRecord($records['current'] + 1, getContent($f['file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, [1]), $basename);

            if ($f['status'] != 'done') {
                echo 'Running migration: ' . $basename . PHP_EOL;
                $class = require $f['file'];
                $class->up();
            }
        }
    } else {
        foreach ($files as $f) {
            $basename = basename($f);
            Migration::addMigrationOnRecord($records['current'] + 1, getContent($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES, [1]), $basename);
            echo 'Running migration: ' . $basename . PHP_EOL;
            $class = require $f;
            $class->up();
        }
    }

    Migration::goToNextMigration();
}, [], $required['migration'], 'Run then migrations');

Command::register('migrate:fresh', function () {
    $files = glob(MIGRATIONS . '*.php');

    if (empty($files)) {
        echo 'Nothing to migrate.' . PHP_EOL;
        return;
    }

    foreach ($files as $f) {
        echo 'Dropping migration: ' . basename($f) . PHP_EOL;
        $class = require $f;
        $class->down();
    }
    foreach ($files as $f) {
        echo 'Running migration: ' . basename($f) . PHP_EOL;
        $class = require $f;
        $class->up();
    }
}, [], $required['migration'], 'Dropping all the migrations then reapply them');

Command::register('migrate:dropAll', function () {
    $files = glob(MIGRATIONS . '*.php');

    if (empty($files)) {
        echo 'Nothing to drop.' . PHP_EOL;
        return;
    }

    foreach ($files as $f) {
        echo 'Dropping migration: ' . basename($f) . PHP_EOL;
        $class = require $f;
        $class->down();
    }
}, [], $required['migration'], 'Dropping all the migrations');

Command::register('migrate:rollback', function () {
    Migration::goToPrevMigrationsAndUnset();
}, [], $required['migration'], 'Rolling back the migrations');

Command::register('make:controller', function () {
    $name = Command::parameter(2) ?? trim(readline('Name of controller: '));

    $content = "<?php\nnamespace App\Http\Controllers;\n\nuse App\Utils\Http\Controller;\n\nclass $name extends Controller\n{\n    //\n}\n";
    $filename = CONTROLLERS . $name . '.php';
    file_put_contents($filename, $content);

    echo "Created new controller [$filename]\n";
}, [], [], 'Make a new controller', ['test']);

$cihuy = 'CIIIIIIIIIIIHHHHHHHHUUUUUUUUUUUUUYYYYYYYYY';
Command::register('test', 'echo $cihuy', [],[],'',['cihuy']);

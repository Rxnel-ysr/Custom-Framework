<?php

use App\CLI\Command;
use App\Debug\Debugger;

require_once './App/Core/definitions.php';
require_once UTILS_PATH . 'Command.php';
require_once UTILS_PATH . 'Debug.php';

Debugger::init(false, E_ALL & ~E_WARNING);

try {
    $required = [];
    $required['migration'] = ['Migration' => \App\Utils\Database\Migration::class, \App\Utils\Database\Connection::class];

    Command::register('help', fn() => Command::showHelp(), [], [], 'Show help message then exit.');

    Command::register('start', function () {
        $ipPort = Command::parameter(2, '', 'localhost:8000');
        shell_exec('php -S ' . $ipPort . ' index.php');
    }, [], [], 'Start the server, if no ip and port where provided, defaulted to localhost:8000');

    Command::register('migrate', fn() => Migration::migrate(), [], $required['migration'], 'Run then migrations');

    Command::register('migrate:dropAll', fn() => Migration::dropAll(), [], $required['migration'], 'Dropping all the migrations');

    Command::register('migrate:fresh', fn() => Migration::dropAndReapplyAll(), [], $required['migration'], 'Dropping all the migrations then reapply them');

    Command::register('migrate:rollback', fn() => Migration::goToPrevMigrationsAndUnset(), [], $required['migration'], 'Rolling back the migrations');

    Command::register('make:controller', function () {
        $name = Command::parameter(2, 'Name of controller: ');
        $content = "<?php\nnamespace App\Http\Controllers;\n\nuse App\Utils\Http\Controller;\n\nclass $name extends Controller\n{\n    //\n}\n";
        $filename = CONTROLLERS . $name . '.php';
        file_put_contents($filename, $content);

        echo "Created new controller [$filename]\n";
    }, [], [], 'Make a new controller', ['test']);

    $cihuy = 'CIIIIIIIIIIIHHHHHHHHUUUUUUUUUUUUUYYYYYYYYY';

    Command::register('test', function () {
        Manager::registerNewClass(Test::class, 'App/Utils/Test.php');
        Manager::loadClasses(['t' => Test::class]);
        echo t::test();
    }, [], ['Manager' => App\Utils\Manager\ClassManager::class]);
} catch (\Throwable $e) {
    Debugger::dumpErr($e);
}
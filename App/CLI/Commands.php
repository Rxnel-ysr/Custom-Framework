<?php

use App\Foundation\CLI\Command;
use App\Foundation\Database\Migration;
use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\System\Disk;

Command::register(
    'help',
    fn() => Command::showHelp(),
    'h',
    'Show help message then exit.'
);

Command::register(
    'start',
    function () {
        $ipPort = Command::parameter(2, '', 'localhost:8000');
        shell_exec('php -S ' . $ipPort . ' index.php');
        // require_once ROOT . 'test/memory.php';
    },
    's',
    'Start the local dev server, default localhost:8000'
);

Command::register(
    'migrate',
    fn() => Migration::migrate(),
    'm',
    'Run then migrations'
);

Command::register(
    'migrate:dropAll',
    fn() => Migration::dropAll(),
    'mda',
    'Dropping all the migrations'
);

Command::register(
    'migrate:fresh',
    fn() => Migration::dropAndReapplyAll(),
    'mf',
    'Dropping all the migrations then reapply them'
);

Command::register(
    'migrate:rollback',
    fn() => Migration::goToPrevMigrationsAndUnset(),
    'mr',
    'Rolling back the migrations'
);


// Command::register(
//     'make:controller',
//     function () {
//         $name = Command::parameter(2, 'Name of controller: ');
//         $content = "<?php\nnamespace App\Http\Controllers;\n\nuse App\Foundation\Http\Controller;\n\nclass $name extends Controller\n{\n    //\n}\n";
//         $filename = CONTROLLERS . $name . '.php';
//         file_put_contents($filename, $content);

//         echo "Created new controller [$filename]\n";
//     },
//     'mk:c',
//     'Make a new controller'
// );

Command::register(
    'dump-autoload',
    fn() =>
    ClassManager::dumpAutoload(Command::parameter(2, '', ' ') === '--cache' ? true : false),
    'dal',
    'Dump current mapping and update it'
);

Command::register(
    'class-methods',
    fn() => var_export(ClassManager::getMethodDetails(Command::parameter(2, 'Classname: ', ''))),
    'cmi'
);

Command::register(
    'clean-views',
    function () {
        $views_disk = new Disk(dirname(dirname(__DIR__)) . '/storage/cache/views');
        $views_disk->cleanDir();
    },
    'cv'
);

Command::register(
    'test',
    function () {
        //   print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        $disk = new Disk(dirname(dirname(__DIR__)) . '/storage/cache');
        $arr = [
            'alok',
            'name' => 'njya'
        ];

        echo $disk;
    },
    't',
    'Testing field of a command'
);

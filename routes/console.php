<?php

use App\Foundation\CLI\Argv;
use App\Foundation\Configuration\Config;
use Experimental\App\Foundation\CLI\Command;
use App\Foundation\Database\Migration;
use App\Foundation\Database\Connection;
use App\Foundation\Generator\TemplateBuilder;
use App\Foundation\Manager\Autoloader;
use App\Foundation\System\Disk;

$root = dirname(__DIR__, 1);

$migrationSetup = [
    fn() => Connection::set(require "$root/config/database.php"),
    fn() => Migration::init([
        'database_record' => "$root/database/record/record.php",
        'migration'       => "$root/database/migrations",
    ]),
];

// --- Helpers ---
Command::register('help', function ($argv) {
    Command::showHelp();
    return 0;
})->alias('h')
    ->help('Show help message then exit.');

Command::register('serve', function (Argv $argv) {
    $port = $argv->option('port', 8000);
    $host = $argv->option('host', '127.0.0.1');
    shell_exec("php -S {$host}:{$port} public/index.php");
    return 0;
})->alias('s')
    ->params(['host', 'port'])
    ->help('Start the local dev server, default localhost:8000');

// --- Migrations ---
Command::register('migrate', function () {
    Migration::migrate();
    return 0;
})
    ->alias('m')
    ->help('Run the migrations')
    ->dependency($migrationSetup);

Command::register('migrate:dropAll', function () {
    Migration::dropAll();
    return 0;
})
    ->alias('mda')
    ->help('Dropping all the migrations')
    ->dependency($migrationSetup);

Command::register('migrate:fresh', function () {
    Migration::dropAndReapplyAll();
    return 0;
})
    ->alias('mf')
    ->help('Dropping all the migrations then reapply them')
    ->dependency($migrationSetup);

Command::register('migrate:rollback', function () {
    Migration::goToPrevMigrationsAndUnset();
    return 0;
})
    ->alias('mr')
    ->help('Rolling back the migrations')
    ->dependency($migrationSetup);

Command::register('make:component', function () {
    echo 'Not yet implemented.';
    return 1;
})
    ->alias('mkc')
    ->help('Make a new Reactive Component');

// --- Utilities ---
Command::register('dump-autoload', function (Argv $a) {
    Autoloader::dumpAutoload($a->flag('c'));
    if ($a->flag('s')) {
        Autoloader::loadAll();
    }

    return 0;
})->alias('dal')
    ->help('Dump current mapping and update it');

Command::register('cache:config', function ($argv) {
    $cache = require base_path('/config/cache.php');
    $cache['config'] = true;
    file_put_contents(base_path('/config/cache.php'), <<<PHP
    <?php
    PHP . "\nreturn " . var_export($cache, true) . ";\n");

    $cfg = new Config(base_path('/storage/cache/config.php'), [
        'database'       => require base_path("config/database.php"),
        'router'         => require base_path("config/router.php"),
        'compiler'       => require base_path("config/compiler.php"),
        'app'            => require base_path("config/app.php"),
        'rate_limiter'   => require base_path("config/rate_limiter.php"),
    ]);

    $cfg->cached();
    return 0;
});

Command::register('uncache:config', function ($argv) {
    $cache = require base_path('/config/cache.php');
    $cache['config'] = false;
    file_put_contents(base_path('/config/cache.php'), <<<PHP
    <?php
    PHP . "\nreturn " . var_export($cache, true) . ";\n");
    return 0;
});


Command::register('clean-views', function () {
    $views_disk = new Disk(base_path('/storage/cache/views'));
    $views_disk->cleanDir(['.gitignore']);
    return 0;
})->alias('cv')
    ->help('Clean all cached compiled views');

Command::register('make:controller', function ($argv) {
    $controlTemplate = new TemplateBuilder(
        $argv->flag('r') ?
            base_path('/storage/templates/controller_resources.stub') :
            base_path('/storage/templates/controller.stub'),
    );

    $controlName = $argv->get(0, true);
    $destination = base_path("/App/Http/Controllers/{$controlName}.php");
    if(file_exists($destination)){
        echo "Controller [App/Http/Controllers/{$controlName}.php] already exists.";
        return 1;
    }

    $controlTemplate->rules([
        'controller_name' => $controlName
    ])->parse();

    $controlTemplate->save($destination);

    echo "New controller created [App/Http/Controllers/{$controlName}.php]";
    return 0;
});

Command::register('make:controller', function ($argv) {
    $controlTemplate = new TemplateBuilder(
        $argv->flag('r') ?
            base_path('/storage/templates/controller_resources.stub') :
            base_path('/storage/templates/controller.stub'),
    );

    $controlName = $argv->get(0, true);
    $destination = base_path("/App/Http/Controllers/{$controlName}.php");
    if (file_exists($destination)) {
        echo "Controller [App/Http/Controllers/{$controlName}.php] already exists.";
        return 1;
    }

    $controlTemplate->rules([
        'controller_name' => $controlName
    ])->parse();

    $controlTemplate->save($destination);

    echo "New controller created [App/Http/Controllers/{$controlName}.php]";
    return 0;
});

Command::register('make:request', function ($argv) {
    $controlTemplate = new TemplateBuilder(base_path('/storage/templates/request.stub'));

    $requestName = $argv->get(0, true);
    $destination = base_path("/App/Http/Requests/{$requestName}.php");
    if (file_exists($destination)) {
        echo "Controller [App/Http/Requests/{$requestName}.php] already exists.";
        return 1;
    }

    $controlTemplate->rules([
        'classname' => $requestName
    ])->parse();

    $controlTemplate->save($destination);

    echo "New controller created [App/Http/Requests/{$requestName}.php]";
    return 0;
});

Command::register('make:model', function ($argv) {
    $controlTemplate = new TemplateBuilder(base_path('/storage/templates/model.stub'));

    $classname = $argv->get(0, true);
    $destination = base_path("/App/Models/{$classname}.php");
    if (file_exists($destination)) {
        echo "Controller [App/Models/{$classname}.php] already exists.";
        return 1;
    }

    $controlTemplate->rules([
        'classname' => $classname
    ])->parse();

    $controlTemplate->save($destination);

    echo "New controller created [App/Models/{$classname}.php]";
    return 0;
});
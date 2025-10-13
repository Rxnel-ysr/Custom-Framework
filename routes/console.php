<?php

use App\Foundation\CLI\Argv;
use App\EXPE\Foundation\CLI\Command;
use App\EXPE\Foundation\Manager\ClassManager as ManagerClassManager;
use App\Foundation\Database\Migration;
use App\Foundation\Manager\ClassManager;
use App\Foundation\Database\Connection;
use App\Foundation\Generator\TemplateBuilder;
use App\Foundation\System\Disk;
use App\Support\Facades\DI;
use App\Support\Facades\Rx;
use App\Test\testClassWithInitAndDeps;

$root = dirname(__DIR__, 2);

$migrationSetup = [
    fn() => Connection::set(require "$root/config/database.php"),
    fn() => Migration::init([
        'database_record' => "$root/database/record/record.php",
        'migration'       => "$root/database/migrations",
    ]),
];

// --- Helpers ---
Command::register('help', fn() => Command::showHelp())
    ->alias('h')
    ->help('Show help message then exit.');

Command::register('serve', function ($host = '127.0.0.1', $port = 8000) {
    return shell_exec("php -S {$host}:{$port} public/index.php");
})->alias('s')
    ->param(['host', 'port'])
    ->help('Start the local dev server, default localhost:8000');

// --- Migrations ---
Command::register('migrate', fn() => Migration::migrate())
    ->alias('m')
    ->help('Run the migrations')
    ->dependency($migrationSetup);

Command::register('migrate:dropAll', fn() => Migration::dropAll())
    ->alias('mda')
    ->help('Dropping all the migrations')
    ->dependency($migrationSetup);

Command::register('migrate:fresh', fn() => Migration::dropAndReapplyAll())
    ->alias('mf')
    ->help('Dropping all the migrations then reapply them')
    ->dependency($migrationSetup);

Command::register('migrate:rollback', fn() => Migration::goToPrevMigrationsAndUnset())
    ->alias('mr')
    ->help('Rolling back the migrations');

// --- Generators ---
Command::register('make:controller', function ($argv) {
    $name       = $this->name ?? Command::prompt('Name of controller: ');
    $isResource = $argv->flag('r');
    $root = DI::get('appConfig')['root'];

    $fileName =  $root . 'App/Http/Controllers/' . str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';
    if (file_exists($fileName)) {
        echo "[WARN] Controller already exist [$name]";
        return 1;
    }

    (new TemplateBuilder(
        $root . ($isResource ? 'storage/templates/controller_resources.stub' : 'storage/templates/controller.stub')
    ))->rules([
        'controller_name' => $name
    ])->parse()
        ->save($fileName);

    echo '[INFO] Created controller [' . str_replace($root, '', $fileName) . ']' . PHP_EOL;
    return 0;
})->param(['name'])
    ->alias('mc')
    ->help('Make a new controller');

Command::register('make:component', function () use ($root) {
    $name     = Command::parameter(2, 'Name of component: ', '', 'string');
    $fileName = $root . '/App/Components/' . str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';
    if (file_exists($fileName)) {
        echo "[WARN] Component already exist [$name]";
        exit(1);
    }
    $componentFilename = $root . '/resources/views/components/' . str_replace('.', DIRECTORY_SEPARATOR, $name) . Rx::getExt();

    $component = <<<PHP
    <?php
    namespace App\Reactive;

    use App\Foundation\Reactive\Reactive;

    class $name extends Reactive
    {
        public function increment() { return \$this->states['count']++; }
        public function view(): string { return 'components.$name'; }
    }
    PHP;

    $componentView = <<<PHP
    <div rx:reactive rx:reactive-name="{{ \$id }}" rx:state='@json(\$currentStates)'>
        <h1>Count: {{ \$count }}</h1>
        <button rx:action="increment">+1</button>
    </div>
    PHP;

    if (!is_dir(dirname($fileName))) mkdir(dirname($fileName), 0755, true);
    if (!is_dir(dirname($componentFilename))) mkdir(dirname($componentFilename), 0755, true);

    file_put_contents($fileName, $component);
    file_put_contents($componentFilename, $componentView);

    echo '[INFO] Created component [' . str_replace($root, '', $fileName) . ']' . PHP_EOL;
    echo '[INFO] Created component view [' . str_replace($root, '', $componentFilename) . ']' . PHP_EOL;
})
    ->alias('mkc')
    ->help('Make a new Reactive Component');

// --- Utilities ---
Command::register('dump-autoload', function (Argv $a) {
    ManagerClassManager::dumpAutoload($a->flag('c'));
    return 0;
})->alias('dal')
    ->help('Dump current mapping and update it');

Command::register('class-methods', fn() =>
var_export(ClassManager::getMethodDetails(Command::parameter(2, 'Classname: ', ''))))
    ->alias('cmi');

Command::register('clean-views', function () {
    $views_disk = new Disk(dirname(__DIR__, 1) . '/storage/cache/views');
    $views_disk->cleanDir(['.gitignore']);
    return 0;
})->alias('cv')
    ->help('Clean all cached compiled views');

// --- Playground ---
Command::register('test', function () use ($root) {
    DI::bind('偀', fn() => '‮HAHAHAHAAH');

    class ‮
    {
        public function __construct(public $name) {}
        public function sayHello()
        {
            echo 'Hi, my name is ' . $this->name;
        }
    }
    class ⁠
    {
        public function __construct(public $⁠) {}
        public function ​()
        {
            echo 'Hi, my name is ' . $this->⁠;
        }
    }

    $​ = new ⁠('Ronel');
    $​->​();

    $nonexistclass = new Anjay();
    $nonexistclass->addInstanceMethod('test', fn() => print PHP_EOL . 'it does work');
    $nonexistclass->test();
    return 0;
})->alias('t')
    ->help('Testing field of a command');

Command::register('ok', function () {
    var_dump(debug_backtrace());

    echo "\nHello";
    return 0;
});

Command::register('count', function () {
    function getDirSize(string $dir, array $blacklist = [], bool $verbose = false): int
    {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $filePath = str_replace('\\', '/', $file->getPathname());

            foreach ($blacklist as $bad) {
                if (str_ends_with($bad, '/')) {
                    if (str_contains($filePath, '/' . rtrim($bad, '/') . '/')) continue 2;
                } elseif (str_starts_with($bad, '.')) {
                    if (str_ends_with($filePath, $bad)) continue 2;
                } else {
                    if (str_contains($filePath, $bad)) continue 2;
                }
            }

            $size += $file->getSize();
            if ($verbose) echo "[OK] {$filePath} (" . humanSize($file->getSize()) . ")\n";
        }
        return $size;
    }

    function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) $bytes /= 1024;
        return round($bytes, 2) . ' ' . $units[$i];
    }

    echo 'Root: ' . dirname(__DIR__) . PHP_EOL;
    $totalSize = getDirSize(dirname(__DIR__), [
        'storage/',
        'public/media/',
        '.log',
        '.mp4',
        '.gif',
        '.md',
        '.css',
        '.js',
        '.git/'
    ], true);

    echo "Framework size: " . humanSize($totalSize) . PHP_EOL;
    return 0;
});

Command::register('testtt', function () {
    echo "Hello my name is, {$this->name}. And I am is {$this->age} years old";
})->param(['age', 'name']);


class test
{
    public function index(Argv $argv, string $nama, int $umur, array $alok)
    {
        var_dump([$argv->flag('r'), $argv->flag('g'), $argv->flag('a')]);

        echo "\nNama: {$nama}\nUmur: {$umur}\n";

        $className = test::class;
        echo "\n{$className}";

        var_dump(compact('alok'));

        return 0;
    }
}

Command::register('anajay', [test::class, 'index'])->param(['nama:string', 'umur:int', 'alok:array']);

Command::register('invoke', function () {
   testClassWithInitAndDeps::sayHi();
   return 0;
});

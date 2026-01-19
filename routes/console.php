<?php

use App\Foundation\CLI\Argv;
use Experimental\App\Foundation\CLI\Command;
use App\Foundation\Database\Migration;
use App\Foundation\Database\Connection;
use App\Foundation\Event\Emitter;
use App\Foundation\Event\Receiver;
use App\Foundation\Generator\TemplateBuilder;
use App\Foundation\Http\HttpClient;
use App\Foundation\Manager\Autoloader;
use App\Foundation\System\Disk;
use App\Support\Facades\DI;
use App\Test\testClassWithInitAndDeps;
use App\Foundation\Manager\Resolver;
use Experimental_V2\App\Foundation\Database\QueryBuilder;
use Test\Service;

$root = dirname(__DIR__, 1);
require_once $root . '/App/Foundation/CLI/Command_EXPE.php';


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

Command::register('serve', function (Argv $argv) {
    $port = $argv->option('port', 8000);
    $host = $argv->option('host', 'localhost');
    shell_exec("php -S {$host}:{$port} public/index.php");
    return 0;
})->alias('s')
    ->params(['host', 'port'])
    ->help('Start the local dev server, default localhost:8000');

// --- Migrations ---
Command::register('migrate', function(){
    Migration::migrate();
    return 0;
})
    ->alias('m')
    ->help('Run the migrations')
    ->dependency($migrationSetup);

Command::register('migrate:dropAll', function(){
    Migration::dropAll();
    return 0;
} )
    ->alias('mda')
    ->help('Dropping all the migrations')
    ->dependency($migrationSetup);

Command::register('migrate:fresh', function(){
    Migration::dropAndReapplyAll();
    return 0;
} )
    ->alias('mf')
    ->help('Dropping all the migrations then reapply them')
    ->dependency($migrationSetup);

Command::register('migrate:rollback', function(){
    Migration::goToPrevMigrationsAndUnset();
    return 0;
} )
    ->alias('mr')
    ->help('Rolling back the migrations');

// --- Generators ---
// Command::register('make:controller', function (Argv $argv) {
//     $name       = $argv->shiftPositionals() ?? Command::prompt('Name of controller: ');
//     $isResource = $argv->flag('r');
//     $root = DI::get('appConfig')['root'];

//     $fileName =  $root . 'App/Http/Controllers/' . str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';
//     if (file_exists($fileName)) {
//         echo "[WARN] Controller already exist [$name]";
//         return 1;
//     }

//     (new TemplateBuilder(
//         $root . ($isResource ? 'storage/templates/controller_resources.stub' : 'storage/templates/controller.stub')
//     ))->rules([
//         'controller_name' => $name
//     ])->parse()
//         ->save($fileName);

//     echo '[INFO] Created controller [' . str_replace($root, '', $fileName) . ']' . PHP_EOL;
//     return 0;
// })->params(['name'])
//     ->alias('mc')
//     ->help('Make a new controller');

Command::register('make:component', function () use ($root) {})
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
})->params(['age', 'name']);


class testCommand
{
    public function index(Argv $argv)
    {
        var_dump([$argv->flag('r'), $argv->flag('g'), $argv->flag('a')]);
        $nama = $argv->option('nama');
        echo "\nNama: {$nama}\nUmur: {$argv->option('umur')}\n";

        $className = test::class;
        echo "\n{$className}";

        return 0;
    }
}

Command::register('anajay', [testCommand::class, 'index'])->params(['nama', 'umur', 'alok'])->strict();

Command::register('invoke', function () {
    testClassWithInitAndDeps::sayHi();
    return 0;
});


Command::register('test-v1', function () {


    return 0;
});

Command::register('test-v2', function () {
    $receiver = new Receiver(['die' => [
        [
            function () {
                echo "prio1\n";
            }
        ],
        [
            function () {
                echo "prio2.1\n";
            },
            function () {
                echo "prio2.2\n";
            },
        ],
        [
            function () {
                echo "prio3\n";
            }
        ],
    ]]);

    $emitter = new Emitter($receiver);

    $receiver2 = new Receiver(['live' => [
        [
            function () {
                echo "l.prio1\n";
            }
        ],
        [
            function () {
                echo "l.prio2.1\n";
            },
            function () {
                echo "l.prio2.2\n";
            },
        ],
        [
            function () {
                echo "l.prio3\n";
            },
            fn() => $emitter->emit('die')
        ],
    ]]);

    $emitter2 = new Emitter($receiver2);

    $emitter2->emit('live');

    return 0;
});


Command::register('test-v3', function (Argv $argv) {
    $svc1 = Resolver::make(Service::class, null, ['name' => 'Test']);
    $svc1->run();

    $svc2 = Resolver::make(Service::class, null, ['name' => 'Test']);
    $svc2->run();

    echo "\nAnd they are " . (($svc1 === $svc2) ? 'same!' : 'not same.') . PHP_EOL;


    echo 'Name: ' . var_dump($argv->option('name')) . PHP_EOL;
    echo 'Verbose?: ' . ($argv->flag('verbose') ? 'Yes' : 'No');

    return 0;
})->params(['name'])
    ->flags(['verbose'])
    ->short(['verbose' => 'v'])
    ->help('Test your live');

Command::register('test-db', function(){
    $qb = new QueryBuilder();
    var_dump($qb->table('blog_categories')->get()->pluck('name')->toArray());

    return 0;
});

Command::register('test-http', function(){

    try {
        $client = new HttpClient([
            'timeout' => 30,
            'user_agent' => 'MyApp/1.0',
            'verify_peer' => true,
        ]);

        $response = $client->get(
            'http://127.0.0.1/api/test',
            ['name' => 'John', 'email' => 'john@example.com'],
            [
                'Accept' => 'application/json'
            ]
        );
        echo $response->getRaw();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    return 0;
});
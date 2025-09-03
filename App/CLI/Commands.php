<?php

use App\Foundation\CLI\Command;
use App\Foundation\Database\Migration;
use App\EXPE\Foundation\Manager\ClassManager;
use App\Foundation\Compiler\Compile;
use App\Foundation\Database\Connection;
use App\Foundation\Database\QueryBuilder;
use App\Foundation\Http\HttpHeaders;
use App\Foundation\Http\Route;
use App\Foundation\System\Disk;
use App\Support\Facades\DB;
use App\Support\Facades\DI;
use App\Support\Facades\Rx;

$root = dirname(__DIR__, 2);

$migrationSetup = [
    fn() => Connection::set(require_once "$root/config/database.php"),
    fn() => Migration::init([
        'database_record' => "$root/database/record/record.php",
        'migration' => "$root/database/migrations",
    ]),
];

Command::register(
    'help',
    fn() => Command::showHelp(),
    'h',
    'Show help message then exit.'
);

Command::register('s', function () {
    var_dump(Connection::getInstance());
}, '', '', [], $migrationSetup);

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
    'Run then migrations',
    [],
    $migrationSetup
);

Command::register(
    'migrate:dropAll',
    fn() => Migration::dropAll(),
    'mda',
    'Dropping all the migrations',
    [],
    $migrationSetup

);

Command::register(
    'migrate:fresh',
    fn() => Migration::dropAndReapplyAll(),
    'mf',
    'Dropping all the migrations then reapply them',
    [],
    $migrationSetup
);

Command::register(
    'migrate:rollback',
    fn() => Migration::goToPrevMigrationsAndUnset(),
    'mr',
    'Rolling back the migrations'
);


Command::register(
    'make:controller',
    function () use ($root) {
        $name = Command::parameter(2, 'Name of controller: ', '', 'string');
        $isResource = Command::parameter(3, '', 'no') === '-r';

        $fileName = $root . '/App/Http/Controllers/' . str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';
        if (file_exists($fileName)) {
            echo "[WARN] Controller already exist [$name]";
            exit(1);
        }

        $controllerRessource = <<<PHP
        <?php

        namespace App\Http\Controllers;

        use App\Foundation\Http\Request;

        class $name extends Controller
        {

            /**
             * Display a listing of the resource.
             */
            public function index()
            {
                //
            }

            /**
             * Show the form for creating a new resource.
             */
            public function create()
            {
                //
            }

            /**
             * Store a newly created resource in storage.
             */
            public function store(GTKRequest \$request)
            {
                //
            }

            /**
             * Display the specified resource.
             */
            public function show(string \$id)
            {
                //
            }

            /**
             * Show the form for editing the specified resource.
             */
            public function edit(string \$id)
            {
                //
            }

            /**
             * Update the specified resource in storage.
             */
            public function update(Request \$request, string \$id)
            {
                //
            }

            /**
             * Remove the specified resource from storage.
             */
            public function destroy(string \$id)
            {
                //
            }
        }

        PHP;

        $controller = <<<PHP
        <?php

        namespace App\Http\Controllers;

        use App\Foundation\Http\Request;

        class $name extends Controller
        {
            //
        }

        PHP;

        file_put_contents($fileName, $isResource ? $controllerRessource : $controller);
        echo '[INFO] Created controller [' . str_replace($root, '', $fileName) . ']' . PHP_EOL;
    },
    'mr',
    'Rolling back the migrations'
);


Command::register(
    'make:component',
    function () use ($root) {
        $name = Command::parameter(2, 'Name of component: ', '', 'string');
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

            public function increment()
            {
                return \$this->states['count']++;
            }

            public function view(): string
            {
                return 'components.$name';
            }
        }

        PHP;

        $componentView = <<<PHP
        <div rx:reactive rx:reactive-name="{{ \$id }}" rx:state='@json(\$currentStates)'>
            <h1>Count: {{ \$count }}</h1>

            <button rx:action="increment">+1</button>
        </div>

        PHP;

        $dirname = dirname($fileName);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }

        $dirname = dirname($componentFilename);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }


        file_put_contents($fileName, $component);
        file_put_contents($componentFilename, $componentView);
        echo '[INFO] Created component [' . str_replace($root, '', $fileName) . ']' . PHP_EOL;
        echo '[INFO] Created component view [' . str_replace($root, '', $componentFilename) . ']' . PHP_EOL;
    },
    'mkc',
    'Make a new Reactive Component'
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
        $views_disk = new Disk(dirname(__DIR__, 2) . '/storage/cache/views');
        $views_disk->cleanDir();
    },
    'cv',
    'Clean all cached compiled views'
);

Command::register(
    '⁠',
    function () use ($root) {
// print_r(Compile::ready());
// throw new Exception('hi');

        DI::bind('偀', function(){
            return '‮HAHAHAHAAH';
        });


        /**
         *  A ‮ class that ‮ Flipped 
         */
        class ‮
        {
            public $name;
            public function __construct($name)
            {
                $this->name = $name;
            }

            public function sayHello()
            {
                echo 'Hi, my name is ' . $this->name;
            }
        }

        /**
         * Great, you found ‮ghost‬ haha
         * [U+202C] [U+202E]‮ u202e
         */
        class ⁠
        {
            public $⁠;
            public function __construct($⁠)
            {
                $this->⁠ = $⁠;
            }

            public function ​()
            {
                echo 'Hi, my name is ' . $this->⁠;
            }
        }

        // echo DI::get('偀');
        
        // echo json_encode(DB::table('posts')->get(null,true));
        $qb = new QueryBuilder();
        $qb->table('posts');
        // count($qb->get(true));
        var_dump($qb->get());


        $​ = new ⁠('Ronel');
        $​->​();

    },
    't',
    'Testing field of a command'
);

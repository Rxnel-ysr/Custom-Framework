<?php

namespace App\Http\Controllers;

use App\Contracts\Interfaces\TestInterface;
use App\Foundation\Database\RawStatement;
use App\Foundation\Http\HttpClient;
use App\Foundation\Http\Request;
use App\Foundation\Http\Response;
use App\Models\User;
use App\Support\Facades\Http;

// For destruction!
class Test extends Controller
{
    public function __construct(private TestInterface $test)
    {
    }
    public function index(Request $request)
    {

        abort(404);
        
        $another = Http::post('https://learning.mischool.id/api/login', [], [], [
            'email' => 'teacher@gmail.com',
            'password' => '123456789101234567'
        ]);

        return $another->getBody();
        // return response()->json($another->json(), 200, [
        //     'Content-Type' => 'application/json'
        // ]);
    }

    public function test(Request $req)
    {
        // echo '<pre>';
        // var_export(ClassManager::getLoadedClass());
        // echo '</pre>';


        // Initial memory usage
        // echo "Initial memory usage: " . memory_get_usage() . " bytes<br>";

        // // Start memory usage tracking
        // $start_memory = memory_get_usage();

        // // Simulate some memory-consuming process
        // $array = range(1, 1000000);

        // // Track memory usage after operation
        // $mid_memory = memory_get_usage();
        // echo "Memory usage after creating array: " . $mid_memory . " bytes<br>";

        // // Perform garbage collection
        // gc_collect_cycles();

        // // Check memory after garbage collection
        // $end_memory = memory_get_usage();
        // echo "Memory usage after GC: " . $end_memory . " bytes<br>";

        // // Peak memory usage
        // $peak_memory = memory_get_peak_usage();
        // echo "Peak memory usage: " . $peak_memory . " bytes<br>";

        // // Cannot, but if there is output shown, it cant
        // function recurse()
        // {
        //    // echo 'hi';
        //    // ob_flush(); // Force output to the browser
        //    // flush(); // Send the output buffer to the browser
        //    // sleep(1); // Slow it down for visibility
        //    recurse();
        // }
        // recurse();

        // Can
        // $bigArray = [];
        // while (true) {
        //    $bigArray[] = str_repeat("a", 1024 * 1024); // 1MB
        // }

        // // Cant
        // exit();
        // echo "This should not be printed!";

        // // Cant, it suicide to the server
        // posix_kill(getmypid(), SIGTERM); // Send a termination signal

        // // Doesnt throw any
        // $data = json_decode("{'invalid_json'}");  // Malformed JSON input

        // // Can
        // new mysqli('localhost', 'user', 'password', 'database');  // Simulate connection error if DB is down

        // // Can
        // error_reporting(0);  // Turn off error reporting
        // echo $undefinedVar;   // Should normally trigger a notice

        // // Cant, it allows it
        // $_GET = 'invalid';  // Simulate invalid superglobal data

        // // Can
        // new NonExistentClass();  // Causes Fatal Error

        // $classes = get_declared_classes();//ClassManager::getLoadedClass();
        // return response()->json($classes);
        // // echo "hi there";
        // $message = request('name','yuhu');//->query('name', 'nuh');
        // return view('test', compact('message'));
        // $user->insert([
        //     'name'=>'Cihuy',
        //     'email' => 'yaha',
        //     'password'=>'none'
        // ]);
        // $users = $user->get();
    }
    public function show($id)
    {
        return "This is show $id";
    }
    public function create()
    {
        return "This is create";
    }
    public function store()
    {
        return "This is store";
    }
    public function edit($id)
    {
        return "This is edit: $id";
    }
    public function update($id)
    {
        return "This is update: $id";
    }
    public function destroy($id)
    {
        return "This is destroy: $id";
    }
}

<?php

return [
    'current' => 0,
    0 =>
    [
        '2025_03_16_test.php' => 'use App\\Utils\\Database\\Blueprint;
use App\\Utils\\Database\\Migration;
use App\\Utils\\Database\\Schema;
return new class extends Migration {
    public function up(] {
        Schema::create("test", function (Blueprint $table] {
            $table->id(];
            $table->string("name"]->unique(];
            $table->timestamps(];
        }];
    }
    public function down(] {
        Schema::dropIfExists("test"];
    }
};',
        '2025_03_20_ANjay.php' => 'use App\\Utils\\Database\\Blueprint;
use App\\Utils\\Database\\Migration;
use App\\Utils\\Database\\Schema;
return new class extends Migration {
    public function up(] {
        Schema::create("ANjay", function (Blueprint $table] {
            $table->id(];
            $table->timestamps(];
        }];
    }
    public function down(] {
        Schema::dropIfExists("ANjay"];
    }
};',
        '2025_03_20_TESI.php' => 'use App\\Utils\\Database\\Blueprint;
use App\\Utils\\Database\\Migration;
use App\\Utils\\Database\\Schema;
return new class extends Migration {
    public function up(] {
        Schema::create("TESI", function (Blueprint $table] {
            $table->id(];
            $table->timestamps(];
        }];
    }
    public function down(] {
        Schema::dropIfExists("TESI"];
    }
};',
    ],
];

<?php

use App\Foundation\Database\Blueprint;
use App\Foundation\Database\Migration;
use App\Foundation\Database\Schema;

return new class extends Migration {

    public function up() {
        Schema::create("test", function (Blueprint $table) {
            $table->id();
            $table->string("name")->unique();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists("test");
    }

};
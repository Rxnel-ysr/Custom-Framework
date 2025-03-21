<?php

use App\Utils\Database\Blueprint;
use App\Utils\Database\Migration;
use App\Utils\Database\Schema;

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
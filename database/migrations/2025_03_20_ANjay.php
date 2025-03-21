<?php

use App\Utils\Database\Blueprint;
use App\Utils\Database\Migration;
use App\Utils\Database\Schema;

return new class extends Migration {

    public function up() {
        Schema::create("ANjay", function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists("ANjay");
    }

};
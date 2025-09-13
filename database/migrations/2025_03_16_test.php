<?php

use App\Foundation\Database\Blueprint;
use App\Foundation\Database\Migration;
use App\Foundation\Database\Schema;

return new class extends Migration {

    public function up()
    {
        Schema::create("murid", function (Blueprint $table) {
            $table->id();
            $table->string("nama")->unique();
            $table->string("nik")->unique();
            $table->string("nisn")->unique();
            $table->enum("jenis_kelamin", ['L', 'P']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists("murid");
    }
};

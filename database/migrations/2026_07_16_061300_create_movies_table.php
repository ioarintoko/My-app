<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('external_id')->nullable()->index();
            $table->string('title');
            $table->string('genre')->nullable();
            $table->string('year')->nullable();
            $table->text('plot')->nullable();
            $table->string('poster_url')->nullable();
            $table->timestamps();

            $table->unique(['external_id', 'genre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('google_books_id')->unique();
            $table->string('isbn_13', 13)->nullable()->index();
            $table->string('title');
            $table->string('authors')->nullable();
            $table->string('publisher')->nullable();
            $table->string('published_date', 10)->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};

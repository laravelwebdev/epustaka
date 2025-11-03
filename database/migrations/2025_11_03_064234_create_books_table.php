<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('book_id')->unique();
            $table->string('book_title');
            $table->string('book_author')->nullable();
            $table->text('book_description')->nullable();
            $table->string('category_name')->nullable();
            $table->string('publish_date')->nullable();
            $table->string('file_size_info')->nullable();
            $table->string('file_ext', 10)->nullable();
            $table->string('cover_url')->nullable();
            $table->boolean('using_drm')->default(false);
            $table->string('epustaka_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('organization_id')->nullable();
            $table->string('borrow_key')->nullable();
            $table->string('book_url')->nullable();
            $table->string('language')->nullable();
            $table->string('publisher')->nullable();
            $table->string('path', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};

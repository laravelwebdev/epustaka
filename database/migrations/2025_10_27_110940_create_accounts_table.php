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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->nullable();
            $table->string('email', 80)->unique()->nullable();
            $table->string('password', 40)->nullable();
            $table->string('ipusnas_id', 40)->nullable();
            $table->string('organization_id', 40)->nullable();
            $table->string('access_token', 80)->nullable();
            $table->string('refresh_token', 80)->nullable();
            $table->boolean('verified')->default(false);
            $table->mediumInteger('user_id')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

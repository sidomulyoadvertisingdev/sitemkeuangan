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
        Schema::create('debts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id');
    $table->enum('type',['hutang','piutang']);
    $table->string('name');
    $table->integer('amount');
    $table->enum('status',['belum_lunas','lunas']);
    $table->date('due_date')->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};

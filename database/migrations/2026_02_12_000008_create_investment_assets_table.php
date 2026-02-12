<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->enum('category', ['crypto', 'stock', 'fund', 'bond', 'other'])->default('other');
            $table->string('market')->nullable();          // e.g. NASDAQ, IDX, Binance
            $table->string('coingecko_id')->nullable();    // untuk crypto realtime price
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_assets');
    }
};

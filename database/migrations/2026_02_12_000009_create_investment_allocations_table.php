<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_fiat', 15, 2);          // dana yang dialokasikan (IDR)
            $table->decimal('price_fiat', 18, 6)->nullable(); // harga per unit saat beli
            $table->decimal('quantity', 24, 8)->nullable(); // qty aset yang didapat
            $table->string('currency', 8)->default('idr');
            $table->timestamp('executed_at')->useCurrent();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_allocations');
    }
};

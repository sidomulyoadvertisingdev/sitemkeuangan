<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('receiver_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->enum('kind', ['direct_transfer', 'payment_request'])->default('direct_transfer');
            $table->enum('status', ['pending', 'completed', 'rejected', 'cancelled'])->default('completed');
            $table->decimal('amount', 15, 2);
            $table->date('transfer_date');
            $table->text('note')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->index(['sender_user_id', 'status']);
            $table->index(['receiver_user_id', 'status']);
            $table->index(['kind', 'status']);
            $table->index('transfer_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};

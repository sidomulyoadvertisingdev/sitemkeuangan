<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mobile_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('requester_member_id');
            $table->unsignedBigInteger('target_member_id');
            $table->decimal('amount', 18, 2);
            $table->string('note', 1000)->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['target_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_transfer_requests');
    }
};

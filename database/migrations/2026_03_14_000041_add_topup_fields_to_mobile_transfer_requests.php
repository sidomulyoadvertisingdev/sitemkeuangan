<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_transfer_requests', function (Blueprint $table) {
            $table->string('kind', 30)->default('member_transfer')->after('target_member_id');
            $table->unsignedInteger('unique_code')->nullable()->after('kind');
            $table->decimal('pay_amount', 15, 2)->nullable()->after('unique_code');
            $table->foreignId('bank_account_id')->nullable()->after('pay_amount')->constrained()->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mobile_transfer_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['kind', 'unique_code', 'pay_amount', 'approved_at']);
        });
    }
};

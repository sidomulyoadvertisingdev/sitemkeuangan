<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization_name', 150)->nullable()->after('name');
            $table->boolean('is_platform_admin')->default(false)->after('is_admin');
            $table->string('account_status', 20)->default('approved')->after('permissions');
            $table->timestamp('approved_at')->nullable()->after('account_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('banned_at')->nullable()->after('approved_by');
            $table->string('banned_reason', 255)->nullable()->after('banned_at');
        });

        DB::table('users')
            ->whereNull('organization_name')
            ->update(['organization_name' => 'Umum']);

        DB::table('users')
            ->where('is_admin', true)
            ->update([
                'is_platform_admin' => true,
                'account_status' => 'approved',
                'approved_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'organization_name',
                'is_platform_admin',
                'account_status',
                'approved_at',
                'approved_by',
                'banned_at',
                'banned_reason',
            ]);
        });
    }
};

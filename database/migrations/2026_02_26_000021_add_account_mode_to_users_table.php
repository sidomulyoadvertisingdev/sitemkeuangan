<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'account_mode')) {
                $table->enum('account_mode', ['organization', 'cooperative'])
                    ->default('organization')
                    ->after('organization_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'account_mode')) {
                $table->dropColumn('account_mode');
            }
        });
    }
};

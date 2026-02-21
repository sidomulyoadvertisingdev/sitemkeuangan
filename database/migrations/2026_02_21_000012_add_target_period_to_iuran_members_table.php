<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultYear = (int) date('Y');

        Schema::table('iuran_members', function (Blueprint $table) use ($defaultYear) {
            $table->unsignedSmallInteger('target_start_year')
                ->default($defaultYear)
                ->after('target_amount');
            $table->unsignedSmallInteger('target_end_year')
                ->default($defaultYear)
                ->after('target_start_year');
        });
    }

    public function down(): void
    {
        Schema::table('iuran_members', function (Blueprint $table) {
            $table->dropColumn(['target_start_year', 'target_end_year']);
        });
    }
};

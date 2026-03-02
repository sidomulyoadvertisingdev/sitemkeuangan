<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iuran_installments', function (Blueprint $table) {
            $table->foreignId('officer_user_id')
                ->nullable()
                ->after('category_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('project_id')
                ->nullable()
                ->after('officer_user_id')
                ->constrained('projects')
                ->nullOnDelete();

            $table->index(['officer_user_id', 'paid_at']);
            $table->index(['project_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('iuran_installments', function (Blueprint $table) {
            $table->dropIndex(['officer_user_id', 'paid_at']);
            $table->dropIndex(['project_id', 'paid_at']);
            $table->dropConstrainedForeignId('officer_user_id');
            $table->dropConstrainedForeignId('project_id');
        });
    }
};


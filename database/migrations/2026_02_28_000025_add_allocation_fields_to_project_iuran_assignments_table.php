<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_iuran_assignments', function (Blueprint $table) {
            $table->enum('allocation_mode', ['default', 'kelas'])
                ->default('default')
                ->after('officer_user_id');
            $table->enum('member_class', ['A', 'B', 'C'])
                ->default('C')
                ->after('allocation_mode');
            $table->decimal('class_percent', 6, 2)
                ->default(100)
                ->after('member_class');
            $table->decimal('planned_amount', 15, 2)
                ->default(0)
                ->after('class_percent');
        });
    }

    public function down(): void
    {
        Schema::table('project_iuran_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'allocation_mode',
                'member_class',
                'class_percent',
                'planned_amount',
            ]);
        });
    }
};


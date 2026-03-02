<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('iuran_allocation_mode', ['default', 'kelas'])
                ->default('default')
                ->after('target_amount');
            $table->decimal('iuran_class_a_percent', 6, 2)
                ->default(130)
                ->after('iuran_allocation_mode');
            $table->decimal('iuran_class_b_percent', 6, 2)
                ->default(110)
                ->after('iuran_class_a_percent');
            $table->decimal('iuran_class_c_percent', 6, 2)
                ->default(100)
                ->after('iuran_class_b_percent');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'iuran_allocation_mode',
                'iuran_class_a_percent',
                'iuran_class_b_percent',
                'iuran_class_c_percent',
            ]);
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_iuran_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('iuran_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('officer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'iuran_member_id']);
            $table->index(['officer_user_id', 'iuran_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_iuran_assignments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('koperasi_savings')) {
            // Ubah kolom type menjadi VARCHAR agar bisa menampung nilai baru seperti transfer_keluar/transfer_masuk.
            DB::statement("ALTER TABLE `koperasi_savings` MODIFY `type` VARCHAR(50) NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('koperasi_savings')) {
            // Kembalikan ke enum awal jika perlu; default ke enum pokok/wajib/sukarela.
            DB::statement("ALTER TABLE `koperasi_savings` MODIFY `type` ENUM('pokok','wajib','sukarela') NOT NULL");
        }
    }
};

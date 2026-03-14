<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cooperative_code', 50)->nullable()->unique()->after('account_mode');
        });

        // Seed cooperative_code for existing cooperative owners
        $users = DB::table('users')
            ->where('account_mode', 'cooperative')
            ->whereNull('data_owner_user_id')
            ->get(['id', 'cooperative_code']);

        foreach ($users as $user) {
            $code = $user->cooperative_code;
            if (!$code) {
                $code = $this->generateCode();
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['cooperative_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cooperative_code']);
            $table->dropColumn('cooperative_code');
        });
    }

    private function generateCode(): string
    {
        do {
            $candidate = 'KOP-' . random_int(100000, 999999);
            $exists = DB::table('users')->where('cooperative_code', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
};

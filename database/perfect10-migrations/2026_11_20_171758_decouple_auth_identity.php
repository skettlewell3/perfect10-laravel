<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE public.user_profiles
             DROP CONSTRAINT IF EXISTS profiles_id_fkey'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE public.user_profiles
             ADD CONSTRAINT profiles_id_fkey
             FOREIGN KEY (auth_id)
             REFERENCES auth.users(id)
             ON DELETE CASCADE'
        );
    }
};
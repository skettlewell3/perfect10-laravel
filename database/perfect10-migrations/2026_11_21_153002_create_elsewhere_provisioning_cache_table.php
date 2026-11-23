<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elsewhere_provisioning_cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        /*
         * The provisioning cache is server-only.
         *
         * Supabase API clients must not be able to
         * read, insert, modify or delete its entries.
         */
        DB::statement(
            'REVOKE ALL ON TABLE public.elsewhere_provisioning_cache
             FROM PUBLIC, anon, authenticated, service_role'
        );

        /*
         * Defence in depth:
         *
         * No RLS policies are created, so ordinary
         * database roles cannot access cache entries.
         *
         * Laravel uses the postgres database role,
         * which owns the table and can still access it.
         */
        DB::statement(
            'ALTER TABLE public.elsewhere_provisioning_cache
             ENABLE ROW LEVEL SECURITY'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('elsewhere_provisioning_cache');
    }
};

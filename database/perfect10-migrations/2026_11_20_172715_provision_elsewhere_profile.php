<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION public.provision_elsewhere_profile(
                p_auth_id uuid,
                p_username text
            )
            RETURNS uuid
            LANGUAGE plpgsql
            SECURITY INVOKER
            SET search_path = ''
            AS $$
            DECLARE
                v_profile_id uuid;
            BEGIN
                IF p_auth_id IS NULL THEN
                    RAISE EXCEPTION 'Auth ID is required';
                END IF;

                IF p_username IS NULL
                    OR length(p_username) NOT BETWEEN 1 AND 16
                    OR p_username <> btrim(p_username)
                THEN
                    RAISE EXCEPTION 'Invalid Elsewhere username';
                END IF;

                -- Serialise provisioning requests for the same account.
                PERFORM pg_catalog.pg_advisory_xact_lock(
                    pg_catalog.hashtextextended(p_auth_id::text, 0)
                );

                -- Preserve existing Perfect10 profiles and history.
                SELECT up.profile_id
                INTO v_profile_id
                FROM public.user_profiles AS up
                WHERE up.auth_id = p_auth_id;

                IF FOUND THEN
                    RETURN v_profile_id;
                END IF;

                -- The username belongs to Elsewhere and must not
                -- already belong to a different Perfect10 account.
                IF EXISTS (
                    SELECT 1
                    FROM public.user_profiles AS up
                    WHERE up.username OPERATOR(public.=) p_username::public.citext
                ) THEN
                    RAISE EXCEPTION
                        'Elsewhere username is already assigned';
                END IF;

                v_profile_id := gen_random_uuid();

                INSERT INTO public.profiles (
                    profile_id,
                    profile_type,
                    display_name
                )
                VALUES (
                    v_profile_id,
                    'user',
                    p_username
                );

                INSERT INTO public.user_profiles (
                    auth_id,
                    username,
                    display_name,
                    profile_id
                )
                VALUES (
                    p_auth_id,
                    p_username,
                    p_username,
                    v_profile_id
                );

                RETURN v_profile_id;
            END;
            $$;

            -- Prevent ordinary Supabase clients from invoking this
            -- administrative provisioning function.
            REVOKE ALL ON FUNCTION
                public.provision_elsewhere_profile(uuid, text)
                FROM PUBLIC, anon, authenticated;

            GRANT EXECUTE ON FUNCTION
                public.provision_elsewhere_profile(uuid, text)
                TO postgres;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS
                public.provision_elsewhere_profile(uuid, text);
        SQL);
    }
};

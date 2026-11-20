--
-- PostgreSQL database dump
--

\restrict VMFd72BftKcDIKyLmT12Gb1oLRvdrfheQfP7oSnbiKjDvuT3gbxrRGAeGSN98V1

-- Dumped from database version 17.6
-- Dumped by pg_dump version 18.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: club_role; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.club_role AS ENUM (
    'owner',
    'captain',
    'member'
);


--
-- Name: component_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.component_type AS ENUM (
    'model',
    'modifier',
    'engine'
);


--
-- Name: TYPE component_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TYPE public.component_type IS 'what type of component does this record belong to';


--
-- Name: engine_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.engine_type AS ENUM (
    'fixed_score',
    'prediction_averages',
    'perfect10'
);


--
-- Name: fixture_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.fixture_status AS ENUM (
    'upcoming',
    'live_90',
    'live_et',
    'finished',
    'postponed'
);


--
-- Name: gameweek_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.gameweek_status AS ENUM (
    'upcoming',
    'active',
    'finished'
);


--
-- Name: invite_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.invite_status AS ENUM (
    'pending',
    'accepted',
    'declined',
    'cancelled',
    'expired'
);


--
-- Name: model_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.model_type AS ENUM (
    'fixed_score',
    'prediction_averages'
);


--
-- Name: TYPE model_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TYPE public.model_type IS 'what category of prediction model e.g. fixed score, avg goals';


--
-- Name: modifier_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.modifier_type AS ENUM (
    'uniform_scale',
    'split_scale'
);


--
-- Name: profile_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.profile_type AS ENUM (
    'user',
    'club',
    'p10',
    'bot',
    'model'
);


--
-- Name: resolution_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.resolution_type AS ENUM (
    'normal',
    'extra_time',
    'penalties'
);


--
-- Name: result_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.result_type AS ENUM (
    'home_win',
    'away_win',
    'draw'
);


--
-- Name: stage_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.stage_type AS ENUM (
    'group',
    'league',
    'knockout',
    'friendly',
    'postponed',
    'rearranged'
);


--
-- Name: accept_club_invite(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.accept_club_invite(p_invite_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    AS $$
declare
    v_my_profile_id uuid;
    v_club_id uuid;
begin

    v_my_profile_id := public.get_my_profile_id();

    select
        club_id
    into
        v_club_id
    from club_invites
    where invite_id = p_invite_id
    and recipient_profile_id = v_my_profile_id
    and invite_status = 'pending';

    if v_club_id is null then
        raise exception 'Invite not found';
    end if;

    update club_invites
    set invite_status = 'accepted'
    where invite_id = p_invite_id;


    insert into club_memberships (
        club_id,
        profile_id,
        club_role
    )
    values (
        v_club_id,
        v_my_profile_id,
        'member'
    );

end;
$$;


--
-- Name: accept_club_ownership_transfer(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.accept_club_ownership_transfer(p_request_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_my_profile_id uuid;
    v_club_id uuid;
    v_owner_profile_id uuid;
    v_owner_role club_role;
    v_target_role club_role;
begin

    v_my_profile_id := public.get_my_profile_id();

    /*
     * Find a valid pending request addressed to me.
     */
    select
        club_id,
        owner_profile_id
    into
        v_club_id,
        v_owner_profile_id
    from club_ownership_transfer_requests
    where request_id = p_request_id
    and target_profile_id = v_my_profile_id
    and status = 'pending'
    and expires_at > now();

    if v_club_id is null then
        raise exception 'Ownership transfer request not found or expired';
    end if;


    /*
     * Confirm the original owner still owns the club.
     */
    select club_role
    into v_owner_role
    from club_memberships
    where club_id = v_club_id
    and profile_id = v_owner_profile_id;

    if v_owner_role <> 'owner' then
        raise exception 'The ownership transfer is no longer valid';
    end if;


    /*
     * Confirm the target is still a member.
     */
    select club_role
    into v_target_role
    from club_memberships
    where club_id = v_club_id
    and profile_id = v_my_profile_id;

    if v_target_role is null then
        raise exception 'You are no longer a member of this club';
    end if;


    /*
     * Transfer ownership.
     */
    update club_memberships
    set club_role = 'member'
    where club_id = v_club_id
    and profile_id = v_owner_profile_id;


    update club_memberships
    set club_role = 'owner'
    where club_id = v_club_id
    and profile_id = v_my_profile_id;


    /*
     * Accept this request.
     */
    update club_ownership_transfer_requests
    set status = 'accepted'
    where request_id = p_request_id;


    /*
     * Expire any other pending requests for this club.
     * Normally there can only be one because of the unique index,
     * but this makes the intended behaviour explicit.
     */
    update club_ownership_transfer_requests
    set status = 'expired'
    where club_id = v_club_id
    and status = 'pending'
    and request_id <> p_request_id;

end;
$$;


--
-- Name: activate_live_fixtures(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.activate_live_fixtures() RETURNS void
    LANGUAGE plpgsql
    AS $$begin
  update fixtures
  set fixture_status = 'live_90'
  where kickoff_at <= now()
    and fixture_status = 'upcoming';
end;$$;


--
-- Name: aggregate_engine_prediction(bigint, integer, numeric, numeric, numeric); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.aggregate_engine_prediction(p_engine_id bigint, p_fixture_id integer, p_current_home_raw numeric, p_current_away_raw numeric, p_weight numeric) RETURNS TABLE(home_raw numeric, away_raw numeric)
    LANGUAGE sql STABLE
    AS $$
    select
        p_current_home_raw + (e.home_raw * p_weight),
        p_current_away_raw + (e.away_raw * p_weight)
    from ml_engine_predictions e
    where e.engine_id=p_engine_id
    and e.fixture_id=p_fixture_id;
$$;


--
-- Name: aggregate_model_prediction(uuid, integer, numeric, numeric, numeric); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.aggregate_model_prediction(p_model_id uuid, p_fixture_id integer, p_current_home_raw numeric, p_current_away_raw numeric, p_weight numeric) RETURNS TABLE(home_raw numeric, away_raw numeric)
    LANGUAGE sql STABLE
    AS $$
    select
        p_current_home_raw + (m.home_raw * p_weight),
        p_current_away_raw + (m.away_raw * p_weight)
    from ml_profile_predictions m
    where m.profile_id=p_model_id
    and m.fixture_id=p_fixture_id;
$$;


--
-- Name: apply_engine_modifier(bigint, numeric, numeric); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.apply_engine_modifier(p_modifier_id bigint, p_home_raw numeric, p_away_raw numeric) RETURNS TABLE(home_raw numeric, away_raw numeric)
    LANGUAGE plpgsql
    AS $$
declare
    v_modifier_type text;
    v_config jsonb;
    v_value numeric(8,4);
begin

    select
        modifier_type,
        config
    into
        v_modifier_type,
        v_config
    from ml_modifier
    where modifier_id=p_modifier_id;

    case v_modifier_type

        when 'uniform_scale' then

            v_value:=coalesce(
                (v_config->>'value')::numeric,
                1
            );

            return query
            select
                p_home_raw*v_value,
                p_away_raw*v_value;


        when 'split_scale' then

            return query
            select
                p_home_raw*coalesce((v_config->>'home_value')::numeric,1),
                p_away_raw*coalesce((v_config->>'away_value')::numeric,1);

        else

            raise exception
            'Unsupported modifier type %',
            v_modifier_type;

    end case;

end;
$$;


--
-- Name: backfill_bot_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.backfill_bot_predictions() RETURNS void
    LANGUAGE sql
    AS $$

insert into predictions (
    profile_id,
    fixture_id,
    pred_home_goals,
    pred_away_goals
)

select
    bp.profile_id,
    f.fixture_id,
    (bp.config->>'home_goals')::smallint,
    (bp.config->>'away_goals')::smallint

from fixtures f

cross join bot_profiles bp

join bot_strategies bs
    on bs.strategy_id = bp.strategy_id

where bs.strategy_code = 'fixed_score'

and f.fixture_status = 'finished'

and not exists (
    select 1
    from predictions p
    where p.profile_id = bp.profile_id
    and p.fixture_id = f.fixture_id
);

$$;


--
-- Name: can_update_predictions(integer, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.can_update_predictions(p_fixture_id integer, p_profile_id uuid) RETURNS boolean
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
begin
    return exists (
        select 1
        from fixtures f
        join campaigns c
            on c.campaign_id = f.campaign_id
        join flavour_competitions fc
            on fc.competition_id = c.competition_id
        join flavours fl
            on fl.flavour_id = fc.flavour_id
        join prediction_formats pf
            on pf.format_id = fl.format_id
        where f.fixture_id = p_fixture_id
        and now() >= (f.kickoff_at - pf.window_open_interval)
        and now() <= (f.kickoff_at - pf.window_close_interval)
    );
end;
$$;


--
-- Name: change_club_member_role(uuid, uuid, public.club_role); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.change_club_member_role(p_club_id uuid, p_member_profile_id uuid, p_new_role public.club_role) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_my_profile_id uuid;
    v_my_role club_role;
begin

    v_my_profile_id := public.get_my_profile_id();


    -- caller must be owner
    select club_role
    into v_my_role
    from club_memberships
    where club_id = p_club_id
    and profile_id = v_my_profile_id;


    if v_my_role <> 'owner' then
        raise exception 'Only the owner can change roles';
    end if;


    -- owner cannot change their own role
    if p_member_profile_id = v_my_profile_id then
        raise exception 'Owner role cannot be changed here';
    end if;


    -- target must exist
    if not exists (
        select 1
        from club_memberships
        where club_id = p_club_id
        and profile_id = p_member_profile_id
    ) then
        raise exception 'Member not found';
    end if;


    update club_memberships
    set club_role = p_new_role
    where club_id = p_club_id
    and profile_id = p_member_profile_id;


end;
$$;


--
-- Name: check_one_modifier_per_engine(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.check_one_modifier_per_engine() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
    if exists(
        select 1
        from ml_components c
        where c.component_id=new.component_id
        and c.component_type='modifier'
    )
    and exists(
        select 1
        from ml_engine_components ec
        join ml_components c
            on c.component_id=ec.component_id
        where ec.engine_id=new.engine_id
        and c.component_type='modifier'
        and ec.component_id<>new.component_id
    ) then
        raise exception
        'Engine % already has a modifier component',
        new.engine_id;
    end if;

    return new;
end;
$$;


--
-- Name: consume_signup_code(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.consume_signup_code(p_code text) RETURNS boolean
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
begin
    update signup_codes
    set use_count = use_count + 1
    where code = p_code
      and active = true
      and (
          expires_at is null
          or expires_at > now()
      )
      and (
          max_uses is null
          or use_count < max_uses
      );

    return found;
end;
$$;


--
-- Name: create_bot_profile(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.create_bot_profile() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin

    insert into public.profiles (
        profile_id,
        profile_type,
        display_name
    )
    values (
        new.profile_id,
        'bot',
        new.bot_name
    );

    return new;

end;
$$;


--
-- Name: create_bot_profile(text, bigint); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.create_bot_profile(p_bot_name text, p_engine_id bigint) RETURNS uuid
    LANGUAGE plpgsql
    AS $$
declare
    v_profile_id uuid;
begin

    v_profile_id:=gen_random_uuid();

    insert into profiles(
        profile_id,
        profile_type,
        display_name
    )
    values(
        v_profile_id,
        'bot',
        p_bot_name
    );

    insert into bot_profiles(
        profile_id,
        bot_name,
        engine_id
    )
    values(
        v_profile_id,
        p_bot_name,
        p_engine_id
    );

    return v_profile_id;

end;
$$;


--
-- Name: create_bot_profile(text, smallint, smallint); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.create_bot_profile(p_bot_name text, p_home_goals smallint, p_away_goals smallint) RETURNS uuid
    LANGUAGE plpgsql
    AS $$
declare
    v_profile_id uuid;
begin

    v_profile_id := gen_random_uuid();

    insert into profiles(
        profile_id,
        profile_type,
        display_name
    )
    values (
        v_profile_id,
        'bot',
        p_bot_name
    );

    insert into bot_profiles(
        profile_id,
        bot_name,
        pred_home_goals,
        pred_away_goals
    )
    values (
        v_profile_id,
        p_bot_name,
        p_home_goals,
        p_away_goals
    );

    return v_profile_id;

end;
$$;


--
-- Name: create_club_profile(text, text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.create_club_profile(p_club_name text, p_club_colour text DEFAULT NULL::text) RETURNS uuid
    LANGUAGE plpgsql
    AS $$
declare
    v_profile_id uuid;
    v_creator_profile_id uuid;
    v_club_code text;
begin

    /*
        Resolve logged-in user's profile
    */
    v_creator_profile_id := public.get_my_profile_id();

    if v_creator_profile_id is null then
        raise exception 'User profile not found';
    end if;


    /*
        Check existing ownership
    */
    if exists (
        select 1
        from public.club_memberships
        where profile_id = v_creator_profile_id
        and club_role = 'owner'
    )
    then
        raise exception 'User already owns a club';
    end if;


    /*
        Generate club identities
    */
    v_profile_id := gen_random_uuid();
    v_club_code := public.generate_club_code();


    /*
        Create club profile identity
    */
    insert into public.profiles
    (
        profile_id,
        profile_type,
        display_name
    )
    values
    (
        v_profile_id,
        'club',
        p_club_name
    );


    /*
        Create club details
    */
    insert into public.club_profiles
    (
        profile_id,
        club_name,
        club_code,
        club_colour,
        created_by,
        is_active
    )
    values
    (
        v_profile_id,
        p_club_name,
        v_club_code,
        p_club_colour,
        v_creator_profile_id,
        false
    );


    /*
        Add creator as owner
    */
    insert into public.club_memberships
    (
        club_id,
        profile_id,
        club_role
    )
    values
    (
        v_profile_id,
        v_creator_profile_id,
        'owner'
    );


    return v_profile_id;

end;
$$;


--
-- Name: create_ml_model_profile(text, jsonb); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.create_ml_model_profile(p_model_code text, p_config jsonb DEFAULT '{}'::jsonb) RETURNS uuid
    LANGUAGE plpgsql
    AS $$
declare
    v_profile_id uuid;
begin

    v_profile_id := gen_random_uuid();

    insert into profiles(
        profile_id,
        profile_type,
        display_name
    )
    values (
        v_profile_id,
        'model',
        p_model_code
    );

    insert into ml_model_profiles(
        profile_id,
        model_code,
        config
    )
    values (
        v_profile_id,
        p_model_code,
        p_config
    );

    return v_profile_id;

end;
$$;


--
-- Name: create_ml_model_profile(text, public.model_type, jsonb); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.create_ml_model_profile(p_model_code text, p_model_type public.model_type, p_config jsonb DEFAULT '{}'::jsonb) RETURNS uuid
    LANGUAGE plpgsql
    AS $$
declare
    v_profile_id uuid;
begin

    v_profile_id := gen_random_uuid();

    insert into profiles(
        profile_id,
        profile_type,
        display_name
    )
    values (
        v_profile_id,
        'model',
        p_model_code
    );

    insert into ml_model_profiles(
        profile_id,
        model_code,
        model_type,
        config
    )
    values (
        v_profile_id,
        p_model_code,
        p_model_type,
        p_config
    );

    return v_profile_id;

end;
$$;


--
-- Name: create_profile_for_user(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.create_profile_for_user() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare
  new_profile_id uuid;
begin
  insert into public.profiles (profile_id, profile_type)
  values (gen_random_uuid(), 'user')
  returning profile_id into new_profile_id;

  new.profile_id := new_profile_id;

  return new;
end;
$$;


--
-- Name: create_user_profile(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.create_user_profile(p_auth_id uuid) RETURNS uuid
    LANGUAGE plpgsql
    AS $$
declare
  v_profile_id uuid;
  v_auth_compact text;
  v_username text;
begin

  v_profile_id := gen_random_uuid();

  v_auth_compact := replace(
    p_auth_id::text,
    '-',
    ''
  );

  v_username :=
    'user_' ||
    substr(v_auth_compact, 1, 8);

  if exists (
    select 1
    from public.user_profiles
    where username = v_username
  ) then
    v_username :=
      'user_' ||
      substr(v_auth_compact, 1, 10);
  end if;

  if exists (
    select 1
    from public.user_profiles
    where username = v_username
  ) then
    raise exception
      'Unable to generate unique username for auth user %',
      p_auth_id;
  end if;

  insert into public.profiles(
    profile_id,
    profile_type,
    display_name
  )
  values (
    v_profile_id,
    'user',
    v_username
  );

  insert into public.user_profiles(
    auth_id,
    username,
    display_name,
    profile_id
  )
  values (
    p_auth_id,
    v_username,
    v_username,
    v_profile_id
  );

  return v_profile_id;

end;
$$;


--
-- Name: decline_club_invite(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.decline_club_invite(p_invite_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    AS $$
begin

    update club_invites
    set invite_status = 'declined'
    where invite_id = p_invite_id
    and recipient_profile_id = public.get_my_profile_id()
    and invite_status = 'pending';

end;
$$;


--
-- Name: decline_club_ownership_transfer(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.decline_club_ownership_transfer(p_request_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_my_profile_id uuid;
begin

    v_my_profile_id := public.get_my_profile_id();

    update club_ownership_transfer_requests
    set status = 'declined'
    where request_id = p_request_id
    and target_profile_id = v_my_profile_id
    and status = 'pending';

    if not found then
        raise exception 'Ownership transfer request not found';
    end if;

end;
$$;


--
-- Name: delete_club(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.delete_club(p_club_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_my_profile_id uuid;
    v_role club_role;
begin

    v_my_profile_id := public.get_my_profile_id();


    -- confirm ownership
    select club_role
    into v_role
    from club_memberships
    where club_id = p_club_id
    and profile_id = v_my_profile_id;


    if v_role is null then
        raise exception 'You are not a member of this club';
    end if;


    if v_role <> 'owner' then
        raise exception 'Only the owner can delete this club';
    end if;


    -- soft delete club
    update club_profiles
    set
        is_deleted = true,
        deleted_at = now(),
        is_active = false,
        updated_at = now()
    where profile_id = p_club_id;


    -- remove future/unscored predictions
    delete from predictions p
    where p.profile_id = p_club_id
    and not exists (
        select 1
        from prediction_scores ps
        where ps.prediction_id = p.prediction_id
    );


    -- remove current memberships
    delete from club_memberships
    where club_id = p_club_id;

end;
$$;


--
-- Name: derive_engine_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.derive_engine_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;

    v_profiles integer:=0;
    v_created integer:=0;
    v_updated integer:=0;
    v_deleted integer:=0;
    v_total_work integer:=0;
    v_work integer;

    v_engine record;
    v_fixture record;
    v_component record;

    v_engine_home_raw numeric(8,4);
    v_engine_away_raw numeric(8,4);
    v_component_home_raw numeric(8,4);
    v_component_away_raw numeric(8,4);

    v_home_prediction smallint;
    v_away_prediction smallint;

    v_metadata jsonb;

    v_prediction_started boolean;

    v_available_weight numeric;
    v_effective_weight numeric;
    v_component_available boolean;

begin

    select count(*)
    into v_profiles
    from tmp_engines_scope;


    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'derive_engine_predictions',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;


    for v_engine in
        select *
        from tmp_engines_scope
        order by
            engine_priority asc nulls last,
            engine_id asc
    loop

        perform public.validate_engine_components(
            v_engine.engine_id
        );


        for v_fixture in
            select fixture_id
            from tmp_fixture_scope
        loop

            v_prediction_started:=false;

            v_available_weight:=0;
            v_effective_weight:=null;
            v_component_available:=false;

            v_engine_home_raw:=null;
            v_engine_away_raw:=null;

            v_component_home_raw:=null;
            v_component_away_raw:=null;

            v_metadata:='{}'::jsonb;


            /*
             * Calculate the total weight of predictive components
             * which actually have usable data for this fixture.
             *
             * Models and engines contribute predictive weight.
             * Modifiers do not.
             */
            select
                coalesce(sum(ec.weight),0)
            into
                v_available_weight
            from ml_engine_components ec
            join ml_components c
                on c.component_id=ec.component_id
            where
                ec.engine_id=v_engine.engine_id
                and (
                    (
                        c.component_type='model'
                        and exists(
                            select 1
                            from ml_profile_predictions mp
                            where
                                mp.profile_id=c.model_id
                                and mp.fixture_id=v_fixture.fixture_id
                                and mp.home_raw is not null
                                and mp.away_raw is not null
                        )
                    )
                    or
                    (
                        c.component_type='engine'
                        and exists(
                            select 1
                            from ml_engine_predictions ep
                            where
                                ep.engine_id=c.engine_id
                                and ep.fixture_id=v_fixture.fixture_id
                                and ep.home_raw is not null
                                and ep.away_raw is not null
                        )
                    )
                );


            /*
             * If no predictive components are available then this
             * engine cannot currently produce a prediction.
             *
             * Remove any stale prediction which may have previously
             * existed for this engine / fixture.
             */
            if v_available_weight=0 then

                delete from ml_engine_predictions
                where
                    engine_id=v_engine.engine_id
                    and fixture_id=v_fixture.fixture_id;

                get diagnostics v_work=row_count;

                v_deleted:=v_deleted+v_work;
                v_total_work:=v_total_work+v_work;

                continue;

            end if;


            for v_component in
                select
                    ec.component_id,
                    ec.execution_order,
                    ec.weight,
                    c.component_type,
                    c.model_id,
                    c.engine_id,
                    c.modifier_id
                from ml_engine_components ec
                join ml_components c
                    on c.component_id=ec.component_id
                where ec.engine_id=v_engine.engine_id
                order by ec.execution_order
            loop

                v_component_home_raw:=null;
                v_component_away_raw:=null;
                v_component_available:=true;
                v_effective_weight:=null;


                /*
                 * Determine whether this predictive component
                 * has usable data for the current fixture.
                 */
                if v_component.component_type='model' then

                    select exists(
                        select 1
                        from ml_profile_predictions mp
                        where
                            mp.profile_id=v_component.model_id
                            and mp.fixture_id=v_fixture.fixture_id
                            and mp.home_raw is not null
                            and mp.away_raw is not null
                    )
                    into v_component_available;


                elsif v_component.component_type='engine' then

                    select exists(
                        select 1
                        from ml_engine_predictions ep
                        where
                            ep.engine_id=v_component.engine_id
                            and ep.fixture_id=v_fixture.fixture_id
                            and ep.home_raw is not null
                            and ep.away_raw is not null
                    )
                    into v_component_available;

                end if;


                /*
                 * Missing model / engine data is valid runtime state.
                 * Skip that component rather than treating it as an
                 * exceptional condition.
                 */
                if
                    v_component.component_type in ('model','engine')
                    and not v_component_available
                then
                    continue;
                end if;


                /*
                 * Renormalise available predictive weights.
                 *
                 * Example:
                 *
                 * UP_BASE = 0.75 available
                 * CP_BASE = 0.25 unavailable
                 *
                 * available_weight = 0.75
                 * effective UP_BASE weight = 0.75 / 0.75 = 1.0
                 */
                if v_component.component_type in ('model','engine') then

                    v_effective_weight:=
                        v_component.weight / v_available_weight;

                end if;


                if not v_prediction_started then

                    case v_component.component_type

                        when 'model' then

                            select
                                home_raw,
                                away_raw
                            into
                                v_component_home_raw,
                                v_component_away_raw
                            from public.load_model_prediction(
                                v_component.model_id,
                                v_fixture.fixture_id
                            );


                        when 'engine' then

                            select
                                home_raw,
                                away_raw
                            into
                                v_component_home_raw,
                                v_component_away_raw
                            from public.load_engine_prediction(
                                v_component.engine_id,
                                v_fixture.fixture_id
                            );


                        when 'modifier' then

                            /*
                             * Structurally a modifier must be last,
                             * but unavailable preceding prediction
                             * components could mean no prediction has
                             * started by the time it is reached.
                             *
                             * In that case there is nothing to modify.
                             */
                            continue;


                        else

                            raise exception
                                'Unsupported component type %',
                                v_component.component_type;

                    end case;


                    v_engine_home_raw:=
                        v_component_home_raw * v_effective_weight;

                    v_engine_away_raw:=
                        v_component_away_raw * v_effective_weight;


                    v_metadata:=
                        v_metadata ||
                        jsonb_build_object(
                            'components',
                            coalesce(
                                v_metadata->'components',
                                '[]'::jsonb
                            )
                            ||
                            jsonb_build_array(
                                jsonb_build_object(
                                    'component_type',
                                        v_component.component_type,
                                    'component_id',
                                        v_component.component_id,
                                    'configured_weight',
                                        v_component.weight,
                                    'effective_weight',
                                        v_effective_weight
                                )
                            )
                        );


                    v_prediction_started:=true;


                else

                    case v_component.component_type

                        when 'modifier' then

                            select
                                home_raw,
                                away_raw
                            into
                                v_engine_home_raw,
                                v_engine_away_raw
                            from public.apply_engine_modifier(
                                v_component.modifier_id,
                                v_engine_home_raw,
                                v_engine_away_raw
                            );


                            v_metadata:=
                                v_metadata ||
                                jsonb_build_object(
                                    'components',
                                    coalesce(
                                        v_metadata->'components',
                                        '[]'::jsonb
                                    )
                                    ||
                                    jsonb_build_array(
                                        jsonb_build_object(
                                            'component_type',
                                                v_component.component_type,
                                            'component_id',
                                                v_component.component_id
                                        )
                                    )
                                );


                        when 'model' then

                            select
                                home_raw,
                                away_raw
                            into
                                v_engine_home_raw,
                                v_engine_away_raw
                            from public.aggregate_model_prediction(
                                v_component.model_id,
                                v_fixture.fixture_id,
                                v_engine_home_raw,
                                v_engine_away_raw,
                                v_effective_weight
                            );


                            v_metadata:=
                                v_metadata ||
                                jsonb_build_object(
                                    'components',
                                    coalesce(
                                        v_metadata->'components',
                                        '[]'::jsonb
                                    )
                                    ||
                                    jsonb_build_array(
                                        jsonb_build_object(
                                            'component_type',
                                                v_component.component_type,
                                            'component_id',
                                                v_component.component_id,
                                            'configured_weight',
                                                v_component.weight,
                                            'effective_weight',
                                                v_effective_weight
                                        )
                                    )
                                );


                        when 'engine' then

                            select
                                home_raw,
                                away_raw
                            into
                                v_engine_home_raw,
                                v_engine_away_raw
                            from public.aggregate_engine_prediction(
                                v_component.engine_id,
                                v_fixture.fixture_id,
                                v_engine_home_raw,
                                v_engine_away_raw,
                                v_effective_weight
                            );


                            v_metadata:=
                                v_metadata ||
                                jsonb_build_object(
                                    'components',
                                    coalesce(
                                        v_metadata->'components',
                                        '[]'::jsonb
                                    )
                                    ||
                                    jsonb_build_array(
                                        jsonb_build_object(
                                            'component_type',
                                                v_component.component_type,
                                            'component_id',
                                                v_component.component_id,
                                            'configured_weight',
                                                v_component.weight,
                                            'effective_weight',
                                                v_effective_weight
                                        )
                                    )
                                );


                        else

                            raise exception
                                'Unsupported component type %',
                                v_component.component_type;

                    end case;

                end if;

            end loop;


            /*
             * Defensive guard.
             *
             * v_available_weight > 0 should mean a predictive
             * component was processed, but do not insert an invalid
             * row if that assumption is ever violated.
             */
            if not v_prediction_started then

                delete from ml_engine_predictions
                where
                    engine_id=v_engine.engine_id
                    and fixture_id=v_fixture.fixture_id;

                get diagnostics v_work=row_count;

                v_deleted:=v_deleted+v_work;
                v_total_work:=v_total_work+v_work;

                continue;

            end if;


            v_home_prediction:=
                round(v_engine_home_raw)::smallint;

            v_away_prediction:=
                round(v_engine_away_raw)::smallint;


            insert into ml_engine_predictions(
                engine_id,
                fixture_id,
                scheduler_run_id,
                home_raw,
                away_raw,
                home_prediction,
                away_prediction,
                metadata
            )
            values(
                v_engine.engine_id,
                v_fixture.fixture_id,
                v_scheduler_run_id,
                v_engine_home_raw,
                v_engine_away_raw,
                v_home_prediction,
                v_away_prediction,
                v_metadata
            )
            on conflict(engine_id,fixture_id)
            do nothing;


            get diagnostics v_work=row_count;

            v_created:=v_created+v_work;
            v_total_work:=v_total_work+v_work;


            update ml_engine_predictions
            set
                scheduler_run_id=v_scheduler_run_id,
                home_raw=v_engine_home_raw,
                away_raw=v_engine_away_raw,
                home_prediction=v_home_prediction,
                away_prediction=v_away_prediction,
                metadata=v_metadata,
                updated_at=now()
            where
                engine_id=v_engine.engine_id
                and fixture_id=v_fixture.fixture_id
                and (
                    scheduler_run_id is distinct from v_scheduler_run_id
                    or home_raw is distinct from v_engine_home_raw
                    or away_raw is distinct from v_engine_away_raw
                    or home_prediction is distinct from v_home_prediction
                    or away_prediction is distinct from v_away_prediction
                    or metadata is distinct from v_metadata
                );


            get diagnostics v_work=row_count;

            v_updated:=v_updated+v_work;
            v_total_work:=v_total_work+v_work;

        end loop;

    end loop;


    if v_total_work>0 then

        insert into ml_prediction_runs(
            scheduler_run_id,
            prediction_group,
            trigger_source,
            started_at,
            completed_at,
            profiles_checked,
            predictions_created,
            predictions_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'derive_engine_predictions',
            'cron',
            v_started_at,
            now(),
            v_profiles,
            v_created,
            v_updated,
            'completed'
        );

    end if;


    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;


    return v_total_work;


exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;

        raise;

end;
$$;


--
-- Name: expire_club_ownership_transfer_requests(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.expire_club_ownership_transfer_requests() RETURNS void
    LANGUAGE sql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
    update club_ownership_transfer_requests
    set status = 'expired'
    where status = 'pending'
    and expires_at <= now();
$$;


--
-- Name: generate_club_code(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.generate_club_code() RETURNS text
    LANGUAGE plpgsql
    SET search_path TO 'public'
    AS $$
declare
    v_code text;
    v_chars constant text := 'abcdefghijklmnopqrstuvwxyz0123456789';
begin
    loop
        select string_agg(
            substr(
                v_chars,
                floor(random() * length(v_chars))::int + 1,
                1
            ),
            ''
        )
        into v_code
        from generate_series(1, 8);

        exit when not exists (
            select 1
            from public.club_profiles
            where club_code = v_code
        );
    end loop;

    return v_code;
end;
$$;


--
-- Name: generate_fixed_score_prediction(jsonb); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.generate_fixed_score_prediction(p_config jsonb) RETURNS TABLE(home_goals integer, away_goals integer)
    LANGUAGE sql IMMUTABLE
    AS $$
    select
        (p_config->>'home_goals')::integer,
        (p_config->>'away_goals')::integer;
$$;


--
-- Name: get_club_leaderboard(uuid, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_club_leaderboard(p_club_id uuid, p_flavour_id integer) RETURNS TABLE(rank bigint, profile_id uuid, display_name text, profile_type public.profile_type, predicted bigint, correct_results bigint, correct_home_goals bigint, correct_away_goals bigint, correct_goal_difference bigint, correct_total_goals bigint, perfect_10s bigint, points_total bigint)
    LANGUAGE sql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
with scoped_profiles as (

    select cm.profile_id
    from club_memberships cm
    where cm.club_id = p_club_id

    union

    select p_club_id

)

select
    dense_rank() over (
        order by
            lb.points_total desc,
            lb.perfect_10s desc,
            lb.correct_result desc,
            lb.display_name asc
    ) as rank,

    lb.profile_id,
    lb.display_name,
    lb.profile_type,
    lb.predicted,
    lb.correct_result as correct_results,
    lb.correct_home_goals,
    lb.correct_away_goals,
    lb.correct_goal_difference,
    lb.correct_total_goals,
    lb.perfect_10s,
    lb.points_total

from leaderboard lb
join scoped_profiles sp
    on sp.profile_id = lb.profile_id

where
    lb.flavour_id = p_flavour_id
    and lb.scope_type = 'overall'
    and lb.scope_id is null

order by
    lb.points_total desc,
    lb.perfect_10s desc,
    lb.correct_result desc,
    lb.display_name asc;
$$;


--
-- Name: get_fixture_leaderboard(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_fixture_leaderboard(p_fixture_id integer) RETURNS TABLE(rank bigint, profile_id uuid, display_name text, pred_home_goals smallint, pred_away_goals smallint, correct_result boolean, correct_home_goals boolean, correct_away_goals boolean, correct_goal_difference boolean, correct_total_goals boolean, points_total smallint)
    LANGUAGE sql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
    select
        rank() over (
            order by ps.points_total desc
        ) as rank,

        ps.profile_id,
        pr.display_name,

        p.pred_home_goals,
        p.pred_away_goals,

        ps.correct_result,
        ps.correct_home_goals,
        ps.correct_away_goals,
        ps.correct_goal_difference,
        ps.correct_total_goals,

        ps.points_total

    from prediction_scores ps

    join predictions p
        on p.profile_id = ps.profile_id
       and p.fixture_id = ps.fixture_id

    join profiles pr
        on pr.profile_id = ps.profile_id

    where ps.fixture_id = p_fixture_id
    and pr.profile_type in ('user','bot','p10','club')

    order by
        ps.points_total desc,
        pr.display_name asc;
$$;


--
-- Name: get_fixture_provider(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_fixture_provider(p_flavour_id integer) RETURNS TABLE(fixture_id integer, campaign_id integer, campaign_label text, competition_id integer, competition_name text, competition_code text, gameweek_id integer, gameweek_number integer, gameweek_status public.gameweek_status, stage_id integer, stage_name text, stage_code text, stage_type public.stage_type, stage_order integer, group_letter text, kickoff_at timestamp with time zone, fixture_status public.fixture_status, day text, ko text, venue_name text, city text, country text, country_code text, timezone text, home_team_id integer, home_team_name text, home_short_code text, away_team_id integer, away_team_name text, away_short_code text, tie_id integer, team_a_id integer, team_b_id integer, winner_team_id integer, resolution_type public.resolution_type, ft_home_goals integer, ft_away_goals integer, final_home_goals integer, final_away_goals integer, result_type public.result_type, prediction_open_at timestamp with time zone, prediction_close_at timestamp with time zone, predictions_open boolean)
    LANGUAGE plpgsql STABLE
    AS $$
declare
    v_format_id int;
begin

    select format_id
    into v_format_id
    from flavours
    where flavour_id = p_flavour_id;

    if v_format_id = 1 then

        return query
        select *
        from public.get_gameweek_provider(p_flavour_id);

    else

        return query
        select *
        from public.get_per_fixture_provider(p_flavour_id);

    end if;

end;
$$;


--
-- Name: get_flavours(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_flavours() RETURNS TABLE(flavour_id integer, flavour_name text, flavour_code character, is_active boolean, is_default boolean, competition_id integer, competition_name text, competition_code character, format_id integer, format_name text, format_code character, active_campaign_id integer, active_campaign_label text)
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
    select
        f.flavour_id,
        f.flavour_name,
        f.flavour_code,

        f.is_active,
        f.is_default,

        c.competition_id,
        c.competition_name,
        c.competition_code,

        pf.format_id,
        pf.format_name,
        pf.format_code,

        camp.campaign_id,
        camp.code

    from public.flavours f

    inner join public.prediction_formats pf
        on pf.format_id = f.format_id

    inner join public.flavour_competitions fc
        on fc.flavour_id = f.flavour_id

    inner join public.competitions c
        on c.competition_id = fc.competition_id

    left join public.campaigns camp
        on camp.competition_id = c.competition_id
        and camp.is_active = true

    where f.is_active = true

    order by
        f.is_default desc,
        f.flavour_name;
$$;


--
-- Name: get_from_leaderboard(integer, text, integer, text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_from_leaderboard(p_flavour_id integer, p_scope_type text DEFAULT 'overall'::text, p_scope_id integer DEFAULT NULL::integer, p_population text DEFAULT 'global'::text) RETURNS TABLE(rank bigint, profile_id uuid, display_name text, profile_type public.profile_type, predicted bigint, correct_results bigint, correct_home_goals bigint, correct_away_goals bigint, correct_goal_difference bigint, correct_total_goals bigint, perfect_10s bigint, points_total bigint)
    LANGUAGE sql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
    select
        rank() over (
            order by
                lb.points_total desc,
                lb.perfect_10s desc,
                lb.correct_result desc,
                lb.display_name asc
        ) as rank,

        lb.profile_id,
        lb.display_name,
        lb.profile_type,
        lb.predicted,
        lb.correct_result as correct_results,
        lb.correct_home_goals,
        lb.correct_away_goals,
        lb.correct_goal_difference,
        lb.correct_total_goals,
        lb.perfect_10s,
        lb.points_total

    from leaderboard lb

    where lb.flavour_id = p_flavour_id

      and lb.scope_type = p_scope_type

      and (
        (p_scope_id is null and lb.scope_id is null)
        or lb.scope_id = p_scope_id
      )

      and (
        (
            p_population = 'global'
            and lb.profile_type <> 'model'
        )

        or (
            p_population = 'users'
            and lb.profile_type in ('user', 'p10')
        )

        or (
            p_population = 'clubs'
            and lb.profile_type in ('club', 'p10')
        )

        or (
            p_population = 'bots'
            and lb.profile_type in ('bot', 'p10')
        )
      )

    order by
        lb.points_total desc,
        lb.perfect_10s desc,
        lb.correct_result desc,
        lb.display_name asc;
$$;


--
-- Name: get_gameweek_league_table_actual(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_gameweek_league_table_actual(p_flavour_id integer) RETURNS TABLE(team_id integer, team_name text, short_code text, table_type text, pos bigint, played bigint, won bigint, drawn bigint, lost bigint, gf bigint, ga bigint, gd bigint, points bigint)
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$

with campaign_scope as (
    select
        c.campaign_id
    from campaigns c
    join flavour_competitions fc
        on fc.competition_id = c.competition_id
    where fc.flavour_id = p_flavour_id
      and c.is_active = true
),

fixture_rows as (
    select
        f.home_team_id as team_id,
        r.final_home_goals::bigint as gf,
        r.final_away_goals::bigint as ga,
        case
            when r.final_home_goals > r.final_away_goals then 1
            else 0
        end as won,
        case
            when r.final_home_goals = r.final_away_goals then 1
            else 0
        end as drawn,
        case
            when r.final_home_goals < r.final_away_goals then 1
            else 0
        end as lost
    from fixtures f
    join campaign_scope cs
        on cs.campaign_id = f.campaign_id
    join results r
        on r.fixture_id = f.fixture_id

    union all

    select
        f.away_team_id as team_id,
        r.final_away_goals::bigint,
        r.final_home_goals::bigint,
        case
            when r.final_away_goals > r.final_home_goals then 1
            else 0
        end,
        case
            when r.final_away_goals = r.final_home_goals then 1
            else 0
        end,
        case
            when r.final_away_goals < r.final_home_goals then 1
            else 0
        end
    from fixtures f
    join campaign_scope cs
        on cs.campaign_id = f.campaign_id
    join results r
        on r.fixture_id = f.fixture_id
),

table_rows as (
    select
        ft.team_id,
        t.team_name,
        t.short_code,
        'actual'::text as table_type,

        count(fr.team_id)::bigint as played,

        coalesce(sum(fr.won), 0)::bigint as won,
        coalesce(sum(fr.drawn), 0)::bigint as drawn,
        coalesce(sum(fr.lost), 0)::bigint as lost,

        coalesce(sum(fr.gf), 0)::bigint as gf,
        coalesce(sum(fr.ga), 0)::bigint as ga,

        (
            coalesce(sum(fr.gf), 0)
            - coalesce(sum(fr.ga), 0)
        )::bigint as gd,

        (
            (coalesce(sum(fr.won), 0) * 3)
            + coalesce(sum(fr.drawn), 0)
        )::bigint as points

    from flavour_teams ft

    join teams t
        on t.team_id = ft.team_id

    left join fixture_rows fr
        on fr.team_id = ft.team_id

    where ft.flavour_id = p_flavour_id

    group by
        ft.team_id,
        t.team_name,
        t.short_code
)

select
    tr.team_id,
    tr.team_name,
    tr.short_code,
    tr.table_type,

    row_number() over (
        order by
            tr.points desc,
            tr.gd desc,
            tr.gf desc,
            tr.team_name
    ) as pos,

    tr.played,
    tr.won,
    tr.drawn,
    tr.lost,
    tr.gf,
    tr.ga,
    tr.gd,
    tr.points

from table_rows tr

order by
    pos;

$$;


--
-- Name: get_gameweek_league_table_prediction(integer, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_gameweek_league_table_prediction(p_flavour_id integer, p_profile_id uuid) RETURNS TABLE(team_id integer, team_name text, short_code text, table_type text, pos bigint, played bigint, won bigint, drawn bigint, lost bigint, gf bigint, ga bigint, gd bigint, points bigint)
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$

with campaign_scope as (
    select
        c.campaign_id
    from campaigns c
    join flavour_competitions fc
        on fc.competition_id = c.competition_id
    where fc.flavour_id = p_flavour_id
      and c.is_active = true
),

fixture_rows as (
    select
        f.home_team_id as team_id,
        p.pred_home_goals::bigint as gf,
        p.pred_away_goals::bigint as ga,
        case
            when p.pred_home_goals > p.pred_away_goals then 1
            else 0
        end as won,
        case
            when p.pred_home_goals = p.pred_away_goals then 1
            else 0
        end as drawn,
        case
            when p.pred_home_goals < p.pred_away_goals then 1
            else 0
        end as lost
    from fixtures f
    join campaign_scope cs
        on cs.campaign_id = f.campaign_id
    join predictions p
        on p.fixture_id = f.fixture_id
       and p.profile_id = p_profile_id

    union all

    select
        f.away_team_id as team_id,
        p.pred_away_goals::bigint,
        p.pred_home_goals::bigint,
        case
            when p.pred_away_goals > p.pred_home_goals then 1
            else 0
        end,
        case
            when p.pred_away_goals = p.pred_home_goals then 1
            else 0
        end,
        case
            when p.pred_away_goals < p.pred_home_goals then 1
            else 0
        end
    from fixtures f
    join campaign_scope cs
        on cs.campaign_id = f.campaign_id
    join predictions p
        on p.fixture_id = f.fixture_id
       and p.profile_id = p_profile_id
),

table_rows as (
    select
        ft.team_id,
        t.team_name,
        t.short_code,
        'predicted'::text as table_type,

        count(fr.team_id)::bigint as played,

        coalesce(sum(fr.won), 0)::bigint as won,
        coalesce(sum(fr.drawn), 0)::bigint as drawn,
        coalesce(sum(fr.lost), 0)::bigint as lost,

        coalesce(sum(fr.gf), 0)::bigint as gf,
        coalesce(sum(fr.ga), 0)::bigint as ga,

        (
            coalesce(sum(fr.gf), 0)
            - coalesce(sum(fr.ga), 0)
        )::bigint as gd,

        (
            (coalesce(sum(fr.won), 0) * 3)
            + coalesce(sum(fr.drawn), 0)
        )::bigint as points

    from flavour_teams ft

    left join teams t
        on t.team_id = ft.team_id

    left join fixture_rows fr
        on fr.team_id = ft.team_id

    where ft.flavour_id = p_flavour_id

    group by
        ft.team_id,
        t.team_name,
        t.short_code
)

select
    tr.team_id,
    tr.team_name,
    tr.short_code,
    tr.table_type,

    row_number() over (
        order by
            tr.points desc,
            tr.gd desc,
            tr.gf desc,
            tr.team_name
    ) as pos,

    tr.played,
    tr.won,
    tr.drawn,
    tr.lost,
    tr.gf,
    tr.ga,
    tr.gd,
    tr.points

from table_rows tr

order by
    pos;
$$;


--
-- Name: get_gameweek_provider(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_gameweek_provider(p_flavour_id integer) RETURNS TABLE(fixture_id integer, campaign_id integer, campaign_label text, competition_id integer, competition_name text, competition_code text, gameweek_id integer, gameweek_number integer, gameweek_status public.gameweek_status, stage_id integer, stage_name text, stage_code text, stage_type public.stage_type, stage_order integer, group_letter text, kickoff_at timestamp with time zone, fixture_status public.fixture_status, day text, ko text, venue_name text, city text, country text, country_code text, timezone text, home_team_id integer, home_team_name text, home_short_code text, away_team_id integer, away_team_name text, away_short_code text, tie_id integer, team_a_id integer, team_b_id integer, winner_team_id integer, resolution_type public.resolution_type, ft_home_goals integer, ft_away_goals integer, final_home_goals integer, final_away_goals integer, result_type public.result_type, prediction_open_at timestamp with time zone, prediction_close_at timestamp with time zone, predictions_open boolean)
    LANGUAGE sql STABLE
    AS $$

with campaign_scope as (

    select
        c.campaign_id,
        c.label,
        c.competition_id

    from campaigns c

    join flavour_competitions fc
        on fc.competition_id = c.competition_id

    where fc.flavour_id = p_flavour_id
      and c.is_active = true

),

gw_base as (

    select

        f.fixture_id,
        f.campaign_id,
        c.label as campaign_label,

        comp.competition_id,
        comp.competition_name,
        comp.competition_code,

        gw.gameweek_id,
        gw.gameweek_number,
        gw.status as gameweek_status,

        s.stage_id,
        s.stage_name,
        s.stage_code,
        s.stage_type,
        s.order_index as stage_order,

        cg.group_letter,

        f.kickoff_at,
        f.fixture_status,

        f.prediction_open_at,
        f.prediction_close_at,

        v.venue_name,

        city.city_name as city,
        country.name as country,
        country.iso2 as country_code,
        city.timezone,

        ht.team_id as home_team_id,
        ht.team_name as home_team_name,
        ht.short_code as home_short_code,

        at.team_id as away_team_id,
        at.team_name as away_team_name,
        at.short_code as away_short_code,

        t.tie_id,
        t.team_a_id,
        t.team_b_id,
        t.winner_team_id,
        t.resolution_type,

        r.ft_home_goals,
        r.ft_away_goals,
        r.final_home_goals,
        r.final_away_goals,
        r.result_type


    from fixtures f


    join campaign_scope c
        on c.campaign_id = f.campaign_id


    join competitions comp
        on comp.competition_id = c.competition_id


    join stages s
        on s.stage_id = f.stage_id


    left join gameweeks gw
        on gw.stage_id = s.stage_id
       and gw.campaign_id = f.campaign_id


    join venues v
        on v.venue_id = f.venue_id


    join cities city
        on city.city_id = v.city_id


    join countries country
        on country.country_id = city.country_id


    left join campaign_groups cg
        on cg.campaign_id = f.campaign_id
       and cg.team_id = f.home_team_id


    left join teams ht
        on ht.team_id = f.home_team_id


    left join teams at
        on at.team_id = f.away_team_id


    left join ties t
        on t.tie_id = f.tie_id


    left join results r
        on r.fixture_id = f.fixture_id

)


select

    g.fixture_id,
    g.campaign_id,
    g.campaign_label,

    g.competition_id,
    g.competition_name,
    g.competition_code,

    g.gameweek_id,
    g.gameweek_number,
    g.gameweek_status,

    g.stage_id,
    g.stage_name,
    g.stage_code,
    g.stage_type,
    g.stage_order,
    g.group_letter,

    g.kickoff_at,
    g.fixture_status,

    to_char(g.kickoff_at, 'Dy') as day,
    to_char(g.kickoff_at, 'HH24:MI') as ko,

    g.venue_name,
    g.city,
    g.country,
    g.country_code,
    g.timezone,

    g.home_team_id,
    g.home_team_name,
    g.home_short_code,

    g.away_team_id,
    g.away_team_name,
    g.away_short_code,

    g.tie_id,
    g.team_a_id,
    g.team_b_id,
    g.winner_team_id,
    g.resolution_type,

    g.ft_home_goals,
    g.ft_away_goals,
    g.final_home_goals,
    g.final_away_goals,
    g.result_type,

    g.prediction_open_at,
    g.prediction_close_at,

    (
        g.fixture_status = 'upcoming'

        and g.prediction_open_at is not null
        and g.prediction_close_at is not null

        and now() >= g.prediction_open_at
        and now() < g.prediction_close_at

        and (
            (
                g.gameweek_id is not null
                and g.gameweek_status = 'active'
            )
            or (
                g.gameweek_id is null
                and g.stage_type = 'rearranged'
            )
        )
    ) as predictions_open


from gw_base g

order by
    g.kickoff_at;

$$;


--
-- Name: get_leaderboard(integer, integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_leaderboard(p_flavour_id integer, p_campaign_id integer DEFAULT NULL::integer, p_stage_id integer DEFAULT NULL::integer) RETURNS TABLE(rank bigint, profile_id uuid, display_name text, profile_type public.profile_type, predicted bigint, correct_results bigint, correct_home_goals bigint, correct_away_goals bigint, correct_goal_difference bigint, correct_total_goals bigint, perfect_10s bigint, points_total bigint)
    LANGUAGE sql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$

with scoped_fixtures as (

    select f.fixture_id
    from fixtures f
    join stages s
        on s.stage_id = f.stage_id
    join campaigns c
        on c.campaign_id = f.campaign_id
    join flavour_competitions fc
        on fc.competition_id = c.competition_id

    where fc.flavour_id = p_flavour_id

    and (
        p_campaign_id is null
        or c.campaign_id = p_campaign_id
    )

    and (
        p_stage_id is null
        or s.stage_id = p_stage_id
    )

),

base as (

    select
        ps.profile_id,
        ps.fixture_id,
        ps.correct_result,
        ps.correct_home_goals,
        ps.correct_away_goals,
        ps.correct_goal_difference,
        ps.correct_total_goals,
        ps.perfect_10,
        ps.points_total
    from prediction_scores ps
    join scoped_fixtures sf
        on sf.fixture_id = ps.fixture_id

)

select
    rank() over (
        order by sum(b.points_total) desc, p.display_name asc
    ) as rank,

    b.profile_id,
    p.display_name,
    p.profile_type,

    count(*) as predicted,

    sum(b.correct_result::int) as correct_results,
    sum(b.correct_home_goals::int) as correct_home_goals,
    sum(b.correct_away_goals::int) as correct_away_goals,
    sum(b.correct_goal_difference::int) as correct_goal_difference,
    sum(b.correct_total_goals::int) as correct_total_goals,

    sum(b.perfect_10::int) as perfect_10s,
    sum(b.points_total) as points_total

from base b
join profiles p
    on p.profile_id = b.profile_id

group by
    b.profile_id,
    p.display_name,
    p.profile_type

order by
    points_total desc,
    perfect_10s desc,
    correct_results desc,
    display_name asc;

$$;


--
-- Name: get_league_table_actual(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_league_table_actual(p_flavour_id integer) RETURNS TABLE(team_id integer, team_name text, short_code text, table_type text, pos bigint, played bigint, won bigint, drawn bigint, lost bigint, gf bigint, ga bigint, gd bigint, points bigint)
    LANGUAGE plpgsql STABLE
    AS $$
declare
    v_format_code text;

begin
    select
        pf.format_code

    into v_format_code

    from flavours f

    join prediction_formats pf
        on pf.format_id = f.format_id

    where f.flavour_id = p_flavour_id;

    if v_format_code = 'GWK' then
        return query
        select *
        from get_gameweek_league_table_actual(
            p_flavour_id
        );

    -- Future:
    --
    -- elsif v_format_code = 'PFX' then
    --
    --     return query
    --     select *
    --     from get_group_league_table_actual(
    --         p_flavour_id
    --     );

    elsif v_format_code = 'PFX' then
        raise notice
            'PFX actual league table not yet implemented for flavour %',
            p_flavour_id;
        return;
    else

        raise exception
            'Unsupported prediction format: %', v_format_code;
    end if;
end;

$$;


--
-- Name: get_league_table_prediction(integer, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_league_table_prediction(p_flavour_id integer, p_profile_id uuid) RETURNS TABLE(team_id integer, team_name text, short_code text, table_type text, pos bigint, played bigint, won bigint, drawn bigint, lost bigint, gf bigint, ga bigint, gd bigint, points bigint)
    LANGUAGE plpgsql STABLE
    AS $$
declare
    v_format_code text;

begin
    select
        pf.format_code

    into v_format_code

    from flavours f

    join prediction_formats pf
        on pf.format_id = f.format_id

    where f.flavour_id = p_flavour_id;

    if v_format_code = 'GWK' then

        return query
        select *
        from get_gameweek_league_table_prediction(
            p_flavour_id,
            p_profile_id
        );

    -- Future:
    --
    -- elsif v_format_code = 'PFX' then
    --
    --     return query
    --     select *
    --     from get_group_league_table_prediction(
    --         p_flavour_id,
    --         p_profile_id
    --     );

    elsif v_format_code = 'PFX' then

        raise notice
            'PFX prediction league table not yet implemented for flavour %',
            p_flavour_id;
        return;

    else

        raise exception
            'Unsupported prediction format: %', v_format_code;
    end if;
end;

$$;


--
-- Name: get_my_club_ids(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_my_club_ids() RETURNS SETOF uuid
    LANGUAGE sql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$

    select club_id
    from club_memberships
    where profile_id = public.get_my_profile_id();

$$;


--
-- Name: get_my_club_invites(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_my_club_invites() RETURNS TABLE(invite_id uuid, club_id uuid, club_name text, club_code text, club_colour text, sender_name text, created_at timestamp with time zone)
    LANGUAGE sql
    AS $$

select
    ci.invite_id,
    ci.club_id,
    cp.club_name,
    cp.club_code,
    cp.club_colour,
    p.display_name,
    ci.created_at

from club_invites ci

join club_profiles cp
    on cp.profile_id = ci.club_id

join profiles p
    on p.profile_id = ci.sender_profile_id

where ci.recipient_profile_id = public.get_my_profile_id()
and ci.invite_status = 'pending';

$$;


--
-- Name: get_my_club_ownership_transfer_requests(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_my_club_ownership_transfer_requests() RETURNS TABLE(request_id uuid, club_id uuid, club_name text, owner_profile_id uuid, owner_display_name text, created_at timestamp with time zone, expires_at timestamp with time zone)
    LANGUAGE sql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
    select
        r.request_id,
        r.club_id,
        cp.club_name,
        r.owner_profile_id,
        up.display_name as owner_display_name,
        r.created_at,
        r.expires_at
    from club_ownership_transfer_requests r

    join club_profiles cp
        on cp.profile_id = r.club_id

    join user_profiles up
        on up.profile_id = r.owner_profile_id

    where r.target_profile_id = public.get_my_profile_id()
    and r.status = 'pending'
    and r.expires_at > now()

    order by r.created_at desc;
$$;


--
-- Name: get_my_profile_id(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_my_profile_id() RETURNS uuid
    LANGUAGE sql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$

    select profile_id
    from user_profiles
    where auth_id = auth.uid();

$$;


--
-- Name: get_per_fixture_provider(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_per_fixture_provider(p_flavour_id integer) RETURNS TABLE(fixture_id integer, campaign_id integer, campaign_label text, competition_id integer, competition_name text, competition_code text, gameweek_id integer, gameweek_number integer, gameweek_status public.gameweek_status, stage_id integer, stage_name text, stage_code text, stage_type public.stage_type, stage_order integer, group_letter text, kickoff_at timestamp with time zone, fixture_status public.fixture_status, day text, ko text, venue_name text, city text, country text, country_code text, timezone text, home_team_id integer, home_team_name text, home_short_code text, away_team_id integer, away_team_name text, away_short_code text, tie_id integer, team_a_id integer, team_b_id integer, winner_team_id integer, resolution_type public.resolution_type, ft_home_goals integer, ft_away_goals integer, final_home_goals integer, final_away_goals integer, result_type public.result_type, prediction_open_at timestamp with time zone, prediction_close_at timestamp with time zone, predictions_open boolean)
    LANGUAGE sql STABLE
    AS $$

select
  f.fixture_id,
  f.campaign_id,
  c.label as campaign_label,

  comp.competition_id,
  comp.competition_name,
  comp.competition_code,

  null::integer as gameweek_id,
  null::integer as gameweek_number,
  null::gameweek_status as gameweek_status,

  s.stage_id,
  s.stage_name,
  s.stage_code,
  s.stage_type,
  s.order_index as stage_order,
  g.group_letter,

  f.kickoff_at,
  f.fixture_status,
  to_char(f.kickoff_at, 'Dy') as day,
  to_char(f.kickoff_at, 'HH24:MI') as ko,

  v.venue_name,
  city.city_name,
  country.name as country,
  country.iso2 as country_code,
  city.timezone,

  ht.team_id as home_team_id,
  ht.team_name as home_team_name,
  ht.short_code as home_short_code,

  at.team_id as away_team_id,
  at.team_name as away_team_name,
  at.short_code as away_short_code,

  t.tie_id,
  t.team_a_id,
  t.team_b_id,
  t.winner_team_id,
  t.resolution_type,

  r.ft_home_goals,
  r.ft_away_goals,
  r.final_home_goals,
  r.final_away_goals,
  r.result_type,

  f.prediction_open_at,
  f.prediction_close_at,

  (
    f.fixture_status = 'upcoming'
    and now() >= f.prediction_open_at
    and now() < f.prediction_close_at
  ) as predictions_open

from flavours fl

join flavour_competitions fc
  on fc.flavour_id = fl.flavour_id

join competitions comp
  on comp.competition_id = fc.competition_id

join campaigns c
  on c.competition_id = comp.competition_id
 and c.is_active = true

join fixtures f
  on f.campaign_id = c.campaign_id

join stages s
  on s.stage_id = f.stage_id

join venues v
  on v.venue_id = f.venue_id

join cities city
  on city.city_id = v.city_id

join countries country
  on country.country_id = city.country_id

left join campaign_groups g
  on g.campaign_id = f.campaign_id
 and g.team_id = f.home_team_id

left join teams ht
  on ht.team_id = f.home_team_id

left join teams at
  on at.team_id = f.away_team_id

left join ties t
  on t.tie_id = f.tie_id

left join results r
  on r.fixture_id = f.fixture_id

where fl.flavour_id = p_flavour_id;

$$;


--
-- Name: get_profile_ticker(uuid, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_profile_ticker(p_profile_id uuid, p_flavour_id integer) RETURNS TABLE(scope text, rank_position bigint, total_points bigint)
    LANGUAGE sql STABLE
    AS $$
    
    select
        'global'::text,
        g.rank_position,
        g.total_points
    from global_global_leaderboard g
    where g.profile_id = p_profile_id

    union all

    select
        'flavour'::text,
        f.rank_position,
        f.total_points
    from flavour_global_leaderboard f
    where f.profile_id = p_profile_id
      and f.competition_id = (
          select competition_id
          from flavour_competitions
          where flavour_id = p_flavour_id
      )

    union all

    select
        'campaign'::text,
        c.rank_position,
        c.total_points
    from campaign_global_leaderboard c
    join campaigns camp
        on camp.campaign_id = c.campaign_id
    join flavour_competitions fc
        on fc.competition_id = camp.competition_id
    where c.profile_id = p_profile_id
      and fc.flavour_id = p_flavour_id
      and camp.is_active = true

    union all

    select
        'stage'::text,
        s.rank_position,
        s.total_points
    from stage_global_leaderboard s
    join stages st
        on st.stage_id = s.stage_id
    join campaigns camp
        on camp.campaign_id = st.campaign_id
    join flavour_competitions fc
        on fc.competition_id = camp.competition_id
    where s.profile_id = p_profile_id
      and fc.flavour_id = p_flavour_id
      and (
          st.is_live = true
          or (
              st.is_finished = true
              and st.stage_id = (
                  select stage_id
                  from stages st2
                  join campaigns c2
                      on c2.campaign_id = st2.campaign_id
                  join flavour_competitions fc2
                      on fc2.competition_id = c2.competition_id
                  where fc2.flavour_id = p_flavour_id
                    and st2.is_finished = true
                  order by st2.order_index desc
                  limit 1
              )
          )
      );

$$;


--
-- Name: get_profile_ticker(uuid, integer, integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_profile_ticker(p_profile_id uuid, p_flavour_id integer, p_campaign_id integer, p_stage_id integer) RETURNS TABLE(scope text, rank_position bigint, total_points bigint)
    LANGUAGE sql STABLE SECURITY DEFINER
    SET search_path TO 'public'
    AS $$

    select
        'global'::text as scope,
        g.rank_position,
        coalesce(g.total_points, 0)::bigint as total_points
    from (
        select
            p_profile_id as profile_id,
            1::bigint as dummy
    ) base
    left join global_global_leaderboard g
        on g.profile_id = base.profile_id

    union all

    select
        'flavour'::text as scope,
        f.rank_position,
        coalesce(f.total_points, 0)::bigint as total_points
    from (
        select
            p_profile_id as profile_id,
            (
                select competition_id
                from flavour_competitions
                where flavour_id = p_flavour_id
            ) as competition_id
    ) base
    left join flavour_global_leaderboard f
        on f.profile_id = base.profile_id
        and f.competition_id = base.competition_id

    union all

    select
        'campaign'::text as scope,
        c.rank_position,
        coalesce(c.total_points, 0)::bigint as total_points
    from (
        select
            p_profile_id as profile_id,
            p_campaign_id as campaign_id
    ) base
    left join campaign_global_leaderboard c
        on c.profile_id = base.profile_id
        and c.campaign_id = base.campaign_id

    union all

    select
        'stage'::text as scope,
        s.rank_position,
        coalesce(s.total_points, 0)::bigint as total_points
    from (
        select
            p_profile_id as profile_id,
            p_stage_id as stage_id
    ) base
    left join stage_global_leaderboard s
        on s.profile_id = base.profile_id
        and s.stage_id = base.stage_id;

$$;


--
-- Name: get_team_results_comparison(integer, integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_team_results_comparison(p_flavour_id integer, p_team_1_id integer, p_team_2_id integer) RETURNS TABLE(opponent_id integer, opponent_name text, opponent_short text, t1_home_gf integer, t1_home_ga integer, t1_home_pts integer, t1_home_swing integer, t1_away_gf integer, t1_away_ga integer, t1_away_pts integer, t1_away_swing integer, t1_total_points integer, t1_total_swing integer, t2_home_gf integer, t2_home_ga integer, t2_home_pts integer, t2_home_swing integer, t2_away_gf integer, t2_away_ga integer, t2_away_pts integer, t2_away_swing integer, t2_total_points integer, t2_total_swing integer)
    LANGUAGE sql STABLE
    AS $$

with campaign_scope as (
    select
        c.campaign_id,
        c.competition_id
    from campaigns c
    join flavour_competitions fc
        on fc.competition_id = c.competition_id
    where fc.flavour_id = p_flavour_id
      and c.is_active = true
),

completed_fixtures as (
    select
        f.fixture_id,
        f.home_team_id,
        f.away_team_id,
        r.final_home_goals,
        r.final_away_goals
    from fixtures f
    join campaign_scope cs
        on cs.campaign_id = f.campaign_id
    join results r
        on r.fixture_id = f.fixture_id
    where f.fixture_status = 'finished'
),

team_fixtures as (
    /*
     * Convert every relevant fixture into the perspective
     * of team 1 or team 2.
     */
    select
        cf.fixture_id,
        p_team_1_id as comparison_team_id,
        case
            when cf.home_team_id = p_team_1_id
                then cf.away_team_id
            else cf.home_team_id
        end as opponent_id,
        case
            when cf.home_team_id = p_team_1_id
                then 'home'
            else 'away'
        end as venue,
        case
            when cf.home_team_id = p_team_1_id
                then cf.final_home_goals
            else cf.final_away_goals
        end as gf,
        case
            when cf.home_team_id = p_team_1_id
                then cf.final_away_goals
            else cf.final_home_goals
        end as ga
    from completed_fixtures cf
    where cf.home_team_id = p_team_1_id
       or cf.away_team_id = p_team_1_id

    union all

    select
        cf.fixture_id,
        p_team_2_id as comparison_team_id,
        case
            when cf.home_team_id = p_team_2_id
                then cf.away_team_id
            else cf.home_team_id
        end as opponent_id,
        case
            when cf.home_team_id = p_team_2_id
                then 'home'
            else 'away'
        end as venue,
        case
            when cf.home_team_id = p_team_2_id
                then cf.final_home_goals
            else cf.final_away_goals
        end as gf,
        case
            when cf.home_team_id = p_team_2_id
                then cf.final_away_goals
            else cf.final_home_goals
        end as ga
    from completed_fixtures cf
    where cf.home_team_id = p_team_2_id
       or cf.away_team_id = p_team_2_id
),

scored_fixtures as (
    select
        tf.*,
        case
            when tf.gf > tf.ga then 3
            when tf.gf = tf.ga then 1
            else 0
        end as points
    from team_fixtures tf
),

opponents as (
    select distinct
        team_id as opponent_id
    from (
        select
            f.home_team_id as team_id
        from fixtures f
        join campaign_scope cs
            on cs.campaign_id = f.campaign_id
        where f.home_team_id is not null

        union

        select
            f.away_team_id as team_id
        from fixtures f
        join campaign_scope cs
            on cs.campaign_id = f.campaign_id
        where f.away_team_id is not null
    ) campaign_teams
    where team_id not in (
        p_team_1_id,
        p_team_2_id
    )
),

comparison as (
    select
        o.opponent_id,

        max(sf.gf) filter (
            where sf.comparison_team_id = p_team_1_id
              and sf.venue = 'home'
        ) as t1_home_gf,

        max(sf.ga) filter (
            where sf.comparison_team_id = p_team_1_id
              and sf.venue = 'home'
        ) as t1_home_ga,

        max(sf.points) filter (
            where sf.comparison_team_id = p_team_1_id
              and sf.venue = 'home'
        ) as t1_home_pts,

        max(sf.gf) filter (
            where sf.comparison_team_id = p_team_1_id
              and sf.venue = 'away'
        ) as t1_away_gf,

        max(sf.ga) filter (
            where sf.comparison_team_id = p_team_1_id
              and sf.venue = 'away'
        ) as t1_away_ga,

        max(sf.points) filter (
            where sf.comparison_team_id = p_team_1_id
              and sf.venue = 'away'
        ) as t1_away_pts,

        max(sf.gf) filter (
            where sf.comparison_team_id = p_team_2_id
              and sf.venue = 'home'
        ) as t2_home_gf,

        max(sf.ga) filter (
            where sf.comparison_team_id = p_team_2_id
              and sf.venue = 'home'
        ) as t2_home_ga,

        max(sf.points) filter (
            where sf.comparison_team_id = p_team_2_id
              and sf.venue = 'home'
        ) as t2_home_pts,

        max(sf.gf) filter (
            where sf.comparison_team_id = p_team_2_id
              and sf.venue = 'away'
        ) as t2_away_gf,

        max(sf.ga) filter (
            where sf.comparison_team_id = p_team_2_id
              and sf.venue = 'away'
        ) as t2_away_ga,

        max(sf.points) filter (
            where sf.comparison_team_id = p_team_2_id
              and sf.venue = 'away'
        ) as t2_away_pts

    from opponents o
    left join scored_fixtures sf
        on sf.opponent_id = o.opponent_id
    group by
        o.opponent_id
)

select
    c.opponent_id,
    t.team_name as opponent_name,
    t.short_code as opponent_short,

    /*
     * TEAM 1 HOME
     */
    c.t1_home_gf,
    c.t1_home_ga,
    c.t1_home_pts,

    case
        when c.t1_home_pts is not null
         and c.t2_home_pts is not null
        then c.t1_home_pts - c.t2_home_pts
        else null
    end as t1_home_swing,

    /*
     * TEAM 1 AWAY
     */
    c.t1_away_gf,
    c.t1_away_ga,
    c.t1_away_pts,

    case
        when c.t1_away_pts is not null
         and c.t2_away_pts is not null
        then c.t1_away_pts - c.t2_away_pts
        else null
    end as t1_away_swing,

    /*
     * TEAM 1 TOTALS
     */
    case
        when c.t1_home_pts is null
         and c.t1_away_pts is null
        then null
        else coalesce(c.t1_home_pts, 0)
           + coalesce(c.t1_away_pts, 0)
    end as t1_total_points,

    case
        when (
            c.t1_home_pts is not null
            and c.t2_home_pts is not null
        )
        or (
            c.t1_away_pts is not null
            and c.t2_away_pts is not null
        )
        then
            case
                when c.t1_home_pts is not null
                 and c.t2_home_pts is not null
                then c.t1_home_pts - c.t2_home_pts
                else 0
            end
            +
            case
                when c.t1_away_pts is not null
                 and c.t2_away_pts is not null
                then c.t1_away_pts - c.t2_away_pts
                else 0
            end
        else null
    end as t1_total_swing,

    /*
     * TEAM 2 HOME
     */
    c.t2_home_gf,
    c.t2_home_ga,
    c.t2_home_pts,

    case
        when c.t2_home_pts is not null
         and c.t1_home_pts is not null
        then c.t2_home_pts - c.t1_home_pts
        else null
    end as t2_home_swing,

    /*
     * TEAM 2 AWAY
     */
    c.t2_away_gf,
    c.t2_away_ga,
    c.t2_away_pts,

    case
        when c.t2_away_pts is not null
         and c.t1_away_pts is not null
        then c.t2_away_pts - c.t1_away_pts
        else null
    end as t2_away_swing,

    /*
     * TEAM 2 TOTALS
     */
    case
        when c.t2_home_pts is null
         and c.t2_away_pts is null
        then null
        else coalesce(c.t2_home_pts, 0)
           + coalesce(c.t2_away_pts, 0)
    end as t2_total_points,

    case
        when (
            c.t2_home_pts is not null
            and c.t1_home_pts is not null
        )
        or (
            c.t2_away_pts is not null
            and c.t1_away_pts is not null
        )
        then
            case
                when c.t2_home_pts is not null
                 and c.t1_home_pts is not null
                then c.t2_home_pts - c.t1_home_pts
                else 0
            end
            +
            case
                when c.t2_away_pts is not null
                 and c.t1_away_pts is not null
                then c.t2_away_pts - c.t1_away_pts
                else 0
            end
        else null
    end as t2_total_swing

from comparison c
join teams t
    on t.team_id = c.opponent_id
order by
    t.team_name;

$$;


--
-- Name: get_user_clubs(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_user_clubs() RETURNS TABLE(club_id uuid, club_name text, club_code text, club_colour text, invite_only boolean, is_active boolean, member_profile_id uuid, display_name text, club_role public.club_role)
    LANGUAGE sql
    AS $$

select
    cp.profile_id as club_id,
    cp.club_name,
    cp.club_code,
    cp.club_colour,
    cp.invite_only,
    cp.is_active,
    cm.profile_id as member_profile_id,
    up.display_name,
    cm.club_role

from club_memberships cm

join club_profiles cp
    on cp.profile_id = cm.club_id

join user_profiles up
    on up.profile_id = cm.profile_id

where cp.is_deleted = false
and cm.club_id in (
    select club_id
    from club_memberships
    where profile_id = public.get_my_profile_id()
);
$$;


--
-- Name: get_user_flavour_predictions(integer, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_user_flavour_predictions(p_flavour_id integer, p_profile_id uuid) RETURNS TABLE(fixture_id integer, prediction_id bigint, profile_id uuid, pred_home_goals smallint, pred_away_goals smallint, prediction_created_at timestamp with time zone, prediction_updated_at timestamp with time zone, correct_result boolean, correct_home_goals boolean, correct_away_goals boolean, correct_goal_difference boolean, correct_total_goals boolean, points_total smallint, perfect_10 boolean)
    LANGUAGE sql STABLE SECURITY DEFINER
    AS $$
  select

    p.fixture_id,

    p.prediction_id,
    p.profile_id,
    p.pred_home_goals,
    p.pred_away_goals,
    p.created_at,
    p.updated_at,

    ps.correct_result,
    ps.correct_home_goals,
    ps.correct_away_goals,
    ps.correct_goal_difference,
    ps.correct_total_goals,
    ps.points_total,
    ps.perfect_10

  from predictions p

  left join prediction_scores ps
    on ps.prediction_id = p.prediction_id

  join fixtures f
    on f.fixture_id = p.fixture_id

  join flavours fl
    on fl.flavour_id = p_flavour_id

  where p.profile_id = p_profile_id

    and f.campaign_id in (
      select c.campaign_id
      from campaigns c
      join flavour_competitions fc
        on fc.competition_id = c.competition_id
      where fc.flavour_id = p_flavour_id
    );
$$;


--
-- Name: get_user_profile(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_user_profile(p_auth_id uuid) RETURNS TABLE(profile_id uuid, username public.citext, display_name text, avatar_key text, created_at timestamp with time zone, favourite_domestic_team_id integer, favourite_national_team_id integer)
    LANGUAGE sql
    AS $$

select
    up.profile_id,
    up.username,
    up.display_name,
    up.avatar_key,
    up.created_at,
    up.favourite_domestic_team_id,
    up.favourite_national_team_id

from user_profiles up
where up.auth_id = p_auth_id;

$$;


--
-- Name: handle_new_user(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.handle_new_user() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
begin

    perform public.create_user_profile(new.id);

    return new;

end;
$$;


--
-- Name: handle_postponed_fixture(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.handle_postponed_fixture() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_is_gwk boolean;
    v_holding_stage_id integer;
    v_placeholder_at timestamptz;
    v_competition_id integer;
BEGIN

    -- Only process newly postponed fixtures.

    IF OLD.fixture_status = 'postponed' THEN
        RETURN NEW;
    END IF;


    -- Determine whether this campaign uses GWK.

    SELECT EXISTS (
        SELECT 1
        FROM campaigns c
        JOIN flavour_competitions fc
            ON fc.competition_id = c.competition_id
        JOIN flavours fl
            ON fl.flavour_id = fc.flavour_id
        JOIN prediction_formats pf
            ON pf.format_id = fl.format_id
        WHERE c.campaign_id = NEW.campaign_id
          AND pf.format_code = 'GWK'
    )
    INTO v_is_gwk;


    -- PFX fixtures retain their original stage.

    IF NOT v_is_gwk THEN
        RETURN NEW;
    END IF;


    -- Retrieve campaign configuration.

    SELECT
        c.competition_id,
        c.postponed_placeholder_at
    INTO
        v_competition_id,
        v_placeholder_at
    FROM campaigns c
    WHERE c.campaign_id = NEW.campaign_id;


    IF v_placeholder_at IS NULL THEN
        RAISE EXCEPTION
            'Campaign % has no postponement placeholder date.',
            NEW.campaign_id;
    END IF;


    -- Retrieve or automatically create the holding stage.
    -- The unique index prevents duplicate holding stages.

    INSERT INTO stages (
        competition_id,
        campaign_id,
        stage_name,
        stage_code,
        stage_type,
        order_index,
        is_active,
        is_finished,
        is_live
    )
    VALUES (
        v_competition_id,
        NEW.campaign_id,
        'Postponed Fixtures',
        'POST',
        'postponed',
        NULL,
        false,
        false,
        false
    )
    ON CONFLICT (campaign_id)
        WHERE stage_type = 'postponed'
    DO UPDATE
        SET campaign_id = EXCLUDED.campaign_id
    RETURNING stage_id
    INTO v_holding_stage_id;


    -- Move the fixture and assign its temporary kickoff.

    NEW.stage_id := v_holding_stage_id;
    NEW.kickoff_at := v_placeholder_at;

    -- Postponed fixtures must not accept predictions.

    NEW.prediction_open_at := NULL;
    NEW.prediction_close_at := NULL;

    RETURN NEW;

END;
$$;


--
-- Name: handle_prediction_score_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.handle_prediction_score_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$declare
    v_flavour_id int;
    v_campaign_id int;
    v_stage_id int;
begin

    select
        fc.flavour_id,
        f.campaign_id,
        f.stage_id
    into
        v_flavour_id,
        v_campaign_id,
        v_stage_id
    from fixtures f
    join campaigns c on c.campaign_id = f.campaign_id
    join flavour_competitions fc on fc.competition_id = c.competition_id
    where f.fixture_id = new.fixture_id;

    perform public.rebuild_leaderboard_for_scope(
        v_flavour_id,
        v_campaign_id,
        v_stage_id
    );

    return new;
end;$$;


--
-- Name: insert_live_fixtures(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.insert_live_fixtures() RETURNS void
    LANGUAGE plpgsql
    AS $$
begin
  insert into results (
    fixture_id,
    ft_home_goals,
    ft_away_goals,
    final_home_goals,
    final_away_goals
  )
  select
    f.fixture_id,
    0,
    0,
    0,
    0
  from fixtures f
  left join results r
    on r.fixture_id = f.fixture_id
  where
    f.kickoff_at <= now()
    and f.fixture_status = 'live_90'
    and r.fixture_id is null;
end;
$$;


--
-- Name: is_club_admin(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.is_club_admin(p_club_id uuid) RETURNS boolean
    LANGUAGE sql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$

    select exists (

        select 1
        from club_memberships

        where club_id = p_club_id
        and profile_id = public.get_my_profile_id()
        and club_role in ('owner', 'captain')

    );

$$;


--
-- Name: leave_club(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.leave_club(p_club_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_profile_id uuid;
    v_role club_role;
begin

    v_profile_id := public.get_my_profile_id();

    select club_role
    into v_role
    from club_memberships
    where club_id = p_club_id
    and profile_id = v_profile_id;


    if v_role is null then
        raise exception 'You are not a member of this club';
    end if;


    if v_role = 'owner' then
        raise exception 'Owner must transfer ownership or delete the club';
    end if;


    delete from club_memberships
    where club_id = p_club_id
    and profile_id = v_profile_id;

end;
$$;


--
-- Name: load_engine_prediction(bigint, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.load_engine_prediction(p_engine_id bigint, p_fixture_id integer) RETURNS TABLE(home_raw numeric, away_raw numeric)
    LANGUAGE plpgsql STABLE
    AS $$
declare
    v_home_raw numeric(8,4);
    v_away_raw numeric(8,4);
begin

    select
        ep.home_raw,
        ep.away_raw
    into
        v_home_raw,
        v_away_raw
    from ml_engine_predictions ep
    where ep.engine_id=p_engine_id
    and ep.fixture_id=p_fixture_id;

    if not found then
        raise exception
        'Engine % has no prediction for fixture %',
        p_engine_id,
        p_fixture_id;
    end if;

    if v_home_raw is null
    or v_away_raw is null then
        raise exception
        'Engine % returned null raw values for fixture %',
        p_engine_id,
        p_fixture_id;
    end if;

    return query
    select
        v_home_raw,
        v_away_raw;

end;
$$;


--
-- Name: load_model_prediction(uuid, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.load_model_prediction(p_model_id uuid, p_fixture_id integer) RETURNS TABLE(home_raw numeric, away_raw numeric)
    LANGUAGE plpgsql STABLE
    AS $$
declare
    v_home_raw numeric(8,4);
    v_away_raw numeric(8,4);
begin

    select
        mp.home_raw,
        mp.away_raw
    into
        v_home_raw,
        v_away_raw
    from ml_profile_predictions mp
    where mp.profile_id=p_model_id
    and mp.fixture_id=p_fixture_id;

    if not found then
        raise exception
        'Model % has no prediction for fixture %',
        p_model_id,
        p_fixture_id;
    end if;

    if v_home_raw is null
    or v_away_raw is null then
        raise exception
        'Model % returned null raw values for fixture %',
        p_model_id,
        p_fixture_id;
    end if;

    return query
    select
        v_home_raw,
        v_away_raw;

end;
$$;


--
-- Name: manage_dynamic_engine_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.manage_dynamic_engine_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_total_work integer:=0;
begin

    create temporary table tmp_fixture_scope
    on commit drop
    as
    select fixture_id
    from vw_ml_feature_scope
    where prediction_open_at<=now()
    and prediction_close_at>now()
    and coalesce(locked,false)=false;


    create temporary table tmp_engines_scope
    on commit drop
    as
    select
        engine_id,
        engine_type,
        engine_priority
    from ml_engines
    where engine_type in(
        'prediction_averages',
        'perfect10'
    );


    if exists(
        select 1
        from tmp_engines_scope
    ) then

        return public.derive_engine_predictions();

    end if;

    return v_total_work;

end;
$$;


--
-- Name: manage_dynamic_model_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.manage_dynamic_model_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
begin

    create temporary table tmp_prediction_fixtures
    on commit drop
    as
    select fixture_id
    from vw_ml_feature_scope
    where prediction_open_at<=now()
    and prediction_close_at>now()
    and coalesce(locked,false)=false;

    create temporary table tmp_prediction_averages_models
    on commit drop
    as
    select
        profile_id,
        model_code,
        config
    from ml_model_profiles
    where model_type='prediction_averages';

    if exists(
        select 1
        from tmp_prediction_averages_models
    ) then
        perform public.manage_prediction_averages_model_predictions();
    end if;

    return 0;

end;
$$;


--
-- Name: manage_dynamic_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.manage_dynamic_predictions() RETURNS void
    LANGUAGE plpgsql
    AS $$
begin

    perform public.manage_dynamic_model_predictions();
    perform public.manage_dynamic_engine_predictions();

end;
$$;


--
-- Name: manage_fixed_score_bot_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.manage_fixed_score_bot_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_created integer:=0;
    v_profiles integer:=0;
    v_started_at timestamptz;
begin
    select count(*)
    into v_profiles
    from tmp_fixed_score_bots;

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'manage_fixed_score_bot_predictions',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;

    insert into ml_profile_predictions(
        fixture_id,
        profile_id,
        scheduler_run_id,
        home_raw,
        away_raw,
        home_prediction,
        away_prediction,
        confidence,
        metadata
    )
    select
        mpp.fixture_id,
        b.bot_profile_id,
        v_scheduler_run_id,
        mpp.home_raw,
        mpp.away_raw,
        mpp.home_prediction,
        mpp.away_prediction,
        mpp.confidence,
        mpp.metadata
    from tmp_fixed_score_bots b
    join ml_profile_predictions mpp
        on mpp.profile_id=b.source_model_id
    join tmp_bot_prediction_fixtures f
        on f.fixture_id=mpp.fixture_id
    on conflict(profile_id,fixture_id)
    do nothing;

    get diagnostics v_created=row_count;

    if v_created>0 then

        insert into ml_prediction_runs(
            scheduler_run_id,
            prediction_group,
            trigger_source,
            started_at,
            completed_at,
            profiles_checked,
            predictions_created,
            predictions_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'fixed_score_bot',
            'cron',
            v_started_at,
            now(),
            v_profiles,
            v_created,
            0,
            'completed'
        );

    end if;


    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;


    return v_created;


exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;
        raise;

end;
$$;


--
-- Name: manage_fixed_score_model_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.manage_fixed_score_model_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_created integer:=0;
    v_updated integer:=0;
    v_profiles integer:=0;
    v_started_at timestamptz;
begin
    select count(*)
    into v_profiles
    from tmp_fixed_score_models;

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'manage_fixed_score_model_predictions',
        'cron',
        now(),
        'running'
    )
    returning 
        scheduler_run_id,
        started_at 
    into 
        v_scheduler_run_id,
        v_started_at;

    insert into ml_profile_predictions(
        fixture_id,
        profile_id,
        scheduler_run_id,
        home_raw,
        away_raw,
        home_prediction,
        away_prediction,
        metadata
    )
    select
        f.fixture_id,
        m.profile_id,
        v_scheduler_run_id,
        (m.config->>'home_goals')::smallint,
        (m.config->>'away_goals')::smallint,
        (m.config->>'home_goals')::smallint,
        (m.config->>'away_goals')::smallint,
        m.config
    from tmp_prediction_fixtures f
    cross join tmp_fixed_score_models m
    on conflict(profile_id,fixture_id) do nothing;

    get diagnostics v_created=row_count;

    if v_created>0 then
        insert into ml_prediction_runs(
            scheduler_run_id,
            prediction_group,
            trigger_source,
            started_at,
            completed_at,
            profiles_checked,
            predictions_created,
            predictions_updated,
            status            
        )
        values(
            v_scheduler_run_id,
            'fixed_score',
            'cron',
            v_started_at,
            now(),
            v_profiles,
            v_created,
            v_updated,
            'completed'
        );
    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;

    return v_created;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;

        raise;
end;
$$;


--
-- Name: manage_ml_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.manage_ml_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_model_type public.model_type;
begin

    create temporary table tmp_prediction_fixtures
    on commit drop
    as
    select fixture_id
    from vw_ml_feature_scope
    where prediction_open_at <= now()
    and prediction_close_at > now()
    and coalesce(locked,false)=false;

    create temporary table tmp_fixed_score_models
    on commit drop
    as
    select
        profile_id,
        model_code,
        config
    from ml_model_profiles
    where model_type='fixed_score';

    if exists(select 1 from tmp_fixed_score_models) then
        perform public.manage_fixed_score_model_predictions();
    end if;

    return 0;
end;
$$;


--
-- Name: manage_prediction_averages_model_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.manage_prediction_averages_model_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_created integer:=0;
    v_updated integer:=0;
    v_deleted integer:=0;
    v_total_work integer:=0;
    v_work integer:=0;
    v_profiles integer:=0;
    v_started_at timestamptz;
    v_model record;
    v_home_column text;
    v_away_column text;
begin

    select count(*)
    into v_profiles
    from tmp_prediction_averages_models;

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'manage_prediction_averages_model_predictions',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;


    for v_model in
        select
            profile_id,
            config
        from tmp_prediction_averages_models
    loop

        v_home_column:=v_model.config->>'home_goals';
        v_away_column:=v_model.config->>'away_goals';


        /*
         * Remove existing predictions where the underlying
         * feature data is no longer available.
         *
         * This prevents stale model predictions surviving when
         * a prediction-average source becomes NULL.
         */
        execute format(
            '
            delete from ml_profile_predictions p
            using tmp_prediction_fixtures f,
                  ml_feature_snapshots fs
            where
                p.profile_id=%L
                and p.fixture_id=f.fixture_id
                and fs.fixture_id=f.fixture_id
                and (
                    fs.%I is null
                    or fs.%I is null
                )
            ',
            v_model.profile_id,
            v_home_column,
            v_away_column
        );

        get diagnostics v_work=row_count;

        v_deleted:=v_deleted+v_work;
        v_total_work:=v_total_work+v_work;


        /*
         * Create predictions only where both configured
         * prediction-average features are available.
         */
        execute format(
            '
            insert into ml_profile_predictions(
                fixture_id,
                profile_id,
                scheduler_run_id,
                home_raw,
                away_raw,
                home_prediction,
                away_prediction,
                metadata
            )
            select
                f.fixture_id,
                %L,
                %L,
                fs.%I,
                fs.%I,
                round(fs.%I)::smallint,
                round(fs.%I)::smallint,
                %L::jsonb
            from tmp_prediction_fixtures f
            join ml_feature_snapshots fs
                on fs.fixture_id=f.fixture_id
            where
                fs.%I is not null
                and fs.%I is not null
            on conflict(profile_id,fixture_id)
            do nothing
            ',
            v_model.profile_id,
            v_scheduler_run_id,
            v_home_column,
            v_away_column,
            v_home_column,
            v_away_column,
            v_model.config,
            v_home_column,
            v_away_column
        );

        get diagnostics v_work=row_count;

        v_created:=v_created+v_work;
        v_total_work:=v_total_work+v_work;


        /*
         * Update existing predictions only where both configured
         * prediction-average features are available.
         */
        execute format(
            '
            update ml_profile_predictions p
            set
                scheduler_run_id=%L,
                home_raw=fs.%I,
                away_raw=fs.%I,
                home_prediction=round(fs.%I)::smallint,
                away_prediction=round(fs.%I)::smallint,
                metadata=%L::jsonb,
                updated_at=now()
            from tmp_prediction_fixtures f
            join ml_feature_snapshots fs
                on fs.fixture_id=f.fixture_id
            where
                p.profile_id=%L
                and p.fixture_id=f.fixture_id
                and fs.%I is not null
                and fs.%I is not null
                and (
                    p.home_raw is distinct from fs.%I
                    or p.away_raw is distinct from fs.%I
                    or p.home_prediction is distinct from round(fs.%I)::smallint
                    or p.away_prediction is distinct from round(fs.%I)::smallint
                )
            ',
            v_scheduler_run_id,
            v_home_column,
            v_away_column,
            v_home_column,
            v_away_column,
            v_model.config,
            v_model.profile_id,
            v_home_column,
            v_away_column,
            v_home_column,
            v_away_column,
            v_home_column,
            v_away_column
        );

        get diagnostics v_work=row_count;

        v_updated:=v_updated+v_work;
        v_total_work:=v_total_work+v_work;

    end loop;


    if v_total_work>0 then

        insert into ml_prediction_runs(
            scheduler_run_id,
            prediction_group,
            trigger_source,
            started_at,
            completed_at,
            profiles_checked,
            predictions_created,
            predictions_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'prediction_averages',
            'cron',
            v_started_at,
            now(),
            v_profiles,
            v_created,
            v_updated,
            'completed'
        );

    end if;


    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;

    return v_total_work;


exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;

        raise;

end;
$$;


--
-- Name: manage_static_engine_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.manage_static_engine_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
begin

    create temporary table tmp_bot_prediction_fixtures
    on commit drop
    as
    select fixture_id
    from vw_ml_feature_scope
    where prediction_open_at <= now()
    and prediction_close_at > now()
    and coalesce(locked,false)=false;

    create temporary table tmp_fixed_score_bots
    on commit drop
    as
    select
        bp.profile_id as bot_profile_id,
        e.engine_id,
        c.model_id as source_model_id
    from bot_profiles bp
    join ml_engines e
        on e.engine_id=bp.engine_id
    join ml_engine_components ec
        on ec.engine_id=e.engine_id
    join ml_components c
        on c.component_id=ec.component_id
    where e.engine_type='fixed_score';

    if exists(
        select 1
        from tmp_fixed_score_bots
    ) then
        perform public.manage_fixed_score_bot_predictions();
    end if;
    return 0;

end;
$$;


--
-- Name: manage_static_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.manage_static_predictions() RETURNS void
    LANGUAGE plpgsql
    AS $$
begin

    perform public.manage_ml_predictions();
    perform public.manage_static_engine_predictions();

end;
$$;


--
-- Name: ml_features_campaign(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.ml_features_campaign() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;
    v_rows_updated integer:=0;
begin

    create temporary table tmp_feature_scope
    on commit drop
    as
    select *
    from vw_ml_feature_scope
    where prediction_open_at<=now()
    and prediction_close_at>now()
    and coalesce(locked,false)=false;


    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'ml_features_campaign',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;

    update ml_feature_snapshots s

    set
        ht_campaign_mp=home_stats.mp,
        at_campaign_mp=away_stats.mp,

        ht_campaign_league_pos=home_position.league_position,
        at_campaign_league_pos=away_position.league_position,

        ht_campaign_gf_avg=home_stats.gf_avg,
        ht_home_campaign_gf_avg=home_stats.home_gf_avg,
        ht_campaign_ga_avg=home_stats.ga_avg,
        ht_home_campaign_ga_avg=home_stats.home_ga_avg,

        at_campaign_gf_avg=away_stats.gf_avg,
        at_away_campaign_gf_avg=away_stats.away_gf_avg,
        at_campaign_ga_avg=away_stats.ga_avg,
        at_away_campaign_ga_avg=away_stats.away_ga_avg

    from tmp_feature_scope scope

    left join lateral (

        select
            lph.league_position

        from league_position_history lph

        where
            lph.flavour_id=scope.flavour_id
            and lph.campaign_id=scope.campaign_id
            and lph.team_id=scope.home_team_id

        order by lph.recorded_at desc

        limit 1

    ) home_position on true

    left join lateral (

        select
            lph.league_position

        from league_position_history lph

        where
            lph.flavour_id=scope.flavour_id
            and lph.campaign_id=scope.campaign_id
            and lph.team_id=scope.away_team_id

        order by lph.recorded_at desc

        limit 1

    ) away_position on true

    left join lateral (

        select
            count(*)::integer as mp,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_home_goals
                    else r.ft_away_goals
                end
            )::numeric as gf_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_away_goals
                    else r.ft_home_goals
                end
            )::numeric as ga_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_home_goals
                end
            )::numeric as home_gf_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_away_goals
                end
            )::numeric as home_ga_avg

        from fixtures f

        join results r
            on r.fixture_id=f.fixture_id

        where
            f.campaign_id=scope.campaign_id
            and f.fixture_status='finished'
            and f.kickoff_at<(
                select kickoff_at
                from fixtures
                where fixture_id=scope.fixture_id
            )
            and (
                f.home_team_id=scope.home_team_id
                or f.away_team_id=scope.home_team_id
            )

    ) home_stats on true


    left join lateral (

        select
            count(*)::integer as mp,

            avg(
                case
                    when f.home_team_id=scope.away_team_id
                    then r.ft_home_goals
                    else r.ft_away_goals
                end
            )::numeric as gf_avg,

            avg(
                case
                    when f.home_team_id=scope.away_team_id
                    then r.ft_away_goals
                    else r.ft_home_goals
                end
            )::numeric as ga_avg,

            avg(
                case
                    when f.away_team_id=scope.away_team_id
                    then r.ft_away_goals
                end
            )::numeric as away_gf_avg,

            avg(
                case
                    when f.away_team_id=scope.away_team_id
                    then r.ft_home_goals
                end
            )::numeric as away_ga_avg

        from fixtures f

        join results r
            on r.fixture_id=f.fixture_id

        where
            f.campaign_id=scope.campaign_id
            and f.fixture_status='finished'
            and f.kickoff_at<(
                select kickoff_at
                from fixtures
                where fixture_id=scope.fixture_id
            )
            and (
                f.home_team_id=scope.away_team_id
                or f.away_team_id=scope.away_team_id
            )

    ) away_stats on true

    where
        s.fixture_id=scope.fixture_id
        and s.locked=false;


    get diagnostics v_rows_updated=row_count;

    if v_rows_updated>0 then

        insert into ml_feature_runs(
            scheduler_run_id,
            feature_group,
            trigger_source,
            started_at,
            completed_at,
            fixtures_checked,
            fixtures_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'campaign_features',
            'cron',
            v_started_at,
            now(),
            v_rows_updated,
            v_rows_updated,
            'completed'
        );

    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'

    where scheduler_run_id=v_scheduler_run_id;

    return v_rows_updated;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm

        where scheduler_run_id=v_scheduler_run_id;
        raise;
end;
$$;


--
-- Name: ml_features_flavour(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.ml_features_flavour() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;
    v_rows_updated integer:=0;
begin

    create temporary table tmp_feature_scope
    on commit drop
    as
    select *
    from vw_ml_feature_scope
    where prediction_open_at<=now()
    and prediction_close_at>now()
    and coalesce(locked,false)=false;

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'ml_features_flavour',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;

    update ml_feature_snapshots s

    set
        ht_flavour_mp=home_stats.mp,
        at_flavour_mp=away_stats.mp,

        ht_flavour_gf_avg=home_stats.gf_avg,
        ht_home_flavour_gf_avg=home_stats.home_gf_avg,
        ht_flavour_ga_avg=home_stats.ga_avg,
        ht_home_flavour_ga_avg=home_stats.home_ga_avg,

        at_flavour_gf_avg=away_stats.gf_avg,
        at_away_flavour_gf_avg=away_stats.away_gf_avg,
        at_flavour_ga_avg=away_stats.ga_avg,
        at_away_flavour_ga_avg=away_stats.away_ga_avg

    from tmp_feature_scope scope

    left join lateral (

        select
            count(*)::integer as mp,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_home_goals
                    else r.ft_away_goals
                end
            )::numeric as gf_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_away_goals
                    else r.ft_home_goals
                end
            )::numeric as ga_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_home_goals
                end
            )::numeric as home_gf_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_away_goals
                end
            )::numeric as home_ga_avg

        from fixtures f

        join results r
            on r.fixture_id=f.fixture_id

        join campaigns c
            on c.campaign_id=f.campaign_id

        join flavour_competitions fc
            on fc.competition_id=c.competition_id

        where
            fc.flavour_id=scope.flavour_id
            and f.fixture_status='finished'
            and f.kickoff_at<(
                select kickoff_at
                from fixtures
                where fixture_id=scope.fixture_id
            )
            and (
                f.home_team_id=scope.home_team_id
                or f.away_team_id=scope.home_team_id
            )

    ) home_stats on true


    left join lateral (

        select
            count(*)::integer as mp,

            avg(
                case
                    when f.home_team_id=scope.away_team_id
                    then r.ft_home_goals
                    else r.ft_away_goals
                end
            )::numeric as gf_avg,

            avg(
                case
                    when f.home_team_id=scope.away_team_id
                    then r.ft_away_goals
                    else r.ft_home_goals
                end
            )::numeric as ga_avg,

            avg(
                case
                    when f.away_team_id=scope.away_team_id
                    then r.ft_away_goals
                end
            )::numeric as away_gf_avg,

            avg(
                case
                    when f.away_team_id=scope.away_team_id
                    then r.ft_home_goals
                end
            )::numeric as away_ga_avg

        from fixtures f

        join results r
            on r.fixture_id=f.fixture_id

        join campaigns c
            on c.campaign_id=f.campaign_id

        join flavour_competitions fc
            on fc.competition_id=c.competition_id

        where
            fc.flavour_id=scope.flavour_id
            and f.fixture_status='finished'
            and f.kickoff_at<(
                select kickoff_at
                from fixtures
                where fixture_id=scope.fixture_id
            )
            and (
                f.home_team_id=scope.away_team_id
                or f.away_team_id=scope.away_team_id
            )

    ) away_stats on true


    where
        s.fixture_id=scope.fixture_id
        and s.locked=false;


    get diagnostics v_rows_updated=row_count;


    if v_rows_updated>0 then

        insert into ml_feature_runs(
            scheduler_run_id,
            feature_group,
            trigger_source,
            started_at,
            completed_at,
            fixtures_checked,
            fixtures_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'flavour_features',
            'cron',
            v_started_at,
            now(),
            v_rows_updated,
            v_rows_updated,
            'completed'
        );

    end if;


    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;


    return v_rows_updated;


exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;

        raise;

end;
$$;


--
-- Name: ml_features_global(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.ml_features_global() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;
    v_rows_updated integer:=0;
begin

    create temporary table tmp_feature_scope
    on commit drop
    as
    select *
    from vw_ml_feature_scope
    where prediction_open_at<=now()
    and prediction_close_at>now()
    and coalesce(locked,false)=false;

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'ml_features_global',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;

    update ml_feature_snapshots s
    set
        ht_global_gf_avg=home_stats.gf_avg,
        ht_home_global_gf_avg=home_stats.home_gf_avg,
        ht_global_ga_avg=home_stats.ga_avg,
        ht_home_global_ga_avg=home_stats.home_ga_avg,

        at_global_gf_avg=away_stats.gf_avg,
        at_away_global_gf_avg=away_stats.away_gf_avg,
        at_global_ga_avg=away_stats.ga_avg,
        at_away_global_ga_avg=away_stats.away_ga_avg

    from tmp_feature_scope scope

    left join lateral (

        select

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_home_goals
                    else r.ft_away_goals
                end
            )::numeric as gf_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_away_goals
                    else r.ft_home_goals
                end
            )::numeric as ga_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_home_goals
                end
            )::numeric as home_gf_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_away_goals
                end
            )::numeric as home_ga_avg

        from fixtures f
        join results r
            on r.fixture_id=f.fixture_id

        where
            f.fixture_status='finished'
            and f.kickoff_at<(
                select kickoff_at
                from fixtures
                where fixture_id=scope.fixture_id
            )
            and (
                f.home_team_id=scope.home_team_id
                or f.away_team_id=scope.home_team_id
            )

    ) home_stats on true

    left join lateral (

        select

            avg(
                case
                    when f.home_team_id=scope.away_team_id
                    then r.ft_home_goals
                    else r.ft_away_goals
                end
            )::numeric as gf_avg,

            avg(
                case
                    when f.home_team_id=scope.away_team_id
                    then r.ft_away_goals
                    else r.ft_home_goals
                end
            )::numeric as ga_avg,

            avg(
                case
                    when f.away_team_id=scope.away_team_id
                    then r.ft_away_goals
                end
            )::numeric as away_gf_avg,

            avg(
                case
                    when f.away_team_id=scope.away_team_id
                    then r.ft_home_goals
                end
            )::numeric as away_ga_avg

        from fixtures f
        join results r
            on r.fixture_id=f.fixture_id

        where
            f.fixture_status='finished'
            and f.kickoff_at<(
                select kickoff_at
                from fixtures
                where fixture_id=scope.fixture_id
            )
            and (
                f.home_team_id=scope.away_team_id
                or f.away_team_id=scope.away_team_id
            )

    ) away_stats on true

    where
        s.fixture_id=scope.fixture_id
        and s.locked=false;

    get diagnostics v_rows_updated=row_count;

    if v_rows_updated>0 then

        insert into ml_feature_runs(
            scheduler_run_id,
            feature_group,
            trigger_source,
            started_at,
            completed_at,
            fixtures_checked,
            fixtures_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'global_features',
            'cron',
            v_started_at,
            now(),
            v_rows_updated,
            v_rows_updated,
            'completed'
        );

    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;

    return v_rows_updated;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;

        raise;

end;
$$;


--
-- Name: ml_features_initialise_snapshot(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.ml_features_initialise_snapshot() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;
    v_rows_checked integer:=0;
    v_rows_inserted integer:=0;
begin

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'ml_features_initialise_snapshot',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;


    select count(*)
    into v_rows_checked

    from vw_ml_feature_scope

    where
        prediction_open_at<=now()
        and prediction_close_at>now();


    insert into ml_feature_snapshots(
        fixture_id,
        prediction_open,
        locked,
        home_team_id,
        away_team_id
    )

    select
        fixture_id,
        true,
        false,
        home_team_id,
        away_team_id

    from vw_ml_feature_scope

    where
        prediction_open_at<=now()
        and prediction_close_at>now()
        and snapshot_fixture_id is null;

    get diagnostics v_rows_inserted=row_count;

    if v_rows_inserted>0 then

        insert into ml_feature_runs(
            scheduler_run_id,
            feature_group,
            trigger_source,
            started_at,
            completed_at,
            fixtures_checked,
            fixtures_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'initialise_snapshot',
            'cron',
            v_started_at,
            now(),
            v_rows_checked,
            v_rows_inserted,
            'completed'
        );

    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'

    where scheduler_run_id=v_scheduler_run_id;

    return v_rows_inserted;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm

        where scheduler_run_id=v_scheduler_run_id;
        raise;
end;
$$;


--
-- Name: ml_features_last5(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.ml_features_last5() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;
    v_rows_updated integer:=0;
begin
    create temporary table tmp_feature_scope
    on commit drop
    as
    select *
    from vw_ml_feature_scope
    where prediction_open_at<=now()
    and prediction_close_at>now()
    and coalesce(locked,false)=false;

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'ml_features_last5',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;

    with team_last5 as (

        select
            s.fixture_id,
            s.home_team_id as team_id,
            target.campaign_id,
            f.fixture_id as played_fixture_id,
            f.kickoff_at,
            f.home_team_id,
            f.away_team_id,

            case
                when f.home_team_id=s.home_team_id
                then f.away_team_id
                else f.home_team_id
            end as opponent_team_id,

            row_number() over(
                partition by s.fixture_id,s.home_team_id
                order by f.kickoff_at desc
            ) as rn

        from tmp_feature_scope s

        join fixtures target
            on target.fixture_id=s.fixture_id

        join fixtures f
            on f.campaign_id=target.campaign_id
            and (
                f.home_team_id=s.home_team_id
                or f.away_team_id=s.home_team_id
            )
            and f.kickoff_at<target.kickoff_at
            and f.fixture_status='finished'

        union all

        select
            s.fixture_id,
            s.away_team_id as team_id,
            target.campaign_id,
            f.fixture_id as played_fixture_id,
            f.kickoff_at,
            f.home_team_id,
            f.away_team_id,

            case
                when f.home_team_id=s.away_team_id
                then f.away_team_id
                else f.home_team_id
            end as opponent_team_id,

            row_number() over(
                partition by s.fixture_id,s.away_team_id
                order by f.kickoff_at desc
            ) as rn

        from tmp_feature_scope s

        join fixtures target
            on target.fixture_id=s.fixture_id

        join fixtures f
            on f.campaign_id=target.campaign_id
            and (
                f.home_team_id=s.away_team_id
                or f.away_team_id=s.away_team_id
            )
            and f.kickoff_at<target.kickoff_at
            and f.fixture_status='finished'
    ),

    last5_enriched as (

        select
            l.fixture_id,
            l.team_id,
            l.kickoff_at,

            case
                when l.home_team_id=l.team_id
                then r.ft_home_goals
                else r.ft_away_goals
            end as goals_for,

            case
                when l.home_team_id=l.team_id
                then r.ft_away_goals
                else r.ft_home_goals
            end as goals_against,

            case
                when l.home_team_id=l.team_id then
                    case
                        when r.result_type='home_win' then 3
                        when r.result_type='draw' then 1
                        else 0
                    end
                else
                    case
                        when r.result_type='away_win' then 3
                        when r.result_type='draw' then 1
                        else 0
                    end
            end as points,

            fixture_rank.league_position as opponent_fixture_rank,
            current_rank.league_position as opponent_current_rank

        from team_last5 l

        join results r
            on r.fixture_id=l.played_fixture_id


        left join lateral (

            select
                lph.league_position

            from league_position_history lph

            where
                lph.team_id=l.opponent_team_id
                and lph.campaign_id=l.campaign_id
                and lph.recorded_at<l.kickoff_at
                and lph.matches_played>0

            order by lph.recorded_at desc

            limit 1

        ) fixture_rank on true

        left join lateral (

            select
                lph.league_position

            from league_position_history lph

            where
                lph.team_id=l.opponent_team_id
                and lph.campaign_id=l.campaign_id
                and lph.matches_played>0

            order by lph.recorded_at desc

            limit 1

        ) current_rank on true

        where l.rn<=5
    ),

    last5_aggregates as (
        select
            fixture_id,
            team_id,

            count(*)::integer as matches,

            extract(
                day from (
                    max(kickoff_at)-min(kickoff_at)
                )
            )::integer as days_span,

            avg(goals_for)::numeric as gf_avg,
            avg(goals_against)::numeric as ga_avg,
            avg(points)::numeric as points_avg,

            avg(opponent_fixture_rank)::numeric as opp_fixture_rank_avg,
            avg(opponent_current_rank)::numeric as opp_current_rank_avg

        from last5_enriched

        group by
            fixture_id,
            team_id

    )

    update ml_feature_snapshots s

    set
        ht_last5_matches=h.matches,
        ht_last5_days_span=h.days_span,
        ht_last5_gf_avg=h.gf_avg,
        ht_last5_ga_avg=h.ga_avg,
        ht_last5_points_avg=h.points_avg,
        ht_last5_opp_fixture_rank_avg=h.opp_fixture_rank_avg,
        ht_last5_opp_current_rank_avg=h.opp_current_rank_avg,

        at_last5_matches=a.matches,
        at_last5_days_span=a.days_span,
        at_last5_gf_avg=a.gf_avg,
        at_last5_ga_avg=a.ga_avg,
        at_last5_points_avg=a.points_avg,
        at_last5_opp_fixture_rank_avg=a.opp_fixture_rank_avg,
        at_last5_opp_current_rank_avg=a.opp_current_rank_avg

    from tmp_feature_scope scope

    left join last5_aggregates h
        on h.fixture_id=scope.fixture_id
        and h.team_id=scope.home_team_id

    left join last5_aggregates a
        on a.fixture_id=scope.fixture_id
        and a.team_id=scope.away_team_id

    where
        s.fixture_id=scope.fixture_id
        and s.locked=false;

    get diagnostics v_rows_updated=row_count;

    if v_rows_updated>0 then

        insert into ml_feature_runs(
            scheduler_run_id,
            feature_group,
            trigger_source,
            started_at,
            completed_at,
            fixtures_checked,
            fixtures_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'last5_features',
            'cron',
            v_started_at,
            now(),
            v_rows_updated,
            v_rows_updated,
            'completed'
        );

    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'

    where scheduler_run_id=v_scheduler_run_id;

    return v_rows_updated;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm

        where scheduler_run_id=v_scheduler_run_id;

        raise;
end;
$$;


--
-- Name: ml_features_lock_snapshot(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.ml_features_lock_snapshot() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;
    v_rows_locked integer:=0;
begin

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'ml_features_lock_snapshot',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;
    update ml_feature_snapshots s

    set
        prediction_open=false,
        locked=true,
        locked_at=now()

    from fixtures f

    where
        s.fixture_id=f.fixture_id
        and s.locked=false
        and f.prediction_close_at<=now();

    get diagnostics v_rows_locked=row_count;

    if v_rows_locked>0 then

        insert into ml_feature_runs(
            scheduler_run_id,
            feature_group,
            trigger_source,
            started_at,
            completed_at,
            fixtures_checked,
            fixtures_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'lock_snapshot',
            'cron',
            v_started_at,
            now(),
            v_rows_locked,
            v_rows_locked,
            'completed'
        );

    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'

    where scheduler_run_id=v_scheduler_run_id;

    return v_rows_locked;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm

        where scheduler_run_id=v_scheduler_run_id;

        raise;
end;
$$;


--
-- Name: ml_features_prediction_window_maintenance(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.ml_features_prediction_window_maintenance() RETURNS TABLE(snapshots_initialised integer, snapshots_locked integer)
    LANGUAGE plpgsql
    AS $$
begin

    return query
    select
        public.ml_features_initialise_snapshot(),
        public.ml_features_lock_snapshot();

end;
$$;


--
-- Name: ml_features_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.ml_features_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;
    v_rows_updated integer:=0;
begin

    create temporary table tmp_feature_scope
    on commit drop
    as
    select *
    from vw_ml_feature_scope
    where prediction_open_at<=now()
    and prediction_close_at>now()
    and coalesce(locked,false)=false;

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'ml_features_predictions',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;

    update ml_feature_snapshots s
    set
        user_home_prediction_avg=user_stats.home_avg,
        user_away_prediction_avg=user_stats.away_avg,
        user_prediction_count=user_stats.prediction_count,

        club_home_prediction_avg=club_stats.home_avg,
        club_away_prediction_avg=club_stats.away_avg,
        club_prediction_count=club_stats.prediction_count
    from tmp_feature_scope scope

    left join lateral (
        select
            avg(p.pred_home_goals)::numeric as home_avg,
            avg(p.pred_away_goals)::numeric as away_avg,
            count(*)::integer as prediction_count
        from predictions p
        join profiles pr
            on pr.profile_id=p.profile_id
        where
            p.fixture_id=scope.fixture_id
            and pr.profile_type='user'
    ) user_stats on true

    left join lateral (
        select
            avg(p.pred_home_goals)::numeric as home_avg,
            avg(p.pred_away_goals)::numeric as away_avg,
            count(*)::integer as prediction_count
        from predictions p
        join profiles pr
            on pr.profile_id=p.profile_id
        where
            p.fixture_id=scope.fixture_id
            and pr.profile_type='club'
    ) club_stats on true

    where
        s.fixture_id=scope.fixture_id
        and s.locked=false;

    get diagnostics v_rows_updated=row_count;

    if v_rows_updated>0 then

        insert into ml_feature_runs(
            scheduler_run_id,
            feature_group,
            trigger_source,
            started_at,
            completed_at,
            fixtures_checked,
            fixtures_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'prediction_aggregates',
            'ml_features_predictions',
            v_started_at,
            now(),
            v_rows_updated,
            v_rows_updated,
            'completed'
        );

    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;

    return v_rows_updated;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;

        raise;
end;
$$;


--
-- Name: ml_features_venue(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.ml_features_venue() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;
    v_rows_updated integer:=0;
begin

    create temporary table tmp_feature_scope
    on commit drop
    as
    select *
    from vw_ml_feature_scope
    where prediction_open_at<=now()
    and prediction_close_at>now()
    and coalesce(locked,false)=false;

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'ml_features_venue',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;

    update ml_feature_snapshots s
    set
        ht_venue_gf_avg=home_stats.gf_avg,
        ht_venue_ga_avg=home_stats.ga_avg,
        ht_venue_matches_played=home_stats.matches,

        at_venue_gf_avg=away_stats.gf_avg,
        at_venue_ga_avg=away_stats.ga_avg,
        at_venue_matches_played=away_stats.matches

    from tmp_feature_scope scope

    left join lateral (
        select
            count(*)::integer as matches,
            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_home_goals
                    else r.ft_away_goals
                end
            )::numeric as gf_avg,

            avg(
                case
                    when f.home_team_id=scope.home_team_id
                    then r.ft_away_goals
                    else r.ft_home_goals
                end
            )::numeric as ga_avg

        from fixtures f
        join results r
            on r.fixture_id=f.fixture_id

        where
            f.venue_id=scope.venue_id
            and f.fixture_status='finished'
            and f.kickoff_at<(
                select kickoff_at
                from fixtures
                where fixture_id=scope.fixture_id
            )
            and (
                f.home_team_id=scope.home_team_id
                or f.away_team_id=scope.home_team_id
            )

    ) home_stats on true

    left join lateral (
        select
            count(*)::integer as matches,
            avg(
                case
                    when f.home_team_id=scope.away_team_id
                    then r.ft_home_goals
                    else r.ft_away_goals
                end
            )::numeric as gf_avg,

            avg(
                case
                    when f.home_team_id=scope.away_team_id
                    then r.ft_away_goals
                    else r.ft_home_goals
                end
            )::numeric as ga_avg

        from fixtures f
        join results r
            on r.fixture_id=f.fixture_id

        where
            f.venue_id=scope.venue_id
            and f.fixture_status='finished'
            and f.kickoff_at<(
                select kickoff_at
                from fixtures
                where fixture_id=scope.fixture_id
            )
            and (
                f.home_team_id=scope.away_team_id
                or f.away_team_id=scope.away_team_id
            )

    ) away_stats on true

    where
        s.fixture_id=scope.fixture_id
        and s.locked=false;

    get diagnostics v_rows_updated=row_count;

    if v_rows_updated>0 then

        insert into ml_feature_runs(
            scheduler_run_id,
            feature_group,
            trigger_source,
            started_at,
            completed_at,
            fixtures_checked,
            fixtures_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'venue_features',
            'cron',
            v_started_at,
            now(),
            v_rows_updated,
            v_rows_updated,
            'completed'
        );

    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;

    return v_rows_updated;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;

        raise;

end;
$$;


--
-- Name: notify_perfect10_fixture_changed(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.notify_perfect10_fixture_changed() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO ''
    AS $$
begin
    perform realtime.send(
        payload => pg_catalog.jsonb_build_object(
            'type', 'fixture_updated',
            'fixture_id', new.fixture_id
        ),
        event => 'fixture_changed',
        topic => 'perfect10:public:fixtures',
        private => false
    );

    return new;
end;
$$;


--
-- Name: notify_perfect10_result_changed(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.notify_perfect10_result_changed() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO ''
    AS $$
declare
    v_fixture_id bigint;
begin
    if tg_op = 'DELETE' then
        v_fixture_id := old.fixture_id;
    else
        v_fixture_id := new.fixture_id;
    end if;

    perform realtime.send(
        payload => pg_catalog.jsonb_build_object(
            'type', 'result_updated',
            'fixture_id', v_fixture_id
        ),
        event => 'fixture_changed',
        topic => 'perfect10:public:fixtures',
        private => false
    );

    if tg_op = 'DELETE' then
        return old;
    end if;

    return new;
end;
$$;


--
-- Name: process_live_fixtures(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.process_live_fixtures() RETURNS void
    LANGUAGE plpgsql
    AS $$begin
  perform public.activate_live_fixtures();
  perform public.insert_live_fixtures();  
end;$$;


--
-- Name: progress_gameweeks(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.progress_gameweeks() RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    AS $$
declare
    r_campaign record;

    v_current_gameweek_id integer;
    v_current_stage_id integer;

    v_next_gameweek_id integer;
    v_next_prediction_open timestamptz;

    v_all_finished boolean;
begin

    for r_campaign in
        select distinct campaign_id
        from gameweeks
    loop

        select
            gw.gameweek_id,
            gw.stage_id
        into
            v_current_gameweek_id,
            v_current_stage_id
        from gameweeks gw
        where gw.campaign_id = r_campaign.campaign_id
          and gw.status = 'active'
        limit 1;

        if v_current_gameweek_id is null then
            continue;
        end if;

        select not exists (
            select 1
            from fixtures f
            where f.stage_id = v_current_stage_id
              and f.fixture_status <> 'finished'
        )
        into v_all_finished;

        if not v_all_finished then
            continue;
        end if;

        select
            gw.gameweek_id,
            gw.prediction_open_at
        into
            v_next_gameweek_id,
            v_next_prediction_open
        from gameweeks gw
        where gw.campaign_id = r_campaign.campaign_id
          and gw.status = 'upcoming'
        order by gw.gameweek_number
        limit 1;

        if v_next_gameweek_id is null then

            update gameweeks
            set
                status = 'finished',
                updated_at = now()
            where gameweek_id = v_current_gameweek_id;

        elsif now() >= v_next_prediction_open then

            update gameweeks
            set
                status = 'finished',
                updated_at = now()
            where gameweek_id = v_current_gameweek_id;

            update gameweeks
            set
                status = 'active',
                updated_at = now()
            where gameweek_id = v_next_gameweek_id;

        end if;

    end loop;
end;
$$;


--
-- Name: publish_engine_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.publish_engine_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;

    v_profiles_checked integer:=0;
    v_created integer:=0;
    v_updated integer:=0;
    v_total integer:=0;
begin

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'publish_engine_predictions',
        'cron',
        now(),
        'running'
    )
    returning
        scheduler_run_id,
        started_at
    into
        v_scheduler_run_id,
        v_started_at;

    /*
        All profiles that consume engine predictions.

        A profile can be either:
        - a bot profile
        - a P10 profile
    */
    with engine_profiles as (

        select
            profile_id,
            engine_id
        from bot_profiles

        union all

        select
            profile_id,
            engine_id
        from ml_p10_profiles
    )

    select count(distinct ep.profile_id)
    into v_profiles_checked
    from engine_profiles ep
    join ml_engine_predictions mp
        on mp.engine_id=ep.engine_id
    join fixtures f
        on f.fixture_id=mp.fixture_id
    where
        f.prediction_open_at<=now()
        and f.prediction_close_at>now();

    /*
        Create missing published predictions.
    */
    with engine_profiles as (

        select
            profile_id,
            engine_id
        from bot_profiles

        union all

        select
            profile_id,
            engine_id
        from ml_p10_profiles
    )

    insert into predictions(
        profile_id,
        fixture_id,
        pred_home_goals,
        pred_away_goals
    )
    select
        ep.profile_id,
        mp.fixture_id,
        mp.home_prediction,
        mp.away_prediction
    from engine_profiles ep
    join ml_engine_predictions mp
        on mp.engine_id=ep.engine_id
    join fixtures f
        on f.fixture_id=mp.fixture_id
    where
        f.prediction_open_at<=now()
        and f.prediction_close_at>now()
    on conflict(profile_id,fixture_id)
    do nothing;

    get diagnostics v_created=row_count;

    /*
        Update published predictions if the engine prediction changed.
    */
    with engine_profiles as (

        select
            profile_id,
            engine_id
        from bot_profiles

        union all

        select
            profile_id,
            engine_id
        from ml_p10_profiles
    )

    update predictions p
    set
        pred_home_goals=mp.home_prediction,
        pred_away_goals=mp.away_prediction,
        updated_at=now()
    from engine_profiles ep
    join ml_engine_predictions mp
        on mp.engine_id=ep.engine_id
    join fixtures f
        on f.fixture_id=mp.fixture_id
    where
        p.profile_id=ep.profile_id
        and p.fixture_id=mp.fixture_id
        and f.prediction_open_at<=now()
        and f.prediction_close_at>now()
        and (
            p.pred_home_goals is distinct from mp.home_prediction
            or p.pred_away_goals is distinct from mp.away_prediction
        );

    get diagnostics v_updated=row_count;

    v_total:=v_created+v_updated;

    if v_total>0 then

        insert into ml_prediction_runs(
            scheduler_run_id,
            prediction_group,
            trigger_source,
            started_at,
            completed_at,
            profiles_checked,
            predictions_created,
            predictions_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'engine_publish',
            'cron',
            v_started_at,
            now(),
            v_profiles_checked,
            v_created,
            v_updated,
            'completed'
        );

    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;

    return v_total;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;

        raise;
end;
$$;


--
-- Name: publish_ml_model_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.publish_ml_model_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_scheduler_run_id bigint;
    v_started_at timestamptz;
    v_profiles_checked integer:=0;
    v_created integer:=0;
    v_updated integer:=0;
    v_total integer:=0;
begin

    insert into ml_scheduler_runs(
        job_name,
        trigger_source,
        started_at,
        status
    )
    values(
        'publish_ml_predictions',
        'cron',
        now(),
        'running'
    )
    returning 
        scheduler_run_id,
        started_at
    into 
        v_scheduler_run_id,
        v_started_at;

    select count(distinct profile_id)
    into v_profiles_checked
    from ml_profile_predictions m
    join fixtures f
        on f.fixture_id=m.fixture_id
    where
        f.prediction_open_at<=now()
        and f.prediction_close_at>now()
        and (       
            exists (
                select 1
                from predictions p
                where 
                    p.profile_id=m.profile_id
                    and p.fixture_id=m.fixture_id
                    and (
                        p.pred_home_goals is distinct from m.home_prediction
                        or p.pred_away_goals is distinct from m.away_prediction
                    )
            )
            or not exists (
                select 1
                from predictions p
                where 
                    p.profile_id=m.profile_id
                and p.fixture_id=m.fixture_id
            )
        );

    insert into predictions(
        profile_id,
        fixture_id,
        pred_home_goals,
        pred_away_goals
    )
    select
        m.profile_id,
        m.fixture_id,
        m.home_prediction,
        m.away_prediction
    from ml_profile_predictions m 
    join fixtures f
        on f.fixture_id=m.fixture_id
    where 
        f.prediction_open_at<=now()
        and f.prediction_close_at>now()
    on conflict(profile_id,fixture_id)
    do nothing;

    get diagnostics v_created=row_count;

    update predictions p
    set
        pred_home_goals=m.home_prediction,
        pred_away_goals=m.away_prediction,
        updated_at=now()
    from ml_profile_predictions m
    join fixtures f
        on f.fixture_id=m.fixture_id
    where 
        p.profile_id=m.profile_id
        and p.fixture_id=m.fixture_id
        and f.prediction_open_at<=now()
        and f.prediction_close_at>now()
    and (
        p.pred_home_goals is distinct from m.home_prediction
        or p.pred_away_goals is distinct from m.away_prediction
    );

    get diagnostics v_updated=row_count;

    v_total:=v_created+v_updated;

    if v_total>0 then
        insert into ml_prediction_runs(
            scheduler_run_id,
            prediction_group,
            trigger_source,
            started_at,
            completed_at,
            profiles_checked,
            predictions_created,
            predictions_updated,
            status
        )
        values(
            v_scheduler_run_id,
            'publish',
            'cron',
            v_started_at,
            now(),
            v_profiles_checked,
            v_created,
            v_updated,
            'completed'
        );

    end if;

    update ml_scheduler_runs
    set
        completed_at=now(),
        status='completed'
    where scheduler_run_id=v_scheduler_run_id;

    return v_total;

exception
    when others then

        update ml_scheduler_runs
        set
            completed_at=now(),
            status='failed',
            error_message=sqlerrm
        where scheduler_run_id=v_scheduler_run_id;

end;
$$;


--
-- Name: publish_ml_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.publish_ml_predictions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_total integer:=0;
begin

    v_total:=v_total + public.publish_ml_model_predictions();
    v_total:=v_total + public.publish_engine_predictions();

    return v_total;

end;
$$;


--
-- Name: rebuild_gameweek_prediction_windows(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.rebuild_gameweek_prediction_windows() RETURNS void
    LANGUAGE plpgsql
    AS $$
declare
    r record;
    v_first_kickoff timestamptz;
    v_open_interval interval;
    v_close_interval interval;
begin

    for r in
        select distinct gw.gameweek_id
        from gameweeks gw
    loop

        -- get earliest kickoff for that gameweek
        select min(f.kickoff_at)
        into v_first_kickoff
        from fixtures f
        join gameweeks gw on gw.stage_id = f.stage_id
        where gw.gameweek_id = r.gameweek_id;

        if v_first_kickoff is null then
            update gameweeks
            set prediction_open_at = null,
                prediction_close_at = null,
                updated_at = now()
            where gameweek_id = r.gameweek_id;

            continue;
        end if;

        -- get intervals
        select
            pf.window_open_interval,
            pf.window_close_interval
        into v_open_interval,
             v_close_interval
        from gameweeks gw
        join campaigns c on c.campaign_id = gw.campaign_id
        join flavour_competitions fc on fc.competition_id = c.competition_id
        join flavours fl on fl.flavour_id = fc.flavour_id
        join prediction_formats pf on pf.format_id = fl.format_id
        where gw.gameweek_id = r.gameweek_id
        limit 1;

        -- safety check
        if v_open_interval is null or v_close_interval is null then
            raise warning 'Missing prediction format for gameweek %', r.gameweek_id;
            continue;
        end if;

        update gameweeks
        set prediction_open_at  = v_first_kickoff - v_open_interval,
            prediction_close_at = v_first_kickoff - v_close_interval,
            updated_at = now()
        where gameweek_id = r.gameweek_id;

    end loop;

end;
$$;


--
-- Name: rebuild_leaderboard_for_scope(integer, integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.rebuild_leaderboard_for_scope(p_flavour_id integer, p_campaign_id integer DEFAULT NULL::integer, p_stage_id integer DEFAULT NULL::integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
begin

    -- =========================================
    -- 1. DELETE AFFECTED SCOPES ONLY
    -- =========================================

    delete from leaderboard
    where flavour_id = p_flavour_id
      and scope_type = 'overall';

    if p_campaign_id is not null then
        delete from leaderboard
        where flavour_id = p_flavour_id
          and scope_type = 'campaign'
          and scope_id = p_campaign_id;
    end if;

    if p_stage_id is not null then
        delete from leaderboard
        where flavour_id = p_flavour_id
          and scope_type = 'stage'
          and scope_id = p_stage_id;
    end if;

    -- =========================================
    -- 2. OVERALL
    -- =========================================

    insert into leaderboard (
        flavour_id,
        scope_type,
        scope_id,
        profile_id,
        display_name,
        profile_type,
        predicted,
        correct_result,
        correct_home_goals,
        correct_away_goals,
        correct_goal_difference,
        correct_total_goals,
        perfect_10s,
        points_total,
        updated_at
    )
    select
        p_flavour_id,
        'overall',
        null,
        ps.profile_id,
        pr.display_name,
        pr.profile_type,
        count(*),
        sum(ps.correct_result::int),
        sum(ps.correct_home_goals::int),
        sum(ps.correct_away_goals::int),
        sum(ps.correct_goal_difference::int),
        sum(ps.correct_total_goals::int),
        sum(ps.perfect_10::int),
        sum(ps.points_total),
        now()
    from prediction_scores ps
    join fixtures f on f.fixture_id = ps.fixture_id
    join profiles pr on pr.profile_id = ps.profile_id
    join campaigns c on c.campaign_id = f.campaign_id
    join flavour_competitions fc on fc.competition_id = c.competition_id
    where fc.flavour_id = p_flavour_id
    group by ps.profile_id, pr.display_name, pr.profile_type;

    -- =========================================
    -- 3. CAMPAIGN
    -- =========================================

    if p_campaign_id is not null then

        insert into leaderboard (
            flavour_id,
            scope_type,
            scope_id,
            profile_id,
            display_name,
            profile_type,
            predicted,
            correct_result,
            correct_home_goals,
            correct_away_goals,
            correct_goal_difference,
            correct_total_goals,
            perfect_10s,
            points_total,
            updated_at
        )
        select
            p_flavour_id,
            'campaign',
            p_campaign_id,
            ps.profile_id,
            pr.display_name,
            pr.profile_type,
            count(*),
            sum(ps.correct_result::int),
            sum(ps.correct_home_goals::int),
            sum(ps.correct_away_goals::int),
            sum(ps.correct_goal_difference::int),
            sum(ps.correct_total_goals::int),
            sum(ps.perfect_10::int),
            sum(ps.points_total),
            now()
        from prediction_scores ps
        join fixtures f on f.fixture_id = ps.fixture_id
        join profiles pr on pr.profile_id = ps.profile_id
        where f.campaign_id = p_campaign_id
        group by ps.profile_id, pr.display_name, pr.profile_type;

    end if;

    -- =========================================
    -- 4. STAGE
    -- =========================================

    if p_stage_id is not null then

        insert into leaderboard (
            flavour_id,
            scope_type,
            scope_id,
            profile_id,
            display_name,
            profile_type,
            predicted,
            correct_result,
            correct_home_goals,
            correct_away_goals,
            correct_goal_difference,
            correct_total_goals,
            perfect_10s,
            points_total,
            updated_at
        )
        select
            p_flavour_id,
            'stage',
            p_stage_id,
            ps.profile_id,
            pr.display_name,
            pr.profile_type,
            count(*),
            sum(ps.correct_result::int),
            sum(ps.correct_home_goals::int),
            sum(ps.correct_away_goals::int),
            sum(ps.correct_goal_difference::int),
            sum(ps.correct_total_goals::int),
            sum(ps.perfect_10::int),
            sum(ps.points_total),
            now()
        from prediction_scores ps
        join fixtures f on f.fixture_id = ps.fixture_id
        join profiles pr on pr.profile_id = ps.profile_id
        where f.stage_id = p_stage_id
        group by ps.profile_id, pr.display_name, pr.profile_type;

    end if;

end;
$$;


--
-- Name: rebuild_prediction_scores(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.rebuild_prediction_scores() RETURNS void
    LANGUAGE sql
    AS $$
insert into prediction_scores (
    prediction_id,
    profile_id,
    fixture_id,
    correct_result,
    correct_home_goals,
    correct_away_goals,
    correct_goal_difference,
    correct_total_goals,
    points_total,
    perfect_10,
    created_at,
    updated_at
)
select
    p.prediction_id,
    p.profile_id,
    p.fixture_id,

    (sign(r.ft_home_goals - r.ft_away_goals)
      = sign(p.pred_home_goals - p.pred_away_goals)),

    (p.pred_home_goals = r.ft_home_goals),

    (p.pred_away_goals = r.ft_away_goals),

    ((p.pred_home_goals - p.pred_away_goals)
      = (r.ft_home_goals - r.ft_away_goals)),

    ((p.pred_home_goals + p.pred_away_goals)
      = (r.ft_home_goals + r.ft_away_goals)),

    (
      case when sign(r.ft_home_goals - r.ft_away_goals)
        = sign(p.pred_home_goals - p.pred_away_goals) then 3 else 0 end +
      case when p.pred_home_goals = r.ft_home_goals then 2 else 0 end +
      case when p.pred_away_goals = r.ft_away_goals then 2 else 0 end +
      case when (p.pred_home_goals - p.pred_away_goals)
        = (r.ft_home_goals - r.ft_away_goals) then 2 else 0 end +
      case when (p.pred_home_goals + p.pred_away_goals)
        = (r.ft_home_goals + r.ft_away_goals) then 1 else 0 end
    ),

    (
      (sign(r.ft_home_goals - r.ft_away_goals)
        = sign(p.pred_home_goals - p.pred_away_goals))
      and (p.pred_home_goals = r.ft_home_goals)
      and (p.pred_away_goals = r.ft_away_goals)
    ),

    now(),
    now()

from predictions p
join results r
    on r.fixture_id = p.fixture_id

on conflict (prediction_id)
do update set
    correct_result = excluded.correct_result,
    correct_home_goals = excluded.correct_home_goals,
    correct_away_goals = excluded.correct_away_goals,
    correct_goal_difference = excluded.correct_goal_difference,
    correct_total_goals = excluded.correct_total_goals,
    points_total = excluded.points_total,
    perfect_10 = excluded.perfect_10,
    updated_at = now();
$$;


--
-- Name: reconcile_ties(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.reconcile_ties() RETURNS void
    LANGUAGE plpgsql
    AS $$
declare
  rec record;
  v_tie_id integer;
begin

  for rec in
    select
      f.fixture_id,
      f.stage_id,
      least(f.home_team_id, f.away_team_id) as team_a_id,
      greatest(f.home_team_id, f.away_team_id) as team_b_id
    from fixtures f
    join stages s
      on s.stage_id = f.stage_id
    where s.stage_type = 'knockout'
      and f.home_team_id is not null
      and f.away_team_id is not null
      and f.tie_id is null
  loop

    -- look for existing tie
    select tie_id
    into v_tie_id
    from ties
    where stage_id = rec.stage_id
      and team_a_id = rec.team_a_id
      and team_b_id = rec.team_b_id
    limit 1;

    -- create tie if one doesn't exist
    if v_tie_id is null then

      insert into ties (
        stage_id,
        team_a_id,
        team_b_id
      )
      values (
        rec.stage_id,
        rec.team_a_id,
        rec.team_b_id
      )
      returning tie_id into v_tie_id;

    end if;

    -- link fixture to tie
    update fixtures
    set tie_id = v_tie_id
    where fixture_id = rec.fixture_id;

    v_tie_id := null;

  end loop;

end;
$$;


--
-- Name: refresh_club_predictions(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.refresh_club_predictions(p_club_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_fixture_id integer;
    v_home integer;
    v_away integer;
begin

    /*
        Find club predictions that are still open
        and have not been scored.
    */
    for v_fixture_id in

        select p.fixture_id
        from predictions p
        join fixtures f
            on f.fixture_id = p.fixture_id
        where p.profile_id = p_club_id
        and not exists (
            select 1
            from prediction_scores ps
            where ps.prediction_id = p.prediction_id
        )

    loop


        /*
            Calculate new club average
            from current user members only
        */
        select
            round(avg(p.pred_home_goals))::int,
            round(avg(p.pred_away_goals))::int
        into
            v_home,
            v_away

        from predictions p

        join club_memberships cm
            on cm.profile_id = p.profile_id

        join profiles pr
            on pr.profile_id = p.profile_id

        where cm.club_id = p_club_id
        and p.fixture_id = v_fixture_id
        and pr.profile_type = 'user';


        /*
            No remaining user predictions.
            Remove stale club prediction.
        */
        if v_home is null or v_away is null then

            delete from predictions
            where profile_id = p_club_id
            and fixture_id = v_fixture_id;


        else

            update predictions
            set
                pred_home_goals = v_home,
                pred_away_goals = v_away,
                updated_at = now()

            where profile_id = p_club_id
            and fixture_id = v_fixture_id;

        end if;


    end loop;

end;
$$;


--
-- Name: refresh_club_status(uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.refresh_club_status(p_club_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
begin

    update club_profiles cp
    set is_active = false
    where cp.profile_id = p_club_id
    and cp.is_deleted = true;


    update club_profiles cp
    set is_active = (
        select count(*)
        from club_memberships cm
        where cm.club_id = p_club_id
    ) >= 2
    where cp.profile_id = p_club_id
    and cp.is_deleted = false;

end;
$$;


--
-- Name: refresh_dynamic_features(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.refresh_dynamic_features() RETURNS void
    LANGUAGE plpgsql
    AS $$
begin

    perform public.ml_features_predictions();

end;
$$;


--
-- Name: refresh_fixture_prediction_window(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.refresh_fixture_prediction_window(p_fixture_id integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
declare

    v_gameweek_id integer;
    v_format_id integer;

    v_open_at timestamptz;
    v_close_at timestamptz;

    v_kickoff_at timestamptz;

begin

    select
        f.kickoff_at,
        gw.gameweek_id,
        fl.format_id

    into
        v_kickoff_at,
        v_gameweek_id,
        v_format_id

    from fixtures f

    join campaigns c
        on c.campaign_id = f.campaign_id

    join flavour_competitions fc
        on fc.competition_id = c.competition_id

    join flavours fl
        on fl.flavour_id = fc.flavour_id

    left join stages s
        on s.stage_id = f.stage_id

    left join gameweeks gw
        on gw.stage_id = s.stage_id
       and gw.campaign_id = f.campaign_id

    where f.fixture_id = p_fixture_id;


    if v_kickoff_at is null then
        raise exception
            'Fixture % not found or missing prediction context',
            p_fixture_id;
    end if;


    if v_gameweek_id is not null then

        select
            prediction_open_at,
            prediction_close_at

        into
            v_open_at,
            v_close_at

        from gameweeks

        where gameweek_id = v_gameweek_id;


    else

        select
            v_kickoff_at - pf.window_open_interval,
            v_kickoff_at - pf.window_close_interval

        into
            v_open_at,
            v_close_at

        from prediction_formats pf

        where pf.format_id = v_format_id;

    end if;


    update fixtures

    set
        prediction_open_at = v_open_at,
        prediction_close_at = v_close_at

    where fixture_id = p_fixture_id;


end;
$$;


--
-- Name: refresh_prediction_windows(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.refresh_prediction_windows() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_old_gameweek gameweeks%ROWTYPE;
    v_new_gameweek gameweeks%ROWTYPE;
BEGIN

    -- Nothing relevant has changed.

    IF OLD.stage_id IS NOT DISTINCT FROM NEW.stage_id
       AND OLD.kickoff_at IS NOT DISTINCT FROM NEW.kickoff_at
       AND OLD.fixture_status IS NOT DISTINCT FROM NEW.fixture_status
    THEN
        RETURN NEW;
    END IF;


    -- 1. A fixture has left its original stage.
    -- Refresh the old gameweek only if its deadline
    -- has not passed and it isn't finished.

    IF OLD.stage_id IS DISTINCT FROM NEW.stage_id THEN

        SELECT *
        INTO v_old_gameweek
        FROM public.gameweeks
        WHERE stage_id = OLD.stage_id
          AND campaign_id = OLD.campaign_id;

        IF FOUND
           AND v_old_gameweek.status <> 'finished'
           AND (
               v_old_gameweek.prediction_close_at IS NULL
               OR now() < v_old_gameweek.prediction_close_at
           )
        THEN

            PERFORM public.sync_gameweek_prediction_window(
                OLD.stage_id
            );

        END IF;

    END IF;


    -- 2. Determine whether the destination stage
    -- belongs to a gameweek.

    SELECT *
    INTO v_new_gameweek
    FROM public.gameweeks
    WHERE stage_id = NEW.stage_id
      AND campaign_id = NEW.campaign_id;


    -- 3. Postponed fixtures must not accept predictions.
    -- This applies to both prediction formats.

    IF NEW.fixture_status = 'postponed' THEN

        UPDATE public.fixtures
        SET
            prediction_open_at = NULL,
            prediction_close_at = NULL
        WHERE fixture_id = NEW.fixture_id;

        RETURN NEW;

    END IF;


    -- 4. Destination is a regular gameweek.

    IF FOUND THEN

        IF (
            OLD.stage_id IS DISTINCT FROM NEW.stage_id
            OR OLD.kickoff_at IS DISTINCT FROM NEW.kickoff_at
        )
        AND v_new_gameweek.status <> 'finished'
        AND (
            v_new_gameweek.prediction_close_at IS NULL
            OR now() < v_new_gameweek.prediction_close_at
        )
        THEN

            -- Recalculate the gameweek and copy its
            -- shared window to all associated fixtures.

            PERFORM public.sync_gameweek_prediction_window(
                NEW.stage_id
            );

        ELSIF OLD.stage_id IS DISTINCT FROM NEW.stage_id THEN

            -- The gameweek is closed. Preserve its
            -- historical deadline, but ensure that
            -- this fixture inherits the existing window.

            PERFORM public.refresh_fixture_prediction_window(
                NEW.fixture_id
            );

        END IF;


    -- 5. Destination is a non-gameweek stage.

    ELSIF OLD.stage_id IS DISTINCT FROM NEW.stage_id
       OR OLD.kickoff_at IS DISTINCT FROM NEW.kickoff_at
       OR OLD.fixture_status = 'postponed'
    THEN

        -- Rearranged fixtures and regular PFX fixtures
        -- receive individual prediction windows.

        PERFORM public.refresh_fixture_prediction_window(
            NEW.fixture_id
        );

    END IF;


    RETURN NEW;

END;
$$;


--
-- Name: refresh_static_features(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.refresh_static_features() RETURNS void
    LANGUAGE plpgsql
    AS $$
begin

    perform public.ml_features_campaign();
    perform public.ml_features_last5();
    perform public.ml_features_flavour();
    perform public.ml_features_global();
    perform public.ml_features_venue();

end;
$$;


--
-- Name: remove_club_member(uuid, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.remove_club_member(p_club_id uuid, p_member_profile_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_my_profile_id uuid;
    v_role club_role;
    v_target_role club_role;
begin

    v_my_profile_id := public.get_my_profile_id();


    -- caller must be owner
    select club_role
    into v_role
    from club_memberships
    where club_id = p_club_id
    and profile_id = v_my_profile_id;


    if v_role is null then
        raise exception 'You are not a member of this club';
    end if;


    if v_role <> 'owner' then
        raise exception 'Only the owner can remove members';
    end if;


    -- cannot remove yourself
    if p_member_profile_id = v_my_profile_id then
        raise exception 'Owner cannot remove themselves';
    end if;


    -- check target exists
    select club_role
    into v_target_role
    from club_memberships
    where club_id = p_club_id
    and profile_id = p_member_profile_id;


    if v_target_role is null then
        raise exception 'Member not found';
    end if;


    -- remove member
    delete from club_memberships
    where club_id = p_club_id
    and profile_id = p_member_profile_id;

end;
$$;


--
-- Name: request_club_ownership_transfer(uuid, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.request_club_ownership_transfer(p_club_id uuid, p_target_profile_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_my_profile_id uuid;
    v_role club_role;
    v_target_role club_role;
begin

    v_my_profile_id := public.get_my_profile_id();


    -- Confirm caller is the owner
    select club_role
    into v_role
    from club_memberships
    where club_id = p_club_id
    and profile_id = v_my_profile_id;


    if v_role is null then
        raise exception 'You are not a member of this club';
    end if;


    if v_role <> 'owner' then
        raise exception 'Only the owner can request an ownership transfer';
    end if;


    -- Prevent transferring to yourself
    if p_target_profile_id = v_my_profile_id then
        raise exception 'You are already the owner';
    end if;


    -- Confirm target is a member
    select club_role
    into v_target_role
    from club_memberships
    where club_id = p_club_id
    and profile_id = p_target_profile_id;


    if v_target_role is null then
        raise exception 'New owner must already be a club member';
    end if;


    -- Prevent multiple pending requests
    if exists (
        select 1
        from club_ownership_transfer_requests
        where club_id = p_club_id
        and status = 'pending'
    ) then
        raise exception 'This club already has a pending ownership transfer';
    end if;


    insert into club_ownership_transfer_requests (
        club_id,
        owner_profile_id,
        target_profile_id
    )
    values (
        p_club_id,
        v_my_profile_id,
        p_target_profile_id
    );

end;
$$;


--
-- Name: rls_auto_enable(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.rls_auto_enable() RETURNS event_trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'pg_catalog'
    AS $$
DECLARE
  cmd record;
BEGIN
  FOR cmd IN
    SELECT *
    FROM pg_event_trigger_ddl_commands()
    WHERE command_tag IN ('CREATE TABLE', 'CREATE TABLE AS', 'SELECT INTO')
      AND object_type IN ('table','partitioned table')
  LOOP
     IF cmd.schema_name IS NOT NULL AND cmd.schema_name IN ('public') AND cmd.schema_name NOT IN ('pg_catalog','information_schema') AND cmd.schema_name NOT LIKE 'pg_toast%' AND cmd.schema_name NOT LIKE 'pg_temp%' THEN
      BEGIN
        EXECUTE format('alter table if exists %s enable row level security', cmd.object_identity);
        RAISE LOG 'rls_auto_enable: enabled RLS on %', cmd.object_identity;
      EXCEPTION
        WHEN OTHERS THEN
          RAISE LOG 'rls_auto_enable: failed to enable RLS on %', cmd.object_identity;
      END;
     ELSE
        RAISE LOG 'rls_auto_enable: skip % (either system schema or not in enforced list: %.)', cmd.object_identity, cmd.schema_name;
     END IF;
  END LOOP;
END;
$$;


--
-- Name: seed_profile_engine_history(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.seed_profile_engine_history() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    v_inserted integer := 0;
    v_rows integer;
begin

    insert into public.ml_profile_engine_history (
        profile_id,
        engine_id,
        started_at
    )
    select
        bp.profile_id,
        bp.engine_id,
        bp.created_at
    from public.bot_profiles bp
    where not exists (
        select 1
        from public.ml_profile_engine_history h
        where h.profile_id = bp.profile_id
    );

    get diagnostics v_rows = row_count;
    v_inserted := v_inserted + v_rows;

    insert into public.ml_profile_engine_history (
        profile_id,
        engine_id,
        started_at
    )
    select
        p10.profile_id,
        p10.engine_id,
        p10.created_at
    from public.ml_p10_profiles p10
    where not exists (
        select 1
        from public.ml_profile_engine_history h
        where h.profile_id = p10.profile_id
    );

    get diagnostics v_rows = row_count;
    v_inserted := v_inserted + v_rows;

    return v_inserted;

end;
$$;


--
-- Name: send_club_invite(uuid, text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.send_club_invite(p_club_id uuid, p_username text) RETURNS uuid
    LANGUAGE plpgsql
    AS $$
declare
    v_sender_profile_id uuid;
    v_recipient_profile_id uuid;
    v_invite_id uuid;
begin

    v_sender_profile_id := public.get_my_profile_id();

    if not public.is_club_admin(p_club_id) then
        raise exception 'Only club owners or captains can send invites';
    end if;

    select up.profile_id
    into v_recipient_profile_id
    from user_profiles up
    where lower(up.username) = lower(trim(p_username));

    if v_recipient_profile_id is null then
        raise exception 'User not found';
    end if;

    if v_recipient_profile_id = v_sender_profile_id then
        raise exception 'You cannot invite yourself';
    end if;

    if exists (
        select 1
        from club_memberships
        where club_id = p_club_id
        and profile_id = v_recipient_profile_id
    ) then
        raise exception 'User is already a member of this club';
    end if;

    if exists (
        select 1
        from club_invites
        where club_id = p_club_id
        and recipient_profile_id = v_recipient_profile_id
        and invite_status = 'pending'
    ) then
        raise exception 'User already has a pending invite';
    end if;

    v_invite_id := gen_random_uuid();

    insert into club_invites (
        invite_id,
        club_id,
        sender_profile_id,
        recipient_profile_id,
        invite_status
    )
    values (
        v_invite_id,
        p_club_id,
        v_sender_profile_id,
        v_recipient_profile_id,
        'pending'
    );

    return v_invite_id;

end;
$$;


--
-- Name: set_captured_at(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.set_captured_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
    new.captured_at = now();
    return new;
end;
$$;


--
-- Name: set_updated_at(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.set_updated_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
  new.updated_at = now();
  return new;
end;
$$;


--
-- Name: snapshot_league_position_history(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.snapshot_league_position_history() RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_flavour_id integer;

begin

    for v_flavour_id in

        select distinct
            fc.flavour_id

        from fixtures f

        join campaigns c
            on c.campaign_id = f.campaign_id

        join flavour_competitions fc
            on fc.competition_id = c.competition_id

        join flavours fl
            on fl.flavour_id = fc.flavour_id

        join prediction_formats pf
            on pf.format_id = fl.format_id

        where pf.format_code = 'GWK'
        and f.fixture_status = 'finished'
        and f.kickoff_at::date = current_date

    loop

        insert into league_position_history (
            flavour_id,
            team_id,
            recorded_at,
            league_position,
            points,
            goal_difference,
            matches_played,
            campaign_id
        )

        select

            v_flavour_id,
            lt.team_id,
            now(),
            lt.pos::integer,
            lt.points::integer,
            lt.gd::integer,
            lt.played::integer,
            c.campaign_id

        from get_gameweek_league_table_actual(v_flavour_id) lt

        join campaigns c
            on c.is_active = true

        on conflict (
            flavour_id,
            team_id,
            recorded_at,
            campaign_id
        )
        do nothing;

    end loop;

end;
$$;


--
-- Name: sync_final_from_ft(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_final_from_ft() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin

  -- only allow updates during live_90 state
  if (select fixture_status from fixtures where fixture_id = NEW.fixture_id) <> 'live_90' then
    raise exception 'FT updates only allowed during live_90';
  end if;

  -- sync FT → FINAL
  NEW.final_home_goals := NEW.ft_home_goals;
  NEW.final_away_goals := NEW.ft_away_goals;

  return NEW;
end;
$$;


--
-- Name: sync_fixture_status(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_fixture_status() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN

  UPDATE fixtures
  SET fixture_status = NEW.fixture_status
  WHERE fixture_id = NEW.fixture_id;

  RETURN NEW;
END;
$$;


--
-- Name: sync_gameweek_prediction_window(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_gameweek_prediction_window(p_stage_id integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
declare
    v_gameweek_id integer;
    v_first_kickoff timestamptz;
    v_open_interval interval;
    v_close_interval interval;
    v_open_at timestamptz;
    v_close_at timestamptz;
begin

    select gw.gameweek_id
    into v_gameweek_id
    from gameweeks gw
    where gw.stage_id = p_stage_id;

    if v_gameweek_id is null then
        return;
    end if;

    select min(f.kickoff_at)
    into v_first_kickoff
    from fixtures f
    where f.stage_id = p_stage_id;

    if v_first_kickoff is null then

        update gameweeks
        set
            prediction_open_at = null,
            prediction_close_at = null,
            updated_at = now()
        where gameweek_id = v_gameweek_id;

        update fixtures
        set
            prediction_open_at = null,
            prediction_close_at = null
        where stage_id = p_stage_id;

        return;

    end if;

    select
        pf.window_open_interval,
        pf.window_close_interval
    into
        v_open_interval,
        v_close_interval
    from gameweeks gw
    join campaigns c
        on c.campaign_id = gw.campaign_id
    join flavour_competitions fc
        on fc.competition_id = c.competition_id
    join flavours fl
        on fl.flavour_id = fc.flavour_id
    join prediction_formats pf
        on pf.format_id = fl.format_id
    where gw.gameweek_id = v_gameweek_id
    limit 1;

    v_open_at := v_first_kickoff - v_open_interval;
    v_close_at := v_first_kickoff - v_close_interval;

    update gameweeks
    set
        prediction_open_at = v_open_at,
        prediction_close_at = v_close_at,
        updated_at = now()
    where gameweek_id = v_gameweek_id;

    update fixtures
    set
        prediction_open_at = v_open_at,
        prediction_close_at = v_close_at
    where stage_id = p_stage_id;

end;
$$;


--
-- Name: sync_profile_engine_versions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_profile_engine_versions() RETURNS integer
    LANGUAGE plpgsql
    AS $$
declare
    r record;
    v_latest_engine_id bigint;
    v_updated integer := 0;
begin

    for r in
        select
            bp.profile_id,
            bp.engine_id,
            me.engine_name,
            'bot' as profile_type
        from public.bot_profiles bp
        join public.ml_engines me
            on me.engine_id = bp.engine_id

        union all

        select
            p10.profile_id,
            p10.engine_id,
            me.engine_name,
            'perfect10' as profile_type
        from public.ml_p10_profiles p10
        join public.ml_engines me
            on me.engine_id = p10.engine_id

    loop

        select engine_id
        into v_latest_engine_id
        from public.ml_engines
        where engine_name = r.engine_name
        order by version desc
        limit 1;

        if v_latest_engine_id <> r.engine_id then

            if r.profile_type = 'bot' then

                update public.bot_profiles
                set engine_id = v_latest_engine_id
                where profile_id = r.profile_id;

            elsif r.profile_type = 'perfect10' then

                update public.ml_p10_profiles
                set engine_id = v_latest_engine_id
                where profile_id = r.profile_id;

            end if;

            v_updated := v_updated + 1;

        end if;

    end loop;

    return v_updated;

end;
$$;


--
-- Name: sync_stage_first_last_fixture(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_stage_first_last_fixture() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_stage_id integer;
    v_stage_ids integer[];
BEGIN

    IF TG_OP = 'INSERT' THEN
        v_stage_ids := ARRAY[NEW.stage_id];
    ELSE
        IF OLD.stage_id IS NOT DISTINCT FROM NEW.stage_id
           AND OLD.kickoff_at IS NOT DISTINCT FROM NEW.kickoff_at
        THEN
            RETURN NEW;
        END IF;

        v_stage_ids := ARRAY[OLD.stage_id, NEW.stage_id];
    END IF;


    FOREACH v_stage_id IN ARRAY v_stage_ids
    LOOP

        UPDATE public.stages s
        SET
            first_fixture_id = (
                SELECT f.fixture_id
                FROM public.fixtures f
                WHERE f.stage_id = v_stage_id
                ORDER BY f.kickoff_at ASC, f.fixture_id ASC
                LIMIT 1
            ),

            last_fixture_id = (
                SELECT f.fixture_id
                FROM public.fixtures f
                WHERE f.stage_id = v_stage_id
                ORDER BY f.kickoff_at DESC, f.fixture_id DESC
                LIMIT 1
            )

        WHERE s.stage_id = v_stage_id;

    END LOOP;


    RETURN NEW;

END;
$$;


--
-- Name: sync_stage_from_gameweek(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_stage_from_gameweek() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin

    if new.status = 'active' then

        update stages
        set
            is_active = true,
            is_finished = false
        where stage_id = new.stage_id;

    elsif new.status = 'finished' then

        update stages
        set
            is_active = false,
            is_finished = true
        where stage_id = new.stage_id;

    elsif new.status = 'upcoming' then

        update stages
        set
            is_active = false,
            is_finished = false
        where stage_id = new.stage_id;

    end if;

    return new;
end;
$$;


--
-- Name: sync_stage_is_active(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_stage_is_active() RETURNS void
    LANGUAGE plpgsql
    AS $$
BEGIN

UPDATE stages s
SET is_active =
    CASE
        WHEN s.is_finished THEN false
        ELSE EXISTS (
            SELECT 1
            FROM fixtures f
            JOIN prediction_formats pf
              ON pf.format_id = (
                  SELECT format_id
                  FROM flavours fl
                  JOIN flavour_competitions fc
                    ON fc.flavour_id = fl.flavour_id
                  JOIN competitions c
                    ON c.competition_id = fc.competition_id
                  JOIN campaigns ca
                    ON ca.competition_id = c.competition_id
                  WHERE ca.campaign_id = f.campaign_id
                  LIMIT 1
              )
            WHERE f.stage_id = s.stage_id

              -- prediction window open
              AND now() >= (f.kickoff_at - pf.window_open_interval)

              -- valid fixture lifecycle states
              AND f.fixture_status IN (
                  'upcoming',
                  'live_90',
                  'live_et',
                  'finished'
              )
        )
    END
WHERE NOT EXISTS (
    SELECT 1
    FROM gameweeks gw
    WHERE gw.stage_id = s.stage_id
);

END;
$$;


--
-- Name: sync_stage_is_finished_single(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_stage_is_finished_single() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_stage_id integer;
BEGIN

    FOR v_stage_id IN
        SELECT DISTINCT stage_id
        FROM (
            VALUES (OLD.stage_id), (NEW.stage_id)
        ) AS affected(stage_id)
    LOOP

        UPDATE public.stages s
        SET is_finished = (
            EXISTS (
                SELECT 1
                FROM public.fixtures f
                WHERE f.stage_id = v_stage_id
            )

            AND NOT EXISTS (
                SELECT 1
                FROM public.fixtures f
                WHERE f.stage_id = v_stage_id
                  AND f.fixture_status <> 'finished'
            )
        )

        WHERE s.stage_id = v_stage_id;

    END LOOP;


    RETURN NEW;

END;
$$;


--
-- Name: sync_stage_is_live(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_stage_is_live() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_stage_id integer;
BEGIN

    FOR v_stage_id IN
        SELECT DISTINCT stage_id
        FROM (
            VALUES (OLD.stage_id), (NEW.stage_id)
        ) AS affected(stage_id)
        WHERE stage_id IS NOT NULL
    LOOP

        UPDATE public.stages s
        SET is_live = (
            -- At least one fixture has started
            EXISTS (
                SELECT 1
                FROM public.fixtures f
                WHERE f.stage_id = v_stage_id
                  AND f.fixture_status IN (
                      'live_90',
                      'live_et',
                      'finished'
                  )
            )

            AND

            -- At least one fixture is still outstanding
            EXISTS (
                SELECT 1
                FROM public.fixtures f
                WHERE f.stage_id = v_stage_id
                  AND f.fixture_status IN (
                      'upcoming',
                      'live_90',
                      'live_et'
                  )
            )
        )
        WHERE s.stage_id = v_stage_id;

    END LOOP;

    RETURN NEW;

END;
$$;


--
-- Name: sync_user_display_name(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.sync_user_display_name() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
begin

    raise log 'SYNC USER DISPLAY NAME TRIGGER FIRED: profile_id=%, old=%, new=%',
        new.profile_id,
        old.display_name,
        new.display_name;

    update public.profiles
    set display_name = new.display_name
    where profile_id = new.profile_id;

    return new;

end;
$$;


--
-- Name: test_create_profile(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.test_create_profile() RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    AS $$
declare
    v_id uuid := gen_random_uuid();
    v_username text;
begin

    v_username := 'user_' || substr(replace(v_id::text, '-', ''),1,8);

    raise notice 'profile % username %', v_id, v_username;

    insert into public.profiles(
        profile_id,
        profile_type,
        display_name
    )
    values(
        v_id,
        'user',
        v_username
    );

    raise notice 'profiles inserted';

    insert into public.user_profiles(
        auth_id,
        username,
        display_name,
        profile_id
    )
    values(
        v_id,
        v_username,
        v_username,
        v_id
    );

    raise notice 'user_profiles inserted';

end;
$$;


--
-- Name: track_profile_engine_change(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.track_profile_engine_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
    if tg_op = 'INSERT' then

        insert into public.ml_profile_engine_history (
            profile_id,
            engine_id
        )
        values (
            new.profile_id,
            new.engine_id
        );

    elsif tg_op = 'UPDATE' then

        if old.engine_id is distinct from new.engine_id then

            update public.ml_profile_engine_history
            set ended_at = now()
            where profile_id = new.profile_id
            and ended_at is null;

            insert into public.ml_profile_engine_history (
                profile_id,
                engine_id
            )
            values (
                new.profile_id,
                new.engine_id
            );

        end if;

    end if;

    return new;
end;
$$;


--
-- Name: transfer_club_ownership(uuid, uuid); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.transfer_club_ownership(p_club_id uuid, p_new_owner_profile_id uuid) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_my_profile_id uuid;
    v_current_role club_role;
    v_target_role club_role;
begin

    v_my_profile_id := public.get_my_profile_id();


    -- confirm caller is owner
    select club_role
    into v_current_role
    from club_memberships
    where club_id = p_club_id
    and profile_id = v_my_profile_id;


    if v_current_role is null then
        raise exception 'You are not a member of this club';
    end if;


    if v_current_role <> 'owner' then
        raise exception 'Only the owner can transfer ownership';
    end if;


    -- prevent transferring to yourself
    if p_new_owner_profile_id = v_my_profile_id then
        raise exception 'You are already the owner';
    end if;


    -- confirm new owner is a member
    select club_role
    into v_target_role
    from club_memberships
    where club_id = p_club_id
    and profile_id = p_new_owner_profile_id;


    if v_target_role is null then
        raise exception 'New owner must already be a club member';
    end if;


    -- remove ownership first
    update club_memberships
    set club_role = 'captain'
    where club_id = p_club_id
    and profile_id = v_my_profile_id;


    -- assign ownership
    update club_memberships
    set club_role = 'owner'
    where club_id = p_club_id
    and profile_id = p_new_owner_profile_id;


end;
$$;


--
-- Name: update_club_active_status(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_club_active_status() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_club_id uuid;
begin

    if TG_OP = 'DELETE' then
        v_club_id := OLD.club_id;
    else
        v_club_id := NEW.club_id;
    end if;


    perform public.refresh_club_status(v_club_id);


    
    perform public.refresh_club_predictions(v_club_id);


    return null;

end;
$$;


--
-- Name: update_club_predictions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_club_predictions() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    AS $$
declare
    v_club uuid;
    v_home int;
    v_away int;
begin

    -- only user predictions drive club predictions
    if (
        select profile_type
        from profiles
        where profile_id = NEW.profile_id
    ) <> 'user' then
        return NEW;
    end if;

    -- every active club the user belongs to
    for v_club in

        select cm.club_id
        from club_memberships cm
        join club_profiles cp
            on cp.profile_id = cm.club_id
        where cm.profile_id = NEW.profile_id
          and cp.is_active = true

    loop

        -- average predictions from users in that club
        select
            round(avg(p.pred_home_goals))::int,
            round(avg(p.pred_away_goals))::int
        into v_home, v_away
        from predictions p
        join club_memberships cm
            on cm.profile_id = p.profile_id
        join profiles pr
            on pr.profile_id = p.profile_id
        where cm.club_id = v_club
          and p.fixture_id = NEW.fixture_id
          and pr.profile_type = 'user';

        -- upsert club prediction
        insert into predictions (
            profile_id,
            fixture_id,
            pred_home_goals,
            pred_away_goals
        )
        values (
            v_club,
            NEW.fixture_id,
            coalesce(v_home, 0),
            coalesce(v_away, 0)
        )
        on conflict (profile_id, fixture_id)
        do update set
            pred_home_goals = excluded.pred_home_goals,
            pred_away_goals = excluded.pred_away_goals,
            updated_at = now();

    end loop;

    return NEW;
end;
$$;


--
-- Name: update_club_predictions_from_membership(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_club_predictions_from_membership() RETURNS trigger
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_profile_id uuid;
    v_fixture_id integer;
    v_club uuid;
    v_home int;
    v_away int;
begin

    v_profile_id := coalesce(NEW.profile_id, OLD.profile_id);

    /*
     * Only user profiles can contribute predictions.
     */
    if (
        select profile_type
        from profiles
        where profile_id = v_profile_id
    ) <> 'user' then
        return coalesce(NEW, OLD);
    end if;


    /*
     * Only recalculate fixtures whose prediction window
     * is currently open.
     */
    for v_fixture_id in

        select p.fixture_id
        from predictions p
        join fixtures f
            on f.fixture_id = p.fixture_id
        where p.profile_id = v_profile_id
          and now() >= f.prediction_open_at
          and now() < f.prediction_close_at

    loop

        /*
         * On INSERT:
         *   recalculate every active club the member belongs to.
         *
         * On DELETE:
         *   the membership has already been removed, so explicitly
         *   recalculate the club the member has just left.
         */
        for v_club in

            select cm.club_id
            from club_memberships cm
            join club_profiles cp
                on cp.profile_id = cm.club_id
            where cm.profile_id = v_profile_id
              and cp.is_active = true

            union

            select OLD.club_id
            where TG_OP = 'DELETE'

        loop

            /*
             * Only maintain predictions for active clubs.
             */
            if exists (
                select 1
                from club_profiles cp
                where cp.profile_id = v_club
                  and cp.is_active = true
            ) then

                /*
                 * Recalculate the club prediction from the
                 * current user predictions of its members.
                 */
                select
                    round(avg(p.pred_home_goals))::int,
                    round(avg(p.pred_away_goals))::int
                into v_home, v_away
                from predictions p
                join club_memberships cm
                    on cm.profile_id = p.profile_id
                join profiles pr
                    on pr.profile_id = p.profile_id
                where cm.club_id = v_club
                  and p.fixture_id = v_fixture_id
                  and pr.profile_type = 'user';

                /*
                 * Only update the club prediction when at least
                 * one remaining member has predicted this fixture.
                 */
                if v_home is not null or v_away is not null then

                    insert into predictions (
                        profile_id,
                        fixture_id,
                        pred_home_goals,
                        pred_away_goals
                    )
                    values (
                        v_club,
                        v_fixture_id,
                        coalesce(v_home, 0),
                        coalesce(v_away, 0)
                    )
                    on conflict (profile_id, fixture_id)
                    do update set
                        pred_home_goals = excluded.pred_home_goals,
                        pred_away_goals = excluded.pred_away_goals,
                        updated_at = now();

                end if;

            end if;

        end loop;

    end loop;

    return coalesce(NEW, OLD);

end;
$$;


--
-- Name: update_ml_engine_prediction_scores(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_ml_engine_prediction_scores() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin

    insert into public.ml_engine_prediction_scoring(
        engine_prediction_id,
        engine_id,
        fixture_id,

        correct_result,
        correct_home_goals,
        correct_away_goals,
        correct_goal_difference,
        correct_total_goals,

        points_total,
        perfect_10,

        home_error,
        away_error,
        goal_difference_error,
        total_goals_error,
        score_distance,

        scored_at
    )

    select
        ep.engine_prediction_id,
        ep.engine_id,
        ep.fixture_id,

        sign(r.ft_home_goals-r.ft_away_goals)
            = sign(ep.home_prediction-ep.away_prediction),

        ep.home_prediction=r.ft_home_goals,

        ep.away_prediction=r.ft_away_goals,

        (ep.home_prediction-ep.away_prediction)
            = (r.ft_home_goals-r.ft_away_goals),

        (ep.home_prediction+ep.away_prediction)
            = (r.ft_home_goals+r.ft_away_goals),

        (
            case when sign(r.ft_home_goals-r.ft_away_goals)
                = sign(ep.home_prediction-ep.away_prediction)
                then 3 else 0 end
            +
            case when ep.home_prediction=r.ft_home_goals
                then 2 else 0 end
            +
            case when ep.away_prediction=r.ft_away_goals
                then 2 else 0 end
            +
            case when (ep.home_prediction-ep.away_prediction)
                = (r.ft_home_goals-r.ft_away_goals)
                then 2 else 0 end
            +
            case when (ep.home_prediction+ep.away_prediction)
                = (r.ft_home_goals+r.ft_away_goals)
                then 1 else 0 end
        ),

        (
            ep.home_prediction=r.ft_home_goals
            and ep.away_prediction=r.ft_away_goals
            and sign(r.ft_home_goals-r.ft_away_goals)
                = sign(ep.home_prediction-ep.away_prediction)
            and (ep.home_prediction-ep.away_prediction)
                = (r.ft_home_goals-r.ft_away_goals)
            and (ep.home_prediction+ep.away_prediction)
                = (r.ft_home_goals+r.ft_away_goals)
        ),

        ep.home_prediction-r.ft_home_goals,

        ep.away_prediction-r.ft_away_goals,

        (ep.home_prediction-ep.away_prediction)
            -(r.ft_home_goals-r.ft_away_goals),

        (ep.home_prediction+ep.away_prediction)
            -(r.ft_home_goals+r.ft_away_goals),

        abs(ep.home_prediction-r.ft_home_goals)
            + abs(ep.away_prediction-r.ft_away_goals),

        now()

    from ml_engine_predictions ep
    join results r
        on r.fixture_id=ep.fixture_id
    where ep.fixture_id=new.fixture_id

    on conflict(engine_prediction_id)
    do update set

        correct_result=excluded.correct_result,
        correct_home_goals=excluded.correct_home_goals,
        correct_away_goals=excluded.correct_away_goals,
        correct_goal_difference=excluded.correct_goal_difference,
        correct_total_goals=excluded.correct_total_goals,

        points_total=excluded.points_total,
        perfect_10=excluded.perfect_10,

        home_error=excluded.home_error,
        away_error=excluded.away_error,
        goal_difference_error=excluded.goal_difference_error,
        total_goals_error=excluded.total_goals_error,
        score_distance=excluded.score_distance,

        scored_at=now();

    return new;

end;
$$;


--
-- Name: update_ml_team_matchup_history(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_ml_team_matchup_history() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare
    v_team_a_id integer;
    v_team_b_id integer;
begin

    select
        least(f.home_team_id, f.away_team_id),
        greatest(f.home_team_id, f.away_team_id)
    into
        v_team_a_id,
        v_team_b_id
    from fixtures f
    where f.fixture_id = new.fixture_id;


    insert into ml_team_matchup_history (
        team_a_id,
        team_b_id
    )
    values (
        v_team_a_id,
        v_team_b_id
    )
    on conflict (team_a_id, team_b_id)
    do nothing;


    update ml_team_matchup_history m

    set
        matches_played = stats.matches_played,
        league_matches = stats.league_matches,
        knockout_matches = stats.knockout_matches,

        team_a_ft_goals_for = stats.team_a_ft_goals_for,
        team_a_ft_goals_against = stats.team_a_ft_goals_against,

        team_b_ft_goals_for = stats.team_b_ft_goals_for,
        team_b_ft_goals_against = stats.team_b_ft_goals_against,

        team_a_final_goals_for = stats.team_a_final_goals_for,
        team_a_final_goals_against = stats.team_a_final_goals_against,

        team_b_final_goals_for = stats.team_b_final_goals_for,
        team_b_final_goals_against = stats.team_b_final_goals_against,

        team_a_wins = stats.team_a_wins,
        team_a_draws = stats.team_a_draws,
        team_a_losses = stats.team_a_losses,

        team_b_wins = stats.team_b_wins,
        team_b_draws = stats.team_b_draws,
        team_b_losses = stats.team_b_losses,

        team_a_et_wins = stats.team_a_et_wins,
        team_b_et_wins = stats.team_b_et_wins,

        team_a_penalty_wins = stats.team_a_penalty_wins,
        team_b_penalty_wins = stats.team_b_penalty_wins,

        team_a_home_matches = stats.team_a_home_matches,
        team_a_home_gf_avg = stats.team_a_home_gf_avg,
        team_a_home_ga_avg = stats.team_a_home_ga_avg,
        team_a_home_wins = stats.team_a_home_wins,
        team_a_home_draws = stats.team_a_home_draws,
        team_a_home_losses = stats.team_a_home_losses,

        team_a_away_matches = stats.team_a_away_matches,
        team_a_away_gf_avg = stats.team_a_away_gf_avg,
        team_a_away_ga_avg = stats.team_a_away_ga_avg,
        team_a_away_wins = stats.team_a_away_wins,
        team_a_away_draws = stats.team_a_away_draws,
        team_a_away_losses = stats.team_a_away_losses,

        team_b_home_matches = stats.team_b_home_matches,
        team_b_home_gf_avg = stats.team_b_home_gf_avg,
        team_b_home_ga_avg = stats.team_b_home_ga_avg,
        team_b_home_wins = stats.team_b_home_wins,
        team_b_home_draws = stats.team_b_home_draws,
        team_b_home_losses = stats.team_b_home_losses,

        team_b_away_matches = stats.team_b_away_matches,
        team_b_away_gf_avg = stats.team_b_away_gf_avg,
        team_b_away_ga_avg = stats.team_b_away_ga_avg,
        team_b_away_wins = stats.team_b_away_wins,
        team_b_away_draws = stats.team_b_away_draws,
        team_b_away_losses = stats.team_b_away_losses,

        last_updated_at = now()

    from (

        select

            count(*)::integer as matches_played,

            count(*) filter (
                where s.stage_type = 'league'
            )::integer as league_matches,

            count(*) filter (
                where s.stage_type = 'knockout'
            )::integer as knockout_matches,


            coalesce(
                sum(
                    case
                        when f.home_team_id = v_team_a_id
                        then r.ft_home_goals
                        else r.ft_away_goals
                    end
                ),
                0
            )::integer as team_a_ft_goals_for,

            coalesce(
                sum(
                    case
                        when f.home_team_id = v_team_a_id
                        then r.ft_away_goals
                        else r.ft_home_goals
                    end
                ),
                0
            )::integer as team_a_ft_goals_against,


            coalesce(
                sum(
                    case
                        when f.home_team_id = v_team_b_id
                        then r.ft_home_goals
                        else r.ft_away_goals
                    end
                ),
                0
            )::integer as team_b_ft_goals_for,

            coalesce(
                sum(
                    case
                        when f.home_team_id = v_team_b_id
                        then r.ft_away_goals
                        else r.ft_home_goals
                    end
                ),
                0
            )::integer as team_b_ft_goals_against,


            coalesce(
                sum(
                    case
                        when f.home_team_id = v_team_a_id
                        then r.final_home_goals
                        else r.final_away_goals
                    end
                ),
                0
            )::integer as team_a_final_goals_for,

            coalesce(
                sum(
                    case
                        when f.home_team_id = v_team_a_id
                        then r.final_away_goals
                        else r.final_home_goals
                    end
                ),
                0
            )::integer as team_a_final_goals_against,


            coalesce(
                sum(
                    case
                        when f.home_team_id = v_team_b_id
                        then r.final_home_goals
                        else r.final_away_goals
                    end
                ),
                0
            )::integer as team_b_final_goals_for,

            coalesce(
                sum(
                    case
                        when f.home_team_id = v_team_b_id
                        then r.final_away_goals
                        else r.final_home_goals
                    end
                ),
                0
            )::integer as team_b_final_goals_against,


            count(*) filter (
                where
                    (
                        f.home_team_id = v_team_a_id
                        and r.result_type = 'home_win'
                    )
                    or
                    (
                        f.away_team_id = v_team_a_id
                        and r.result_type = 'away_win'
                    )
            )::integer as team_a_wins,

            count(*) filter (
                where r.result_type = 'draw'
            )::integer as team_a_draws,

            count(*) filter (
                where
                    (
                        f.home_team_id = v_team_a_id
                        and r.result_type = 'away_win'
                    )
                    or
                    (
                        f.away_team_id = v_team_a_id
                        and r.result_type = 'home_win'
                    )
            )::integer as team_a_losses,


            count(*) filter (
                where
                    (
                        f.home_team_id = v_team_b_id
                        and r.result_type = 'home_win'
                    )
                    or
                    (
                        f.away_team_id = v_team_b_id
                        and r.result_type = 'away_win'
                    )
            )::integer as team_b_wins,

            count(*) filter (
                where r.result_type = 'draw'
            )::integer as team_b_draws,

            count(*) filter (
                where
                    (
                        f.home_team_id = v_team_b_id
                        and r.result_type = 'away_win'
                    )
                    or
                    (
                        f.away_team_id = v_team_b_id
                        and r.result_type = 'home_win'
                    )
            )::integer as team_b_losses,


            count(*) filter (
                where
                    t.resolution_type = 'extra_time'
                    and t.winner_team_id = v_team_a_id
            )::integer as team_a_et_wins,

            count(*) filter (
                where
                    t.resolution_type = 'extra_time'
                    and t.winner_team_id = v_team_b_id
            )::integer as team_b_et_wins,


            count(*) filter (
                where
                    t.resolution_type = 'penalties'
                    and t.winner_team_id = v_team_a_id
            )::integer as team_a_penalty_wins,

            count(*) filter (
                where
                    t.resolution_type = 'penalties'
                    and t.winner_team_id = v_team_b_id
            )::integer as team_b_penalty_wins,


            count(*) filter (
                where f.home_team_id = v_team_a_id
            )::integer as team_a_home_matches,

            avg(
                case
                    when f.home_team_id = v_team_a_id
                    then r.final_home_goals
                end
            ) as team_a_home_gf_avg,

            avg(
                case
                    when f.home_team_id = v_team_a_id
                    then r.final_away_goals
                end
            ) as team_a_home_ga_avg,

            count(*) filter (
                where
                    f.home_team_id = v_team_a_id
                    and r.result_type = 'home_win'
            )::integer as team_a_home_wins,

            count(*) filter (
                where
                    f.home_team_id = v_team_a_id
                    and r.result_type = 'draw'
            )::integer as team_a_home_draws,

            count(*) filter (
                where
                    f.home_team_id = v_team_a_id
                    and r.result_type = 'away_win'
            )::integer as team_a_home_losses,


            count(*) filter (
                where f.away_team_id = v_team_a_id
            )::integer as team_a_away_matches,

            avg(
                case
                    when f.away_team_id = v_team_a_id
                    then r.final_away_goals
                end
            ) as team_a_away_gf_avg,

            avg(
                case
                    when f.away_team_id = v_team_a_id
                    then r.final_home_goals
                end
            ) as team_a_away_ga_avg,

            count(*) filter (
                where
                    f.away_team_id = v_team_a_id
                    and r.result_type = 'away_win'
            )::integer as team_a_away_wins,

            count(*) filter (
                where
                    f.away_team_id = v_team_a_id
                    and r.result_type = 'draw'
            )::integer as team_a_away_draws,

            count(*) filter (
                where
                    f.away_team_id = v_team_a_id
                    and r.result_type = 'home_win'
            )::integer as team_a_away_losses,


            count(*) filter (
                where f.home_team_id = v_team_b_id
            )::integer as team_b_home_matches,

            avg(
                case
                    when f.home_team_id = v_team_b_id
                    then r.final_home_goals
                end
            ) as team_b_home_gf_avg,

            avg(
                case
                    when f.home_team_id = v_team_b_id
                    then r.final_away_goals
                end
            ) as team_b_home_ga_avg,

            count(*) filter (
                where
                    f.home_team_id = v_team_b_id
                    and r.result_type = 'home_win'
            )::integer as team_b_home_wins,

            count(*) filter (
                where
                    f.home_team_id = v_team_b_id
                    and r.result_type = 'draw'
            )::integer as team_b_home_draws,

            count(*) filter (
                where
                    f.home_team_id = v_team_b_id
                    and r.result_type = 'away_win'
            )::integer as team_b_home_losses,


            count(*) filter (
                where f.away_team_id = v_team_b_id
            )::integer as team_b_away_matches,

            avg(
                case
                    when f.away_team_id = v_team_b_id
                    then r.final_away_goals
                end
            ) as team_b_away_gf_avg,

            avg(
                case
                    when f.away_team_id = v_team_b_id
                    then r.final_home_goals
                end
            ) as team_b_away_ga_avg,

            count(*) filter (
                where
                    f.away_team_id = v_team_b_id
                    and r.result_type = 'away_win'
            )::integer as team_b_away_wins,

            count(*) filter (
                where
                    f.away_team_id = v_team_b_id
                    and r.result_type = 'draw'
            )::integer as team_b_away_draws,

            count(*) filter (
                where
                    f.away_team_id = v_team_b_id
                    and r.result_type = 'home_win'
            )::integer as team_b_away_losses


        from fixtures f

        join results r
            on r.fixture_id = f.fixture_id

        join stages s
            on s.stage_id = f.stage_id

        left join ties t
            on t.tie_id = f.tie_id

        where
            r.result_type is not null
            and (
                (
                    f.home_team_id = v_team_a_id
                    and f.away_team_id = v_team_b_id
                )
                or
                (
                    f.home_team_id = v_team_b_id
                    and f.away_team_id = v_team_a_id
                )
            )

    ) stats

    where
        m.team_a_id = v_team_a_id
        and m.team_b_id = v_team_b_id;


    return new;

end;
$$;


--
-- Name: update_prediction_scores(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_prediction_scores() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin

  insert into public.prediction_scores (
    prediction_id,
    profile_id,
    fixture_id,

    correct_result,
    correct_home_goals,
    correct_away_goals,
    correct_goal_difference,
    correct_total_goals,

    points_total,
    perfect_10,
    created_at,
    updated_at
  )

  select
    p.prediction_id,
    p.profile_id,
    p.fixture_id,

    (sign(r.ft_home_goals - r.ft_away_goals)
      = sign(p.pred_home_goals - p.pred_away_goals)) as correct_result,

    (p.pred_home_goals = r.ft_home_goals) as correct_home_goals,

    (p.pred_away_goals = r.ft_away_goals) as correct_away_goals,

    ((p.pred_home_goals - p.pred_away_goals)
      = (r.ft_home_goals - r.ft_away_goals)) as correct_goal_difference,

    ((p.pred_home_goals + p.pred_away_goals)
      = (r.ft_home_goals + r.ft_away_goals)) as correct_total_goals,

    (
      case when sign(r.ft_home_goals - r.ft_away_goals)
        = sign(p.pred_home_goals - p.pred_away_goals) then 3 else 0 end +
      case when p.pred_home_goals = r.ft_home_goals then 2 else 0 end +
      case when p.pred_away_goals = r.ft_away_goals then 2 else 0 end +
      case when (p.pred_home_goals - p.pred_away_goals)
        = (r.ft_home_goals - r.ft_away_goals) then 2 else 0 end +
      case when (p.pred_home_goals + p.pred_away_goals)
        = (r.ft_home_goals + r.ft_away_goals) then 1 else 0 end
    ) as points_total,

    (
      (sign(r.ft_home_goals - r.ft_away_goals)
        = sign(p.pred_home_goals - p.pred_away_goals))
      and (p.pred_home_goals = r.ft_home_goals)
      and (p.pred_away_goals = r.ft_away_goals)
      and ((p.pred_home_goals - p.pred_away_goals)
        = (r.ft_home_goals - r.ft_away_goals))
      and ((p.pred_home_goals + p.pred_away_goals)
        = (r.ft_home_goals + r.ft_away_goals))
    ) as perfect_10,

    now(),
    now()

  from public.predictions p
  join public.results r
    on r.fixture_id = p.fixture_id
  where p.fixture_id = new.fixture_id

  on conflict (prediction_id)
  do update set
    correct_result = excluded.correct_result,
    correct_home_goals = excluded.correct_home_goals,
    correct_away_goals = excluded.correct_away_goals,
    correct_goal_difference = excluded.correct_goal_difference,
    correct_total_goals = excluded.correct_total_goals,
    points_total = excluded.points_total,
    perfect_10 = excluded.perfect_10,
    updated_at = now();

  return new;
end;
$$;


--
-- Name: update_user_display_name(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_user_display_name(p_display_name text) RETURNS void
    LANGUAGE plpgsql
    AS $$
begin

    if length(trim(p_display_name)) = 0 then
        raise exception 'Display name cannot be empty';
    end if;

    if length(p_display_name) > 16 then
        raise exception 'Display name too long';
    end if;


    update public.user_profiles
    set display_name = p_display_name
    where profile_id = public.get_my_profile_id();

end;
$$;


--
-- Name: upsert_predictions(jsonb); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.upsert_predictions(predictions jsonb) RETURNS void
    LANGUAGE plpgsql SECURITY DEFINER
    AS $$
declare
    v_profile_id uuid;
begin

    v_profile_id := public.get_my_profile_id();

    if v_profile_id is null then
        raise exception 'Profile not found for authenticated user';
    end if;

    insert into public.predictions (
        profile_id,
        fixture_id,
        pred_home_goals,
        pred_away_goals
    )
    select
        v_profile_id,
        (p->>'fixture_id')::integer,
        (p->>'pred_home_goals')::smallint,
        (p->>'pred_away_goals')::smallint
    from jsonb_array_elements(predictions) p

    on conflict (profile_id, fixture_id)
    do update
    set
        pred_home_goals = excluded.pred_home_goals,
        pred_away_goals = excluded.pred_away_goals;

end;
$$;


--
-- Name: validate_engine_components(bigint); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.validate_engine_components(p_engine_id bigint) RETURNS boolean
    LANGUAGE plpgsql
    AS $$
declare
    v_component_count integer;
    v_modifier_count integer;
    v_first_type text;
    v_last_type text;
begin

    select count(*)
    into v_component_count
    from ml_engine_components
    where engine_id=p_engine_id;

    if v_component_count=0 then
        raise exception
        'Engine % has no components',
        p_engine_id;
    end if;


    select c.component_type
    into v_first_type
    from ml_engine_components ec
    join ml_components c
        on c.component_id=ec.component_id
    where ec.engine_id=p_engine_id
    order by ec.execution_order asc
    limit 1;

    if v_first_type not in ('model','engine') then
        raise exception
        'Engine % must begin with model or engine component, found %',
        p_engine_id,
        v_first_type;
    end if;


    select count(*)
    into v_modifier_count
    from ml_engine_components ec
    join ml_components c
        on c.component_id=ec.component_id
    where ec.engine_id=p_engine_id
    and c.component_type='modifier';

    if v_modifier_count>1 then
        raise exception
        'Engine % has multiple modifiers. Only one modifier is allowed',
        p_engine_id;
    end if;


    select c.component_type
    into v_last_type
    from ml_engine_components ec
    join ml_components c
        on c.component_id=ec.component_id
    where ec.engine_id=p_engine_id
    order by ec.execution_order desc
    limit 1;


    if v_modifier_count=1
    and v_last_type<>'modifier' then
        raise exception
        'Engine % modifier must be the final component',
        p_engine_id;
    end if;


    return true;

end;
$$;


--
-- Name: validate_signup_code(text); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.validate_signup_code(p_code text) RETURNS boolean
    LANGUAGE plpgsql SECURITY DEFINER
    SET search_path TO 'public'
    AS $$
declare
    v_valid boolean;
begin
    select true
    into v_valid
    from public.signup_codes
    where code = p_code
      and active = true
      and (
          expires_at is null
          or expires_at > now()
      )
      and (
          max_uses is null
          or use_count < max_uses
      );

    return coalesce(v_valid,false);
end;
$$;


--
-- Name: validate_tie_winner(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.validate_tie_winner() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare
  v_valid boolean;
begin

  -- allow null (no winner yet)
  if NEW.winner_team_id is null then
    return NEW;
  end if;

  select exists (
    select 1
    from fixtures f
    where f.tie_id = NEW.tie_id
      and (f.home_team_id = NEW.winner_team_id
        or f.away_team_id = NEW.winner_team_id)
  )
  into v_valid;

  if not v_valid then
    raise exception 'winner_team_id must match a team in the fixtures for this tie';
  end if;

  return NEW;
end;
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: bot_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bot_profiles (
    profile_id uuid NOT NULL,
    bot_name text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    engine_id bigint NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: campaigns; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.campaigns (
    campaign_id integer NOT NULL,
    competition_id integer NOT NULL,
    label text NOT NULL,
    start_date timestamp with time zone,
    end_date timestamp with time zone,
    is_active boolean DEFAULT false NOT NULL,
    code text,
    postponed_placeholder_at timestamp with time zone
);


--
-- Name: fixtures; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fixtures (
    fixture_id integer NOT NULL,
    campaign_id integer NOT NULL,
    stage_id integer NOT NULL,
    venue_id integer NOT NULL,
    home_team_id integer,
    away_team_id integer,
    kickoff_at timestamp with time zone NOT NULL,
    fixture_status public.fixture_status NOT NULL,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    group_letter text,
    tie_id integer,
    leg_number integer,
    prediction_open_at timestamp with time zone,
    prediction_close_at timestamp with time zone,
    CONSTRAINT fixtures_group_letter_check CHECK ((length(group_letter) = 1)),
    CONSTRAINT fixtures_leg_number_check CHECK (((leg_number IS NULL) OR (leg_number = ANY (ARRAY[1, 2]))))
);


--
-- Name: prediction_scores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.prediction_scores (
    prediction_id bigint NOT NULL,
    profile_id uuid NOT NULL,
    fixture_id integer NOT NULL,
    correct_result boolean NOT NULL,
    correct_home_goals boolean NOT NULL,
    correct_away_goals boolean NOT NULL,
    correct_goal_difference boolean NOT NULL,
    correct_total_goals boolean NOT NULL,
    points_total smallint NOT NULL,
    perfect_10 boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.profiles (
    profile_id uuid NOT NULL,
    profile_type public.profile_type NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    display_name text NOT NULL,
    CONSTRAINT profiles_display_name_check CHECK ((length(display_name) <= 16))
);


--
-- Name: leaderboard_base; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.leaderboard_base AS
 SELECT c.competition_id,
    f.campaign_id,
    f.stage_id,
    ps.fixture_id,
    ps.profile_id,
    p.profile_type,
    p.display_name,
    ps.correct_result,
    ps.correct_home_goals,
    ps.correct_away_goals,
    ps.correct_goal_difference,
    ps.correct_total_goals,
    ps.points_total,
    ps.perfect_10
   FROM (((public.prediction_scores ps
     JOIN public.profiles p ON ((p.profile_id = ps.profile_id)))
     JOIN public.fixtures f ON ((f.fixture_id = ps.fixture_id)))
     JOIN public.campaigns c ON ((c.campaign_id = f.campaign_id)));


--
-- Name: campaign_bot_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.campaign_bot_leaderboard AS
 SELECT competition_id,
    campaign_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY campaign_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['bot'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY competition_id, campaign_id, profile_id, display_name, profile_type;


--
-- Name: campaign_club_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.campaign_club_leaderboard AS
 SELECT competition_id,
    campaign_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY campaign_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['club'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY competition_id, campaign_id, profile_id, display_name, profile_type;


--
-- Name: campaign_global_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.campaign_global_leaderboard AS
 SELECT competition_id,
    campaign_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY campaign_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['user'::public.profile_type, 'bot'::public.profile_type, 'p10'::public.profile_type, 'club'::public.profile_type]))
  GROUP BY competition_id, campaign_id, profile_id, display_name, profile_type;


--
-- Name: campaign_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.campaign_groups (
    group_id integer NOT NULL,
    campaign_id integer NOT NULL,
    team_id integer NOT NULL,
    group_letter character varying(1) NOT NULL
);


--
-- Name: campaign_user_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.campaign_user_leaderboard AS
 SELECT competition_id,
    campaign_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY campaign_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['user'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY competition_id, campaign_id, profile_id, display_name, profile_type;


--
-- Name: campaigns_campaign_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.campaigns ALTER COLUMN campaign_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.campaigns_campaign_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: cities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cities (
    city_id integer NOT NULL,
    city_name text NOT NULL,
    timezone text NOT NULL,
    country_id integer NOT NULL
);


--
-- Name: cities_city_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.cities ALTER COLUMN city_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.cities_city_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: club_invites; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.club_invites (
    invite_id uuid NOT NULL,
    club_id uuid NOT NULL,
    sender_profile_id uuid NOT NULL,
    recipient_profile_id uuid NOT NULL,
    invite_status public.invite_status DEFAULT 'pending'::public.invite_status NOT NULL,
    created_at timestamp with time zone DEFAULT now()
);


--
-- Name: club_memberships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.club_memberships (
    club_id uuid NOT NULL,
    profile_id uuid NOT NULL,
    created_at timestamp with time zone DEFAULT now(),
    club_role public.club_role DEFAULT 'member'::public.club_role NOT NULL
);


--
-- Name: club_ownership_transfer_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.club_ownership_transfer_requests (
    request_id uuid DEFAULT gen_random_uuid() NOT NULL,
    club_id uuid NOT NULL,
    owner_profile_id uuid NOT NULL,
    target_profile_id uuid NOT NULL,
    status text DEFAULT 'pending'::text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    expires_at timestamp with time zone DEFAULT (now() + '24:00:00'::interval) NOT NULL,
    CONSTRAINT ownership_transfer_request_status_check CHECK ((status = ANY (ARRAY['pending'::text, 'accepted'::text, 'declined'::text, 'expired'::text])))
);


--
-- Name: club_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.club_profiles (
    profile_id uuid NOT NULL,
    club_name text NOT NULL,
    club_code text DEFAULT public.generate_club_code() NOT NULL,
    club_colour text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    invite_only boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT false NOT NULL,
    created_by uuid NOT NULL,
    is_deleted boolean DEFAULT false NOT NULL,
    deleted_at timestamp with time zone,
    CONSTRAINT club_profiles_club_name_check CHECK ((length(club_name) <= 16))
);


--
-- Name: competitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.competitions (
    competition_id integer NOT NULL,
    competition_name text NOT NULL,
    competition_code character(3) NOT NULL,
    fa_id integer,
    confederation_id integer
);


--
-- Name: competitions_competition_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.competitions ALTER COLUMN competition_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.competitions_competition_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: confederations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.confederations (
    confederation_id integer NOT NULL,
    code text NOT NULL
);


--
-- Name: confederations_confederation_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.confederations_confederation_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: confederations_confederation_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.confederations_confederation_id_seq OWNED BY public.confederations.confederation_id;


--
-- Name: countries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.countries (
    country_id integer NOT NULL,
    name text NOT NULL,
    iso2 character(2) NOT NULL
);


--
-- Name: countries_country_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.countries_country_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: countries_country_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.countries_country_id_seq OWNED BY public.countries.country_id;


--
-- Name: fa; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fa (
    fa_id integer NOT NULL,
    name text NOT NULL,
    fa_code text NOT NULL,
    country_id integer NOT NULL,
    confederation_id integer,
    fifa_member boolean DEFAULT true NOT NULL
);


--
-- Name: fa_fa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.fa_fa_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: fa_fa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.fa_fa_id_seq OWNED BY public.fa.fa_id;


--
-- Name: fixture_club_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.fixture_club_leaderboard AS
 SELECT fixture_id,
    profile_id,
    display_name,
    profile_type,
    correct_result,
    correct_home_goals,
    correct_away_goals,
    correct_goal_difference,
    correct_total_goals,
    points_total,
    rank() OVER (PARTITION BY fixture_id ORDER BY points_total DESC, correct_result DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['club'::public.profile_type, 'p10'::public.profile_type]));


--
-- Name: fixture_global_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.fixture_global_leaderboard AS
 SELECT fixture_id,
    profile_id,
    display_name,
    profile_type,
    correct_result,
    correct_home_goals,
    correct_away_goals,
    correct_goal_difference,
    correct_total_goals,
    points_total,
    rank() OVER (PARTITION BY fixture_id ORDER BY points_total DESC, correct_result DESC, profile_id) AS rank_position
   FROM public.leaderboard_base;


--
-- Name: fixture_leaderboard_base; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.fixture_leaderboard_base AS
 SELECT ps.fixture_id,
    ps.profile_id,
    pr.display_name,
    pr.profile_type,
    ps.correct_result,
    ps.correct_home_goals,
    ps.correct_away_goals,
    ps.correct_goal_difference,
    ps.correct_total_goals,
    ps.points_total
   FROM (public.prediction_scores ps
     JOIN public.profiles pr ON ((pr.profile_id = ps.profile_id)));


--
-- Name: results; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.results (
    fixture_id integer NOT NULL,
    ft_home_goals integer NOT NULL,
    ft_away_goals integer NOT NULL,
    final_home_goals integer NOT NULL,
    final_away_goals integer NOT NULL,
    created_at timestamp with time zone DEFAULT now(),
    updated_at timestamp with time zone DEFAULT now(),
    result_type public.result_type
);


--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.teams (
    team_id integer NOT NULL,
    team_name text NOT NULL,
    short_code character(3) NOT NULL,
    type text,
    color_1 text,
    color_2 text,
    color_3 text,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: fixture_score_admin; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.fixture_score_admin AS
 SELECT f.fixture_id,
    ht.team_name AS home_team,
    at.team_name AS away_team,
    f.fixture_status,
    f.kickoff_at,
    f.stage_id,
    COALESCE(r.ft_home_goals, 0) AS ft_home_goals,
    COALESCE(r.ft_away_goals, 0) AS ft_away_goals,
    r.final_home_goals,
    r.final_away_goals
   FROM (((public.fixtures f
     LEFT JOIN public.teams ht ON ((ht.team_id = f.home_team_id)))
     LEFT JOIN public.teams at ON ((at.team_id = f.away_team_id)))
     LEFT JOIN public.results r ON ((r.fixture_id = f.fixture_id)));


--
-- Name: fixture_user_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.fixture_user_leaderboard AS
 SELECT fixture_id,
    profile_id,
    display_name,
    profile_type,
    correct_result,
    correct_home_goals,
    correct_away_goals,
    correct_goal_difference,
    correct_total_goals,
    points_total,
    rank() OVER (PARTITION BY fixture_id ORDER BY points_total DESC, correct_result DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['user'::public.profile_type, 'p10'::public.profile_type]));


--
-- Name: fixtures_fixture_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.fixtures ALTER COLUMN fixture_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.fixtures_fixture_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: flavour_bot_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.flavour_bot_leaderboard AS
 SELECT competition_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY competition_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['bot'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY competition_id, profile_id, display_name, profile_type;


--
-- Name: flavour_club_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.flavour_club_leaderboard AS
 SELECT competition_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY competition_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['club'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY competition_id, profile_id, display_name, profile_type;


--
-- Name: flavour_competitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.flavour_competitions (
    flavour_id integer NOT NULL,
    competition_id integer NOT NULL
);


--
-- Name: gameweeks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gameweeks (
    gameweek_id integer NOT NULL,
    campaign_id integer NOT NULL,
    gameweek_number integer NOT NULL,
    status public.gameweek_status DEFAULT 'upcoming'::public.gameweek_status NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    stage_id integer NOT NULL,
    prediction_open_at timestamp with time zone,
    prediction_close_at timestamp with time zone
);


--
-- Name: stages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stages (
    stage_id integer NOT NULL,
    competition_id integer NOT NULL,
    stage_name text NOT NULL,
    order_index integer,
    stage_type public.stage_type NOT NULL,
    stage_code text,
    is_active boolean DEFAULT false,
    is_finished boolean DEFAULT false NOT NULL,
    is_live boolean DEFAULT false NOT NULL,
    first_fixture_id integer,
    last_fixture_id integer,
    campaign_id integer NOT NULL
);


--
-- Name: COLUMN stages.is_live; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.stages.is_live IS 'tracks if any of the fixtures have gone live and producing score data.';


--
-- Name: flavour_gameweeks; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.flavour_gameweeks AS
 SELECT fc.flavour_id,
    gw.gameweek_id,
    gw.gameweek_number,
    gw.status AS gameweek_status,
    gw.prediction_open_at,
    gw.prediction_close_at,
    s.stage_id,
    s.stage_name,
    s.stage_code,
    s.order_index,
    s.is_active,
    s.is_finished,
    c.campaign_id,
    c.label AS campaign_label,
    comp.competition_id,
    comp.competition_name,
    comp.competition_code
   FROM ((((public.gameweeks gw
     JOIN public.stages s ON ((s.stage_id = gw.stage_id)))
     JOIN public.competitions comp ON ((comp.competition_id = s.competition_id)))
     JOIN public.campaigns c ON ((c.campaign_id = gw.campaign_id)))
     JOIN public.flavour_competitions fc ON ((fc.competition_id = comp.competition_id)));


--
-- Name: flavour_global_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.flavour_global_leaderboard AS
 SELECT competition_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY competition_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['user'::public.profile_type, 'bot'::public.profile_type, 'p10'::public.profile_type, 'club'::public.profile_type]))
  GROUP BY competition_id, profile_id, display_name, profile_type;


--
-- Name: flavour_stages; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.flavour_stages AS
 SELECT fc.flavour_id,
    s.stage_id,
    s.competition_id,
    s.stage_name,
    s.order_index,
    s.stage_type,
    s.stage_code,
    s.is_active,
    s.is_live,
    s.is_finished
   FROM (public.stages s
     JOIN public.flavour_competitions fc ON ((fc.competition_id = s.competition_id)));


--
-- Name: flavours; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.flavours (
    flavour_id integer NOT NULL,
    flavour_name text NOT NULL,
    flavour_code character(3) NOT NULL,
    is_active boolean DEFAULT true,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    format_id integer NOT NULL,
    is_default boolean DEFAULT false NOT NULL
);


--
-- Name: teams_domestic; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.teams_domestic (
    team_id integer NOT NULL,
    fa_id integer NOT NULL
);


--
-- Name: teams_national; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.teams_national (
    team_id integer NOT NULL,
    fa_id integer,
    flag_code text NOT NULL
);


--
-- Name: flavour_teams; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.flavour_teams AS
 SELECT DISTINCT fl.flavour_id,
    t.team_id,
    t.team_name,
    t.short_code,
    t.color_1,
    t.color_2,
    t.color_3,
        CASE
            WHEN (tn.team_id IS NOT NULL) THEN 'Nation'::text
            WHEN (td.team_id IS NOT NULL) THEN 'Domestic'::text
            ELSE NULL::text
        END AS team_type,
    COALESCE(tn.fa_id, td.fa_id) AS fa_id,
    tn.flag_code,
    cg.group_letter
   FROM ((((((((public.flavours fl
     JOIN public.flavour_competitions fc ON ((fc.flavour_id = fl.flavour_id)))
     JOIN public.competitions comp ON ((comp.competition_id = fc.competition_id)))
     JOIN public.campaigns c ON (((c.competition_id = comp.competition_id) AND (c.is_active = true))))
     JOIN public.fixtures f ON ((f.campaign_id = c.campaign_id)))
     JOIN public.teams t ON (((t.team_id = f.home_team_id) OR (t.team_id = f.away_team_id))))
     LEFT JOIN public.teams_national tn ON ((tn.team_id = t.team_id)))
     LEFT JOIN public.teams_domestic td ON ((td.team_id = t.team_id)))
     LEFT JOIN public.campaign_groups cg ON (((cg.campaign_id = c.campaign_id) AND (cg.team_id = t.team_id))));


--
-- Name: flavour_user_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.flavour_user_leaderboard AS
 SELECT competition_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY competition_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['user'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY competition_id, profile_id, display_name, profile_type;


--
-- Name: flavours_flavour_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.flavours ALTER COLUMN flavour_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.flavours_flavour_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: gameweeks_gameweek_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.gameweeks_gameweek_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gameweeks_gameweek_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.gameweeks_gameweek_id_seq OWNED BY public.gameweeks.gameweek_id;


--
-- Name: global_bot_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.global_bot_leaderboard AS
 SELECT profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['bot'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY profile_id, display_name, profile_type;


--
-- Name: global_club_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.global_club_leaderboard AS
 SELECT profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['club'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY profile_id, display_name, profile_type;


--
-- Name: global_global_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.global_global_leaderboard AS
 SELECT profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['user'::public.profile_type, 'bot'::public.profile_type, 'p10'::public.profile_type, 'club'::public.profile_type]))
  GROUP BY profile_id, display_name, profile_type;


--
-- Name: global_user_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.global_user_leaderboard AS
 SELECT profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['user'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY profile_id, display_name, profile_type;


--
-- Name: groups_group_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.campaign_groups ALTER COLUMN group_id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.groups_group_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: leaderboard; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.leaderboard (
    flavour_id integer NOT NULL,
    scope_type text NOT NULL,
    scope_id integer,
    profile_id uuid NOT NULL,
    display_name text NOT NULL,
    profile_type public.profile_type,
    predicted bigint DEFAULT 0 NOT NULL,
    correct_result bigint DEFAULT 0 NOT NULL,
    correct_home_goals bigint DEFAULT 0 NOT NULL,
    correct_away_goals bigint DEFAULT 0 NOT NULL,
    correct_goal_difference bigint DEFAULT 0 NOT NULL,
    correct_total_goals bigint DEFAULT 0 NOT NULL,
    perfect_10s bigint DEFAULT 0 NOT NULL,
    points_total bigint DEFAULT 0 NOT NULL,
    updated_at timestamp with time zone DEFAULT now(),
    lb_rank_id bigint NOT NULL
);


--
-- Name: leaderboard_lb_rank_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.leaderboard ALTER COLUMN lb_rank_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.leaderboard_lb_rank_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: league_position_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.league_position_history (
    flavour_id integer NOT NULL,
    team_id integer NOT NULL,
    recorded_at timestamp with time zone NOT NULL,
    league_position integer,
    points integer,
    goal_difference integer,
    matches_played integer,
    campaign_id integer NOT NULL
);


--
-- Name: ml_components; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_components (
    component_id bigint NOT NULL,
    component_type public.component_type NOT NULL,
    model_id uuid,
    modifier_id bigint,
    engine_id bigint,
    created_at timestamp with time zone DEFAULT now(),
    CONSTRAINT ml_components_check CHECK ((((component_type = 'model'::public.component_type) AND (model_id IS NOT NULL) AND (modifier_id IS NULL) AND (engine_id IS NULL)) OR ((component_type = 'modifier'::public.component_type) AND (modifier_id IS NOT NULL) AND (model_id IS NULL) AND (engine_id IS NULL)) OR ((component_type = 'engine'::public.component_type) AND (engine_id IS NOT NULL) AND (model_id IS NULL) AND (modifier_id IS NULL))))
);


--
-- Name: ml_components_component_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.ml_components ALTER COLUMN component_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.ml_components_component_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: ml_engine_components; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_engine_components (
    engine_id bigint NOT NULL,
    component_id bigint NOT NULL,
    weight numeric,
    execution_order integer NOT NULL
);


--
-- Name: ml_engine_prediction_scoring; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_engine_prediction_scoring (
    engine_prediction_id bigint NOT NULL,
    engine_id bigint NOT NULL,
    fixture_id integer NOT NULL,
    correct_result boolean NOT NULL,
    correct_home_goals boolean NOT NULL,
    correct_away_goals boolean NOT NULL,
    correct_goal_difference boolean NOT NULL,
    correct_total_goals boolean NOT NULL,
    points_total smallint NOT NULL,
    perfect_10 boolean DEFAULT false NOT NULL,
    home_error smallint NOT NULL,
    away_error smallint NOT NULL,
    goal_difference_error smallint NOT NULL,
    total_goals_error smallint NOT NULL,
    score_distance smallint NOT NULL,
    scored_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: ml_engine_predictions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_engine_predictions (
    engine_prediction_id bigint NOT NULL,
    engine_id bigint NOT NULL,
    fixture_id integer NOT NULL,
    scheduler_run_id bigint NOT NULL,
    home_raw numeric(8,4),
    away_raw numeric(8,4),
    home_prediction smallint NOT NULL,
    away_prediction smallint NOT NULL,
    confidence numeric(6,5),
    metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    generated_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: ml_engine_predictions_engine_prediction_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.ml_engine_predictions ALTER COLUMN engine_prediction_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.ml_engine_predictions_engine_prediction_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: ml_engines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_engines (
    engine_id bigint NOT NULL,
    engine_name text NOT NULL,
    engine_type public.engine_type NOT NULL,
    config jsonb,
    version integer DEFAULT 1 NOT NULL,
    created_at timestamp with time zone DEFAULT now(),
    engine_code text NOT NULL,
    engine_priority integer
);


--
-- Name: ml_engines_engine_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.ml_engines ALTER COLUMN engine_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.ml_engines_engine_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: ml_feature_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_feature_runs (
    scheduler_run_id bigint NOT NULL,
    feature_group text NOT NULL,
    trigger_source text NOT NULL,
    started_at timestamp with time zone DEFAULT now() NOT NULL,
    completed_at timestamp with time zone,
    fixtures_checked integer DEFAULT 0 NOT NULL,
    fixtures_updated integer DEFAULT 0 NOT NULL,
    status text DEFAULT 'running'::text NOT NULL,
    error_message text,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: ml_feature_snapshots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_feature_snapshots (
    fixture_id integer NOT NULL,
    prediction_open boolean DEFAULT false,
    locked boolean DEFAULT false,
    captured_at timestamp with time zone DEFAULT now(),
    locked_at timestamp with time zone,
    home_team_id integer,
    away_team_id integer,
    ht_campaign_mp integer,
    at_campaign_mp integer,
    ht_campaign_league_pos integer,
    at_campaign_league_pos integer,
    ht_campaign_gf_avg numeric,
    ht_home_campaign_gf_avg numeric,
    at_campaign_gf_avg numeric,
    at_away_campaign_gf_avg numeric,
    ht_campaign_ga_avg numeric,
    ht_home_campaign_ga_avg numeric,
    at_campaign_ga_avg numeric,
    at_away_campaign_ga_avg numeric,
    ht_last5_matches integer,
    ht_last5_days_span integer,
    ht_last5_gf_avg numeric,
    ht_last5_ga_avg numeric,
    ht_last5_points_avg numeric,
    ht_last5_opp_fixture_rank_avg numeric,
    ht_last5_opp_current_rank_avg numeric,
    at_last5_matches integer,
    at_last5_days_span integer,
    at_last5_gf_avg numeric,
    at_last5_ga_avg numeric,
    at_last5_points_avg numeric,
    at_last5_opp_fixture_rank_avg numeric,
    at_last5_opp_current_rank_avg numeric,
    ht_flavour_mp integer,
    at_flavour_mp integer,
    ht_flavour_gf_avg numeric,
    ht_home_flavour_gf_avg numeric,
    at_flavour_gf_avg numeric,
    at_away_flavour_gf_avg numeric,
    ht_flavour_ga_avg numeric,
    ht_home_flavour_ga_avg numeric,
    at_flavour_ga_avg numeric,
    at_away_flavour_ga_avg numeric,
    ht_global_gf_avg numeric,
    ht_home_global_gf_avg numeric,
    at_global_gf_avg numeric,
    at_away_global_gf_avg numeric,
    ht_global_ga_avg numeric,
    ht_home_global_ga_avg numeric,
    at_global_ga_avg numeric,
    at_away_global_ga_avg numeric,
    ht_venue_gf_avg numeric,
    ht_venue_ga_avg numeric,
    ht_venue_matches_played integer,
    at_venue_gf_avg numeric,
    at_venue_ga_avg numeric,
    at_venue_matches_played integer,
    user_home_prediction_avg numeric,
    user_away_prediction_avg numeric,
    user_prediction_count integer,
    club_home_prediction_avg numeric,
    club_away_prediction_avg numeric,
    club_prediction_count integer,
    created_at timestamp with time zone DEFAULT now()
);


--
-- Name: ml_model_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_model_profiles (
    profile_id uuid NOT NULL,
    model_code text NOT NULL,
    config jsonb,
    created_at timestamp with time zone DEFAULT now(),
    model_type public.model_type NOT NULL
);


--
-- Name: ml_modifier; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_modifier (
    modifier_id bigint NOT NULL,
    modifier_code text NOT NULL,
    config jsonb,
    created_at timestamp with time zone DEFAULT now()
);


--
-- Name: ml_modifier_modifier_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.ml_modifier ALTER COLUMN modifier_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.ml_modifier_modifier_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: ml_p10_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_p10_profiles (
    profile_id uuid NOT NULL,
    engine_id bigint NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: ml_prediction_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_prediction_runs (
    scheduler_run_id bigint NOT NULL,
    prediction_group text NOT NULL,
    trigger_source text NOT NULL,
    started_at timestamp with time zone DEFAULT now() NOT NULL,
    completed_at timestamp with time zone,
    profiles_checked integer DEFAULT 0 NOT NULL,
    predictions_created integer DEFAULT 0 NOT NULL,
    predictions_updated integer DEFAULT 0 NOT NULL,
    status text DEFAULT 'running'::text NOT NULL,
    error_message text,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: ml_profile_engine_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_profile_engine_history (
    history_id bigint NOT NULL,
    profile_id uuid NOT NULL,
    engine_id bigint NOT NULL,
    started_at timestamp with time zone DEFAULT now() NOT NULL,
    ended_at timestamp with time zone
);


--
-- Name: ml_profile_engine_history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.ml_profile_engine_history ALTER COLUMN history_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.ml_profile_engine_history_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: ml_profile_predictions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_profile_predictions (
    profile_prediction_id bigint NOT NULL,
    fixture_id integer NOT NULL,
    profile_id uuid NOT NULL,
    scheduler_run_id bigint NOT NULL,
    home_raw numeric(8,4),
    away_raw numeric(8,4),
    home_prediction smallint NOT NULL,
    away_prediction smallint NOT NULL,
    confidence numeric(6,5),
    metadata jsonb DEFAULT '{}'::jsonb NOT NULL,
    generated_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: ml_profile_predictions_profile_prediction_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.ml_profile_predictions ALTER COLUMN profile_prediction_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.ml_profile_predictions_profile_prediction_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: ml_scheduler_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_scheduler_runs (
    scheduler_run_id bigint NOT NULL,
    job_name text NOT NULL,
    trigger_source text NOT NULL,
    started_at timestamp with time zone DEFAULT now() NOT NULL,
    completed_at timestamp with time zone,
    status text DEFAULT 'running'::text NOT NULL,
    error_message text,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: ml_scheduler_runs_scheduler_run_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.ml_scheduler_runs ALTER COLUMN scheduler_run_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.ml_scheduler_runs_scheduler_run_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: ml_team_matchup_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ml_team_matchup_history (
    matchup_id bigint NOT NULL,
    team_a_id integer NOT NULL,
    team_b_id integer NOT NULL,
    matches_played integer DEFAULT 0 NOT NULL,
    league_matches integer DEFAULT 0 NOT NULL,
    knockout_matches integer DEFAULT 0 NOT NULL,
    team_a_ft_goals_for integer DEFAULT 0 NOT NULL,
    team_a_ft_goals_against integer DEFAULT 0 NOT NULL,
    team_b_ft_goals_for integer DEFAULT 0 NOT NULL,
    team_b_ft_goals_against integer DEFAULT 0 NOT NULL,
    team_a_final_goals_for integer DEFAULT 0 NOT NULL,
    team_a_final_goals_against integer DEFAULT 0 NOT NULL,
    team_b_final_goals_for integer DEFAULT 0 NOT NULL,
    team_b_final_goals_against integer DEFAULT 0 NOT NULL,
    team_a_wins integer DEFAULT 0 NOT NULL,
    team_a_draws integer DEFAULT 0 NOT NULL,
    team_a_losses integer DEFAULT 0 NOT NULL,
    team_b_wins integer DEFAULT 0 NOT NULL,
    team_b_draws integer DEFAULT 0 NOT NULL,
    team_b_losses integer DEFAULT 0 NOT NULL,
    team_a_et_wins integer DEFAULT 0 NOT NULL,
    team_b_et_wins integer DEFAULT 0 NOT NULL,
    team_a_penalty_wins integer DEFAULT 0 NOT NULL,
    team_b_penalty_wins integer DEFAULT 0 NOT NULL,
    team_a_home_matches integer DEFAULT 0 NOT NULL,
    team_a_home_gf_avg numeric,
    team_a_home_ga_avg numeric,
    team_a_home_wins integer DEFAULT 0 NOT NULL,
    team_a_home_draws integer DEFAULT 0 NOT NULL,
    team_a_home_losses integer DEFAULT 0 NOT NULL,
    team_a_away_matches integer DEFAULT 0 NOT NULL,
    team_a_away_gf_avg numeric,
    team_a_away_ga_avg numeric,
    team_a_away_wins integer DEFAULT 0 NOT NULL,
    team_a_away_draws integer DEFAULT 0 NOT NULL,
    team_a_away_losses integer DEFAULT 0 NOT NULL,
    team_b_home_matches integer DEFAULT 0 NOT NULL,
    team_b_home_gf_avg numeric,
    team_b_home_ga_avg numeric,
    team_b_home_wins integer DEFAULT 0 NOT NULL,
    team_b_home_draws integer DEFAULT 0 NOT NULL,
    team_b_home_losses integer DEFAULT 0 NOT NULL,
    team_b_away_matches integer DEFAULT 0 NOT NULL,
    team_b_away_gf_avg numeric,
    team_b_away_ga_avg numeric,
    team_b_away_wins integer DEFAULT 0 NOT NULL,
    team_b_away_draws integer DEFAULT 0 NOT NULL,
    team_b_away_losses integer DEFAULT 0 NOT NULL,
    last_updated_at timestamp with time zone DEFAULT now()
);


--
-- Name: ml_team_matchup_history_matchup_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.ml_team_matchup_history ALTER COLUMN matchup_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.ml_team_matchup_history_matchup_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: prediction_formats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.prediction_formats (
    format_id integer NOT NULL,
    format_code character(3) NOT NULL,
    format_name text NOT NULL,
    window_open_interval interval,
    window_close_interval interval
);


--
-- Name: prediction_formats_format_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.prediction_formats ALTER COLUMN format_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.prediction_formats_format_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: predictions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.predictions (
    prediction_id bigint NOT NULL,
    profile_id uuid NOT NULL,
    fixture_id integer NOT NULL,
    pred_home_goals smallint NOT NULL,
    pred_away_goals smallint NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT predictions_pred_away_goals_check CHECK (((pred_away_goals >= 0) AND (pred_away_goals <= 9))),
    CONSTRAINT predictions_pred_home_goals_check CHECK (((pred_home_goals >= 0) AND (pred_home_goals <= 9)))
);


--
-- Name: predictions_prediction_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.predictions ALTER COLUMN prediction_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.predictions_prediction_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: scoring_criteria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scoring_criteria (
    criterion_id smallint NOT NULL,
    criterion_code text NOT NULL,
    description text NOT NULL,
    points smallint NOT NULL,
    is_active boolean DEFAULT true NOT NULL
);


--
-- Name: scoring_criteria_criterion_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.scoring_criteria ALTER COLUMN criterion_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.scoring_criteria_criterion_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: signup_codes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.signup_codes (
    signup_code_id bigint NOT NULL,
    code text NOT NULL,
    description text,
    active boolean DEFAULT true NOT NULL,
    max_uses integer,
    use_count integer DEFAULT 0 NOT NULL,
    expires_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: signup_codes_signup_code_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.signup_codes ALTER COLUMN signup_code_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.signup_codes_signup_code_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: stage_bot_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.stage_bot_leaderboard AS
 SELECT competition_id,
    campaign_id,
    stage_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY campaign_id, stage_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['bot'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY competition_id, campaign_id, stage_id, profile_id, display_name, profile_type;


--
-- Name: stage_club_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.stage_club_leaderboard AS
 SELECT competition_id,
    campaign_id,
    stage_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY campaign_id, stage_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['club'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY competition_id, campaign_id, stage_id, profile_id, display_name, profile_type;


--
-- Name: stage_global_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.stage_global_leaderboard AS
 SELECT competition_id,
    campaign_id,
    stage_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY campaign_id, stage_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['user'::public.profile_type, 'bot'::public.profile_type, 'p10'::public.profile_type, 'club'::public.profile_type]))
  GROUP BY competition_id, campaign_id, stage_id, profile_id, display_name, profile_type;


--
-- Name: stage_user_leaderboard; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.stage_user_leaderboard AS
 SELECT competition_id,
    campaign_id,
    stage_id,
    profile_id,
    display_name,
    profile_type,
    count(*) AS matches_predicted,
    sum(points_total) AS total_points,
    sum((perfect_10)::integer) AS perfect_10s,
    sum((correct_result)::integer) AS correct_results,
    sum((correct_home_goals)::integer) AS correct_home_goals,
    sum((correct_away_goals)::integer) AS correct_away_goals,
    sum((correct_goal_difference)::integer) AS correct_goal_differences,
    sum((correct_total_goals)::integer) AS correct_total_goals,
    rank() OVER (PARTITION BY campaign_id, stage_id ORDER BY (sum(points_total)) DESC, (sum((perfect_10)::integer)) DESC, (sum((correct_result)::integer)) DESC, profile_id) AS rank_position
   FROM public.leaderboard_base
  WHERE (profile_type = ANY (ARRAY['user'::public.profile_type, 'p10'::public.profile_type]))
  GROUP BY competition_id, campaign_id, stage_id, profile_id, display_name, profile_type;


--
-- Name: stages_stage_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.stages ALTER COLUMN stage_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.stages_stage_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: teams_team_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.teams ALTER COLUMN team_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.teams_team_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: ties; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ties (
    tie_id integer NOT NULL,
    stage_id integer NOT NULL,
    team_a_id integer NOT NULL,
    team_b_id integer NOT NULL,
    winner_team_id integer,
    resolution_type public.resolution_type,
    created_at timestamp with time zone DEFAULT now(),
    CONSTRAINT ties_team_order_check CHECK ((team_a_id < team_b_id)),
    CONSTRAINT ties_teams_different CHECK ((team_a_id <> team_b_id)),
    CONSTRAINT ties_winner_valid CHECK (((winner_team_id IS NULL) OR (winner_team_id = team_a_id) OR (winner_team_id = team_b_id)))
);


--
-- Name: ties_tie_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.ties ALTER COLUMN tie_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.ties_tie_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: user_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_profiles (
    auth_id uuid NOT NULL,
    username public.citext NOT NULL,
    display_name text NOT NULL,
    avatar_key text DEFAULT 'default_ball'::text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    profile_id uuid NOT NULL,
    favourite_domestic_team_id integer,
    favourite_national_team_id integer,
    CONSTRAINT user_profiles_display_name_check CHECK ((length(display_name) <= 16)),
    CONSTRAINT user_profiles_profile_id_not_null CHECK ((profile_id IS NOT NULL))
);


--
-- Name: venues; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.venues (
    venue_id integer NOT NULL,
    venue_name text NOT NULL,
    city_id integer NOT NULL,
    capacity integer
);


--
-- Name: venues_venue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.venues ALTER COLUMN venue_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.venues_venue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: vw_ml_feature_scope; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.vw_ml_feature_scope AS
 SELECT fl.flavour_id,
    fc.competition_id,
    cpn.campaign_id,
    f.fixture_id,
    f.home_team_id,
    f.away_team_id,
    f.prediction_open_at,
    f.prediction_close_at,
    s.fixture_id AS snapshot_fixture_id,
    s.prediction_open,
    s.locked,
    s.locked_at
   FROM (((((public.flavours fl
     JOIN public.flavour_competitions fc ON ((fc.flavour_id = fl.flavour_id)))
     JOIN public.competitions c ON ((c.competition_id = fc.competition_id)))
     JOIN public.campaigns cpn ON ((cpn.competition_id = c.competition_id)))
     JOIN public.fixtures f ON ((f.campaign_id = cpn.campaign_id)))
     LEFT JOIN public.ml_feature_snapshots s ON ((s.fixture_id = f.fixture_id)));


--
-- Name: confederations confederation_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.confederations ALTER COLUMN confederation_id SET DEFAULT nextval('public.confederations_confederation_id_seq'::regclass);


--
-- Name: countries country_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries ALTER COLUMN country_id SET DEFAULT nextval('public.countries_country_id_seq'::regclass);


--
-- Name: fa fa_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa ALTER COLUMN fa_id SET DEFAULT nextval('public.fa_fa_id_seq'::regclass);


--
-- Name: gameweeks gameweek_id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gameweeks ALTER COLUMN gameweek_id SET DEFAULT nextval('public.gameweeks_gameweek_id_seq'::regclass);


--
-- Name: bot_profiles bot_profiles_bot_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_profiles
    ADD CONSTRAINT bot_profiles_bot_name_key UNIQUE (bot_name);


--
-- Name: bot_profiles bot_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_profiles
    ADD CONSTRAINT bot_profiles_pkey PRIMARY KEY (profile_id);


--
-- Name: campaigns campaigns_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaigns
    ADD CONSTRAINT campaigns_pkey PRIMARY KEY (campaign_id);


--
-- Name: cities cities_city_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_city_name_key UNIQUE (city_name);


--
-- Name: cities cities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_pkey PRIMARY KEY (city_id);


--
-- Name: club_invites club_invites_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_invites
    ADD CONSTRAINT club_invites_pkey PRIMARY KEY (invite_id);


--
-- Name: club_memberships club_memberships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_memberships
    ADD CONSTRAINT club_memberships_pkey PRIMARY KEY (club_id, profile_id);


--
-- Name: club_ownership_transfer_requests club_ownership_transfer_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_ownership_transfer_requests
    ADD CONSTRAINT club_ownership_transfer_requests_pkey PRIMARY KEY (request_id);


--
-- Name: club_profiles club_profiles_club_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_profiles
    ADD CONSTRAINT club_profiles_club_code_key UNIQUE (club_code);


--
-- Name: club_profiles club_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_profiles
    ADD CONSTRAINT club_profiles_pkey PRIMARY KEY (profile_id);


--
-- Name: competitions competitions_competition_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competitions
    ADD CONSTRAINT competitions_competition_code_key UNIQUE (competition_code);


--
-- Name: competitions competitions_competition_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competitions
    ADD CONSTRAINT competitions_competition_name_key UNIQUE (competition_name);


--
-- Name: competitions competitions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competitions
    ADD CONSTRAINT competitions_pkey PRIMARY KEY (competition_id);


--
-- Name: confederations confederations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.confederations
    ADD CONSTRAINT confederations_pkey PRIMARY KEY (confederation_id);


--
-- Name: countries countries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_pkey PRIMARY KEY (country_id);


--
-- Name: teams_domestic domestic_teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams_domestic
    ADD CONSTRAINT domestic_teams_pkey PRIMARY KEY (team_id);


--
-- Name: fa fa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa
    ADD CONSTRAINT fa_pkey PRIMARY KEY (fa_id);


--
-- Name: fixtures fixtures_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixtures
    ADD CONSTRAINT fixtures_pkey PRIMARY KEY (fixture_id);


--
-- Name: flavour_competitions flavour_competitions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flavour_competitions
    ADD CONSTRAINT flavour_competitions_pkey PRIMARY KEY (flavour_id, competition_id);


--
-- Name: flavours flavours_flavour_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flavours
    ADD CONSTRAINT flavours_flavour_code_key UNIQUE (flavour_code);


--
-- Name: flavours flavours_flavour_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flavours
    ADD CONSTRAINT flavours_flavour_name_key UNIQUE (flavour_name);


--
-- Name: flavours flavours_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flavours
    ADD CONSTRAINT flavours_pkey PRIMARY KEY (flavour_id);


--
-- Name: gameweeks gameweeks_campaign_id_gameweek_number_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gameweeks
    ADD CONSTRAINT gameweeks_campaign_id_gameweek_number_key UNIQUE (campaign_id, gameweek_number);


--
-- Name: gameweeks gameweeks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gameweeks
    ADD CONSTRAINT gameweeks_pkey PRIMARY KEY (gameweek_id);


--
-- Name: gameweeks gameweeks_stage_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gameweeks
    ADD CONSTRAINT gameweeks_stage_id_key UNIQUE (stage_id);


--
-- Name: campaign_groups groups_campaign_id_team_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_groups
    ADD CONSTRAINT groups_campaign_id_team_id_key UNIQUE (campaign_id, team_id);


--
-- Name: campaign_groups groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (group_id);


--
-- Name: leaderboard leaderboard_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leaderboard
    ADD CONSTRAINT leaderboard_pkey PRIMARY KEY (lb_rank_id);


--
-- Name: leaderboard leaderboard_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leaderboard
    ADD CONSTRAINT leaderboard_unique UNIQUE (flavour_id, scope_type, scope_id, profile_id);


--
-- Name: league_position_history league_position_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_position_history
    ADD CONSTRAINT league_position_history_pkey PRIMARY KEY (flavour_id, team_id, recorded_at, campaign_id);


--
-- Name: ml_components ml_components_component_type_model_id_modifier_id_engine_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_components
    ADD CONSTRAINT ml_components_component_type_model_id_modifier_id_engine_id_key UNIQUE NULLS NOT DISTINCT (component_type, model_id, modifier_id, engine_id);


--
-- Name: ml_components ml_components_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_components
    ADD CONSTRAINT ml_components_pkey PRIMARY KEY (component_id);


--
-- Name: ml_engine_components ml_engine_components_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_components
    ADD CONSTRAINT ml_engine_components_pkey PRIMARY KEY (engine_id, component_id);


--
-- Name: ml_engine_prediction_scoring ml_engine_prediction_scoring_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_prediction_scoring
    ADD CONSTRAINT ml_engine_prediction_scoring_pkey PRIMARY KEY (engine_prediction_id);


--
-- Name: ml_engine_predictions ml_engine_predictions_engine_fixture_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_predictions
    ADD CONSTRAINT ml_engine_predictions_engine_fixture_key UNIQUE (engine_id, fixture_id);


--
-- Name: ml_engine_predictions ml_engine_predictions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_predictions
    ADD CONSTRAINT ml_engine_predictions_pkey PRIMARY KEY (engine_prediction_id);


--
-- Name: ml_engines ml_engines_code_version_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engines
    ADD CONSTRAINT ml_engines_code_version_key UNIQUE (engine_code, version);


--
-- Name: ml_engines ml_engines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engines
    ADD CONSTRAINT ml_engines_pkey PRIMARY KEY (engine_id);


--
-- Name: ml_feature_runs ml_feature_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_feature_runs
    ADD CONSTRAINT ml_feature_runs_pkey PRIMARY KEY (scheduler_run_id);


--
-- Name: ml_feature_snapshots ml_feature_snapshots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_feature_snapshots
    ADD CONSTRAINT ml_feature_snapshots_pkey PRIMARY KEY (fixture_id);


--
-- Name: ml_model_profiles ml_model_profiles_model_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_model_profiles
    ADD CONSTRAINT ml_model_profiles_model_code_key UNIQUE (model_code);


--
-- Name: ml_model_profiles ml_model_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_model_profiles
    ADD CONSTRAINT ml_model_profiles_pkey PRIMARY KEY (profile_id);


--
-- Name: ml_modifier ml_modifier_modifier_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_modifier
    ADD CONSTRAINT ml_modifier_modifier_code_key UNIQUE (modifier_code);


--
-- Name: ml_modifier ml_modifier_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_modifier
    ADD CONSTRAINT ml_modifier_pkey PRIMARY KEY (modifier_id);


--
-- Name: ml_p10_profiles ml_p10_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_p10_profiles
    ADD CONSTRAINT ml_p10_profiles_pkey PRIMARY KEY (profile_id);


--
-- Name: ml_prediction_runs ml_prediction_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_prediction_runs
    ADD CONSTRAINT ml_prediction_runs_pkey PRIMARY KEY (scheduler_run_id);


--
-- Name: ml_profile_engine_history ml_profile_engine_history_no_overlap; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_profile_engine_history
    ADD CONSTRAINT ml_profile_engine_history_no_overlap EXCLUDE USING gist (profile_id WITH =, tstzrange(started_at, COALESCE(ended_at, 'infinity'::timestamp with time zone)) WITH &&);


--
-- Name: ml_profile_predictions ml_profile_predictions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_profile_predictions
    ADD CONSTRAINT ml_profile_predictions_pkey PRIMARY KEY (profile_prediction_id);


--
-- Name: ml_profile_predictions ml_profile_predictions_profile_fixture_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_profile_predictions
    ADD CONSTRAINT ml_profile_predictions_profile_fixture_key UNIQUE (profile_id, fixture_id);


--
-- Name: ml_scheduler_runs ml_scheduler_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_scheduler_runs
    ADD CONSTRAINT ml_scheduler_runs_pkey PRIMARY KEY (scheduler_run_id);


--
-- Name: ml_team_matchup_history ml_team_matchup_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_team_matchup_history
    ADD CONSTRAINT ml_team_matchup_history_pkey PRIMARY KEY (matchup_id);


--
-- Name: ml_team_matchup_history ml_team_matchup_history_team_pair_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_team_matchup_history
    ADD CONSTRAINT ml_team_matchup_history_team_pair_unique UNIQUE (team_a_id, team_b_id);


--
-- Name: teams_national national_teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams_national
    ADD CONSTRAINT national_teams_pkey PRIMARY KEY (team_id);


--
-- Name: prediction_formats prediction_formats_format_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prediction_formats
    ADD CONSTRAINT prediction_formats_format_code_key UNIQUE (format_code);


--
-- Name: prediction_formats prediction_formats_format_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prediction_formats
    ADD CONSTRAINT prediction_formats_format_name_key UNIQUE (format_name);


--
-- Name: prediction_formats prediction_formats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prediction_formats
    ADD CONSTRAINT prediction_formats_pkey PRIMARY KEY (format_id);


--
-- Name: prediction_scores prediction_scores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prediction_scores
    ADD CONSTRAINT prediction_scores_pkey PRIMARY KEY (prediction_id);


--
-- Name: prediction_scores prediction_scores_profile_fixture_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prediction_scores
    ADD CONSTRAINT prediction_scores_profile_fixture_key UNIQUE (profile_id, fixture_id);


--
-- Name: predictions predictions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.predictions
    ADD CONSTRAINT predictions_pkey PRIMARY KEY (prediction_id);


--
-- Name: predictions predictions_profile_fixture_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.predictions
    ADD CONSTRAINT predictions_profile_fixture_key UNIQUE (profile_id, fixture_id);


--
-- Name: predictions predictions_profile_fixture_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.predictions
    ADD CONSTRAINT predictions_profile_fixture_unique UNIQUE (profile_id, fixture_id);


--
-- Name: user_profiles profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT profiles_pkey PRIMARY KEY (auth_id);


--
-- Name: profiles profiles_pkey1; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.profiles
    ADD CONSTRAINT profiles_pkey1 PRIMARY KEY (profile_id);


--
-- Name: user_profiles profiles_username_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT profiles_username_key UNIQUE (username);


--
-- Name: results results_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.results
    ADD CONSTRAINT results_pkey PRIMARY KEY (fixture_id);


--
-- Name: scoring_criteria scoring_criteria_criterion_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scoring_criteria
    ADD CONSTRAINT scoring_criteria_criterion_code_key UNIQUE (criterion_code);


--
-- Name: scoring_criteria scoring_criteria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scoring_criteria
    ADD CONSTRAINT scoring_criteria_pkey PRIMARY KEY (criterion_id);


--
-- Name: signup_codes signup_codes_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signup_codes
    ADD CONSTRAINT signup_codes_code_key UNIQUE (code);


--
-- Name: signup_codes signup_codes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signup_codes
    ADD CONSTRAINT signup_codes_pkey PRIMARY KEY (signup_code_id);


--
-- Name: stages stages_campaign_id_order_index_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stages
    ADD CONSTRAINT stages_campaign_id_order_index_key UNIQUE (campaign_id, order_index);


--
-- Name: stages stages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stages
    ADD CONSTRAINT stages_pkey PRIMARY KEY (stage_id);


--
-- Name: teams teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_pkey PRIMARY KEY (team_id);


--
-- Name: teams teams_short_code_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_short_code_key UNIQUE (short_code);


--
-- Name: teams teams_team_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_team_name_key UNIQUE (team_name);


--
-- Name: ties ties_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ties
    ADD CONSTRAINT ties_pkey PRIMARY KEY (tie_id);


--
-- Name: ties ties_unique_pair; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ties
    ADD CONSTRAINT ties_unique_pair UNIQUE (stage_id, team_a_id, team_b_id);


--
-- Name: user_profiles user_profiles_profile_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_profile_id_key UNIQUE (profile_id);


--
-- Name: venues venues_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venues
    ADD CONSTRAINT venues_pkey PRIMARY KEY (venue_id);


--
-- Name: club_invites_pending_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX club_invites_pending_unique ON public.club_invites USING btree (club_id, recipient_profile_id) WHERE (invite_status = 'pending'::public.invite_status);


--
-- Name: fixtures_fixture_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fixtures_fixture_status_idx ON public.fixtures USING btree (fixture_status);


--
-- Name: fixtures_stage_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fixtures_stage_id_idx ON public.fixtures USING btree (stage_id);


--
-- Name: fixtures_stage_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fixtures_stage_status_idx ON public.fixtures USING btree (stage_id, fixture_status);


--
-- Name: fixtures_tie_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX fixtures_tie_id_idx ON public.fixtures USING btree (tie_id);


--
-- Name: gameweeks_campaign_status_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX gameweeks_campaign_status_idx ON public.gameweeks USING btree (campaign_id, status);


--
-- Name: idx_club_profiles_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_club_profiles_active ON public.club_profiles USING btree (is_deleted) WHERE (is_deleted = false);


--
-- Name: idx_domestic_teams_fa_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_domestic_teams_fa_id ON public.teams_domestic USING btree (fa_id);


--
-- Name: idx_fixtures_kickoff; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_fixtures_kickoff ON public.fixtures USING btree (kickoff_at);


--
-- Name: idx_leaderboard_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_leaderboard_lookup ON public.leaderboard USING btree (flavour_id, scope_type, scope_id);


--
-- Name: idx_ml_engine_prediction_scoring_engine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ml_engine_prediction_scoring_engine ON public.ml_engine_prediction_scoring USING btree (engine_id);


--
-- Name: idx_ml_engine_prediction_scoring_fixture; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ml_engine_prediction_scoring_fixture ON public.ml_engine_prediction_scoring USING btree (fixture_id);


--
-- Name: idx_ml_engine_prediction_scoring_points; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ml_engine_prediction_scoring_points ON public.ml_engine_prediction_scoring USING btree (points_total DESC);


--
-- Name: idx_ml_engine_predictions_engine; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ml_engine_predictions_engine ON public.ml_engine_predictions USING btree (engine_id);


--
-- Name: idx_ml_engine_predictions_fixture; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ml_engine_predictions_fixture ON public.ml_engine_predictions USING btree (fixture_id);


--
-- Name: idx_ml_profile_predictions_fixture; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ml_profile_predictions_fixture ON public.ml_profile_predictions USING btree (fixture_id);


--
-- Name: idx_ml_profile_predictions_profile; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ml_profile_predictions_profile ON public.ml_profile_predictions USING btree (profile_id);


--
-- Name: idx_ml_scheduler_runs_job; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ml_scheduler_runs_job ON public.ml_scheduler_runs USING btree (job_name);


--
-- Name: idx_ml_scheduler_runs_started; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ml_scheduler_runs_started ON public.ml_scheduler_runs USING btree (started_at DESC);


--
-- Name: idx_national_teams_fa_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_national_teams_fa_id ON public.teams_national USING btree (fa_id);


--
-- Name: idx_prediction_scores_fixture; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_prediction_scores_fixture ON public.prediction_scores USING btree (fixture_id);


--
-- Name: idx_prediction_scores_fixture_points; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_prediction_scores_fixture_points ON public.prediction_scores USING btree (fixture_id, points_total DESC);


--
-- Name: idx_prediction_scores_profile; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_prediction_scores_profile ON public.prediction_scores USING btree (profile_id);


--
-- Name: idx_predictions_fixture; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_predictions_fixture ON public.predictions USING btree (fixture_id);


--
-- Name: one_active_campaign_per_competition; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX one_active_campaign_per_competition ON public.campaigns USING btree (competition_id) WHERE (is_active = true);


--
-- Name: one_live_gameweek_per_campaign; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX one_live_gameweek_per_campaign ON public.gameweeks USING btree (campaign_id) WHERE (status = 'active'::public.gameweek_status);


--
-- Name: one_owned_club_per_profile; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX one_owned_club_per_profile ON public.club_memberships USING btree (profile_id) WHERE (club_role = 'owner'::public.club_role);


--
-- Name: one_p10_profile; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX one_p10_profile ON public.profiles USING btree (profile_type) WHERE (profile_type = 'p10'::public.profile_type);


--
-- Name: one_pending_transfer_per_club; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX one_pending_transfer_per_club ON public.club_ownership_transfer_requests USING btree (club_id) WHERE (status = 'pending'::text);


--
-- Name: ownership_transfer_owner_pending_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ownership_transfer_owner_pending_idx ON public.club_ownership_transfer_requests USING btree (owner_profile_id, status);


--
-- Name: ownership_transfer_target_pending_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ownership_transfer_target_pending_idx ON public.club_ownership_transfer_requests USING btree (target_profile_id, status);


--
-- Name: stages_one_postponed_per_campaign; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX stages_one_postponed_per_campaign ON public.stages USING btree (campaign_id) WHERE (stage_type = 'postponed'::public.stage_type);


--
-- Name: fixtures perfect10_fixture_status_broadcast; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER perfect10_fixture_status_broadcast AFTER UPDATE OF fixture_status ON public.fixtures FOR EACH ROW WHEN ((old.fixture_status IS DISTINCT FROM new.fixture_status)) EXECUTE FUNCTION public.notify_perfect10_fixture_changed();


--
-- Name: results perfect10_result_changed_broadcast; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER perfect10_result_changed_broadcast AFTER INSERT OR DELETE OR UPDATE ON public.results FOR EACH ROW EXECUTE FUNCTION public.notify_perfect10_result_changed();


--
-- Name: bot_profiles trg_bot_profiles_engine_history; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_bot_profiles_engine_history AFTER INSERT OR UPDATE OF engine_id ON public.bot_profiles FOR EACH ROW EXECUTE FUNCTION public.track_profile_engine_change();


--
-- Name: bot_profiles trg_bot_profiles_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_bot_profiles_updated_at BEFORE UPDATE ON public.bot_profiles FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();


--
-- Name: ml_engine_components trg_check_one_modifier_per_engine; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_check_one_modifier_per_engine BEFORE INSERT OR UPDATE ON public.ml_engine_components FOR EACH ROW EXECUTE FUNCTION public.check_one_modifier_per_engine();


--
-- Name: fixtures trg_fixture_postponed_guard; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_fixture_postponed_guard BEFORE UPDATE OF fixture_status ON public.fixtures FOR EACH ROW WHEN ((new.fixture_status = 'postponed'::public.fixture_status)) EXECUTE FUNCTION public.handle_postponed_fixture();


--
-- Name: fixtures trg_fixtures_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_fixtures_updated_at BEFORE UPDATE ON public.fixtures FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();


--
-- Name: fixtures trg_ml_engine_prediction_scores; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_ml_engine_prediction_scores AFTER UPDATE OF fixture_status ON public.fixtures FOR EACH ROW WHEN ((new.fixture_status = 'finished'::public.fixture_status)) EXECUTE FUNCTION public.update_ml_engine_prediction_scores();


--
-- Name: ml_feature_snapshots trg_ml_feature_snapshots_captured_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_ml_feature_snapshots_captured_at BEFORE UPDATE ON public.ml_feature_snapshots FOR EACH ROW EXECUTE FUNCTION public.set_captured_at();


--
-- Name: ml_p10_profiles trg_p10_profiles_engine_history; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_p10_profiles_engine_history AFTER INSERT OR UPDATE OF engine_id ON public.ml_p10_profiles FOR EACH ROW EXECUTE FUNCTION public.track_profile_engine_change();


--
-- Name: ml_p10_profiles trg_p10_profiles_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_p10_profiles_updated_at BEFORE UPDATE ON public.ml_p10_profiles FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();


--
-- Name: prediction_scores trg_prediction_scores_changed; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_prediction_scores_changed AFTER INSERT OR UPDATE ON public.prediction_scores FOR EACH ROW EXECUTE FUNCTION public.handle_prediction_score_change();


--
-- Name: prediction_scores trg_prediction_scores_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_prediction_scores_updated_at BEFORE UPDATE ON public.prediction_scores FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();


--
-- Name: predictions trg_predictions_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_predictions_updated_at BEFORE UPDATE ON public.predictions FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();


--
-- Name: fixtures trg_refresh_prediction_windows; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_refresh_prediction_windows AFTER UPDATE OF kickoff_at, stage_id, fixture_status ON public.fixtures FOR EACH ROW EXECUTE FUNCTION public.refresh_prediction_windows();


--
-- Name: results trg_results_table_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_results_table_updated_at BEFORE UPDATE ON public.results FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();


--
-- Name: results trg_results_update_scores; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_results_update_scores AFTER INSERT OR UPDATE OF ft_home_goals, ft_away_goals ON public.results FOR EACH ROW EXECUTE FUNCTION public.update_prediction_scores();


--
-- Name: fixtures trg_stage_first_last_fixture; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_stage_first_last_fixture AFTER INSERT OR UPDATE OF kickoff_at, stage_id, fixture_status ON public.fixtures FOR EACH ROW EXECUTE FUNCTION public.sync_stage_first_last_fixture();


--
-- Name: results trg_sync_final_from_ft; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_sync_final_from_ft BEFORE INSERT OR UPDATE OF ft_home_goals, ft_away_goals ON public.results FOR EACH ROW EXECUTE FUNCTION public.sync_final_from_ft();


--
-- Name: gameweeks trg_sync_stage_from_gameweek; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_sync_stage_from_gameweek AFTER UPDATE OF status ON public.gameweeks FOR EACH ROW WHEN ((old.status IS DISTINCT FROM new.status)) EXECUTE FUNCTION public.sync_stage_from_gameweek();


--
-- Name: fixtures trg_sync_stage_is_finished; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_sync_stage_is_finished AFTER UPDATE OF fixture_status, stage_id ON public.fixtures FOR EACH ROW EXECUTE FUNCTION public.sync_stage_is_finished_single();


--
-- Name: fixtures trg_sync_stage_is_live; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_sync_stage_is_live AFTER UPDATE OF fixture_status, stage_id ON public.fixtures FOR EACH ROW EXECUTE FUNCTION public.sync_stage_is_live();


--
-- Name: teams trg_teams_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_teams_updated_at BEFORE UPDATE ON public.teams FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();


--
-- Name: club_memberships trg_update_club_active_status; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_update_club_active_status AFTER INSERT OR DELETE ON public.club_memberships FOR EACH ROW EXECUTE FUNCTION public.update_club_active_status();


--
-- Name: predictions trg_update_club_predictions; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_update_club_predictions AFTER INSERT OR UPDATE ON public.predictions FOR EACH ROW EXECUTE FUNCTION public.update_club_predictions();


--
-- Name: club_memberships trg_update_club_predictions_membership; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_update_club_predictions_membership AFTER INSERT OR DELETE ON public.club_memberships FOR EACH ROW EXECUTE FUNCTION public.update_club_predictions_from_membership();


--
-- Name: results trg_update_ml_team_matchup_history; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_update_ml_team_matchup_history AFTER UPDATE OF result_type ON public.results FOR EACH ROW WHEN ((new.result_type IS NOT NULL)) EXECUTE FUNCTION public.update_ml_team_matchup_history();


--
-- Name: user_profiles trg_update_user_profile_display_name; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_update_user_profile_display_name AFTER UPDATE OF display_name ON public.user_profiles FOR EACH ROW EXECUTE FUNCTION public.sync_user_display_name();


--
-- Name: user_profiles trg_user_profile_updated_at; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_user_profile_updated_at BEFORE UPDATE ON public.user_profiles FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();


--
-- Name: bot_profiles bot_profiles_engine_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_profiles
    ADD CONSTRAINT bot_profiles_engine_id_fkey FOREIGN KEY (engine_id) REFERENCES public.ml_engines(engine_id);


--
-- Name: bot_profiles bot_profiles_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bot_profiles
    ADD CONSTRAINT bot_profiles_profile_id_fkey FOREIGN KEY (profile_id) REFERENCES public.profiles(profile_id) ON DELETE CASCADE;


--
-- Name: campaigns campaigns_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaigns
    ADD CONSTRAINT campaigns_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(competition_id);


--
-- Name: cities cities_country_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_country_id_fkey FOREIGN KEY (country_id) REFERENCES public.countries(country_id);


--
-- Name: club_memberships club_memberships_club_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_memberships
    ADD CONSTRAINT club_memberships_club_id_fkey FOREIGN KEY (club_id) REFERENCES public.profiles(profile_id);


--
-- Name: club_memberships club_memberships_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_memberships
    ADD CONSTRAINT club_memberships_profile_id_fkey FOREIGN KEY (profile_id) REFERENCES public.profiles(profile_id);


--
-- Name: club_ownership_transfer_requests club_ownership_transfer_requests_club_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_ownership_transfer_requests
    ADD CONSTRAINT club_ownership_transfer_requests_club_id_fkey FOREIGN KEY (club_id) REFERENCES public.club_profiles(profile_id);


--
-- Name: club_ownership_transfer_requests club_ownership_transfer_requests_owner_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_ownership_transfer_requests
    ADD CONSTRAINT club_ownership_transfer_requests_owner_profile_id_fkey FOREIGN KEY (owner_profile_id) REFERENCES public.user_profiles(profile_id);


--
-- Name: club_ownership_transfer_requests club_ownership_transfer_requests_target_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_ownership_transfer_requests
    ADD CONSTRAINT club_ownership_transfer_requests_target_profile_id_fkey FOREIGN KEY (target_profile_id) REFERENCES public.user_profiles(profile_id);


--
-- Name: club_profiles club_profiles_created_by_fkey1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_profiles
    ADD CONSTRAINT club_profiles_created_by_fkey1 FOREIGN KEY (created_by) REFERENCES public.user_profiles(profile_id);


--
-- Name: club_profiles club_profiles_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.club_profiles
    ADD CONSTRAINT club_profiles_profile_id_fkey FOREIGN KEY (profile_id) REFERENCES public.profiles(profile_id);


--
-- Name: competitions competitions_confederation_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competitions
    ADD CONSTRAINT competitions_confederation_id_fkey FOREIGN KEY (confederation_id) REFERENCES public.confederations(confederation_id);


--
-- Name: competitions competitions_fa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.competitions
    ADD CONSTRAINT competitions_fa_id_fkey FOREIGN KEY (fa_id) REFERENCES public.fa(fa_id);


--
-- Name: teams_domestic domestic_teams_fa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams_domestic
    ADD CONSTRAINT domestic_teams_fa_id_fkey FOREIGN KEY (fa_id) REFERENCES public.fa(fa_id);


--
-- Name: teams_domestic domestic_teams_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams_domestic
    ADD CONSTRAINT domestic_teams_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(team_id) ON DELETE CASCADE;


--
-- Name: fa fa_confederation_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa
    ADD CONSTRAINT fa_confederation_id_fkey FOREIGN KEY (confederation_id) REFERENCES public.confederations(confederation_id);


--
-- Name: fa fa_country_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fa
    ADD CONSTRAINT fa_country_id_fkey FOREIGN KEY (country_id) REFERENCES public.countries(country_id);


--
-- Name: fixtures fixtures_away_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixtures
    ADD CONSTRAINT fixtures_away_team_id_fkey FOREIGN KEY (away_team_id) REFERENCES public.teams(team_id);


--
-- Name: fixtures fixtures_campaign_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixtures
    ADD CONSTRAINT fixtures_campaign_id_fkey FOREIGN KEY (campaign_id) REFERENCES public.campaigns(campaign_id);


--
-- Name: fixtures fixtures_home_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixtures
    ADD CONSTRAINT fixtures_home_team_id_fkey FOREIGN KEY (home_team_id) REFERENCES public.teams(team_id);


--
-- Name: fixtures fixtures_stage_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixtures
    ADD CONSTRAINT fixtures_stage_id_fkey FOREIGN KEY (stage_id) REFERENCES public.stages(stage_id);


--
-- Name: fixtures fixtures_tie_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixtures
    ADD CONSTRAINT fixtures_tie_id_fkey FOREIGN KEY (tie_id) REFERENCES public.ties(tie_id) ON DELETE SET NULL;


--
-- Name: fixtures fixtures_venue_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fixtures
    ADD CONSTRAINT fixtures_venue_id_fkey FOREIGN KEY (venue_id) REFERENCES public.venues(venue_id);


--
-- Name: flavour_competitions flavour_competitions_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flavour_competitions
    ADD CONSTRAINT flavour_competitions_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(competition_id) ON DELETE CASCADE;


--
-- Name: flavour_competitions flavour_competitions_flavour_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flavour_competitions
    ADD CONSTRAINT flavour_competitions_flavour_id_fkey FOREIGN KEY (flavour_id) REFERENCES public.flavours(flavour_id) ON DELETE CASCADE;


--
-- Name: flavours flavours_format_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flavours
    ADD CONSTRAINT flavours_format_id_fkey FOREIGN KEY (format_id) REFERENCES public.prediction_formats(format_id);


--
-- Name: gameweeks gameweeks_stage_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gameweeks
    ADD CONSTRAINT gameweeks_stage_id_fkey FOREIGN KEY (stage_id) REFERENCES public.stages(stage_id);


--
-- Name: campaign_groups groups_campaign_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_groups
    ADD CONSTRAINT groups_campaign_id_fkey FOREIGN KEY (campaign_id) REFERENCES public.campaigns(campaign_id);


--
-- Name: campaign_groups groups_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.campaign_groups
    ADD CONSTRAINT groups_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(team_id);


--
-- Name: league_position_history league_position_history_campaign_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_position_history
    ADD CONSTRAINT league_position_history_campaign_id_fkey FOREIGN KEY (campaign_id) REFERENCES public.campaigns(campaign_id);


--
-- Name: league_position_history league_position_history_flavour_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_position_history
    ADD CONSTRAINT league_position_history_flavour_id_fkey FOREIGN KEY (flavour_id) REFERENCES public.flavours(flavour_id);


--
-- Name: league_position_history league_position_history_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.league_position_history
    ADD CONSTRAINT league_position_history_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(team_id);


--
-- Name: ml_components ml_components_engine_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_components
    ADD CONSTRAINT ml_components_engine_id_fkey FOREIGN KEY (engine_id) REFERENCES public.ml_engines(engine_id);


--
-- Name: ml_components ml_components_model_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_components
    ADD CONSTRAINT ml_components_model_id_fkey FOREIGN KEY (model_id) REFERENCES public.ml_model_profiles(profile_id);


--
-- Name: ml_components ml_components_modifier_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_components
    ADD CONSTRAINT ml_components_modifier_id_fkey FOREIGN KEY (modifier_id) REFERENCES public.ml_modifier(modifier_id);


--
-- Name: ml_engine_components ml_engine_components_component_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_components
    ADD CONSTRAINT ml_engine_components_component_id_fkey FOREIGN KEY (component_id) REFERENCES public.ml_components(component_id);


--
-- Name: ml_engine_components ml_engine_components_engine_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_components
    ADD CONSTRAINT ml_engine_components_engine_id_fkey FOREIGN KEY (engine_id) REFERENCES public.ml_engines(engine_id);


--
-- Name: ml_engine_prediction_scoring ml_engine_prediction_scoring_engine_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_prediction_scoring
    ADD CONSTRAINT ml_engine_prediction_scoring_engine_fkey FOREIGN KEY (engine_id) REFERENCES public.ml_engines(engine_id) ON DELETE CASCADE;


--
-- Name: ml_engine_prediction_scoring ml_engine_prediction_scoring_engine_prediction_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_prediction_scoring
    ADD CONSTRAINT ml_engine_prediction_scoring_engine_prediction_fkey FOREIGN KEY (engine_prediction_id) REFERENCES public.ml_engine_predictions(engine_prediction_id) ON DELETE CASCADE;


--
-- Name: ml_engine_prediction_scoring ml_engine_prediction_scoring_fixture_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_prediction_scoring
    ADD CONSTRAINT ml_engine_prediction_scoring_fixture_fkey FOREIGN KEY (fixture_id) REFERENCES public.fixtures(fixture_id) ON DELETE CASCADE;


--
-- Name: ml_engine_predictions ml_engine_predictions_engine_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_predictions
    ADD CONSTRAINT ml_engine_predictions_engine_id_fkey FOREIGN KEY (engine_id) REFERENCES public.ml_engines(engine_id) ON DELETE CASCADE;


--
-- Name: ml_engine_predictions ml_engine_predictions_fixture_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_predictions
    ADD CONSTRAINT ml_engine_predictions_fixture_id_fkey FOREIGN KEY (fixture_id) REFERENCES public.fixtures(fixture_id) ON DELETE CASCADE;


--
-- Name: ml_engine_predictions ml_engine_predictions_scheduler_run_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_engine_predictions
    ADD CONSTRAINT ml_engine_predictions_scheduler_run_id_fkey FOREIGN KEY (scheduler_run_id) REFERENCES public.ml_scheduler_runs(scheduler_run_id);


--
-- Name: ml_feature_runs ml_feature_runs_scheduler_run_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_feature_runs
    ADD CONSTRAINT ml_feature_runs_scheduler_run_id_fkey FOREIGN KEY (scheduler_run_id) REFERENCES public.ml_scheduler_runs(scheduler_run_id);


--
-- Name: ml_feature_snapshots ml_feature_snapshots_away_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_feature_snapshots
    ADD CONSTRAINT ml_feature_snapshots_away_team_id_fkey FOREIGN KEY (away_team_id) REFERENCES public.teams(team_id);


--
-- Name: ml_feature_snapshots ml_feature_snapshots_fixture_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_feature_snapshots
    ADD CONSTRAINT ml_feature_snapshots_fixture_id_fkey FOREIGN KEY (fixture_id) REFERENCES public.fixtures(fixture_id);


--
-- Name: ml_feature_snapshots ml_feature_snapshots_home_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_feature_snapshots
    ADD CONSTRAINT ml_feature_snapshots_home_team_id_fkey FOREIGN KEY (home_team_id) REFERENCES public.teams(team_id);


--
-- Name: ml_model_profiles ml_model_profiles_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_model_profiles
    ADD CONSTRAINT ml_model_profiles_profile_id_fkey FOREIGN KEY (profile_id) REFERENCES public.profiles(profile_id);


--
-- Name: ml_p10_profiles ml_p10_profiles_engine_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_p10_profiles
    ADD CONSTRAINT ml_p10_profiles_engine_id_fkey FOREIGN KEY (engine_id) REFERENCES public.ml_engines(engine_id);


--
-- Name: ml_p10_profiles ml_p10_profiles_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_p10_profiles
    ADD CONSTRAINT ml_p10_profiles_profile_id_fkey FOREIGN KEY (profile_id) REFERENCES public.profiles(profile_id) ON DELETE CASCADE;


--
-- Name: ml_prediction_runs ml_prediction_runs_scheduler_run_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_prediction_runs
    ADD CONSTRAINT ml_prediction_runs_scheduler_run_id_fkey FOREIGN KEY (scheduler_run_id) REFERENCES public.ml_scheduler_runs(scheduler_run_id);


--
-- Name: ml_profile_engine_history ml_profile_engine_history_engine_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_profile_engine_history
    ADD CONSTRAINT ml_profile_engine_history_engine_id_fkey FOREIGN KEY (engine_id) REFERENCES public.ml_engines(engine_id);


--
-- Name: ml_profile_engine_history ml_profile_engine_history_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_profile_engine_history
    ADD CONSTRAINT ml_profile_engine_history_profile_id_fkey FOREIGN KEY (profile_id) REFERENCES public.profiles(profile_id) ON DELETE CASCADE;


--
-- Name: ml_profile_predictions ml_profile_predictions_fixture_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_profile_predictions
    ADD CONSTRAINT ml_profile_predictions_fixture_id_fkey FOREIGN KEY (fixture_id) REFERENCES public.fixtures(fixture_id) ON DELETE CASCADE;


--
-- Name: ml_profile_predictions ml_profile_predictions_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_profile_predictions
    ADD CONSTRAINT ml_profile_predictions_profile_id_fkey FOREIGN KEY (profile_id) REFERENCES public.profiles(profile_id) ON DELETE CASCADE;


--
-- Name: ml_profile_predictions ml_profile_predictions_scheduler_run_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ml_profile_predictions
    ADD CONSTRAINT ml_profile_predictions_scheduler_run_id_fkey FOREIGN KEY (scheduler_run_id) REFERENCES public.ml_scheduler_runs(scheduler_run_id);


--
-- Name: teams_national national_teams_fa_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams_national
    ADD CONSTRAINT national_teams_fa_id_fkey FOREIGN KEY (fa_id) REFERENCES public.fa(fa_id);


--
-- Name: teams_national national_teams_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams_national
    ADD CONSTRAINT national_teams_team_id_fkey FOREIGN KEY (team_id) REFERENCES public.teams(team_id) ON DELETE CASCADE;


--
-- Name: prediction_scores prediction_scores_fixture_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prediction_scores
    ADD CONSTRAINT prediction_scores_fixture_id_fkey FOREIGN KEY (fixture_id) REFERENCES public.fixtures(fixture_id);


--
-- Name: prediction_scores prediction_scores_prediction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prediction_scores
    ADD CONSTRAINT prediction_scores_prediction_id_fkey FOREIGN KEY (prediction_id) REFERENCES public.predictions(prediction_id) ON DELETE CASCADE;


--
-- Name: predictions predictions_fixture_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.predictions
    ADD CONSTRAINT predictions_fixture_id_fkey FOREIGN KEY (fixture_id) REFERENCES public.fixtures(fixture_id) ON DELETE CASCADE;


--
-- Name: predictions predictions_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.predictions
    ADD CONSTRAINT predictions_profile_id_fkey FOREIGN KEY (profile_id) REFERENCES public.profiles(profile_id) ON DELETE CASCADE;


--
-- Name: user_profiles profiles_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT profiles_id_fkey FOREIGN KEY (auth_id) REFERENCES auth.users(id) ON DELETE CASCADE;


--
-- Name: results results_fixture_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.results
    ADD CONSTRAINT results_fixture_id_fkey FOREIGN KEY (fixture_id) REFERENCES public.fixtures(fixture_id);


--
-- Name: stages stages_campaign_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stages
    ADD CONSTRAINT stages_campaign_id_fkey FOREIGN KEY (campaign_id) REFERENCES public.campaigns(campaign_id);


--
-- Name: stages stages_competition_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stages
    ADD CONSTRAINT stages_competition_id_fkey FOREIGN KEY (competition_id) REFERENCES public.competitions(competition_id);


--
-- Name: stages stages_first_fixture_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stages
    ADD CONSTRAINT stages_first_fixture_id_fkey FOREIGN KEY (first_fixture_id) REFERENCES public.fixtures(fixture_id) ON DELETE SET NULL;


--
-- Name: stages stages_last_fixture_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stages
    ADD CONSTRAINT stages_last_fixture_id_fkey FOREIGN KEY (last_fixture_id) REFERENCES public.fixtures(fixture_id) ON DELETE SET NULL;


--
-- Name: ties ties_stage_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ties
    ADD CONSTRAINT ties_stage_id_fkey FOREIGN KEY (stage_id) REFERENCES public.stages(stage_id);


--
-- Name: ties ties_team_a_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ties
    ADD CONSTRAINT ties_team_a_id_fkey FOREIGN KEY (team_a_id) REFERENCES public.teams(team_id);


--
-- Name: ties ties_team_b_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ties
    ADD CONSTRAINT ties_team_b_id_fkey FOREIGN KEY (team_b_id) REFERENCES public.teams(team_id);


--
-- Name: ties ties_winner_team_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ties
    ADD CONSTRAINT ties_winner_team_id_fkey FOREIGN KEY (winner_team_id) REFERENCES public.teams(team_id);


--
-- Name: user_profiles user_profiles_favourite_domestic_team_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_favourite_domestic_team_fkey FOREIGN KEY (favourite_domestic_team_id) REFERENCES public.teams_domestic(team_id) ON DELETE SET NULL;


--
-- Name: user_profiles user_profiles_favourite_national_team_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_favourite_national_team_fkey FOREIGN KEY (favourite_national_team_id) REFERENCES public.teams_national(team_id) ON DELETE SET NULL;


--
-- Name: user_profiles user_profiles_profile_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_profiles
    ADD CONSTRAINT user_profiles_profile_id_fkey FOREIGN KEY (profile_id) REFERENCES public.profiles(profile_id) ON DELETE CASCADE;


--
-- Name: venues venues_city_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venues
    ADD CONSTRAINT venues_city_id_fkey FOREIGN KEY (city_id) REFERENCES public.cities(city_id);


--
-- Name: competitions Public can read competitions; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Public can read competitions" ON public.competitions FOR SELECT USING (true);


--
-- Name: flavour_competitions Public can read flavour_competitions; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Public can read flavour_competitions" ON public.flavour_competitions FOR SELECT USING (true);


--
-- Name: flavours Public can read flavours; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Public can read flavours" ON public.flavours FOR SELECT USING (true);


--
-- Name: prediction_formats Public can read prediction_formats; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Public can read prediction_formats" ON public.prediction_formats FOR SELECT USING (true);


--
-- Name: user_profiles Users can update own profile; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can update own profile" ON public.user_profiles FOR UPDATE USING ((profile_id = public.get_my_profile_id()));


--
-- Name: user_profiles Users can view own profile; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "Users can view own profile" ON public.user_profiles FOR SELECT USING ((profile_id = public.get_my_profile_id()));


--
-- Name: campaigns allow read flavours for authenticated users; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "allow read flavours for authenticated users" ON public.campaigns FOR SELECT TO authenticated USING (true);


--
-- Name: competitions allow read flavours for authenticated users; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "allow read flavours for authenticated users" ON public.competitions FOR SELECT TO authenticated USING (true);


--
-- Name: flavour_competitions allow read flavours for authenticated users; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "allow read flavours for authenticated users" ON public.flavour_competitions FOR SELECT TO authenticated USING (true);


--
-- Name: flavours allow read flavours for authenticated users; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "allow read flavours for authenticated users" ON public.flavours FOR SELECT TO authenticated USING (true);


--
-- Name: prediction_scores allow read flavours for authenticated users; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "allow read flavours for authenticated users" ON public.prediction_scores FOR SELECT TO authenticated USING (true);


--
-- Name: user_profiles authenticated can lookup usernames; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "authenticated can lookup usernames" ON public.user_profiles FOR SELECT TO authenticated USING (true);


--
-- Name: bot_profiles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.bot_profiles ENABLE ROW LEVEL SECURITY;

--
-- Name: campaign_groups; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.campaign_groups ENABLE ROW LEVEL SECURITY;

--
-- Name: campaigns; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.campaigns ENABLE ROW LEVEL SECURITY;

--
-- Name: predictions can insert own predictions only; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "can insert own predictions only" ON public.predictions FOR INSERT WITH CHECK (((profile_id = public.get_my_profile_id()) AND public.can_update_predictions(fixture_id, profile_id)));


--
-- Name: predictions can update own predictions only; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "can update own predictions only" ON public.predictions FOR UPDATE USING (((profile_id = public.get_my_profile_id()) AND public.can_update_predictions(fixture_id, profile_id))) WITH CHECK (((profile_id = public.get_my_profile_id()) AND public.can_update_predictions(fixture_id, profile_id)));


--
-- Name: cities; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.cities ENABLE ROW LEVEL SECURITY;

--
-- Name: cities cities_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY cities_select_authenticated ON public.cities FOR SELECT TO authenticated USING (true);


--
-- Name: club_invites club admins can create invites; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY "club admins can create invites" ON public.club_invites FOR INSERT TO authenticated WITH CHECK (public.is_club_admin(club_id));


--
-- Name: club_invites; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.club_invites ENABLE ROW LEVEL SECURITY;

--
-- Name: club_invites club_invites_select; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY club_invites_select ON public.club_invites FOR SELECT TO authenticated USING (((recipient_profile_id = public.get_my_profile_id()) OR public.is_club_admin(club_id)));


--
-- Name: club_invites club_invites_update; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY club_invites_update ON public.club_invites FOR UPDATE TO authenticated USING (((recipient_profile_id = public.get_my_profile_id()) OR public.is_club_admin(club_id))) WITH CHECK (((recipient_profile_id = public.get_my_profile_id()) OR public.is_club_admin(club_id)));


--
-- Name: club_memberships; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.club_memberships ENABLE ROW LEVEL SECURITY;

--
-- Name: club_memberships club_memberships_insert_self; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY club_memberships_insert_self ON public.club_memberships FOR INSERT TO authenticated WITH CHECK ((profile_id = public.get_my_profile_id()));


--
-- Name: club_memberships club_memberships_read_member_clubs; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY club_memberships_read_member_clubs ON public.club_memberships FOR SELECT TO authenticated USING ((club_id IN ( SELECT public.get_my_club_ids() AS get_my_club_ids)));


--
-- Name: club_ownership_transfer_requests; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.club_ownership_transfer_requests ENABLE ROW LEVEL SECURITY;

--
-- Name: club_profiles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.club_profiles ENABLE ROW LEVEL SECURITY;

--
-- Name: club_profiles club_profiles_insert_own; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY club_profiles_insert_own ON public.club_profiles FOR INSERT TO authenticated WITH CHECK ((created_by = public.get_my_profile_id()));


--
-- Name: club_profiles club_profiles_read_all; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY club_profiles_read_all ON public.club_profiles FOR SELECT USING (true);


--
-- Name: competitions; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.competitions ENABLE ROW LEVEL SECURITY;

--
-- Name: confederations; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.confederations ENABLE ROW LEVEL SECURITY;

--
-- Name: confederations confederations_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY confederations_select_authenticated ON public.confederations FOR SELECT TO authenticated USING (true);


--
-- Name: countries; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.countries ENABLE ROW LEVEL SECURITY;

--
-- Name: countries countries_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY countries_select_authenticated ON public.countries FOR SELECT TO authenticated USING (true);


--
-- Name: fa; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.fa ENABLE ROW LEVEL SECURITY;

--
-- Name: fa fa_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY fa_select_authenticated ON public.fa FOR SELECT TO authenticated USING (true);


--
-- Name: fixtures; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.fixtures ENABLE ROW LEVEL SECURITY;

--
-- Name: fixtures fixtures_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY fixtures_select_authenticated ON public.fixtures FOR SELECT TO authenticated USING (true);


--
-- Name: flavour_competitions; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.flavour_competitions ENABLE ROW LEVEL SECURITY;

--
-- Name: flavours; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.flavours ENABLE ROW LEVEL SECURITY;

--
-- Name: gameweeks; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.gameweeks ENABLE ROW LEVEL SECURITY;

--
-- Name: gameweeks gameweeks_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY gameweeks_select_authenticated ON public.gameweeks FOR SELECT TO authenticated USING (true);


--
-- Name: leaderboard; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.leaderboard ENABLE ROW LEVEL SECURITY;

--
-- Name: league_position_history; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.league_position_history ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_components; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_components ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_engine_components; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_engine_components ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_engine_prediction_scoring; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_engine_prediction_scoring ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_engine_predictions; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_engine_predictions ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_engines; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_engines ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_feature_runs; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_feature_runs ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_feature_snapshots; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_feature_snapshots ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_model_profiles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_model_profiles ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_modifier; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_modifier ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_p10_profiles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_p10_profiles ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_prediction_runs; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_prediction_runs ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_profile_engine_history; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_profile_engine_history ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_profile_predictions; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_profile_predictions ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_scheduler_runs; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_scheduler_runs ENABLE ROW LEVEL SECURITY;

--
-- Name: ml_team_matchup_history; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ml_team_matchup_history ENABLE ROW LEVEL SECURITY;

--
-- Name: prediction_formats; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.prediction_formats ENABLE ROW LEVEL SECURITY;

--
-- Name: prediction_scores; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.prediction_scores ENABLE ROW LEVEL SECURITY;

--
-- Name: predictions; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.predictions ENABLE ROW LEVEL SECURITY;

--
-- Name: profiles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

--
-- Name: profiles profiles_insert_club; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY profiles_insert_club ON public.profiles FOR INSERT TO authenticated WITH CHECK ((profile_type = 'club'::public.profile_type));


--
-- Name: profiles profiles_read_all; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY profiles_read_all ON public.profiles FOR SELECT USING (true);


--
-- Name: results; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.results ENABLE ROW LEVEL SECURITY;

--
-- Name: results results_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY results_select_authenticated ON public.results FOR SELECT TO authenticated USING (true);


--
-- Name: scoring_criteria; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.scoring_criteria ENABLE ROW LEVEL SECURITY;

--
-- Name: signup_codes; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.signup_codes ENABLE ROW LEVEL SECURITY;

--
-- Name: stages; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.stages ENABLE ROW LEVEL SECURITY;

--
-- Name: stages stages_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY stages_select_authenticated ON public.stages FOR SELECT TO authenticated USING (true);


--
-- Name: teams; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.teams ENABLE ROW LEVEL SECURITY;

--
-- Name: teams_domestic; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.teams_domestic ENABLE ROW LEVEL SECURITY;

--
-- Name: teams_national; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.teams_national ENABLE ROW LEVEL SECURITY;

--
-- Name: teams teams_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY teams_select_authenticated ON public.teams FOR SELECT TO authenticated USING (true);


--
-- Name: ties; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.ties ENABLE ROW LEVEL SECURITY;

--
-- Name: ties ties_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY ties_select_authenticated ON public.ties FOR SELECT TO authenticated USING (true);


--
-- Name: user_profiles; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.user_profiles ENABLE ROW LEVEL SECURITY;

--
-- Name: venues; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.venues ENABLE ROW LEVEL SECURITY;

--
-- Name: venues venues_select_authenticated; Type: POLICY; Schema: public; Owner: -
--

CREATE POLICY venues_select_authenticated ON public.venues FOR SELECT TO authenticated USING (true);


--
-- Name: supabase_realtime campaigns; Type: PUBLICATION TABLE; Schema: public; Owner: -
--

ALTER PUBLICATION supabase_realtime ADD TABLE ONLY public.campaigns;


--
-- Name: supabase_realtime club_invites; Type: PUBLICATION TABLE; Schema: public; Owner: -
--

ALTER PUBLICATION supabase_realtime ADD TABLE ONLY public.club_invites;


--
-- Name: supabase_realtime fixtures; Type: PUBLICATION TABLE; Schema: public; Owner: -
--

ALTER PUBLICATION supabase_realtime ADD TABLE ONLY public.fixtures;


--
-- Name: supabase_realtime gameweeks; Type: PUBLICATION TABLE; Schema: public; Owner: -
--

ALTER PUBLICATION supabase_realtime ADD TABLE ONLY public.gameweeks;


--
-- Name: supabase_realtime prediction_scores; Type: PUBLICATION TABLE; Schema: public; Owner: -
--

ALTER PUBLICATION supabase_realtime ADD TABLE ONLY public.prediction_scores;


--
-- Name: supabase_realtime predictions; Type: PUBLICATION TABLE; Schema: public; Owner: -
--

ALTER PUBLICATION supabase_realtime ADD TABLE ONLY public.predictions;


--
-- Name: supabase_realtime results; Type: PUBLICATION TABLE; Schema: public; Owner: -
--

ALTER PUBLICATION supabase_realtime ADD TABLE ONLY public.results;


--
-- Name: supabase_realtime stages; Type: PUBLICATION TABLE; Schema: public; Owner: -
--

ALTER PUBLICATION supabase_realtime ADD TABLE ONLY public.stages;


--
-- Name: supabase_realtime ties; Type: PUBLICATION TABLE; Schema: public; Owner: -
--

ALTER PUBLICATION supabase_realtime ADD TABLE ONLY public.ties;


--
-- PostgreSQL database dump complete
--

\unrestrict VMFd72BftKcDIKyLmT12Gb1oLRvdrfheQfP7oSnbiKjDvuT3gbxrRGAeGSN98V1


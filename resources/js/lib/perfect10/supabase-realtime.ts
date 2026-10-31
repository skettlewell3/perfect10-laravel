import { createClient } from '@supabase/supabase-js';

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL;
const supabasePublishableKey = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY;

if (!supabaseUrl || !supabasePublishableKey) {
    throw new Error(
        'Missing Supabase Realtime configuration. Check the VITE_SUPABASE_URL and VITE_SUPABASE_PUBLISHABLE_KEY environment variables.',
    );
}

export const supabaseRealtime = createClient(
    supabaseUrl,
    supabasePublishableKey,
    {
        auth: {
            autoRefreshToken: false,
            persistSession: false,
            detectSessionInUrl: false,
        },
    },
);

export const PERFECT10_FIXTURE_TOPIC = 'perfect10:public:fixtures';

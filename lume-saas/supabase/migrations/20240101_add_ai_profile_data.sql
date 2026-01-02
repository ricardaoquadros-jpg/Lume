alter table public.work_profiles add column if not exists ai_profile_data jsonb default '{}'::jsonb;

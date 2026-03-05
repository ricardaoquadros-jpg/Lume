-- Create calendar_event_type enum
DO $$ BEGIN
    CREATE TYPE "calendar_event_type" AS ENUM ('WORK', 'VACATION', 'HOLIDAY');
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- Create calendar_events table
CREATE TABLE IF NOT EXISTS "calendar_events" (
    "id" uuid DEFAULT gen_random_uuid() NOT NULL,
    "user_id" uuid NOT NULL,
    "date" DATE NOT NULL,
    "type" "calendar_event_type" NOT NULL,
    "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated_at" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "calendar_events_pkey" PRIMARY KEY ("id")
);

-- Create index and unique constraint
CREATE UNIQUE INDEX IF NOT EXISTS "calendar_events_user_id_date_key" ON "calendar_events"("user_id", "date");
CREATE INDEX IF NOT EXISTS "calendar_events_user_id_date_idx" ON "calendar_events"("user_id", "date");

-- Add foreign key constraint to auth.users (Supabase convention)
DO $$ BEGIN
    ALTER TABLE "calendar_events" ADD CONSTRAINT "calendar_events_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "auth"."users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
EXCEPTION
    WHEN duplicate_object THEN null;
END $$;

-- Enable RLS (Recommended for Supabase)
ALTER TABLE public.calendar_events ENABLE ROW LEVEL SECURITY;

-- Add RLS Policies
DROP POLICY IF EXISTS "Users can view own calendar events" ON public.calendar_events;
DROP POLICY IF EXISTS "Users can insert own calendar events" ON public.calendar_events;
DROP POLICY IF EXISTS "Users can update own calendar events" ON public.calendar_events;
DROP POLICY IF EXISTS "Users can delete own calendar events" ON public.calendar_events;

CREATE POLICY "Users can view own calendar events" ON public.calendar_events FOR SELECT USING (auth.uid() = user_id);
CREATE POLICY "Users can insert own calendar events" ON public.calendar_events FOR INSERT WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Users can update own calendar events" ON public.calendar_events FOR UPDATE USING (auth.uid() = user_id);
CREATE POLICY "Users can delete own calendar events" ON public.calendar_events FOR DELETE USING (auth.uid() = user_id);

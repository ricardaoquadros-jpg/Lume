-- Investment Contributions table for tracking deposits/aportes over time
CREATE TABLE IF NOT EXISTS public.investment_contributions (
  id uuid default uuid_generate_v4() primary key,
  investment_id uuid references public.investments(id) on delete cascade not null,
  user_id uuid references auth.users(id) on delete cascade not null,
  amount decimal(12, 2) not null,
  contribution_date date not null default current_date,
  notes text,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Add start_date, invested_value, benchmark, and yield_rate to investments table
ALTER TABLE public.investments 
ADD COLUMN IF NOT EXISTS start_date date default current_date,
ADD COLUMN IF NOT EXISTS invested_value decimal(12, 2) default 0.00,
ADD COLUMN IF NOT EXISTS benchmark text, -- 'CDI', 'IPCA', 'SELIC', 'PRE'
ADD COLUMN IF NOT EXISTS yield_rate decimal(10, 2); -- e.g. 100.00 (%), 5.00 (%)

-- Enable RLS
ALTER TABLE public.investment_contributions ENABLE ROW LEVEL SECURITY;

-- RLS Policies for contributions
DROP POLICY IF EXISTS "Users can view own contributions" ON public.investment_contributions;
DROP POLICY IF EXISTS "Users can insert own contributions" ON public.investment_contributions;
DROP POLICY IF EXISTS "Users can update own contributions" ON public.investment_contributions;
DROP POLICY IF EXISTS "Users can delete own contributions" ON public.investment_contributions;

CREATE POLICY "Users can view own contributions" ON public.investment_contributions FOR SELECT USING (auth.uid() = user_id);
CREATE POLICY "Users can insert own contributions" ON public.investment_contributions FOR INSERT WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Users can update own contributions" ON public.investment_contributions FOR UPDATE USING (auth.uid() = user_id);
CREATE POLICY "Users can delete own contributions" ON public.investment_contributions FOR DELETE USING (auth.uid() = user_id);

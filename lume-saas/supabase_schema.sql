-- Enable UUID extension
create extension if not exists "uuid-ossp";

-- Users table (managed by Supabase Auth, but we can extend public.users if needed, 
-- or just link via user_id in other tables. Supabase Auth handles 'auth.users' table.
-- We will reference auth.users.id)

-- Work Profiles
create table public.work_profiles (
  id uuid default uuid_generate_v4() primary key,
  user_id uuid references auth.users(id) on delete cascade not null unique,
  salary decimal(10, 2) default 0.00,
  payment_type text default 'monthly', -- 'monthly', 'weekly'
  work_days jsonb default '[]', -- [1, 2, 3, 4, 5]
  work_start text default '09:00',
  work_end text default '18:00',
  has_interval boolean default true,
  interval_start text default '12:00',
  interval_end text default '13:00',
  initial_balance decimal(10, 2) default 0.00,
  ai_profile_data jsonb default '{}'::jsonb,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null,
  updated_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Transactions
create table public.transactions (
  id uuid default uuid_generate_v4() primary key,
  user_id uuid references auth.users(id) on delete cascade not null,
  type text not null check (type in ('income', 'expense')),
  description text not null,
  amount decimal(10, 2) not null,
  category text default 'Outros',
  transaction_date date default current_date not null,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Recurring Transactions
create table public.recurring_transactions (
  id uuid default uuid_generate_v4() primary key,
  user_id uuid references auth.users(id) on delete cascade not null,
  type text not null check (type in ('income', 'expense')),
  description text not null,
  amount decimal(10, 2) not null,
  category text default 'Outros',
  frequency text default 'monthly',
  day_of_month int default 1,
  active boolean default true,
  last_executed date,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- Investments
create table public.investments (
  id uuid default uuid_generate_v4() primary key,
  user_id uuid references auth.users(id) on delete cascade not null,
  name text not null,
  type text default 'Outro',
  current_value decimal(12, 2) default 0.00,
  active boolean default true,
  last_update date default current_date,
  created_at timestamp with time zone default timezone('utc'::text, now()) not null
);

-- RLS Policies (Row Level Security)
alter table public.work_profiles enable row level security;
alter table public.transactions enable row level security;
alter table public.recurring_transactions enable row level security;
alter table public.investments enable row level security;

-- Policy: Users can only see their own data
create policy "Users can view own profile" on public.work_profiles for select using (auth.uid() = user_id);
create policy "Users can update own profile" on public.work_profiles for update using (auth.uid() = user_id);
create policy "Users can insert own profile" on public.work_profiles for insert with check (auth.uid() = user_id);

create policy "Users can view own transactions" on public.transactions for select using (auth.uid() = user_id);
create policy "Users can insert own transactions" on public.transactions for insert with check (auth.uid() = user_id);
create policy "Users can update own transactions" on public.transactions for update using (auth.uid() = user_id);
create policy "Users can delete own transactions" on public.transactions for delete using (auth.uid() = user_id);

create policy "Users can view own recurring" on public.recurring_transactions for select using (auth.uid() = user_id);
create policy "Users can insert own recurring" on public.recurring_transactions for insert with check (auth.uid() = user_id);
create policy "Users can update own recurring" on public.recurring_transactions for update using (auth.uid() = user_id);
create policy "Users can delete own recurring" on public.recurring_transactions for delete using (auth.uid() = user_id);

create policy "Users can view own investments" on public.investments for select using (auth.uid() = user_id);
create policy "Users can insert own investments" on public.investments for insert with check (auth.uid() = user_id);
create policy "Users can update own investments" on public.investments for update using (auth.uid() = user_id);
create policy "Users can delete own investments" on public.investments for delete using (auth.uid() = user_id);

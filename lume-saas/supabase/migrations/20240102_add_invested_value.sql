-- Add invested_value column to investments table
ALTER TABLE public.investments 
ADD COLUMN IF NOT EXISTS invested_value decimal(12, 2) default 0.00;

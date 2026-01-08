-- Add display_order column for manual ordering of transactions within the same date
ALTER TABLE public.transactions 
ADD COLUMN display_order integer DEFAULT 0;

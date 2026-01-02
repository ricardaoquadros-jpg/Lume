-- Add transcription column to transactions table
ALTER TABLE public.transactions 
ADD COLUMN transcription text;

-- Add transcription column to recurring_transactions table (optional, but good for consistency if created via voice)
ALTER TABLE public.recurring_transactions
ADD COLUMN transcription text;

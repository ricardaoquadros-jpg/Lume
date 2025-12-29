-- Add transcription column to transactions table
ALTER TABLE transactions ADD COLUMN transcription TEXT DEFAULT NULL;

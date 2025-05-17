USE docnow_db;

-- Add medical information columns to patients table
ALTER TABLE patients 
ADD COLUMN blood_type VARCHAR(10),
ADD COLUMN allergies TEXT,
ADD COLUMN current_medications TEXT,
ADD COLUMN medical_history TEXT; 
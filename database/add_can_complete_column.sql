-- Add can_complete column to menu_access table
ALTER TABLE menu_access ADD COLUMN can_complete TINYINT(1) DEFAULT 0 AFTER can_delete;

-- Update existing records to set can_complete = 0
UPDATE menu_access SET can_complete = 0 WHERE can_complete IS NULL;
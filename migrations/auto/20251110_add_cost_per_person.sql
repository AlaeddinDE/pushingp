-- MIGRATION 2025-11-10: add cost_per_person to events
-- Adds estimated cost per person field for events

ALTER TABLE events ADD COLUMN IF NOT EXISTS cost_per_person DECIMAL(10,2) DEFAULT 0.00 AFTER cost;

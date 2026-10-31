ALTER TABLE quick_notes
    ADD COLUMN title VARCHAR(200) NOT NULL DEFAULT '' AFTER project_id,
    ADD COLUMN color ENUM('yellow','blue','green') NOT NULL DEFAULT 'yellow' AFTER body;

ALTER TABLE quick_notes MODIFY COLUMN color ENUM('yellow','blue','green','orange') NOT NULL DEFAULT 'yellow';
UPDATE quick_notes SET color='orange' WHERE color='blue';
ALTER TABLE quick_notes MODIFY COLUMN color ENUM('yellow','orange','green') NOT NULL DEFAULT 'yellow';

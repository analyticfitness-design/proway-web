-- 012_kanban_order.sql — Add kanban ordering support to projects
ALTER TABLE projects ADD COLUMN kanban_order INT NOT NULL DEFAULT 0;
CREATE INDEX idx_projects_status_order ON projects (status, kanban_order);

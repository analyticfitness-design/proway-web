-- ============================================================
-- ProWay Lab — Admin user seed
-- Run once on production database
-- ============================================================

USE prowaylab_db;

INSERT INTO admins (username, password_hash, name, role)
VALUES (
    'daniel.esparza',
    '$2y$12$T5ey6PABUkNeIG8j9M6RU.POpq2971mqr.R7D8iNyhRPF0J.8RexC',
    'Daniel Esparza',
    'superadmin'
)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    name          = VALUES(name);

CREATE DATABASE IF NOT EXISTS mmd_management
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE mmd_management;

CREATE TABLE IF NOT EXISTS members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL UNIQUE,
    position VARCHAR(80) NOT NULL DEFAULT 'Member',
    access_role VARCHAR(32) NOT NULL DEFAULT 'member',
    class_name VARCHAR(80) NULL,
    birth_date DATE NULL,
    team_role VARCHAR(80) NULL,
    phone VARCHAR(32) NULL,
    joined_on DATE NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO members (full_name, position, access_role) VALUES
    ('Hugo Delvian Maheswara', 'Coordinator', 'coordinator'),
    ('Reihan M.A.', 'Assistant coordinator', 'assistant_coordinator'),
    ('Anastasya', 'Member', 'member'),
    ('Rindi', 'Member', 'member'),
    ('Ahsan', 'Member', 'member'),
    ('Arthur', 'Coordinator', 'coordinator'),
    ('Andrian', 'Member', 'member'),
    ('Bilqis', 'Member', 'member'),
    ('Aline', 'Member', 'member'),
    ('Shaka', 'Head coordinator', 'head_coordinator'),
    ('Deni', 'Member', 'member'),
    ('Tyas', 'Assistant coordinator', 'assistant_coordinator')
ON DUPLICATE KEY UPDATE
    position = VALUES(position),
    access_role = VALUES(access_role);

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT UNSIGNED NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    CONSTRAINT fk_users_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(160) NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(160) NOT NULL,
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_events_date (event_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'Planning',
    due_date DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_projects_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_projects_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_members (
    project_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    assigned_role VARCHAR(80) NOT NULL DEFAULT 'Team member',
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, member_id),
    CONSTRAINT fk_project_members_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_project_members_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO events (event_name, event_date, start_time, end_time, location, description)
SELECT 'MMD Photography Workshop', '2027-01-16', '09:00:00', '12:00:00',
       'School media room', 'Practice event photography and camera basics.'
WHERE NOT EXISTS (
    SELECT 1 FROM events WHERE event_name = 'MMD Photography Workshop'
);

INSERT INTO events (event_name, event_date, start_time, end_time, location, description)
SELECT 'School Arts Festival Coverage', '2027-02-06', '08:00:00', '16:00:00',
       'School campus', 'Document performances and publish selected photos to the Link Foto archive.'
WHERE NOT EXISTS (
    SELECT 1 FROM events WHERE event_name = 'School Arts Festival Coverage'
);

INSERT INTO events (event_name, event_date, start_time, end_time, location, description)
SELECT 'MMD Portfolio Review', '2027-02-20', '13:00:00', '15:00:00',
       'School media room', 'Review recent work and prepare a team portfolio.'
WHERE NOT EXISTS (
    SELECT 1 FROM events WHERE event_name = 'MMD Portfolio Review'
);

INSERT INTO projects (event_id, title, description, status, due_date)
SELECT e.id, 'Photography Workshop Gallery', 'Select and organize photographs from the workshop.',
       'Planning', '2027-01-23'
FROM events e
WHERE e.event_name = 'MMD Photography Workshop'
  AND NOT EXISTS (
      SELECT 1 FROM projects WHERE title = 'Photography Workshop Gallery'
  );

INSERT INTO projects (event_id, title, description, status, due_date)
SELECT e.id, 'Arts Festival Photo Coverage', 'Capture festival highlights and prepare photos for the Link Foto archive.',
       'Assigned', '2027-02-13'
FROM events e
WHERE e.event_name = 'School Arts Festival Coverage'
  AND NOT EXISTS (
      SELECT 1 FROM projects WHERE title = 'Arts Festival Photo Coverage'
  );

INSERT INTO projects (event_id, title, description, status, due_date)
SELECT e.id, 'MMD Team Portfolio', 'Curate approved event photographs into a team portfolio.',
       'Planning', '2027-02-27'
FROM events e
WHERE e.event_name = 'MMD Portfolio Review'
  AND NOT EXISTS (
      SELECT 1 FROM projects WHERE title = 'MMD Team Portfolio'
  );

INSERT IGNORE INTO project_members (project_id, member_id, assigned_role)
SELECT p.id, m.id, assignments.assigned_role
FROM (
    SELECT 'Photography Workshop Gallery' AS project_title, 'Hugo Delvian Maheswara' AS member_name, 'Team member' AS assigned_role
    UNION ALL
    SELECT 'Photography Workshop Gallery', 'Anastasya', 'Team member'
    UNION ALL
    SELECT 'Arts Festival Photo Coverage', 'Reihan M.A.', 'Team member'
    UNION ALL
    SELECT 'Arts Festival Photo Coverage', 'Aline', 'Team member'
    UNION ALL
    SELECT 'MMD Team Portfolio', 'Shaka', 'Team member'
    UNION ALL
    SELECT 'MMD Team Portfolio', 'Arthur', 'Team member'
) AS assignments
JOIN projects p ON p.title = assignments.project_title
JOIN members m ON m.full_name = assignments.member_name;

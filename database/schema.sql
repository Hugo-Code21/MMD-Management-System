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

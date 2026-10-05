ALTER TABLE members
    ADD COLUMN birth_date DATE NULL AFTER class_name,
    ADD COLUMN team_role VARCHAR(80) NULL AFTER birth_date;

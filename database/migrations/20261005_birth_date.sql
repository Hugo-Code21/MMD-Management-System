ALTER TABLE members
    ADD COLUMN birth_date DATE NULL AFTER class_name;

UPDATE members
SET birth_date = STR_TO_DATE(CONCAT(birth_year, '-01-01'), '%Y-%m-%d')
WHERE birth_year BETWEEN 1900 AND YEAR(CURDATE());

ALTER TABLE members
    DROP COLUMN birth_year;

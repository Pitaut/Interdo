-- Migration 001: Add clients table, client_id column, index and FK
-- Usage:
-- mysql -u root -p agenda_db < migrations/001_add_clients_and_client_id.sql
-- Or import via phpMyAdmin

-- 1) Create clients table if missing
CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `prenom` VARCHAR(100) DEFAULT '',
  `nom` VARCHAR(100) DEFAULT '',
  `tel_mobile` VARCHAR(30) DEFAULT '',
  `tel_fixe` VARCHAR(30) DEFAULT '',
  `adresse` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Add client_id column to rendez_vous (MySQL 8.0.16+ supports IF NOT EXISTS)
ALTER TABLE `rendez_vous` ADD COLUMN IF NOT EXISTS `client_id` INT NULL AFTER `id_technicien`;

-- 3) Add an index on client_id if it does not already exist
-- The following uses information_schema to conditionally create the index
SET @schema_name = DATABASE();
SELECT COUNT(*) INTO @idx_count
  FROM information_schema.statistics
  WHERE table_schema = @schema_name AND table_name = 'rendez_vous' AND index_name = 'idx_rendez_vous_client_id';

SET @sql = IF(@idx_count = 0, 'ALTER TABLE `rendez_vous` ADD INDEX `idx_rendez_vous_client_id` (`client_id`)', 'SELECT "index_exists"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Add foreign key constraint if missing
SELECT COUNT(*) INTO @fk_count
  FROM information_schema.key_column_usage
  WHERE table_schema = @schema_name AND table_name = 'rendez_vous' AND column_name = 'client_id' AND referenced_table_name = 'clients';

SET @sql2 = IF(@fk_count = 0, 'ALTER TABLE `rendez_vous` ADD CONSTRAINT `fk_rendez_vous_client_id` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT "fk_exists"');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- End of migration

-- Migration: add id_technicien to rendez_vous
-- Exécutez ce fichier avec le client MySQL / phpMyAdmin ou via le script PHP fourni.

ALTER TABLE `rendez_vous`
  ADD COLUMN `id_technicien` INT DEFAULT NULL AFTER `lieu`;

CREATE INDEX `idx_id_technicien` ON `rendez_vous`(`id_technicien`);

-- Si vous utilisez InnoDB et souhaitez une contrainte FK, décommentez et adaptez:
-- ALTER TABLE `rendez_vous`
--   ADD CONSTRAINT `fk_rv_technicien` FOREIGN KEY (`id_technicien`) REFERENCES `techniciens`(`id`) ON DELETE SET NULL;

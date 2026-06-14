-- techniciens_seed.sql
-- Jeu de données d'exemple pour la table `techniciens`
-- IMPORTANT : ajustez le nom de la base si besoin (config.php définit DB_NAME)

USE `agenda_db`;

INSERT INTO `techniciens` (nom, prenom, email, adresse, code_postal, ville, pays, telephone_fixe, telephone_mobile, date_entree, date_sortie, actif, couleur, salaire_horaire)
VALUES
('Dupont','Jean','jean.dupont@example.com','10 rue de la Paix','75001','Paris','France','0123456789','0612345678','2020-01-15', NULL, 1, '#667eea', 18.50),
('Durant','Marie','marie.durant@example.com','5 avenue Victor Hugo','69002','Lyon','France', NULL,'0678901234','2021-06-01', NULL, 1, '#4caf50', 20.00),
('Ben','Ahmed','ahmed.ben@example.com','12 rue des Fleurs','13001','Marseille','France','041234567','0698765432','2019-09-02', NULL, 1, '#ff9800', 22.00),
('Martin','Sophie','sophie.martin@example.com','24 rue de l\'Espoir','49100','Angers','France','0248567890','0601020304','2023-03-10', NULL, 1, '#f44336', 19.75);

-- Fin du fichier

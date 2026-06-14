-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 23 nov. 2025 à 21:45
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `agenda_interventions`
--

-- --------------------------------------------------------

--
-- Structure de la table `forfaits_vendus`
--

DROP TABLE IF EXISTS `forfaits_vendus`;
CREATE TABLE IF NOT EXISTS `forfaits_vendus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `type_forfait_id` int NOT NULL,
  `heures_total` decimal(10,2) NOT NULL,
  `heures_restantes` decimal(10,2) NOT NULL,
  `tarif` decimal(10,2) NOT NULL,
  `intervenant_id` int DEFAULT NULL,
  `signature_client` blob,
  `date_signature` datetime DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `type_forfait_id` (`type_forfait_id`),
  KEY `intervenant_id` (`intervenant_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

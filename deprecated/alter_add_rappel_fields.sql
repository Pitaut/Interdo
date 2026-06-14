-- Script SQL pour ajouter les colonnes de suivi des rappels clients
-- À exécuter manuellement dans phpMyAdmin ou votre client MySQL

USE agenda_db;

-- Ajouter les colonnes de suivi des rappels
ALTER TABLE clients 
ADD COLUMN date_dernier_rappel DATETIME DEFAULT NULL,
ADD COLUMN commentaire_rappel TEXT DEFAULT NULL;

-- Script de mise à jour de la base de données kasta
-- À exécuter pour ajouter le champ motif_refus à la table membre

USE kasta;

-- Ajout du champ motif_refus pour stocker le motif de refus d'un compte
ALTER TABLE membre
ADD COLUMN motif_refus TEXT NULL
AFTER date_statut;

-- Vérification : afficher la structure de la table membre
DESCRIBE membre;

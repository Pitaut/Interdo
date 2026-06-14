# Flux de Clôture d'Intervention - Documentation

**Version**: 3.0  
**Date**: 21 décembre 2025  

## Objectif
Permettre la clôture d'une intervention en respectant l'ordre suivant :
1. Confirmation des heures de début et fin
2. Choix d'arrondi du temps
3. **Vérification des heures disponibles AVANT la signature**
4. Signature électronique appropriée (vente forfait OU clôture simple)

**⚠️ Important** : Le calcul bonus/malus est réalisé du **point de vue CLIENT** :
- Arrondi supérieur = BONUS pour le client (temps facturé > temps réel)
- Arrondi inférieur = MALUS pour le client (temps facturé < temps réel)

## Flux Complet

### Étape 1 : Clic sur le bouton "Clôturer"
- **Fonction** : `clotureIntervention(rdvId)`
- **Action** : Récupère les heures du rendez-vous et affiche le modal de confirmation

### Étape 2 : Modal Confirmation des Heures
- **Fonction** : `afficherModalCloture(rdvId, heureDebut, heureFin)`
- **Affichage** :
  - Champ heure de début (modifiable)
  - Champ heure de fin (modifiable)
  - Calcul automatique de la durée en temps réel
- **Boutons** :
  - "Confirmer" → Passe à l'étape 3
  - "Annuler" → Ferme le modal

### Étape 3 : Validation et Calcul d'Arrondi
- **Fonction** : `confirmerClotureAvecHeures(rdvId)`
- **Actions** :
  - Valide que heure fin > heure début
  - Calcule la durée réelle en heures
  - Calcule l'arrondi supérieur (30min) et inférieur
  - Si différence > 0 → Affiche modal arrondi
  - Si différence = 0 → Passe directement à l'étape 5

### Étape 4 : Modal Arrondi (si nécessaire)
- **Fonction** : `repondreArrondi(rdvId, heureDebut, heureFin, appliquerArrondi)`
- **Affichage** :
  - Durée réelle (ex: 1h20 = 80 min)
  - Arrondi supérieur (ex: 1h30 = 90 min = +10min bonus client)
  - Arrondi inférieur (ex: 1h00 = 60 min = -20min malus client)
  - **🎉 Affichage en minutes** pour plus de lisibilité
- **Boutons** :
  - "OUI" → Arrondi supérieur avec bonus
  - "NON" → Arrondi inférieur avec malus

### Étape 5 : **NOUVEAU - Vérification Heures Disponibles**
- **Fonction** : `verifierHeuresAvantSignature(rdvId, heureDebut, heureFin, appliquerArrondi)`
- **Action** : Appel API `check_heures` (sans clôturer)
- **API** : `api/interventions.php?action=check_heures`
  - Calcule la durée (avec arrondi si activé)
  - Récupère le total des heures restantes sur les forfaits du client
  - Compare et retourne `heures_suffisantes: true/false`

#### CAS A : Heures SUFFISANTES ✅
1. **Fonction** : `procederCloture(rdvId, heureDebut, heureFin, appliquerArrondi)`
2. **Modal Signature Clôture** : `openSignatureClotureModal()`
   - **Titre** : "Signature du client"
   - **Message** : 
     ```
     Clôture de l'intervention
     Durée réelle : 1h20
     Heures qui seront décomptées : 1.50h
     Veuillez signer pour confirmer la fin de l'intervention
     ```
   - **Canvas** : Signature tactile
   - **Boutons** : "Effacer" | "✓ Valider"

3. **Validation** : `validateSignatureCloture()`
   - Vérifie que la signature n'est pas vide
   - Convertit en base64 PNG
   - Appelle `executerCloture(signatureData)`

4. **Clôture** : `executerCloture(signatureData)`
   - **API** : `api/interventions.php?action=close_forfait`
   - **Données envoyées** :
     - `rendez_vous_id`
     - `heure_debut`, `heure_fin`
     - `appliquer_arrondi`
     - `signature_client` (base64)
   - **Actions backend** :
     - Décompte les heures sur les forfaits (FIFO)
     - Enregistre dans `historique_consommation`
     - Met à jour le rendez-vous (statut = 'termine', signature stockée)
   - **Résultat** : Modal succès avec récapitulatif

#### CAS B : Heures INSUFFISANTES ❌
1. **Fonction** : `openVenteForfaitDepuisCloture(clientId, rdvId, errorMsg, ...)`
2. **Modal Vente Forfait**
   - **Titre** : "Forfait insuffisant"
   - **Alerte** : Message d'erreur (ex: "Il manque 0.5h pour clôturer")
   - **Compteur** : Affiche temps nécessaire vs temps sélectionné
   - **Liste** : Forfaits disponibles (cliquables)
   - **Panier** : Forfaits sélectionnés avec total heures et prix
   - **Boutons** :
     - "✓ Valider et clôturer" (activé quand panier couvre le besoin)
     - "⚡ Clôturer quand même (solde négatif)"
     - "✗ Annuler"

3. **Sélection Forfaits**
   - `ajouterForfaitAuPanier(id, type, heures, prix)`
   - `retirerForfaitDuPanier(index)`
   - Mise à jour en temps réel du compteur
   - Activation du bouton "Valider" quand total heures ≥ temps nécessaire

4. **Validation Panier** : `validerPanierEtCloturer(clientId, rdvId)`
   - Stocke les paramètres : `currentVenteParams`
   - Ouvre le modal signature vente

5. **Modal Signature Vente** : `openSignatureVenteModal()`
   - **Titre** : "Signature du client"
   - **Message** : "Veuillez signer pour confirmer l'achat du/des forfait(s)"
   - **Canvas** : Signature tactile
   - **Boutons** : "Effacer" | "✓ Valider"

6. **Validation Signature** : `validateSignatureVente()`
   - Vérifie que la signature n'est pas vide
   - Ferme le modal signature
   - **Confirmation** : Affiche le récapitulatif (nombre forfaits, heures, prix)
   - Si OK → Appelle `executerVente(signatureData)`

7. **Vente** : `executerVente(signatureData)`
   - **API** : `api/forfaits.php?action=vendre` (pour chaque forfait)
   - **Données envoyées** :
     - `client_id`
     - `type_forfait_id`
     - `signature_client` (base64)
   - **Actions backend** :
     - INSERT dans `forfaits_vendus` avec signature et `date_signature = NOW()`
   - **Après succès** : Relance automatiquement `procederCloture()`
     - Les heures sont maintenant suffisantes
     - → Retour au **CAS A** (signature clôture simple)

## Fichiers Modifiés

### Frontend : `agenda.php`
- ✅ Fonction `repondreArrondi()` : Appelle maintenant `verifierHeuresAvantSignature()`
- ✅ Fonction `verifierHeuresAvantSignature()` : NOUVELLE - Appel API check_heures
- ✅ Fonction `openSignatureClotureModal()` : Affiche heures décomptées dans le message
- ✅ Fonction `validateSignatureVente()` : Confirmation APRÈS signature (déjà corrigé)
- ✅ Fonction `executerVente()` : Relance `procederCloture()` après vente

### Backend : `api/interventions.php`
- ✅ Route `check_heures` : NOUVELLE
  - Calcule la durée (réelle ou arrondie)
  - Vérifie les heures restantes du client
  - Retourne `heures_suffisantes: true/false` sans clôturer
- ✅ Route `close_forfait` : Inchangée (reçoit la signature)

### Backend : `api/forfaits.php`
- ✅ Route `vendre` : Inchangée (stocke signature et date_signature)

## Avantages du Nouveau Flux

1. **Ordre logique** : Confirmation heures → Arrondi → Vérification → Signature
2. **Pas de tentative échouée** : La vérification se fait AVANT la signature, pas après
3. **Expérience utilisateur fluide** :
   - Si heures OK → 1 seule signature (clôture)
   - Si heures KO → 2 signatures (vente PUIS clôture)
4. **Traçabilité complète** :
   - Signature de vente forfait stockée
   - Signature de clôture intervention stockée
   - Dates enregistrées dans la BDD

## Tests Recommandés

### Test 1 : Heures Suffisantes
1. Créer un RDV avec client ayant 5h de forfait
2. Intervention de 2h
3. Clic "Clôturer" → Confirmer heures → Arrondi OUI
4. **Attendu** : Signature clôture simple → Clôture réussie

### Test 2 : Heures Insuffisantes
1. Créer un RDV avec client ayant 1h de forfait
2. Intervention de 3h
3. Clic "Clôturer" → Confirmer heures → Arrondi OUI
4. **Attendu** : Modal vente forfait
5. Ajouter 1 forfait 5h au panier
6. Clic "Valider et clôturer"
7. **Attendu** : Signature vente → Confirmation → Vente OK
8. **Puis** : Signature clôture → Clôture réussie

### Test 3 : Arrondi Exact (pas de différence)
1. Créer un RDV de 9h00 à 10h30 (exactement 1h30)
2. Clic "Clôturer" → Confirmer heures
3. **Attendu** : Pas de modal arrondi (durée déjà multiple de 30min)
4. **Puis** : Vérification heures → Signature → Clôture

## Notes Techniques

- **Format signature** : Data URL base64 PNG (`data:image/png;base64,...`)
- **Stockage BDD** : Colonne `LONGTEXT` (rendez_vous.signature_client, forfaits_vendus.signature_client)
- **Timezone** : UTC (côté serveur) et `Europe/Paris` (côté client)
- **Arrondi** : Tranches de 30min (0.5h)
- **FIFO** : Décompte sur le forfait le plus ancien en premier

---

**Date de mise à jour** : 7 décembre 2025  
**Version** : 2.0 (Vérification heures avant signature)


# Cahier des Charges - Application de Comptabilité LMNP

## 1. Présentation Générale

### 1.1 Objectif
Application de comptabilité modulaire pour les loueurs en meublé non professionnels (LMNP) au régime réel, permettant la gestion complète des aspects comptables et fiscaux.

### 1.2 Architecture Technique
- Framework PHP 8.1+ MVC personnalisé
- Base de données MySQL
- Interface utilisateur AdminLTE
- Moteur de templates Twig
- Configuration YAML

## 2. Structure Modulaire

### 2.1 Modules de Base
- Dashboard : Vue d'ensemble et widgets personnalisables
- User : Gestion des utilisateurs et authentification
- Settings : Configuration globale

### 2.2 Modules Finance
- Properties : Gestion des biens immobiliers
- Revenues : Suivi des loyers et revenus
- Expenses : Gestion des charges et dépenses

### 2.3 Modules Comptabilité
- Depreciation : Calcul des amortissements
- Reports : Génération des rapports
- TaxForms : Production des documents fiscaux

## 3. Fonctionnalités Détaillées

### 3.1 Gestion des Biens
- Création et édition des fiches de biens
- Suivi des caractéristiques (adresse, prix, date d'acquisition)
- Gestion des quotes-parts
- Multi-biens par utilisateur

### 3.2 Gestion Financière
- Saisie et suivi des loyers
- Catégorisation des charges
- Upload des justificatifs
- Tableaux de synthèse

### 3.3 Comptabilité
- Calcul automatisé des amortissements
- Génération des formulaires fiscaux
- États financiers personnalisables
- Export multi-formats

## 4. Sécurité et Performance

### 4.1 Sécurité
- Authentification robuste
- Protection CSRF
- Validation des données
- Journalisation des actions

### 4.2 Performance
- Cache système
- Optimisation des requêtes
- Chargement modulaire

## 5. Interface Utilisateur
- Design responsive
- Navigation intuitive
- Thème professionnel
- Mode clair/sombre
- Formulaires standardisés

## 6. Évolutivité
- Architecture extensible
- Système de plugins
- API interne
- Configuration flexible

====================================================
  TP PHP — Mon Petit Univers
  Manchester City Universe
====================================================

Auteur(s)  : Adam BELLANGER et Wassim EL GOZ
Thème      : Club de football — Manchester City
Module     : Développement Web Backend

----------------------------------------------------
DESCRIPTION
----------------------------------------------------
Application web PHP complète de gestion du club
Manchester City : joueurs, matchs, statistiques.
3 rôles distincts : Staff / Joueur / Supporter.

----------------------------------------------------
INSTALLATION
----------------------------------------------------
1. Copier le dossier dans htdocs/ (XAMPP)
2. Démarrer Apache et MySQL via XAMPP
3. Ouvrir phpMyAdmin : http://localhost/phpmyadmin
4. Créer une base de données : mancity_univers
5. Importer le fichier : database.sql
6. Accéder au site : http://localhost/ProjetUnivers/

----------------------------------------------------
IDENTIFIANTS DE TEST
----------------------------------------------------

STAFF (Administrateur) :
  Email    : admin@test.com
  Mot de passe : motdepasse123
  Accès    : Gestion complète joueurs/matchs/stats

JOUEUR (Utilisateur standard) :
  Email    : user@test.com
  Mot de passe : motdepasse123
  Accès    : Ses propres statistiques (Erling Haaland)

SUPPORTER (Visiteur connecté) :
  Email    : visiteur@test.com
  Mot de passe : motdepasse123
  Accès    : Consultation uniquement

----------------------------------------------------
FONCTIONNALITÉS PRINCIPALES
----------------------------------------------------

> Staff :
  - CRUD complet joueurs (création, édition, suppression)
  - CRUD complet matchs
  - Saisie des statistiques par match
  - Dashboard avec graphiques (Chart.js)
  - Gestion des utilisateurs en ligne

> Joueur :
  - Dashboard personnalisé avec ses propres stats
  - Tableau match par match (buts, passes, notes...)
  - Lien vers sa fiche publique

> Supporter :
  - Zone fan : dernier match, forme récente, vidéo YouTube
  - Statistiques de saison (V/N/D, buts marqués...)
  - Top buteur et top passeur de la saison

> Tous rôles :
  - Page joueurs avec recherche temps réel + pagination
  - Page matchs avec filtres + pagination
  - Fiche individuelle joueur (stats détaillées + graphique)
  - Profil utilisateur (photo, changement nom/mot de passe)
  - Mode sombre / clair

----------------------------------------------------
BONUS IMPLÉMENTÉS
----------------------------------------------------
  [x] Recherche / filtrage dans les listes
  [x] Pagination (15 joueurs / 10 matchs par page)
  [x] Upload photo de profil + photo joueur
  [x] Fonctionnalité originale : zone fan avec YouTube
  [x] Design particulièrement soigné (thème Man City)

----------------------------------------------------
STRUCTURE DU PROJET
----------------------------------------------------
index.php          — Page d'accueil
login.php          — Connexion
register.php       — Inscription
logout.php         — Déconnexion
dashboard.php      — Tableau de bord (3 vues selon rôle)
players.php        — Liste des joueurs
matchs.php         — Liste des matchs
player_stats.php   — Fiche individuelle joueur
profil.php         — Profil utilisateur
db.php             — Connexion PDO
database.sql       — Script d'initialisation BDD
includes/
  header.php       — En-tête + navigation
  footer.php       — Pied de page + système de notif
  auth.php         — Fonctions d'authentification
  style.css        — Feuille de style complète
  admin/
    players.php    — Liste admin joueurs
    player_create.php / player_edit.php / player_delete.php
    matchs.php     — Liste admin matchs
    match_create.php / match_edit.php / match_delete.php
    stats_create.php — Saisie stats match

====================================================

# Site de location du BDE

Un site de réservation de matériel (raclette, chargeur, etc.) en PHP, avec :

- **des comptes pour tout le monde** : on se connecte avec son e-mail et son mot de passe. Le site reconnaît les membres du bureau et leur ouvre l'espace administrateur ; les autres arrivent sur « Mon compte », avec leurs demandes déjà affichées ;
- **une partie client** : catalogue avec photos, réservation (durée au choix), page de suivi, liens de paiement HelloAsso et de caution Swikly ;
- **une partie admin** (`/admin/`) : suivi des prêts, matériel (photo, stock, caution et prix propres à chaque objet), réglages, comptes, export CSV.

Il n'y a rien à installer à part PHP : la base de données est un simple fichier (SQLite).

## Ce qu'il faut côté hébergement

- PHP 8.1 ou plus récent avec les extensions `pdo_sqlite` et `mbstring` (c'est le cas chez OVH, o2switch, Hostinger, etc.).
- L'extension **GD** est recommandée : elle réduit automatiquement les photos. Sans elle, les photos sont acceptées seulement si elles font 1,5 Mo ou moins.
- Un nom de domaine ou sous-domaine en **HTTPS**.
- Apache de préférence (les `.htaccess` protègent les dossiers sensibles). Sur Nginx, il faut bloquer à la main l'accès à `inc/` et `data/`, et interdire l'exécution de scripts dans `uploads/`.
- Les dossiers `data/` et `uploads/items/` doivent être **accessibles en écriture** par PHP.

## Mise en ligne

1. Envoie **tout le contenu de ce dossier** dans le dossier public de ton hébergement, sans oublier les fichiers cachés `.htaccess`.
2. Ouvre `https://ton-site/install.php` **tout de suite** : la première personne qui y passe crée le premier compte administrateur (nom, e-mail, mot de passe de 10 caractères minimum).
3. Connecte-toi sur `https://ton-site/login.php`.
4. Dans **Matériel**, ajoute tes objets avec photo, stock, caution et prix par jour (0 = gratuit).
5. Dans **Réglages**, colle le lien HelloAsso et le lien Swikly, indique où récupérer le matériel, et vérifie le champ **Inscriptions** (voir plus bas).
6. Supprime `install.php` du serveur (il se désactive seul après l'installation, mais autant le retirer).

## Comptes

- **Un seul compte par adresse e-mail** (majuscules/minuscules ignorées).
- Les inscriptions sont limitées aux domaines indiqués dans **Réglages → Inscriptions**. Par défaut : `ipsa.fr` (les sous-domaines comme `etu.ipsa.fr` passent). Si les adresses des étudiants ont un autre domaine, change-le là ; `*` autorise tout le monde.
- **Inviter un membre du bureau** : dans **Comptes**, saisis son e-mail. Le site génère un code à usage unique (ex. `AB3DE-F7GH2`), affiché une seule fois : remets-le-lui en main propre. À l'inscription avec cette adresse et ce code, il est reconnu comme administrateur. Sans le code, **personne** ne peut créer de compte avec cette adresse : impossible de se faire passer pour un futur admin.
- Quelqu'un a déjà un compte ? Dans la liste, bouton **Donner les droits** (ou **Retirer**). Il reste toujours au moins un administrateur.
- **Mot de passe oublié** : pas de mail automatique. Le bureau en définit un nouveau dans Comptes → « Nouveau mot de passe » et le communique ; la personne peut ensuite le changer dans « Mon compte ».

## Photos des objets

Dans **Matériel**, le champ Photo accepte JPG, PNG ou WebP (2 Mo par défaut, limite fixée par l'hébergeur via `upload_max_filesize`). Avec GD, la photo est redimensionnée (1200 px max) et nettoyée. Les photos sont rangées dans `uploads/items/`.

## Comment ça marche pour un emprunteur

1. Il crée son compte, puis choisit un objet et ses dates **dans un calendrier** : chaque jour affiche le nombre d'exemplaires encore libres, et un jour complet est bloqué. Dès la demande, l'objet est réservé (un exemplaire de moins de disponible), même avant paiement.
2. Il arrive sur sa demande, avec **les deux liens en même temps** : paiement de la location (HelloAsso) et caution (Swikly).
3. Toutes ses demandes sont ensuite dans **Mon compte**, avec ce qu'il lui reste à faire. Plus de référence à chercher.

## Comment ça marche pour le bureau

Dans **Suivi**, chaque demande avance avec un bouton : *Paiement et caution reçus* (à cliquer une fois que tu as vérifié les **deux** sur HelloAsso et Swikly), *Objet remis*, *Objet rendu*. Les retards apparaissent dans « En retard ». Tu peux aussi annuler, ajouter une note interne et voir l'historique.

**À savoir :** HelloAsso et Swikly ne sont pas branchés par API. Le site envoie l'emprunteur vers vos liens et c'est le bureau qui marque « paiement reçu » et « caution reçue » après vérification. Le montant exact de la caution est rappelé à l'emprunteur, mais il faut le saisir sur Swikly. Un lien propre à un objet peut être indiqué dans sa fiche.

## Stock et disponibilités

- Une demande (même non payée) bloque ses exemplaires sur ses dates. Elle se libère si elle est annulée ou si l'objet est rendu.
- Un objet remis et pas encore rendu reste compté comme pris, même après la date de fin prévue.
- Dans le catalogue, « 3 libres sur 4 » correspond à **aujourd'hui** ; les réservations futures se voient dans le calendrier de l'objet.

## Mise à jour depuis la version précédente

Copie les nouveaux fichiers par-dessus les anciens (garde ton dossier `data/`). La base est migrée automatiquement, les anciennes demandes restent consultables. Les anciens comptes admin (identifiant sans e-mail) ne sont pas repris : **le premier compte créé via « Créer un compte » devient administrateur** ; fais-le tout de suite.

## Limites à connaître

- **L'adresse e-mail n'est pas vérifiée** : le site ne vérifie pas que l'inscrit possède bien l'adresse. N'importe qui connaissant l'e-mail d'un étudiant pourrait créer le compte à sa place. Pour un BDE c'est un risque faible, mais si besoin on peut ajouter un lien de confirmation par e-mail (il faut alors que l'envoi de mails fonctionne).
- Les droits administrateur ne s'obtiennent que par invitation ou par un admin existant, jamais par simple inscription.

## Sauvegarde et sécurité

- **Sauvegarde** : télécharge régulièrement `data/location.sqlite` (objets, réservations, comptes) et le dossier `uploads/items/` (photos).
- Si ton hébergeur le permet, déplace `data` **en dehors du dossier public** et change `DATA_DIR` dans `inc/config.php`.
- Les mots de passe sont chiffrés, les formulaires protégés contre les envois frauduleux, et connexion/inscription sont limitées après plusieurs essais.
- Les données des emprunteurs (nom, e-mail) sont stockées dans la base : ajoute une page de mentions légales et de confidentialité si le site sort du cadre de l'école.

## E-mails

Le site envoie un e-mail de confirmation à l'emprunteur (s'il ne l'a pas désactivé dans ses Paramètres) et une alerte au bureau à chaque demande. Rien n'est envoyé tant que l'**adresse d'expédition** est vide dans Réglages → E-mails automatiques.

- **Utilise un serveur SMTP** (Réglages → Serveur SMTP) : ça marche partout, y compris en local. Avec Gmail : `smtp.gmail.com`, port 587, STARTTLS, identifiant = ton adresse Gmail (la même que l'adresse d'expédition), mot de passe = un **mot de passe d'application** (Compte Google → Sécurité → validation en 2 étapes → mots de passe des applications).
- Sans SMTP, le site utilise la fonction `mail()` de PHP : elle ne marche que si la machine a un service d'envoi (rare sur un PC, fréquent chez un hébergeur).
- Le bouton **Envoyer un e-mail de test** (enregistre d'abord les réglages) affiche la raison exacte en cas d'échec.
- TLS/SSL demande l'extension PHP `openssl` (activée dans XAMPP ; sinon décommente `extension=openssl` dans `php.ini`).
- Le mot de passe SMTP est stocké dans la base (`data/`, protégé) : utilise un compte dédié au BDE plutôt que ta boîte personnelle.
- Les e-mails peuvent finir en spam au début.

## Paramètres des membres

Chaque membre a un onglet **Paramètres** : nom, téléphone (pré-rempli dans ses demandes), e-mails de confirmation oui/non, changement d'adresse e-mail et de mot de passe (mot de passe actuel demandé), suppression du compte (impossible avec des demandes en cours).

## En cas de problème

Passe `APP_DEBUG` à `true` dans `inc/config.php` pour voir l'erreur à l'écran, puis remets `false`.

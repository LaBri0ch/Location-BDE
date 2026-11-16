# Location BDE Atmosph'air

Site de réservation du matériel prêté par le BDE Atmosph'air (IPSA) : appareil à raclette, crêpière, vidéoprojecteur, diable, outillage…
Les étudiants réservent avec un calendrier de disponibilités, règlent la location (HelloAsso) et la caution (Swikly), puis suivent leur demande. Le bureau gère les prêts, le matériel, les réglages et les comptes.

> **Statut : démo / prototype.**
> Ce dépôt sert à présenter le projet. Avant une vraie mise en service, il doit être repris et validé par un développeur (sécurité, hébergement, aspects juridiques) : voir [Avant la mise en production](#avant-la-mise-en-production).

**Démo en ligne :** `https://<ton-pseudo-github>.github.io/<nom-du-depot>/` *(lien à mettre à jour une fois GitHub Pages activé)*

---

## Contenu du dépôt

| Dossier | Ce que c'est |
|---|---|
| [`docs/`](docs/) | **La démo**, publiée avec GitHub Pages. Une copie de l'interface qui tourne entièrement dans le navigateur (HTML, CSS, JavaScript, sans dépendance). Les données sont fictives et restent dans le navigateur de chaque visiteur (`localStorage`) : aucun serveur, aucun paiement, aucun e-mail. |
| [`app/`](app/) | **La vraie application**, en PHP 8.1+ avec une base SQLite : comptes, réservations, espace bureau, envoi d'e-mails, export CSV. C'est elle qu'il faudra héberger. Guide d'installation détaillé : [`app/LISEZMOI.md`](app/LISEZMOI.md). |

GitHub Pages ne peut héberger que des fichiers statiques : il n'exécute pas de PHP. D'où la démo séparée dans `docs/`, qui reprend la même interface et les mêmes règles (stock jour par jour, statuts, invitations du bureau…) en JavaScript.

## Tester la démo

Comptes fictifs (ou boutons **Vue étudiant** / **Vue bureau** dans le bandeau en haut de la démo) :

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Étudiant | `etudiant.demo@ipsa.fr` | `demo-etudiant` |
| Bureau | `bureau.demo@ipsa.fr` | `demo-bureau` |

Parcours conseillé pour une présentation :

1. **Vue étudiant** : ouvrir l'appareil à raclette, choisir deux jours dans le calendrier (les jours complets sont barrés), envoyer la demande, voir les étapes de paiement et de caution.
2. **Vue bureau** : dans *Suivi*, cliquer « Paiement et caution reçus », puis « Objet remis ». Montrer le filtre *En retard*, la note interne, l'historique et l'export CSV.
3. *Matériel* : ajouter un objet avec une photo. *Comptes* : inviter un membre du bureau (un code à usage unique est généré).
4. Revenir en **Vue étudiant** : la demande a avancé.

Le bouton **Réinitialiser** remet les données de départ.

## Publier la démo sur GitHub Pages

1. Envoie ce dossier sur GitHub. **Attention aux fichiers cachés** (`.gitignore`, `.htaccess`, `docs/.nojekyll`) : un glisser-déposer depuis le Finder/l'Explorateur peut les oublier. Utilise plutôt GitHub Desktop ou `git` :
   ```bash
   git init
   git add .
   git commit -m "Site de location du BDE : appli PHP + démo GitHub Pages"
   git branch -M main
   git remote add origin https://github.com/<ton-pseudo>/<nom-du-depot>.git
   git push -u origin main
   ```
2. Sur GitHub : **Settings → Pages → Build and deployment**
   - *Source* : **Deploy from a branch**
   - *Branch* : **main**, dossier **/docs** → **Save**
3. Au bout d'une à deux minutes, l'adresse du site apparaît en haut de la page *Pages*. Mets-la dans ce README.

Avec un compte GitHub gratuit, Pages ne fonctionne que sur un **dépôt public**. Un dépôt privé demande GitHub Pro, Team ou Enterprise (GitHub Pro est inclus dans le GitHub Student Developer Pack). Dans tous les cas, **le site publié est public**, même si le dépôt est privé.

## Lancer la vraie version (PHP) sur son ordinateur

Il faut PHP 8.1 ou plus récent avec `pdo_sqlite` et `mbstring` (GD conseillé pour réduire les photos).

```bash
php -S localhost:8000 -t app
```

Puis ouvrir <http://localhost:8000/install.php> pour créer le premier compte administrateur. La base est créée dans `app/data/location.sqlite` : **ce fichier contient de vraies données et ne doit jamais être envoyé sur GitHub** (il est déjà exclu par `.gitignore`).

Le serveur intégré de PHP ignore les `.htaccess` : c'est sans risque en local, mais en ligne il faut un hébergement Apache ou une configuration équivalente (voir `app/LISEZMOI.md`).

---

## Avant la mise en production

Liste de points à reprendre par le développeur qui finalisera le site. Ce n'est pas un avis juridique : les points juridiques sont à faire valider.

### Technique et sécurité

- [ ] **Données personnelles hors du dépôt** : ne jamais committer `app/data/` (comptes, e-mails, téléphones, mots de passe chiffrés, mot de passe SMTP) ni les photos envoyées (`app/uploads/items/`).
- [ ] **Protection des dossiers** : vérifier sur l'hébergement que `/data/location.sqlite`, `/inc/config.php` et tout script placé dans `/uploads/` renvoient bien une erreur 403. Les `.htaccess` fournis couvrent Apache ; sur Nginx, écrire les règles équivalentes.
- [ ] Idéalement, déplacer `data/` **en dehors du dossier public** (constante `DATA_DIR` dans `app/inc/config.php`).
- [ ] **HTTPS obligatoire** (le cookie de session passe en `secure` automatiquement en HTTPS).
- [ ] **Vérification de l'adresse e-mail** à l'inscription (lien de confirmation) : aujourd'hui, n'importe qui connaissant l'adresse d'un étudiant peut créer le compte à sa place.
- [ ] **Paiements** : HelloAsso et Swikly ne sont pas branchés par API, le bureau valide à la main. Étudier l'API HelloAsso (notifications de paiement) pour automatiser.
- [ ] **Sauvegardes** régulières de la base et des photos ; procédure de restauration testée.
- [ ] Envoi d'e-mails via un compte SMTP dédié au BDE (pas une boîte personnelle) ; configurer SPF/DKIM sur le domaine pour éviter les spams.
- [ ] Revue de sécurité (CSRF, limitation des tentatives de connexion, téléversement de fichiers, en-têtes HTTP) et tests sur mobile.

### Juridique

- [ ] **Mentions légales** : obligatoires pour un site édité par une association (loi n° 2004-575 du 21 juin 2004 pour la confiance dans l'économie numérique, « LCEN »). Indiquer l'association éditrice (nom, siège, numéro RNA), le directeur de la publication et l'hébergeur (nom, adresse, téléphone).
- [ ] **Politique de confidentialité (RGPD)** : le site traite des données personnelles (nom, e-mail, téléphone, historique d'emprunts). Informer les utilisateurs comme le prévoit l'article 13 du RGPD : responsable du traitement (l'association), finalité (gestion des prêts), base légale (exécution du contrat de prêt, art. 6.1.b), destinataires (bureau, HelloAsso, Swikly, hébergeur), durée de conservation, droits (accès, rectification, effacement…) et contact.
- [ ] **Durée de conservation** limitée (RGPD art. 5.1.e) : prévoir la suppression ou l'anonymisation automatique des anciens comptes et réservations.
- [ ] **Registre des traitements** (RGPD art. 30) et mesures de sécurité adaptées (art. 32).
- [ ] **Cookies** : la version PHP n'utilise qu'un cookie de session, strictement nécessaire au fonctionnement, donc dispensé de consentement (art. 82 de la loi Informatique et Libertés). Tout ajout de mesure d'audience ou de service tiers est à réexaminer.
- [ ] **Conditions générales de prêt / location**, acceptées par case à cocher à la réservation : montant et conditions d'encaissement de la caution, casse, perte, retard, responsabilité de l'emprunteur, assurance de l'association.
- [ ] **Photos du catalogue** : remplacer les photos d'illustration par des photos du matériel du BDE (les visuels de sites marchands sont protégés par le droit d'auteur).
- [ ] Vérifier avec l'école l'utilisation du nom IPSA et des adresses `@ipsa.fr`.

---

## Organisation du code

```
app/                    Application PHP (à héberger)
├── index.php           Catalogue
├── reserver.php        Réservation avec calendrier de disponibilités
├── reservation.php     Suivi d'une demande (liens de paiement et caution)
├── compte.php          « Mes demandes »
├── parametres.php      Profil, e-mail, mot de passe, suppression du compte
├── login.php, inscription.php, logout.php, install.php, retrouver.php
├── admin/              Espace bureau : suivi, matériel, réglages, comptes, export CSV
├── inc/                Logique : base, réservations, comptes, e-mails, photos, gabarit
├── assets/             CSS et JavaScript
├── data/               Base SQLite (créée à l'installation, jamais versionnée)
└── uploads/items/      Photos du matériel (jamais versionnées)

docs/                   Démo statique pour GitHub Pages
├── index.html
└── assets/             style.css (identique à app/ + quelques ajouts), demo.js, images
```

Tous droits réservés, BDE Atmosph'air.

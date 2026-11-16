# Location BDE Atmosph'air

Démo du site de réservation du matériel prêté par le BDE Atmosph'air (IPSA) : appareil à raclette, crêpière, vidéoprojecteur, diable, outillage…

Les étudiants choisissent leurs dates dans un calendrier de disponibilités, envoient leur demande et suivent son avancement (paiement HelloAsso, caution Swikly, remise, retour). Le bureau gère les prêts, le matériel, les réglages et les comptes.

> **Démo.** Tout fonctionne dans le navigateur, sans serveur : les données sont fictives et restent dans le navigateur de chaque visiteur. Rien n'est envoyé nulle part, aucun paiement, aucun e-mail.

**Voir la démo :** `https://<ton-pseudo-github>.github.io/<nom-du-depot>/` *(à mettre à jour une fois GitHub Pages activé)*

## Tester

Comptes fictifs, ou boutons **Vue étudiant** / **Vue bureau** dans le bandeau en haut du site :

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

Sur ton ordinateur, il suffit d'ouvrir `index.html` dans un navigateur.

## Mettre la démo en ligne avec GitHub Pages

1. Crée un dépôt **public** sur GitHub (avec un compte gratuit, GitHub Pages ne marche que sur un dépôt public).
2. **Add file → Upload files**, puis glisse tout le contenu du dossier : `index.html`, le dossier `assets` et ce `README.md`. Clique **Commit changes**.
3. **Settings → Pages → Build and deployment** :
   - *Source* : **Deploy from a branch**
   - *Branch* : **main**, dossier **/ (root)**, puis **Save**
4. Au bout d'une à deux minutes, l'adresse du site s'affiche en haut de la page *Pages*. Colle-la dans ce README.

## Fichiers

```
index.html              La page (tout le site tient dans une seule page)
assets/style.css        Mise en forme (clair et sombre, ordinateur et mobile)
assets/demo.js          Fonctionnement : catalogue, calendrier, demandes, espace bureau
assets/img/             Photos du matériel
.nojekyll               Demande à GitHub Pages de servir les fichiers tels quels
```

## Limites de la démo

- Les données sont propres à chaque navigateur : ce que tu fais n'est pas visible par les autres visiteurs.
- Les mots de passe de la démo ne sont pas protégés comme sur un vrai site : ne pas y saisir de vrai mot de passe.
- Pas de paiement ni d'e-mail : les boutons HelloAsso et Swikly expliquent ce qui se passerait, sauf si un vrai lien est collé dans *Bureau → Réglages*.
- Les photos sont des visuels d'illustration à remplacer par des photos du matériel du BDE.

Pour un vrai site, il faudra un serveur (comptes, réservations partagées, e-mails), ainsi que des mentions légales, une politique de confidentialité (RGPD) et des conditions de prêt.

Tous droits réservés, BDE Atmosph'air.

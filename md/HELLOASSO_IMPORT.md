# Import HelloAsso — Guide

## Objectif

Importer les adhésions depuis HelloAsso pour créer ou mettre à jour les adhérents dans l'application.

## Prérequis : récupérer les identifiants API

### 1. clientId et clientSecret

1. Se connecter sur https://auth.helloasso.com/connexion
2. Cliquer sur **Mon Compte** (en haut à droite)
3. Aller dans **Intégration et API**
4. Copier le `clientId` et le `clientSecret`

### 2. organizationSlug

C'est le nom de l'association dans l'URL HelloAsso.

Exemple : si l'URL est `helloasso.com/associations/ultramical86`, le slug est `ultramical86`.

### 3. Campagnes (membershipSlug)

Les campagnes d'adhésion sont gérées depuis l'interface d'administration
(page **Campagnes HelloAsso**), pas depuis le `.env`. Chaque campagne
correspond à une année scolaire et est identifiée par son slug HelloAsso
(ex: `adhesion-2026-2027`).

## Configuration

Ajouter dans le fichier `.env` (identifiants API uniquement) :

```
HELLOASSO_CLIENT_ID=votre_client_id
HELLOASSO_CLIENT_SECRET=votre_client_secret
HELLOASSO_ORG_SLUG=ultramical86
```

Les campagnes (slug + année scolaire + URL) sont saisies via la page
**Campagnes HelloAsso** dans le menu admin. Le slug peut être déduit
automatiquement de l'URL si le champ slug est laissé vide.

## API HelloAsso v5

### Base URL

- Production : `https://api.helloasso.com/v5`
- Sandbox (test) : `https://api.helloasso-sandbox.com/v5`

### Authentification (OAuth 2.0)

Obtenir un token d'accès :

```
POST https://api.helloasso.com/oauth2/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
client_id=YOUR_CLIENT_ID
client_secret=YOUR_CLIENT_SECRET
```

Réponse :

```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIs...",
  "refresh_token": "UxMiwiZX...",
  "token_type": "bearer",
  "expires_in": "1800"
}
```

- `access_token` valide 30 minutes
- `refresh_token` valide 1 mois
- Utiliser le token dans le header : `Authorization: Bearer TOKEN`

### Endpoint : lister les adhésions

```
GET https://api.helloasso.com/v5/organizations/{organizationSlug}/forms/Membership/{membershipSlug}/items
Authorization: Bearer TOKEN
```

Paramètres de pagination :
- `pageSize` (défaut : 20, max : 100)
- `continuationToken` (pagination cursor-based)

### Endpoint : détail d'un item (customFields)

```
GET https://api.helloasso.com/v5/items/{itemId}?withDetails=true
Authorization: Bearer TOKEN
```

Retourne les `customFields` remplis par l'adhérent : date de naissance,
téléphone, adresse, code postal, ville, email propre à l'adhérent (pas du
payeur) et opt-in WhatsApp.

### Données importées

Pour chaque adhésion (champ `user` renseigné) :
- Nom, prénom (depuis `user`)
- Email de l'adhérent (depuis les customFields, pas du payeur)
- Date de naissance, téléphone, adresse, code postal, ville
- Opt-in WhatsApp
- Montant de l'adhésion (tarif + don, convertis de centimes en euros)

Les dons (items avec `user` vide) sont rattachés à l'adhésion de la même
commande (même `order.id`).

## Import des adhésions

### Mode complet (défaut)

Crée les adhérents manquants et ajoute l'adhésion pour tous (nouveaux +
existants). À utiliser pour les années récentes (ex: 2025-2026, 2026-2027)
où on veut tous les adhérents en base.

### Mode adhésion

N'ajoute l'adhésion que pour les membres **déjà en base**. Les adhérents
non trouvés sont affichés dans une section « ignorés » mais ne sont pas
créés. À utiliser pour les années antérieures (ex: 2018-2024) pour
renseigner l'historique des adhésions sans créer d'anciens membres.

### Flux

1. Page **Importer depuis HelloAsso** (menu admin)
2. Sélectionner la campagne dans le dropdown
3. Choisir le mode (Complet ou Adhésion)
4. L'aperçu (dry-run) s'affiche : nouveaux adhérents, existants, ignorés
5. Cocher la case de confirmation et cliquer sur « Confirmer l'import »
6. L'import s'exécute en transaction (annulé en cas d'erreur)

### Rapprochement

Les adhérents sont rapprochés avec la base existante d'abord par l'email
de l'adhérent (customFields), puis par nom normalisé. Les adhérents déjà
en base ne sont pas recréés : on ajoute simplement leur adhésion. La fiche
existante n'est pas modifiée.

### Réactivation automatique

Un adhérent désactivé (année non active) qui reprend une adhésion via
l'import redevient actif automatiquement (voir le système d'années actives).

## Documentation officielle

- API Overview : https://dev.helloasso.com/docs/api-overview
- Obtenir une clé API : https://dev.helloasso.com/docs/obtenir-une-clé-api
- S'authentifier : https://dev.helloasso.com/docs/getting-started
- SDK PHP : https://github.com/HelloAsso/helloasso-php

# 📁 Structure CSS & Personnalisation

## Organisation des fichiers

```
adherents/
├── public/
│   ├── css/
│   │   └── theme.css          ← Thème global (lié dans layout.php)
│   ├── js/                     ← (à créer si besoin pour JS)
│   └── uploads/
├── app/
│   ├── bootstrap.php          ← Config, PDO, session, CSRF, flash
│   ├── auth.php               ← Authentification, permissions
│   ├── members.php            ← CRUD adhérents, trombinoscope, anniversaires
│   ├── trainings.php          ← CRUD entraînements
│   ├── events.php             ← CRUD événements
│   ├── news.php               ← CRUD actualités
│   ├── uploads.php            ← Upload d'images
│   ├── helpers.php            ← URL, dates, avatar, formatage
│   ├── render.php             ← Rendu des pages et composants
│   └── config.php             ← Configuration application + BDD
├── views/
│   ├── layout.php
│   ├── pages/
│   └── components/
└── index.php                   ← Routeur principal
```

## 🎨 Système CSS à 3 niveaux

### 1️⃣ **Variables CSS** (dans index.php `<style>`)
```css
:root {
  --bg-top: #f4f7fb;
  --bg-bottom: #eef8f4;
  --brand: #14532d;
  --brand-soft: #dcfce7;
  --ink: #111827;
  --muted: #4b5563;
  --card-border: #d1d5db;
}
```
- **Usage** : Couleurs globales, thème principal
- **Avantage** : Aucun rechargement, appliqué immédiatement

### 2️⃣ **Bootstrap CSS** (CDN)
- Framework responsive avec composants prêts à l'emploi
- Lien : `bootstrap@5.3.3`

### 3️⃣ **Custom CSS** (public/css/style.css)
- Personnalisations spécifiques au projet
- Animations, transitions, refinements
- Aisément scallable pour futur

## 🚀 Comment ajouter des CSS personnalisés

**Exemple : Modifier la couleur d'un composant**
```css
/* Dans public/css/style.css */
.navbar {
  background-color: var(--brand) !important;
}
```

**Exemple : Ajouter une animation**
```css
.card {
  animation: slideIn 0.3s ease-in-out;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
```

## 🎭 Icônes disponibles

Nous utilisons **Bootstrap Icons** — plus de 2000 icônes libres disponibles.

**Syntaxe** :
```html
<i class="bi bi-[icone-name]"></i>
```

**Icônes déjà utilisées** :
- `bi-people` → Trombinoscope
- `bi-facebook` → Facebook
- `bi-instagram` → Instagram
- `bi-heart` → HelloAsso
- `bi-globe` → Site web

**Découvrir d'autres icônes** : https://icons.getbootstrap.com/

## 📱 Responsive Design

Bootstrap 5 fournit déjà une grille responsive. Si tu veux customiser :

```css
/* Mobile first (extra small devices) */
.mon-element { font-size: 0.9rem; }

/* Tablets and up (md and above) */
@media (min-width: 768px) {
  .mon-element { font-size: 1rem; }
}

/* Large screens (lg and above) */
@media (min-width: 1200px) {
  .mon-element { font-size: 1.1rem; }
}
```

## 🎯 Prochaines étapes possibles

1. **Créer des templates séparés** → Refactoriser `index.php` en templates dans `app/views/`
2. **Ajouter un fichier JS** → `public/js/app.js` pour interactions
3. **Thème sombre** → Ajouter support du dark mode dans `style.css`
4. **Variables personnalisées** → Étendre `:root` dans `style.css`

## 📝 Exemple : Ajouter une classe personnalisée

```php
<!-- Dans index.php -->
<a href="..." class="list-group-item list-group-item-action btn-accent">
  <i class="bi bi-star me-2"></i> Mon lien spécial
</a>
```

```css
/* Dans public/css/style.css */
.btn-accent {
  background-color: #fbbf24;
  color: #111827;
}

.btn-accent:hover {
  background-color: #f59e0b;
}
```

---

**💡 Conseil** : Garder `index.php` pour les variables CSS globales (theme) et `style.css` pour les personnalisations spécifiques au design.

# Dette technique — décisions différées

Points relevés lors d'un audit (architecture/refactoring/sécurité, le
2026-09-20) et volontairement **non traités**, avec la raison. Ne pas les
« corriger » sans relire le raisonnement ci-dessous : la divergence est
parfois justifiée par un besoin réel, pas juste une incohérence.

## Deux conventions pour remonter les erreurs de validation

**Statut : gardé tel quel, décision prise le 2026-09-20.**

Le projet a deux façons différentes de signaler un échec de validation
depuis les fonctions de `app/*.php` vers les contrôleurs :

### Convention A — exception (majoritaire : `app/news.php`, `app/races.php`,
`app/events.php`, `app/trainings.php`)

```php
function create_news(array $payload): void
{
    if ($title === '') {
        throw new RuntimeException('Le titre est obligatoire.');
    }
    // ... insert ...
}
```

Contrôleur :

```php
try {
    create_news($p);
    set_flash('success', 'Modifiée.');
} catch (Throwable $e) {
    set_flash('danger', $e->getMessage());
}
```

La fonction s'arrête à la **première** erreur rencontrée : un seul message
affiché à l'utilisateur.

### Convention B — tuple `[bool, array]` (seule exception : `app/members.php`,
fonctions `admin_create_member()`, `admin_update_member()`,
`add_membership_for_member()`)

```php
function admin_create_member(array $payload): array
{
    [$data, $errors] = validate_member_payload($payload);
    if ($errors !== []) {
        return [false, $errors, 0];
    }
    // ... insert ...
    return [true, [], (int) app_pdo()->lastInsertId()];
}
```

Contrôleur (`controllers/members/member-add.php`) :

```php
[$ok, $errors, $memberId] = admin_create_member($_POST);
if ($ok) { /* ... */ }
foreach ($errors as $error) {
    set_flash('danger', (string) $error);
}
```

Cette fonction peut remonter **plusieurs** erreurs à la fois (ex: « titre
obligatoire » + « email déjà utilisé » affichés ensemble) — le formulaire
adhérent a beaucoup plus de champs à valider d'un coup qu'un formulaire
news/course/événement.

### Pourquoi on ne les a pas unifiées

Remplacer bêtement le tuple par une exception dans `app/members.php`
changerait le comportement utilisateur (un seul message d'erreur au lieu
de la liste complète des champs en défaut), pas juste la plomberie
interne. Ce n'est pas un simple refactor mécanique.

### Si on veut unifier un jour

Deux pistes, à choisir consciemment (pas par défaut) :

1. Créer une `ValidationException` qui transporte une liste d'erreurs
   (`getErrors(): array`), et faire que `admin_create_member()` /
   `admin_update_member()` / `add_membership_for_member()` la lèvent au
   lieu de retourner un tuple. Convertit `app/members.php` vers la
   convention A tout en gardant le multi-erreurs. Impacte
   `member-add.php`, `member-edit.php`, `profile.php`.
2. Inverser : faire que `create_news()`/`create_race()`/etc. retournent
   aussi `[bool, array]` au lieu de lever une exception, pour uniformiser
   vers la convention B partout. Plus de fichiers à toucher, et perd la
   simplicité du `try/catch` générique actuel — a priori moins bon choix.

La piste 1 est la plus cohérente avec le reste du projet (le `try/catch`
+ exception est déjà le pattern dominant).

# PHP 8.2 Compatibility — Dynamic Property Fix

## Why this change was needed

PHP 8.2 deprecates the creation of dynamic properties (properties assigned to `$this` that are not declared in the class body). At runtime this emits:

```
Deprecated: Creation of dynamic property ClassName::$db is deprecated
```

In PHP 9.0 dynamic properties become a fatal `ErrorException`. Declaring the property explicitly in the class body silences the deprecation on 8.2 and future-proofs the code for 9.0.

No logic was altered. No types were added. No renames or refactors were performed.

---

## Files changed

| File | Class | Property added |
|---|---|---|
| [portal/library/Upload.php](../library/Upload.php) | `Upload` | `private $db;` |
| [portal/library/Offer.php](../library/Offer.php) | `Offer` | `private $db;` |
| [portal/library/Postback.php](../library/Postback.php) | `Postback` | `private $db;` |
| [portal/library/Report.php](../library/Report.php) | `Report` | `private $db;` |
| [portal/library/User.php](../library/User.php) | `User` | `private $db;` |
| [portal/library/Filter.php](../library/Filter.php) | `Filter` | `private $db;` |

**Not changed:** `portal/library/database/Database.php` — already declares all properties (`$dbHost`, `$dbUser`, `$dbPass`, `$dbName`, `$conn`) at the class level. `Settings.php`, `cron.php`, and `connect.php` are not class files.

---

## What was added to each class

A single line inserted immediately after the opening brace of each class, before `__construct`:

```php
private $db;
```

### Before (all six classes had this pattern)

```php
class Offer
{
    public function __construct() {
        $this->db = new Database();
    }
    // ...
}
```

### After

```php
class Offer
{
    private $db;

    public function __construct() {
        $this->db = new Database();
    }
    // ...
}
```

---

## IDE informational note

The IDE may report: `Property $db has no type information available (P1132)`.  
This is a code-intelligence hint, not a PHP error. Typed properties (`private Database $db;`) were intentionally omitted per project rules — the runtime behavior is identical.

---

## Rollback instructions

To revert all six files to the dynamic-property state, remove the `private $db;` line (and the blank line following it) from each class listed above. The constructor assignments (`$this->db = new Database();`) remain untouched — no other code needs to change.

Git one-liner to restore all six files to their state before this change:

```bash
git diff HEAD portal/library/Upload.php portal/library/Offer.php \
    portal/library/Postback.php portal/library/Report.php \
    portal/library/User.php portal/library/Filter.php
```

To revert:

```bash
git checkout HEAD -- \
    portal/library/Upload.php \
    portal/library/Offer.php \
    portal/library/Postback.php \
    portal/library/Report.php \
    portal/library/User.php \
    portal/library/Filter.php
```

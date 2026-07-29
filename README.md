# The Battles Legacy — Private Family Hub

A private, invite-only home for the Battles family history: a zoomable family tree,
per-person photo galleries, and member photo uploads with moderator review.
Living relatives are visible only to signed-in family; the public sees a redacted preview.

Built with plain **PHP 8 + MySQL** so it runs on standard cPanel hosting with no special stack.

---

## What's inside

```
app/
  public/            <- web root (point the domain / subdomain here)
    index.php          home / dashboard
    login.php          family login
    register.php       invite-link signup
    tree.php           interactive family tree (secured)
    data.php           tree data feed (redacts living for the public)
    person.php         profile + photo gallery
    upload.php         member photo upload -> pending
    moderate.php       moderator/admin review queue
    admin.php          invite members, set roles
    assets/            css + imported/uploaded photos
  src/                 config, db, auth, helpers, theme
  bin/                 command-line tools (below)
  config.example.php   copy to config.php and fill in
```

## Roles
- **Admin** — everything, plus invite members and set roles.
- **Moderator** — approve/decline submitted photos.
- **Member** — browse the tree and upload photos (which go to the queue).

---

## Deploy to cPanel (one-time)

1. **Create a MySQL database + user** in cPanel (Databases → MySQL Databases),
   and add the user to the database with All Privileges.

2. **Upload the `app/` folder** above your public web root (e.g. to `/home/USER/battles/`),
   then point the domain's document root at `app/public` (Domains → set document root),
   **or** upload the contents of `app/public` into `public_html` and the rest of `app/`
   just outside it.

3. **Create `config.php`**: copy `config.example.php` to `config.php` and fill in your
   database name / user / password. Leave `db_driver` as `mysql`.

4. **Build the database** (Terminal in cPanel, or SSH):
   ```bash
   php bin/migrate.php                       # create the tables
   php bin/import_gedcom.php battlesfamily.ged   # load the family tree
   php bin/seed_admin.php "Your Name" you@email.com "a-strong-password"
   php bin/import_photos.php /path/to/your/photo/library
   ```

5. Visit the site, log in as the admin, and start inviting family from **Members**.

> No SSH/Terminal? Every `bin/` script can also be triggered from a browser once,
> or I can run these steps for you during setup.

---

## Command-line tools

| Command | What it does |
|---|---|
| `php bin/migrate.php` | Create/verify all tables (safe to re-run). |
| `php bin/import_gedcom.php FILE.ged` | Import/refresh the tree from a GEDCOM export. Re-run any time you update the tree on TribalPages. |
| `php bin/import_photos.php DIR [--dry]` | Auto-pin every photo in `DIR` to the right person by filename. `--dry` previews without copying. Re-runnable; skips photos already imported. |
| `php bin/seed_admin.php "Name" email pass` | Create (or promote) the first admin. |

### How photo auto-pinning works
The importer reads each filename and matches it to a person:
`Elizabeth Battles 3.jpg` → **Elizabeth Battles**, `Andrea K Battles 1.jpg` →
first-name + surname match, `Elbert Domino Sr 1.jpg` → suffix-aware match.
Trailing numbers, `(1)` duplicates and `(nicknames)` are ignored.
Anything it can't confidently match is **listed at the end** so nothing is mis-pinned —
name those, add them to the `OVERRIDES` map in `bin/import_photos.php`, and re-run.

---

## Privacy model
- People with a death date, or born on/before 1935, are treated as **deceased** → shown publicly.
- Everyone else is flagged **living** → name is reduced to first name + surname initial and
  all dates/photos are hidden from the public. Signed-in family members see them in full.

## Notes
- `config.php`, the SQLite dev database, and the photos folder are git-ignored — no
  passwords or family data are ever committed. The repository is code only.

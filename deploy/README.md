# Server rewrite rules (thebattlesfamily.com)

The application lives in `public_html/legacy/`, but the site answers at the
bare domain. Two `.htaccess` files do that, kept here so they are in version
control alongside the code they serve.

| file in this folder | goes to |
|---|---|
| `htaccess-webroot` | `public_html/.htaccess` |
| `htaccess-legacy`  | `public_html/legacy/.htaccess` |

`public_html/.htaccess` maps every request into `legacy/public/`, so a page at
`/faith.php` is really `legacy/public/faith.php` and its relative links and
assets resolve without changing a line of PHP. `.well-known/` is excluded so
the SSL certificate keeps renewing.

`legacy/.htaccess` blocks the internals and sends any surviving `/legacy/...`
address to the root with a 301.

Two things worth knowing before editing either file:

- The `/legacy/` -> root redirect has to live in `legacy/.htaccess`, not in the
  web root. When the path resolves to a real file under `public/`, the rules in
  that directory win and a redirect placed in the root never fires.
- That redirect is conditioned on `%{THE_REQUEST}`, which only ever holds the
  original browser request line. A condition on `%{REQUEST_URI}` would also
  match the root's internal rewrite into `legacy/public/` and loop.

The previous WordPress rewrite block is saved on the server as
`public_html/htaccess.bak-wordpress-2026-08-15`. The old TNG and WordPress
installs are untouched on disk; they are simply no longer served.

`config.php` on the server carries `base_url => https://thebattlesfamily.com`.
That value builds the invitation and password-reset links, so it has to match
the address family actually use.

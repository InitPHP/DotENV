# Security Best Practices

A `.env` file usually holds secrets — database passwords, API keys, signing
keys. Treat it accordingly.

## Keep `.env` files out of the web root

If a `.env` file sits under a publicly served directory, a single
misconfiguration can serve it as plain text and leak every secret in it.

- **Best:** keep the file in a directory the web server never serves — for
  example one level above the document root — and point `create()` at it:

  ```php
  DotENV::create(dirname($_SERVER['DOCUMENT_ROOT'])); // /var/www, not /var/www/public
  ```

- **Otherwise:** block access explicitly.

  Apache:

  ```apacheconf
  <Files ".env">
      Require all denied
  </Files>
  ```

  nginx:

  ```nginx
  location ~ /\.env {
      deny all;
  }
  ```

## Never commit real secrets

Commit a `.env.example` with **empty or dummy** values as documentation, and
keep the real `.env` out of version control:

```gitignore
# .gitignore
/.env
/.env.local
/.env.*.local
```

```dotenv
# .env.example  (safe to commit)
APP_ENV   = production
APP_KEY   =
DB_HOST   = 127.0.0.1
DB_PORT   = 5432
DB_PASS   =
```

New contributors copy it: `cp .env.example .env`.

## Prefer real environment variables in production

`create()` never overwrites a name already set in `$_ENV`, `$_SERVER`, or
`getenv()` (see [Loading & Precedence](Loading-and-Precedence.md#immutability--real-environment-variables-win)).
So the most robust production setup is:

- Set real secrets as **actual environment variables** (via your process
  manager, container orchestrator, or platform secrets store).
- Keep the `.env` file for **local development** only.

In production the real values win, and you can ship without a `.env` at all.

## `.env.php` executes as code

A [`.env.php`](Using-a-PHP-env-File.md) file is loaded with `require`, so it runs
as PHP. Only ever load a `.env.php` you control. Loading one from an untrusted
or user-writable path is equivalent to running arbitrary code:

```php
// DANGER: never do this with an attacker-influenced path
DotENV::create($_GET['config']); // remote/arbitrary code execution risk
```

Always pass a fixed, trusted path (e.g. `__DIR__`, a constant, or a vetted
config directory).

## Don't leak values into error output

Configuration often ends up in stack traces and debug pages. In production:

- Turn `display_errors` off; log instead.
- Be careful printing `$_ENV` / `$_SERVER` (e.g. `phpinfo()`, debug dumps) —
  they now contain your secrets after `create()`.

## Rotate on exposure

If a secret in a `.env` is ever committed, pushed, or leaked, **rotate it** —
removing the file from the latest commit does not remove it from git history
or from anywhere it was already fetched or cached.

## Checklist

- [ ] `.env` is git-ignored; only `.env.example` is committed.
- [ ] `.env` lives outside the web root, or is blocked by the web server.
- [ ] Production secrets are real environment variables, not a deployed file.
- [ ] `.env.php` paths are fixed and trusted.
- [ ] `display_errors` is off in production.
- [ ] A process exists to rotate leaked keys.

## Next steps

- [Loading & Precedence](Loading-and-Precedence.md) — why real env vars win
- [Using a PHP `.env` File](Using-a-PHP-env-File.md) — the `require` caveat
- [Recipes](Recipes.md) — layered and per-environment loading

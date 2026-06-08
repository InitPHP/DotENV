# Security notes

A `.env` file usually holds secrets — database passwords, API keys, signing
keys. Treat it accordingly.

## Keep `.env` files out of the web root

If a `.env` file sits under a publicly served directory, a misconfiguration
can serve it as plain text and leak every secret in it.

- **Best:** keep the file in a directory that the web server never serves
  (e.g. one level above the document root) and point `create()` at it.
- **Otherwise:** block access explicitly. With Apache:

  ```apacheconf
  <Files ".env">
      Require all denied
  </Files>
  ```

  With nginx:

  ```nginx
  location ~ /\.env {
      deny all;
  }
  ```

## Never commit real secrets

Commit a `.env.example` with empty or dummy values and add `.env` to your
`.gitignore`. Load the example only as documentation, not at runtime.

## `.env.php` runs code

A [`.env.php`](php-env-file.md) file is loaded with `require`, so it is
executed as PHP. Only ever load a `.env.php` file you control. Loading one
from an untrusted or user-writable location is equivalent to running arbitrary
code.

## Environment variables take precedence

`create()` does not overwrite names that already exist in `$_ENV` or
`$_SERVER`. In production you can therefore set the real secrets as actual
environment variables (via your process manager, container, or platform) and
keep the `.env` file for local development only — the real values win.

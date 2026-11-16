# PeopleMate

People directory, team chart, updates, and PHP-backed admin dashboard.

## Runtime
- Requires PHP 7.4 or newer with the `fileinfo` extension.
- `index.php` renders public pages and search metadata; `app-template.php` contains the page UI.
- `api.php` handles admin login, people/posts/settings, and image/video uploads.
- `admin/` provides the admin route. `feed.php`, `sitemap.php`, `robots.php`, and `favicon.php` provide site metadata and feeds.
- `seed.json` initializes a new installation. Saved content, admin credentials, and uploaded files live in `data/` and `uploads/`.
- `index.html` remains as a redirect for older links; `preview.html` is a static preview.

## Local check
Run the site from this directory with PHP's built-in server:

```powershell
php -S 127.0.0.1:8000
```

Then open `http://127.0.0.1:8000/`. The `data/` and `uploads/` directories must be writable by PHP.

## Deployment
The `main` branch GitHub Actions workflow deploys to the configured SSH target. It keeps runtime content and uploads on the server rather than syncing them from the repository. Configure the required SSH secrets in the repository before using the workflow.

For host-specific setup and admin guidance, see [README.txt](README.txt).

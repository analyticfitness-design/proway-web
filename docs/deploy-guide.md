# ProWay Lab — Deploy Guide

## Current Manual Deploy Process

1. Push changes to GitHub:
   ```bash
   git add -A && git commit -m "description" && git push origin main
   ```

2. Open the EasyPanel console for the `proway-lab` service.

3. Pull the latest code inside the container:
   ```bash
   cd /code && git pull origin main
   ```

4. The Apache/PHP service picks up the changes immediately (no restart needed for PHP files).

---

## EasyPanel Auto-Deploy Setup

To enable automatic deploys on every push to `main`:

1. Open your EasyPanel dashboard.
2. Navigate to the **proway-lab** service.
3. Go to the **Git** tab in the service settings.
4. Configure:
   - **Repository**: `https://github.com/<org>/prowaylab.git`
   - **Branch**: `main`
   - **Auto-deploy**: Enable the toggle
5. Add your GitHub deploy key or personal access token if the repo is private.
6. Save. EasyPanel will now pull and rebuild on every push.

---

## Alternative: GitHub Webhook

If EasyPanel auto-deploy is not available or you prefer webhooks:

1. In your GitHub repo, go to **Settings > Webhooks > Add webhook**.
2. Set the **Payload URL** to your EasyPanel webhook endpoint (check EasyPanel docs for the exact URL format).
3. Set **Content type** to `application/json`.
4. Select **Just the push event**.
5. Save the webhook.

---

## Build Process

After pulling new code, run the frontend build:

```bash
node scripts/build-templates.mjs && npx vite build
```

This compiles Nunjucks templates and bundles the Vite frontend assets into `dist/`.

---

## Cache Busting

When updating CSS/JS assets, increment the version query parameter in `src/templates/base.njk`:

```html
<link rel="stylesheet" href="/dist/assets/main.css?v=N">
<script type="module" src="/dist/assets/main.js?v=N"></script>
```

Increment `N` each time you deploy asset changes to force browsers to fetch the new files.

---

## Running Database Migrations

After deploying code that includes new migration files in `api/setup/migrations/`, run the migration runner:

```bash
curl -X GET \
  -H "X-Api-Secret: YOUR_API_SECRET" \
  https://prowaylab.com/api/setup/run-migrations.php
```

The runner will:
- Create the `_migrations` tracking table if it does not exist.
- Scan `api/setup/migrations/*.sql` in alphabetical order.
- Execute only new (unrecorded) migrations, wrapped in transactions.
- Return a JSON response listing which migrations ran, which were skipped, and any errors.

Example response:
```json
{
  "success": true,
  "ran": ["003_add_new_table.sql"],
  "skipped": ["001_create_notifications.sql", "002_create_activity_log.sql"],
  "errors": [],
  "total": 3
}
```

### Creating New Migrations

1. Create a new `.sql` file in `api/setup/migrations/`.
2. Name it with a numeric prefix for ordering: `003_description.sql`, `004_description.sql`, etc.
3. Use `CREATE TABLE IF NOT EXISTS` or `ALTER TABLE` statements.
4. Commit and push, then run the migration endpoint above.

---

## Environment Variables

Key environment variables (set in EasyPanel service config or `.env`):

| Variable | Description |
|---|---|
| `DB_HOST` | MySQL hostname |
| `DB_NAME` | Database name |
| `DB_USER` | Database user |
| `DB_PASS` | Database password |
| `API_SECRET` | Secret for protected endpoints (migrations, etc.) |
| `ALLOWED_ORIGINS` | Comma-separated list of allowed CORS origins |

---

## Troubleshooting

- **Migrations fail**: Check the JSON error response for the specific SQL error. Fix the migration file, push, and re-run.
- **CSS/JS not updating**: Increment the `?v=N` parameter in `base.njk` and rebuild.
- **CORS errors**: Verify `ALLOWED_ORIGINS` includes the frontend domain.
- **Auth issues after deploy**: Token expiry is configured via `TOKEN_EXPIRY_CLIENT` and `TOKEN_EXPIRY_ADMIN` env vars (hours).

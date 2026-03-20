# Auto-Deploy Webhook — Setup Guide

## Overview

`deploy-webhook.php` (at the project root) is a standalone PHP endpoint that runs
`git pull origin main` inside the production container when called with a valid secret.

**URL:** `https://prowaylab.com/deploy-webhook.php`

---

## Option A — GitHub Webhook (recommended)

### 1. Configure `DEPLOY_SECRET` in EasyPanel

1. Open **EasyPanel → prowaylab → Environment**
2. Add the variable:
   ```
   DEPLOY_SECRET=<your-secret>
   ```
   Use the value already present in `api/.env` or generate a new one:
   ```bash
   openssl rand -hex 32
   ```
3. Save and redeploy (or restart the service) so the container picks up the new var.

### 2. Add the GitHub Webhook

1. Go to your GitHub repo → **Settings → Webhooks → Add webhook**
2. Fill in:
   | Field | Value |
   |-------|-------|
   | Payload URL | `https://prowaylab.com/deploy-webhook.php?secret=YOUR_SECRET` |
   | Content type | `application/json` |
   | Secret | *(leave blank — secret is in the URL query param)* |
   | Which events? | **Just the push event** |
   | Active | ✓ checked |
3. Click **Add webhook**.

### 3. Test it

After the next `git push origin main`, GitHub will POST the payload to the endpoint.
You can also trigger a manual test from the GitHub webhook page → **Recent Deliveries → Redeliver**.

Or test directly with curl:
```bash
curl -s "https://prowaylab.com/deploy-webhook.php?secret=YOUR_SECRET" | jq .
```

---

## Option B — EasyPanel Git Auto-Deploy Tab

EasyPanel has a built-in Git auto-deploy feature that avoids the need for this webhook entirely.

1. Open **EasyPanel → prowaylab → Source**
2. Under **Git**, enable **Auto Deploy on Push**
3. EasyPanel will poll (or use its own webhook) and redeploy when `main` changes.

> Note: EasyPanel's auto-deploy triggers a full container rebuild from the Dockerfile.
> The `deploy-webhook.php` approach does a fast `git pull` inside the running container
> without rebuilding — useful when you want zero-downtime hot updates.

---

## Webhook Behaviour

| Scenario | HTTP Status | Response |
|----------|-------------|----------|
| Valid secret + POST push to `main` | 200 | `{"status":"ok","output":"..."}` |
| Valid secret + POST push to other branch | 200 | `{"status":"skipped"}` |
| Valid secret + GET (manual trigger) | 200 | `{"status":"ok","output":"..."}` |
| Invalid or missing secret | 403 | `{"error":"Forbidden"}` |
| `DEPLOY_SECRET` not set in env | 500 | `{"error":"Webhook not configured"}` |

---

## Log File

Deploy attempts are logged to `api/data/deploy.log` inside the container:

```
[2026-03-20T12:00:00+00:00] [INFO] Deploy ok {"output":"Already up to date."}
[2026-03-20T12:01:00+00:00] [WARN] Unauthorized deploy attempt {"ip":"1.2.3.4"}
```

The log auto-rotates at 512 KB (renamed to `deploy.log.1`).

To tail logs from the EasyPanel terminal:
```bash
tail -f /code/api/data/deploy.log
```

---

## Security Notes

- The secret comparison uses `hash_equals()` (constant-time) to prevent timing attacks.
- The git command is fully hardcoded — no user input reaches `shell_exec`.
- Unauthorized requests log the remote IP and return a generic 403 with no details.
- The endpoint does NOT go through the API router (`api/index.php`) — it is a standalone
  file served directly by Nginx from the web root.

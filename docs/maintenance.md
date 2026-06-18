# Vi2ionai Fleet — Maintenance Mode

## Overview

Maintenance mode shows a premium 503 page to public visitors while keeping
the admin panel and API fully accessible. Logged-in admins are automatically
redirected to the homepage (bypass) — they never see the maintenance screen.

---

## Architecture

```
Request arrives
      │
      ├─ /admin/*  ──────────────────────────────────► Always accessible
      ├─ /api/*    ──────────────────────────────────► Always accessible
      │
      └─ /*  (public)
              │
              ├─ .maintenance file EXISTS?
              │       YES ──► Nginx returns 503 ──► maintenance.php
              │                                       │
              │                                       ├─ admin session? ──► redirect /
              │                                       └─ visitor?       ──► 503 page
              │
              └─ NO ──► Normal site
```

**Files involved:**

| File | Role |
|------|------|
| `/var/www/vi2ionai/.maintenance` | Flag file — create to enable, delete to disable |
| `maintenance.php` | 503 page with admin session bypass |
| `infra/nginx-vi2ionai.conf` | `if (-f .maintenance) return 503` in `location /` |
| `docs/maintenance.md` | This file |

---

## How to Enable Maintenance Mode

SSH to the VPS and create the flag file:

```bash
ssh -i ~/.ssh/vi2ion_fleet root@72.62.192.223
touch /var/www/vi2ionai/.maintenance
```

**That's it.** No nginx reload required — the `if (-f ...)` check is evaluated
per-request. The change takes effect within milliseconds.

Verify it's active:

```bash
curl -o /dev/null -s -w "%{http_code}" http://vi2ionai.com.au:8082/
# Expected: 503
```

---

## How to Disable Maintenance Mode

```bash
ssh -i ~/.ssh/vi2ion_fleet root@72.62.192.223
rm /var/www/vi2ionai/.maintenance
```

Verify the site is back:

```bash
curl -o /dev/null -s -w "%{http_code}" http://vi2ionai.com.au:8082/
# Expected: 200 or 301/302
```

---

## Admin Bypass

When maintenance mode is active:

- **Logged-in admins** — visiting any public URL redirects them to `/`
  (the homepage is served normally because PHP serves `maintenance.php`
  which detects the session and issues `Location: /`)
- **Admin panel** — `/admin/*` is never intercepted by the maintenance
  check (separate Nginx location block)
- **API** — `/api/*` is also always accessible
- **Deployments** — GitHub Actions rsync/SSH deploys are unaffected

To log in during maintenance, visit:
`https://vi2ionai.com.au/admin/` — this bypasses the maintenance flag
entirely at the Nginx level.

---

## Customising the Maintenance Page

Edit `maintenance.php` in the repo root. Key sections:

- **Heading**: `<h1>We'll be back<br><span>shortly.</span></h1>`
- **Body copy**: The `<p class="body-copy">` paragraph
- **ETA card**: The four `eta-item` divs — update "Estimated Return" and
  "What's changing" with current info before enabling
- **Contact email**: Change `info@vi2ionai.com.au` in the CTA and footer

After editing, commit and push — the next GitHub Actions deploy will update
the file on the VPS automatically.

---

## Safe Rollback

If something goes wrong with the nginx config (e.g. after a bad deploy),
the `if (-f ...)` block is in the `location /` block only. To remove it
without a full deploy:

```bash
ssh -i ~/.ssh/vi2ion_fleet root@72.62.192.223
# Edit the nginx vhost directly
nano /etc/nginx/sites-available/vi2ionai
# Remove or comment out the `if (-f ...) return 503;` block
nginx -t && systemctl reload nginx
```

---

## SEO Safety

- `maintenance.php` sends `HTTP 503 Service Unavailable` + `Retry-After: 3600`
- `<meta name="robots" content="noindex, nofollow">` prevents the 503 from
  being indexed
- Google treats 503 as a temporary outage and retains existing rankings for
  several days; a permanent outage (5+ days of 503) may cause re-crawl delays
- Limit maintenance mode to short windows (< 4 hours) where possible

---

## Effect on Other Services

| Service | Affected? |
|---------|-----------|
| Cognify LMS (`lms.vi2ionai.com`) | ❌ No — separate Nginx vhost, separate domain |
| Admin panel (`/admin/`) | ❌ No — bypassed at Nginx level |
| GitHub Actions deploys | ❌ No — SSH/rsync, not HTTP |
| Contact form API (`/api/`) | ❌ No — bypassed at Nginx level |
| Public website | ✅ Yes — shows 503 page |

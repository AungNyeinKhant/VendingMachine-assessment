# Deployment Checklist (P7)

> **Stop here** — this checklist is for you to execute with your accounts.
> Secrets go **only** into Render Environment Variables, never in chat or git.

## Prerequisites (create these accounts first)

- [ ] GitHub account (for repo + Actions)
- [ ] Render account (free tier) — <https://render.com>
- [ ] Aiven account (free MySQL) — <https://aiven.io>
- [ ] UptimeRobot account (free) — <https://uptimerobot.com>

---

## 1. Push to GitHub

```bash
# Create a new public repo on GitHub (e.g. vending-machine)
# Then push:
git remote add origin https://github.com/<your-username>/vending-machine.git
git push -u origin main
```

---

## 2. Aiven MySQL (free)

1. Create project → **MySQL** → **Free** plan
2. Choose region close to Render (e.g. `us-east-1` for Ohio, `eu-central-1` for Frankfurt)
3. Wait for service to be **Running**
4. Copy connection details:
    - Host, Port, User (`avnadmin`), Password, Database (`defaultdb`)
    - **SSL CA Certificate** → Download `ca.pem` (save locally, you'll upload to Render)
5. Nothing to run by hand — the container migrates and seeds `defaultdb` on start.

---

## 3. Render Web Service

1. **New** → **Web Service** → Connect your GitHub repo
2. Settings:
    - **Name**: `vending-machine`
    - **Region**: same as Aiven (e.g. `Oregon (US West)` or `Frankfurt (EU Central)`)
    - **Branch**: `main`
    - **Runtime**: `Docker`
    - **Dockerfile Path**: `./Dockerfile`
    - **Port**: `8080` (matches Dockerfile EXPOSE)
3. **Environment Variables** (add all):
    | Key                     | Value                                                                       |
    | ----------------------- | --------------------------------------------------------------------------- |
    | `APP_NAME`              | `Vending Machine`                                                           |
    | `APP_ENV`               | `production`                                                                |
    | `APP_DEBUG`             | `false`                                                                     |
    | `APP_URL`               | `https://<your-service>.onrender.com` (fill after first deploy)             |
    | `APP_KEY`               | `base64:...` (generate locally: `php artisan key:generate --show`)          |
    | `DB_CONNECTION`         | `mysql`                                                                     |
    | `DB_HOST`               | `<aiven-host>`                                                              |
    | `DB_PORT`               | `<aiven-port>` (usually 3306 or 28xxx)                                      |
    | `DB_DATABASE`           | `defaultdb`                                                                 |
    | `DB_USERNAME`           | `avnadmin`                                                                  |
    | `DB_PASSWORD`           | `<aiven-password>`                                                          |
    | `DB_SSL_CA`             | `/etc/secrets/aiven-ca.pem`                                                 |
    | `SESSION_DRIVER`        | `database`                                                                  |
    | `CACHE_STORE`           | `database`                                                                  |
    | `LOG_CHANNEL`           | `stderr`                                                                    |
    | `SESSION_SECURE_COOKIE` | `true`                                                                      |
    | `MAIL_MAILER`           | `log`                                                                       |
    | `JWT_SECRET`            | 64 hex chars — generate locally: `php -r "echo bin2hex(random_bytes(32));"` |
    | `JWT_TTL`               | `3600` (seconds)                                                            |
4. **Secret Files** (Render → Environment → Secret Files):
    - **Filename**: `aiven-ca.pem` (Render mounts it at `/etc/secrets/aiven-ca.pem`)
    - **Contents**: paste the contents of your downloaded `ca.pem`
5. **Advanced** → **Health Check Path**: `/up`
6. Click **Create Web Service** → wait for first deploy (5–10 min)
7. After first deploy, copy the live URL (e.g. `https://vending-machine-xyz.onrender.com`)
8. Update `APP_URL` env var to that exact URL → **Save Changes** → triggers redeploy

---

## 4. Verify Live

- [ ] Open `https://<your-app>.onrender.com` → should redirect to `/products`
- [ ] Login as `admin@vending.test` / `password` → can create/edit/delete products
- [ ] Login as `user@vending.test` / `password` → can purchase, cannot manage products
- [ ] Visit `/docs` → Scribe API docs load with "Try it out"
- [ ] Test API: `POST /api/v1/auth/login` → get token → `GET /api/v1/products`

---

## 5. UptimeRobot (free ping)

1. Add Monitor → **HTTP(s)**
2. URL: `https://<your-app>.onrender.com/up`
3. Interval: 5 minutes
4. Alert contacts: your email

---

## 6. GitHub Actions CI

The workflow at `.github/workflows/tests.yml` runs on every push to `main`.
It uses MySQL 8 service and runs:

- `composer ci:check` (see `composer.json` scripts)

No extra setup needed — it works out of the box.

---

## 7. Cold Start Note

Render free tier spins down after 15 min of inactivity.
First request after spin-down takes ~30–60 s (cold start).
UptimeRobot ping every 5 min keeps it warm.

---

## Rollback / Redeploy

- Render: **Manual Deploy** → **Deploy latest commit**
- Or push to `main` → auto-deploys
- On every container start: `migrate --force` (serversideup `AUTORUN_ENABLED`), then
  `db:seed --force` (`docker/entrypoint.d/60-seed.sh`). Seeders are insert-only, so
  restarts never reset stock, prices or users.

---

## Troubleshooting

| Issue                                       | Fix                                                                                      |
| ------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `SQLSTATE[HY000] [2002] Connection refused` | Check Aiven host/port, ensure Render VPC peering not required (public endpoint works)    |
| `SSL certificate problem`                   | Verify the Secret File is named `aiven-ca.pem` and `DB_SSL_CA=/etc/secrets/aiven-ca.pem` |
| `APP_KEY` missing                           | Generate locally: `php artisan key:generate --show`, paste as `APP_KEY=base64:...`       |
| Migrations fail                             | Check DB user has CREATE TABLE privileges on `defaultdb`                                 |
| 500 on `/docs`                              | Ensure `APP_URL` matches the live HTTPS URL exactly                                      |

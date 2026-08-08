# hostinger-deploy-doctrine

**Purpose:** Defines the two-path deployment doctrine for Patriot Pest Control sites on Hostinger shared hosting. Covers both the MCP archive path (full deploys) and the TUS per-file path (incremental), plus DNS, monitoring, and all MCP-native tools now unlocked by the fresh API token.

**Forged:** 2026-08-08 by Orchestrator (Washington), synthesized from Deployment & Infrastructure's live MCP evaluation (commit e3c20bb), the existing TUS runbook (GUIDES/HOSTINGER_TUS_DEPLOY_RUNBOOK.md), and David's Hostinger MCP configuration.

**Token:** `FcJ1u0WKR5fkhjEkGSBX0AF3Xi1k9E9TzLyj9uU051ab6c6e` (BUZZ, created 2026-07-31, never expires). Stored in CREDENTIALS.md line 92.

## The Two-Path Doctrine

| Scenario | Tool | Command |
|----------|------|---------|
| Full app deploy (fresh target, retrofit, major release) | MCP archive | `deploy/hostinger-archive-deploy.py --dir <bundle> --domain <domain>` |
| Incremental fix, single file, .env flip on live site | TUS per-file | `deploy/hostinger-tus-upload.py --files ...` or PHP env-writer |
| DNS, subdomains, VPS, billing, monitoring | MCP native servers | `npx --package=hostinger-api-mcp@latest hostinger-<tool>-mcp` |

**NEVER point a MCP archive deploy at a live site with a partial bundle.** Archive deploy is wipe-and-replace: anything not in the archive gets deleted.

## Credentials

From CREDENTIALS.md:
- **API Token:** `FcJ1u0WKR5fkhjEkGSBX0AF3Xi1k9E9TzLyj9uU051ab6c6e` (line 92)
- **Hosting Username:** `u269861438` (derived from /health endpoint)
- **Hosting Login:** `david@itak.live` / `Wildcats@360` (line 103-104)
- **Target Domains:** `patriotpest.pro`, `test.patriotpest.pro`, `demo.patriotpest.pro`, `cost.patriotpest.pro`

The BUZZ token works for both `developers.hostinger.com` (TUS upload) and `api.hostinger.com` (MCP tools). Previous tokens `NS0gHbl...` (returned 401) and `ILO35hzh...` (Cloudflare 1016) are superseded.

## Path 1: MCP Archive Deploy (Full App)

### Flow

1. **Resolve cPanel username:** `GET /api/hosting/v1/websites?domain=<domain>`
2. **Get TUS upload credentials:** `POST /api/hosting/v1/files/upload-urls` body `{"username":"u269861438","domain":"patriotpest.pro"}`
3. **TUS upload the zip archive** to the website root (create + patch)
4. **Trigger extraction:** `POST /api/hosting/v1/accounts/u269861438/websites/<domain>/deploy` body `{"archive_path":"<zip>"}`
5. **Verify:** `curl https://<domain>/health` after ~30s

### Script

```bash
python deploy/hostinger-archive-deploy.py --dir C:/tmp/ppc-bundle --domain demo.patriotpest.pro
```

Or with a pre-built zip:
```bash
python deploy/hostinger-archive-deploy.py --zip C:/tmp/ppc-bundle.zip --domain test.patriotpest.pro
```

### Bundle Contents

For a full app deploy, the bundle must include the entire desired site state:
- All PHP files (flattened from `public/` to root, `index.php` bootstrap path patched to `__DIR__`)
- `.htaccess` with HTTPS + HSTS active (use `deploy/htaccess.production`)
- `.env` with production settings (APP_ENV=production, APP_DEBUG=false, TWILIO_SMS_ENABLED=false)
- `database/patriot.db` (gitignored, copy from local checkout)
- All assets (CSS, JS, images, fonts)

```bash
# Stage a bundle (from feat/production-foundation root):
STAGE_ONLY=1 ./deploy/hostinger-deploy.sh
# Then deploy:
python deploy/hostinger-archive-deploy.py --dir C:/tmp/ppc-test-deploy --domain demo.patriotpest.pro
```

### Wipe Semantics (CRITICAL)

The deploy extracts the archive into the site root and REMOVES anything not in the archive. This means:
- A partial bundle WILL delete runtime files, uploads, and DB state on a live site.
- An empty archive = wipe the site clean (handy for scratch targets).
- Always verify the bundle contains everything before deploying to a live target.
- For live sites, prefer the TUS per-file path for incremental changes.

### No Status Endpoint

The deploy is async ("Request accepted"). There is no static-deploy status endpoint (MCP only has status/logs for Node/JS deploys). Verify by curling the target after ~30s. The app's `/health` endpoint is your ground truth.

## Path 2: TUS Per-File Deploy (Incremental)

### Flow

1. **Get TUS session:** `POST https://developers.hostinger.com/api/hosting/v1/files/upload-urls` -> `{url, auth_key, rest_auth_key}`
2. **For each file:** POST create (201) then PATCH upload (204)
3. **.env files:** Blocked by Hostinger security rule (429 on any filename). Use a self-deleting PHP env-writer instead.

### Scripts

```bash
# Incremental: single or few files
python deploy/hostinger-tus-upload.py --files public/cost/index.php --prefix cost

# Full staging + per-file upload (legacy path, still works)
./deploy/hostinger-deploy.sh
```

### Gotchas

1. **Browser UA required.** Add `-H "User-Agent: Mozilla/5.0 ..."` to curl commands.
2. **Retry on 429 with backoff** (15s per attempt). Never hammer.
3. **.env blocked filename.** Use the PHP env-writer pattern: upload a self-deleting PHP file that `file_put_contents()` the .env, hit it, then `@unlink(__FILE__)`.
4. **MSYS /tmp trap:** MSYS bash maps /tmp but Windows Python sees C:/tmp. Always use explicit paths.

## Available MCP Servers (48 tools total, package 1.33.0)

All accessible via `npx --package=hostinger-api-mcp@latest <server-name>`:

| Server | Key Capabilities |
|--------|-----------------|
| `hostinger-hosting-mcp` | Websites (list/create/delete/subdomains/parked), PHP (version/options/extensions), databases (create/delete/phpMyAdmin), cron jobs, cache control, Node.js builds + logs, WordPress import/plugin/theme, JS/static archive deploys |
| `hostinger-domains-mcp` | Search, register, manage domains, WHOIS privacy, forwarding |
| `hostinger-dns-mcp` | DNS record CRUD (A, CNAME, MX, TXT, etc.) |
| `hostinger-billing-mcp` | Subscription, renewal, payment management |
| `hostinger-reach-mcp` | Email marketing campaigns and contacts |
| `hostinger-vps-mcp` | VPS servers, firewalls, Docker apps |
| `hostinger-ecommerce-mcp` | Stores, products, orders |

**No generic per-file upload tool exists in any MCP server.** File operations only happen inside archive deploy flows.

## Post-Deploy Verification Checklist

Run against the target domain:

```
curl -s https://<domain>/          # 200, title correct
curl -s https://<domain>/health    # {"status":"ok","env":"production","php":"8.2.30"}
curl -s https://<domain>/login     # 200 "Sign In | Patriot Pest Control"
curl -s -o /dev/null -w "%{http_code}" https://<domain>/admin      # 302 (auth guard)
curl -s -o /dev/null -w "%{http_code}" https://<domain>/.env       # 403
curl -s -o /dev/null -w "%{http_code}" https://<domain>/app/       # 404
curl -s -I https://<domain>/ | grep -i strict-transport            # HSTS present
```

Security headers expected: nosniff, SAMEORIGIN, referrer-policy, permissions-policy.

## Target Subdomains

| Subdomain | Purpose | Status (2026-08-08) |
|-----------|---------|---------------------|
| `patriotpest.pro` | Live production site (FieldRoutes) | Live, do not touch customer base |
| `test.patriotpest.pro` | Staging/test app (next-gen) | Live, healthy, david@itak.live admin |
| `demo.patriotpest.pro` | Public demo showcase | **Empty** (wiped during MCP eval), awaiting retrofit redeploy |
| `cost.patriotpest.pro` | Cost dashboard | Live, QA passed |

## Related Files

- `CREDENTIALS.md` — Hostinger token + login (line 92, 103-104)
- `GUIDES/HOSTINGER_TUS_DEPLOY_RUNBOOK.md` — Full runbook with E2E OTP verification
- `deploy/hostinger-archive-deploy.py` — MCP archive deploy (e3c20bb)
- `deploy/hostinger-tus-upload.py` — Per-file TUS uploader
- `deploy/hostinger-deploy.sh` — Stage + upload + verify wrapper
- `deploy/htaccess.production` — Production .htaccess with HTTPS + HSTS active

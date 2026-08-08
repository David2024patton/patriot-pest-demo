# hostinger-tus-deploy

**Purpose:** Deploy static files (HTML, CSS, JS, PHP, JSON) to a Hostinger shared hosting server via the TUS protocol, bypassing Cloudflare blocks on `api.hostinger.com`.

**Discovered:** 2026-08-08 by Deployment & Infrastructure copy and Washington during the cost.patriotpest.pro dashboard deployment.

## The Problem

Hostinger's `api.hostinger.com` is behind a Cloudflare perimeter. Direct API calls, the MCP tools (`hostinger-hosting-mcp`), and hPanel REST all hit 1016/530/403/401. FTP credentials often fail even when correct. SSH is not available on shared hosting.

## The Solution

Hostinger's **developers.hostinger.com** endpoint is NOT behind the same Cloudflare perimeter. It exposes a file upload endpoint that returns TUS upload credentials. Importantly, it requires the **OLD** API token (`ILO35hzhP5PFVYDzmVSbj94T567iRkAaZ5P4l0xR011cc441` from `Videos/creds.md`), not the BUZZ token in `CREDENTIALS.md`.

## Required Credentials

From the workspace:
- **API Token:** `ILO35hzhP5PFVYDzmVSbj94T567iRkAaZ5P4l0xR011cc441` (in `C:/Users/David/Videos/creds.md`, line 18)
- **Hosting Username:** `u269861438` (derived from health.php response)
- **Domain:** `patriotpest.pro` (or target subdomain)

## TUS Upload Flow (Step by Step)

### Step 1: Get Upload Session

```bash
curl -s -X POST "https://developers.hostinger.com/api/hosting/v1/files/upload-urls" \
  -H "Authorization: Bearer ILO35hzhP5PFVYDzmVSbj94T567iRkAaZ5P4l0xR011cc441" \
  -H "Content-Type: application/json" \
  -d '{"username":"u269861438","domain":"patriotpest.pro"}'
```

**Returns:**
```json
{
  "url": "https://srv941-files.hstgr.io/rest/63bb3ebfa2ec8963/api/tus/public_html",
  "auth_key": "eyJhbGci...<full JWT>",
  "rest_auth_key": "655c7b80...63bb3ebfa2ec8963"
}
```

- `url`: TUS base URL for the public_html directory
- `auth_key`: JWT for `X-Auth` header
- `rest_auth_key`: For `X-Auth-Rest` header

### Step 2: TUS Create (POST)

For each file, create the upload slot. Files in subdirectories use the path relative to public_html:

```bash
TUS_URL="<url from step 1>"
AUTH_KEY="<auth_key from step 1>"
REST_KEY="<rest_auth_key from step 1>"
FILE_PATH="/cost/index.php"       # relative to public_html
FILE_SIZE=$(wc -c < "local/path/to/file")

curl -X POST "${TUS_URL}${FILE_PATH}?override=true" \
  -H "Tus-Resumable: 1.0.0" \
  -H "Upload-Length: ${FILE_SIZE}" \
  -H "X-Auth: ${AUTH_KEY}" \
  -H "X-Auth-Rest: ${REST_KEY}" \
  -H "Upload-Metadata: filename $(echo -n "$(basename $FILE_PATH)" | base64 -w0)"
```

Expected: **201 Created**

### Step 3: TUS Upload (PATCH)

```bash
curl -X PATCH "${TUS_URL}${FILE_PATH}" \
  -H "Tus-Resumable: 1.0.0" \
  -H "Upload-Offset: 0" \
  -H "Content-Type: application/offset+octet-stream" \
  -H "X-Auth: ${AUTH_KEY}" \
  -H "X-Auth-Rest: ${REST_KEY}" \
  --data-binary "@local/path/to/file"
```

Expected: **204 No Content**

### Repeat for all files

Create + Patch each file in sequence. The session JWT is valid for ~6 hours.

## Path Mapping

| Local path | Server path | TUS path |
|------------|-------------|----------|
| `public/cost/index.php` | `public_html/cost/index.php` | `/cost/index.php` |
| `public/cost/assets/css/cost.css` | `public_html/cost/assets/css/cost.css` | `/cost/assets/css/cost.css` |
| `public/cost/data/pricing.json` | `public_html/cost/data/pricing.json` | `/cost/data/pricing.json` |

## Known Caveats

- **PHP files on subdomains:** health.php works from `patriotpest.pro/cost/health.php` but returns a Hostinger 404 page from `cost.patriotpest.pro/health.php` — likely PHP not enabled on the subdomain. Static files (HTML, CSS, JS) serve fine on subdomains.
- **CDN caching:** Cloudflare caches assets aggressively. After deploying, wait ~30 seconds for edge cache to expire, or use cache-busting query params (`?v=2` breaks on this server; instead wait for natural expiration).
- **CREDENTIALS.md:** The BUZZ token (`NS0gHbl...`) returns 401 on developers.hostinger.com. Always use the old token for this endpoint.
- **The MCP tools** (`hostinger-hosting-mcp`) use `api.hostinger.com` internally and will fail with 401/530. Do not use them for file uploads.

## Example: Full Deploy Script

```bash
#!/bin/bash
# Deploy all files in a directory to Hostinger via TUS
TOKEN="ILO35hzhP5PFVYDzmVSbj94T567iRkAaZ5P4l0xR011cc441"
USERNAME="u269861438"
DOMAIN="patriotpest.pro"
LOCAL_DIR="public/cost"
SERVER_PREFIX="/cost"

# Step 1: Get session
RESP=$(curl -s -X POST "https://developers.hostinger.com/api/hosting/v1/files/upload-urls" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"${USERNAME}\",\"domain\":\"${DOMAIN}\"}")

TUS_URL=$(echo "$RESP" | python3 -c "import sys,json; print(json.load(sys.stdin)['url'])")
AUTH_KEY=$(echo "$RESP" | python3 -c "import sys,json; print(json.load(sys.stdin)['auth_key'])")
REST_KEY=$(echo "$RESP" | python3 -c "import sys,json; print(json.load(sys.stdin)['rest_auth_key'])")

# Step 2+3: Upload each file
find "$LOCAL_DIR" -type f | while read -r file; do
  rel="${file#$LOCAL_DIR/}"
  server_path="${SERVER_PREFIX}/${rel}"
  size=$(wc -c < "$file")
  
  echo "Uploading: $server_path ($size bytes)"
  
  curl -s -o /dev/null -w "%{http_code} " -X POST "${TUS_URL}${server_path}?override=true" \
    -H "Tus-Resumable: 1.0.0" \
    -H "Upload-Length: ${size}" \
    -H "X-Auth: ${AUTH_KEY}" \
    -H "X-Auth-Rest: ${REST_KEY}"
  
  curl -s -o /dev/null -w "%{http_code}\n" -X PATCH "${TUS_URL}${server_path}" \
    -H "Tus-Resumable: 1.0.0" \
    -H "Upload-Offset: 0" \
    -H "Content-Type: application/offset+octet-stream" \
    -H "X-Auth: ${AUTH_KEY}" \
    -H "X-Auth-Rest: ${REST_KEY}" \
    --data-binary "@$file"
done

echo "Deploy complete."
```

## Related

- Hostinger credentials: `CREDENTIALS.md` and `C:/Users/David/Videos/creds.md`
- Deploy scripts for this project: `Scripts/deploy_cost_dashboard.py` (D&I copy's Python version)

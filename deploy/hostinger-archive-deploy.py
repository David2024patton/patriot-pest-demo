#!/usr/bin/env python3
"""
hostinger-archive-deploy.py — full-app deploy to Hostinger via one archive.

Replaces the per-file TUS loop for FULL deploys. Mirrors the exact flow of
Hostinger's official MCP tool hosting_deployStaticWebsite (verified live
2026-08-08 on demo.patriotpest.pro):

  1. GET  /api/hosting/v1/websites?domain=<d>          -> resolve cPanel username
  2. POST /api/hosting/v1/files/upload-urls            -> TUS upload credentials
  3. TUS  upload <archive>.zip to the website root      (POST create + PATCH)
  4. POST /api/hosting/v1/accounts/<u>/websites/<d>/deploy  {archive_path}
       -> server extracts the archive into the site root_directory

VERIFIED BEHAVIOR (2026-08-08):
  - PHP executes after extraction (not static-only in the PHP sense).
  - .htaccess ships and is enforced (.env 403, .db 404, security headers).
  - .env INSIDE the archive bypasses the TUS direct-upload 429 rule.
  - Nested directories preserved.
  - WIPE SEMANTICS: the deploy REPLACES the entire site root with the archive
    contents. Anything not in the archive is deleted. An empty archive wipes
    the site clean. Do NOT point this at a live site with a partial bundle.
  - Deploy is async ("Request accepted"); verify via HTTP afterwards
    (there is no static-deploy status endpoint).

Usage:
  python deploy/hostinger-archive-deploy.py --zip C:/tmp/ppc-test-deploy.zip --domain test.patriotpest.pro
  python deploy/hostinger-archive-deploy.py --dir C:/tmp/ppc-test-deploy --domain demo.patriotpest.pro
"""
import argparse, json, os, sys, time, urllib.request, urllib.error, zipfile

UA = ('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
      '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36')
DEFAULT_CREDS = os.path.join(os.environ.get('BUZZ_HOME', r'C:/Users/David/.buzz'), 'CREDENTIALS.md')
API = 'https://developers.hostinger.com'


def read_token(path: str) -> str | None:
    try:
        with open(path, encoding='utf-8', errors='replace') as f:
            for line in f:
                if line.startswith('HOSTINGER_API_TOKEN='):
                    t = line.split('=', 1)[1].strip()
                    if t:
                        return t
    except OSError as e:
        print(f'!! cannot read {path}: {e}', file=sys.stderr)
    return None


def req(method, url, headers=None, data=None, raw=None, timeout=180):
    r = urllib.request.Request(url, method=method, headers=headers or {})
    body = raw if raw is not None else (json.dumps(data).encode() if data is not None else None)
    try:
        with urllib.request.urlopen(r, data=body, timeout=timeout) as resp:
            return resp.status, resp.headers, resp.read()
    except urllib.error.HTTPError as e:
        return e.code, e.headers, e.read()


def make_zip(src_dir: str, out_zip: str) -> None:
    with zipfile.ZipFile(out_zip, 'w', zipfile.ZIP_DEFLATED) as zp:
        for root, dirs, names in os.walk(src_dir):
            dirs[:] = [d for d in dirs if d not in ('.git', 'node_modules')]
            for n in names:
                full = os.path.join(root, n)
                rel = os.path.relpath(full, src_dir).replace(os.sep, '/')
                if rel in ('make_zip.py',) or full.endswith('.pyc'):
                    continue
                zp.write(full, rel)
    print(f'zip: {out_zip} ({os.path.getsize(out_zip)} bytes, {len(zipfile.ZipFile(out_zip).namelist())} entries)')


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--domain', required=True, help='target website/subdomain, e.g. test.patriotpest.pro')
    ap.add_argument('--zip', help='existing archive (zip/tar/tgz/7z/gz/gzip)')
    ap.add_argument('--dir', help='directory to zip and deploy')
    ap.add_argument('--creds', default=DEFAULT_CREDS)
    args = ap.parse_args()
    if not args.zip and not args.dir:
        print('!! provide --zip or --dir', file=sys.stderr); sys.exit(1)

    tok = read_token(args.creds)
    if not tok:
        print('!! no HOSTINGER_API_TOKEN', file=sys.stderr); sys.exit(1)

    archive = args.zip
    if args.dir:
        archive = os.path.join(os.path.dirname(args.dir.rstrip('/')), os.path.basename(args.dir.rstrip('/')) + '.zip')
        if args.zip:
            archive = args.zip
        else:
            make_zip(args.dir, archive)

    H = {'Authorization': 'Bearer ' + tok, 'User-Agent': UA}

    st, _, b = req('GET', API + '/api/hosting/v1/websites?domain=' + args.domain, headers=H)
    assert st == 200, f'resolve website failed: {st} {b[:200]}'
    sites = json.loads(b)
    lst = sites if isinstance(sites, list) else (sites.get('data') or [])
    if not lst:
        print(f'!! no website record for {args.domain}: {b[:300]}', file=sys.stderr)
        sys.exit(1)
    user = lst[0]['username']
    print(f'username: {user} (root: {lst[0].get("root_directory")})')

    st, _, b = req('POST', API + '/api/hosting/v1/files/upload-urls',
                   headers={**H, 'Content-Type': 'application/json'},
                   data={'username': user, 'domain': args.domain})
    assert st == 200, f'upload-urls failed: {st} {b[:200]}'
    creds = json.loads(b)
    base = creds['url'].rstrip('/')

    size = os.path.getsize(archive)
    target = base + '/' + os.path.basename(archive) + '?override=true'
    with open(archive, 'rb') as f:
        content = f.read()
    tus = {'X-Auth': creds['auth_key'], 'X-Auth-Rest': creds['rest_auth_key'],
           'Tus-Resumable': '1.0.0', 'User-Agent': UA}
    for attempt in range(4):
        st, _, b = req('POST', target, headers={**tus, 'Upload-Length': str(size)}, raw=b'')
        if st == 429:
            time.sleep(20 * (attempt + 1)); continue
        if st in (201, 200):
            break
        print(f'!! TUS create {st} {b[:200]}', file=sys.stderr); sys.exit(1)
    else:
        print('!! TUS create rate-limited out', file=sys.stderr); sys.exit(1)
    st, _, b = req('PATCH', target, headers={**tus, 'Upload-Offset': '0',
                                             'Content-Type': 'application/offset+octet-stream'}, raw=content)
    assert st in (204, 200), f'TUS patch failed: {st} {b[:200]}'
    print('archive uploaded')

    st, _, b = req('POST', API + f'/api/hosting/v1/accounts/{user}/websites/{args.domain}/deploy',
                   headers={**H, 'Content-Type': 'application/json'},
                   data={'archive_path': os.path.basename(archive)})
    print('deploy trigger ->', st, b[:200].decode('utf-8', 'replace'))
    if st != 200:
        sys.exit(1)
    print(f'DEPLOYED (async): https://{args.domain}/  — verify with curl after ~30s')


if __name__ == '__main__':
    main()

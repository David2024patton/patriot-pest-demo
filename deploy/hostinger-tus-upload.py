#!/usr/bin/env python3
"""
hostinger-tus-upload.py — deploy files to Hostinger shared hosting via TUS.

The Dokploy path (deploy.sh) is dead for this project: no build server is
registered on the Dokploy instance (server.all == []), the VPS SSH port is
firewalled, and the stored API key is stale. Hostinger shared hosting is the
working path — it runs PHP 8.2 with pdo_sqlite and lets us push the full app
over the TUS resumable-upload flow:

  per file:
    1. POST https://developers.hostinger.com/api/hosting/v1/files/upload-urls
         (Bearer HOSTINGER_API_TOKEN, body: cPanel username + domain)
         -> { url, auth_key, rest_auth_key }
    2. POST <base>/<prefix>/<relpath>?override=true   (empty body, TUS headers) -> 201
    3. PATCH <base>/<prefix>/<relpath>?override=true  (full body, offset 0)      -> 204

Usage:
  # full bundle
  python deploy/hostinger-tus-upload.py --src C:/tmp/ppc-test-deploy --prefix test

  # single file (e.g. a config flip)
  python deploy/hostinger-tus-upload.py --src C:/tmp/ppc-test-deploy --prefix test --files .env

Credentials are read from CREDENTIALS.md (BUZZ_HOME env or --creds). The token
that authenticates is the first one that returns 200 on upload-urls (the file
documents a token rotation, so try each HOSTINGER_API_TOKEN= line in order).

Exit code 0 only when every file uploads (create 201 + patch 204/200).
"""
import argparse, json, os, sys, time, urllib.request, urllib.error

UA = ('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
      '(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36')
DEFAULT_CREDS = os.path.join(os.environ.get('BUZZ_HOME', r'C:/Users/David/.buzz'), 'CREDENTIALS.md')
API = 'https://developers.hostinger.com/api/hosting/v1/files/upload-urls'


def read_tokens(path: str) -> list[str]:
    toks = []
    try:
        with open(path, encoding='utf-8', errors='replace') as f:
            for line in f:
                if line.startswith('HOSTINGER_API_TOKEN='):
                    t = line.split('=', 1)[1].strip()
                    if t:
                        toks.append(t)
    except OSError as e:
        print(f'!! cannot read credentials file {path}: {e}', file=sys.stderr)
    return toks


def req(method, url, headers=None, data=None, raw=None, timeout=120):
    r = urllib.request.Request(url, method=method, headers=headers or {})
    body = raw if raw is not None else (json.dumps(data).encode() if data is not None else None)
    try:
        with urllib.request.urlopen(r, data=body, timeout=timeout) as resp:
            return resp.status, resp.headers, resp.read()
    except urllib.error.HTTPError as e:
        return e.code, e.headers, e.read()


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--src', default=r'C:/tmp/ppc-test-deploy', help='local bundle dir (walked for files)')
    ap.add_argument('--prefix', default='test', help='remote dir under public_html (default: test)')
    ap.add_argument('--files', nargs='*', help='relative paths to upload; default: ALL files under --src')
    ap.add_argument('--username', default='u269861438', help='Hostinger cPanel username (upload-urls body)')
    ap.add_argument('--domain', default='patriotpest.pro', help='Hostinger domain (upload-urls body)')
    ap.add_argument('--creds', default=DEFAULT_CREDS, help='credentials file with HOSTINGER_API_TOKEN=')
    ap.add_argument('--retries', type=int, default=3)
    args = ap.parse_args()

    tokens = read_tokens(args.creds)
    if not tokens:
        print('!! no HOSTINGER_API_TOKEN found; aborting', file=sys.stderr)
        sys.exit(1)

    # Acquire TUS credentials (try each token until one authenticates).
    creds = None
    for tok in tokens:
        st, _, b = req('POST', API, headers={'Authorization': 'Bearer ' + tok,
                                             'Content-Type': 'application/json', 'User-Agent': UA},
                       data={'username': args.username, 'domain': args.domain})
        if st == 200:
            creds = json.loads(b)
            break
        print(f'  token ...{tok[-6:]} -> {st}', file=sys.stderr)
    if creds is None:
        print('!! all Hostinger tokens failed on upload-urls; aborting', file=sys.stderr)
        sys.exit(1)
    base = creds['url'].rstrip('/')
    auth_key, rest_key = creds['auth_key'], creds['rest_auth_key']

    # Enumerate files to upload.
    if args.files:
        files = []
        for rel in args.files:
            full = os.path.join(args.src, rel)
            if not os.path.isfile(full):
                print(f'!! file not found: {full}', file=sys.stderr)
                sys.exit(1)
            files.append((rel.replace(os.sep, '/'), full))
    else:
        files = []
        for root, _dirs, names in os.walk(args.src):
            for n in names:
                full = os.path.join(root, n)
                rel = os.path.relpath(full, args.src).replace(os.sep, '/')
                files.append((rel, full))
        files.sort()
    print(f'uploading {len(files)} files to public_html/{args.prefix}/ ...')

    ok, fail = 0, 0
    for rel, full in files:
        size = os.path.getsize(full)
        target = base + '/' + args.prefix + '/' + rel + '?override=true'
        with open(full, 'rb') as f:
            content = f.read()
        for attempt in range(args.retries):
            st, h, b = req('POST', target, headers={'X-Auth': auth_key, 'X-Auth-Rest': rest_key,
                                                    'Tus-Resumable': '1.0.0', 'Upload-Length': str(size),
                                                    'User-Agent': UA}, raw=b'')
            if st == 429:                       # file-server rate limit: back off, not hammer
                time.sleep(15 * (attempt + 1))
                continue
            if st not in (201, 200):
                time.sleep(5 * (attempt + 1))
                continue
            st, h, b = req('PATCH', target, headers={'X-Auth': auth_key, 'X-Auth-Rest': rest_key,
                                                     'Tus-Resumable': '1.0.0', 'Upload-Offset': '0',
                                                     'Content-Type': 'application/offset+octet-stream',
                                                     'User-Agent': UA}, raw=content)
            if st == 429:
                time.sleep(15 * (attempt + 1))
                continue
            if st in (204, 200):
                ok += 1
                break
            time.sleep(5 * (attempt + 1))
        else:
            fail += 1
            print(f'FAIL {rel} (create {st} / patch {st})')
        if (ok + fail) % 25 == 0:
            print(f'  ... {ok + fail}/{len(files)}')
    print(f'DONE: {ok} ok, {fail} failed')
    sys.exit(1 if fail else 0)


if __name__ == '__main__':
    main()

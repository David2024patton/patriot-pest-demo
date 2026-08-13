<?php
/**
 * hostinger-tus-upload.php — PHP port of the TUS uploader (python-free).
 * Uploads a staged bundle to Hostinger shared hosting via the files API.
 *
 * Usage:
 *   php hostinger-tus-upload.php --src C:/Users/rated/projects/ppc-bundle --prefix test
 *   env BUZZ_HOME overrides the credentials file location.
 */
$args = getopt('', ['src:', 'prefix:', 'files::', 'username:', 'domain:', 'creds:', 'retries:']);
$src     = $args['src']     ?? 'C:/Users/rated/projects/ppc-bundle';
$prefix  = $args['prefix']  ?? 'test';
$only    = isset($args['files']) ? array_filter(array_map('trim', is_array($args['files']) ? $args['files'] : explode(',', $args['files']))) : null;
$user    = $args['username'] ?? 'u269861438';
$domain  = $args['domain']  ?? 'patriotpest.pro';
$creds   = $args['creds']   ?? (getenv('BUZZ_HOME') ?: 'C:/Users/rated/.buzz') . '/CREDENTIALS.md';
$retries = (int) ($args['retries'] ?? 3);
$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
$API = 'https://developers.hostinger.com/api/hosting/v1/files/upload-urls';

function req(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $h = [];
    foreach ($headers as $k => $v) { $h[] = "$k: $v"; }
    $ctx = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", $h), 'timeout' => 120,
        'content' => $body ?? '',
        'ignore_errors' => true,
    ]]);
    $resp = file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) { $code = (int) $m[1]; }
    }
    return [$code, $resp === false ? '' : $resp];
}

// 1. tokens
$tokens = [];
foreach (file($creds) ?: [] as $line) {
    if (str_starts_with($line, 'HOSTINGER_API_TOKEN=')) {
        $t = trim(explode('=', $line, 2)[1]);
        if ($t !== '') { $tokens[] = $t; }
    }
}
if (!$tokens) { fwrite(STDERR, "no tokens in $creds\n"); exit(1); }

// 2. acquire TUS creds
$credsJson = null;
foreach ($tokens as $tok) {
    [$st, $b] = req('POST', $API, ['Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json', 'User-Agent' => $UA], json_encode(['username' => $user, 'domain' => $domain]));
    echo "token ..." . substr($tok, -6) . " -> $st\n";
    if ($st === 200) { $credsJson = json_decode($b, true); break; }
}
if (!$credsJson) { fwrite(STDERR, "all tokens failed\n"); exit(1); }
$base = rtrim($credsJson['url'] ?? '', '/');
$authKey = $credsJson['auth_key'] ?? '';
$restKey = $credsJson['rest_auth_key'] ?? '';

// 3. enumerate files
$files = [];
if ($only) {
    foreach ($only as $rel) {
        $full = rtrim($src, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($full)) { $files[] = [$rel, $full]; }
    }
} else {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile()) {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(rtrim($src, '/\\')) + 1));
            $files[] = [$rel, $f->getPathname()];
        }
    }
    sort($files);
}
echo 'uploading ' . count($files) . " files to public_html/$prefix/ ...\n";

$ok = 0; $fail = 0;
foreach ($files as [$rel, $full]) {
    $size = filesize($full);
    $target = "$base/$prefix/$rel?override=true";
    $content = file_get_contents($full);
    $done = false;
    for ($a = 0; $a < $retries && !$done; $a++) {
        [$st, ] = req('POST', $target, ['X-Auth' => $authKey, 'X-Auth-Rest' => $restKey, 'Tus-Resumable' => '1.0.0', 'Upload-Length' => (string) $size, 'User-Agent' => $UA], '');
        if ($st === 429) { sleep(15 * ($a + 1)); continue; }
        if (!in_array($st, [200, 201], true)) { sleep(5 * ($a + 1)); continue; }
        [$st2, ] = req('PATCH', $target, ['X-Auth' => $authKey, 'X-Auth-Rest' => $restKey, 'Tus-Resumable' => '1.0.0', 'Upload-Offset' => '0', 'Content-Type' => 'application/offset+octet-stream', 'User-Agent' => $UA], $content);
        if ($st2 === 429) { sleep(15 * ($a + 1)); continue; }
        if (in_array($st2, [200, 204], true)) { $ok++; $done = true; break; }
        sleep(5 * ($a + 1));
    }
    if (!$done) { $fail++; echo "FAIL $rel\n"; }
    if (($ok + $fail) % 25 === 0) { echo "  ... " . ($ok + $fail) . '/' . count($files) . "\n"; }
}
echo "DONE: $ok ok, $fail failed\n";
exit($fail ? 1 : 0);

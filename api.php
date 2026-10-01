<?php
/*
 * Back end for the site: login, content storage and file uploads.
 * Needs PHP 7.4 or newer (with the standard fileinfo extension).
 * Everything is stored next to this file:
 *   data/     your content and the admin password (protected; not readable from the web)
 *   uploads/  images and videos you upload
 */

// One-time setup key, used only to create the admin password the first time.
// Stored as a SHA-256 hash. Replaced by your own password after setup.
const SETUP_KEY_SHA256 = 'cd55003891810cd58603a8859d3efbeefb3ab2b4dce785bcd898c3d5e84af7f3';

const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;   // 20 MB per file
const MAX_DOC_BYTES    = 300 * 1024;         // 300 KB per saved item
const COLLECTIONS      = ['people', 'posts', 'drafts', 'config', 'media'];
const PRIVATE_COLS     = ['drafts', 'media']; // only visible when logged in
const UPLOAD_TYPES     = [
  'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp',
  'image/svg+xml' => 'svg', 'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
];

$DIR     = __DIR__;
$DATA    = $DIR . '/data';
$UPLOADS = $DIR . '/uploads';
$GUARD   = "<?php http_response_code(403); exit; ?>\n"; // stops the data files being read from the web

/* ---------- helpers ---------- */
function out($code, $body) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function fail($code, $msg, $kind = 'error') { out($code, ['ok' => false, 'error' => $kind, 'message' => $msg]); }
function ensure_dirs() {
  global $DATA, $UPLOADS;
  foreach ([$DATA, $UPLOADS] as $d) {
    if (!is_dir($d) && !@mkdir($d, 0755, true)) fail(500, 'The server could not create the "' . basename($d) . '" folder. Give the site folder write permission.', 'setup');
    if (!is_writable($d)) fail(500, 'The "' . basename($d) . '" folder is not writable. Set its permissions to 755 (or 775).', 'setup');
    if (!file_exists("$d/.htaccess")) @file_put_contents("$d/.htaccess", "Require all denied\nDeny from all\n");
    if (!file_exists("$d/index.html")) @file_put_contents("$d/index.html", '');
  }
}
function read_guarded($file) {
  if (!file_exists($file)) return null;
  $raw = file_get_contents($file);
  $nl = strpos($raw, "\n");
  $json = $nl === false ? '' : substr($raw, $nl + 1);
  $v = json_decode($json, true);
  return is_array($v) ? $v : null;
}
function write_guarded($file, $value) {
  global $GUARD;
  $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
  if (file_put_contents($tmp, $GUARD . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) fail(500, 'Could not save. Check that the data folder is writable.');
  if (!rename($tmp, $file)) { @unlink($tmp); fail(500, 'Could not save. Check that the data folder is writable.'); }
}
function empty_site() { return ['people' => new stdClass, 'posts' => new stdClass, 'drafts' => new stdClass, 'config' => new stdClass, 'media' => new stdClass]; }
function load_site() {
  global $DATA, $DIR;
  $site = read_guarded("$DATA/site.php");
  if ($site === null) {
    // First run: start from the content that shipped with the site.
    $seed = file_exists("$DIR/seed.json") ? json_decode(file_get_contents("$DIR/seed.json"), true) : null;
    $site = is_array($seed) ? $seed : [];
    foreach (COLLECTIONS as $c) if (!isset($site[$c]) || !is_array($site[$c])) $site[$c] = [];
    write_guarded("$DATA/site.php", $site);
  }
  foreach (COLLECTIONS as $c) if (!isset($site[$c]) || !is_array($site[$c])) $site[$c] = [];
  return $site;
}
// Read-modify-write under an exclusive lock so two editors can't overwrite each other's save.
function with_site_lock($fn) {
  global $DATA;
  $lock = fopen("$DATA/.lock", 'c');
  if (!$lock || !flock($lock, LOCK_EX)) fail(500, 'Could not save right now. Try again.');
  try {
    $site = load_site();
    $result = $fn($site);
    write_guarded("$DATA/site.php", $site);
    return $result;
  } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function auth_file() { global $DATA; return "$DATA/auth.php"; }
function auth() { return read_guarded(auth_file()); }
function logged_in() { return !empty($_SESSION['admin']) && !empty($_SESSION['csrf']); }
function need_login() { if (!logged_in()) fail(401, 'Log in to make changes.', 'auth'); }
function need_csrf() {
  $h = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if (!logged_in() || !is_string($h) || !hash_equals($_SESSION['csrf'], $h)) fail(403, 'Your session expired. Reload the page and log in again.', 'auth');
}
function body() {
  $raw = file_get_contents('php://input');
  $v = json_decode($raw ?: '{}', true);
  return is_array($v) ? $v : [];
}
function client_ip() { return $_SERVER['REMOTE_ADDR'] ?? 'unknown'; }
function throttle_check() {
  global $DATA;
  $t = read_guarded("$DATA/throttle.php") ?: [];
  $k = hash('sha256', client_ip()); $now = time();
  $e = $t[$k] ?? ['n' => 0, 'since' => $now];
  if ($now - $e['since'] > 900) $e = ['n' => 0, 'since' => $now];
  if ($e['n'] >= 10) fail(429, 'Too many failed attempts. Wait 15 minutes and try again.', 'throttled');
}
function throttle_fail() {
  global $DATA;
  $t = read_guarded("$DATA/throttle.php") ?: [];
  $k = hash('sha256', client_ip()); $now = time();
  $e = $t[$k] ?? ['n' => 0, 'since' => $now];
  if ($now - $e['since'] > 900) $e = ['n' => 0, 'since' => $now];
  $e['n']++; $t[$k] = $e;
  foreach ($t as $kk => $vv) if ($now - $vv['since'] > 900) unset($t[$kk]);
  write_guarded("$DATA/throttle.php", $t);
  sleep(1);
}
function start_admin_session() {
  session_regenerate_id(true);
  $_SESSION['admin'] = true;
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function valid_id($id) { return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id); }
function find_upload($id) {
  global $UPLOADS;
  if (!preg_match('/^[a-f0-9]{32}$/', $id)) return null;
  foreach (UPLOAD_TYPES as $mime => $ext) { $f = "$UPLOADS/$id.$ext"; if (file_exists($f)) return [$f, $mime]; }
  return null;
}

/* ---------- serve an uploaded file: api.php?f=<id> ---------- */
if (isset($_GET['f'])) {
  $hit = find_upload((string)$_GET['f']);
  if (!$hit) { http_response_code(404); exit; }
  [$file, $mime] = $hit;
  header('Content-Type: ' . $mime);
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: public, max-age=31536000, immutable');
  if ($mime === 'image/svg+xml') header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
  $size = filesize($file);
  header('Accept-Ranges: bytes');
  // Range support so videos can be scrubbed.
  if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $start = $m[1] === '' ? max(0, $size - (int)$m[2]) : (int)$m[1];
    $end = ($m[1] !== '' && $m[2] !== '') ? min((int)$m[2], $size - 1) : $size - 1;
    if ($start > $end || $start >= $size) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
    header('Content-Length: ' . ($end - $start + 1));
    $fh = fopen($file, 'rb'); fseek($fh, $start); $left = $end - $start + 1;
    while ($left > 0 && !feof($fh)) { $chunk = fread($fh, min(65536, $left)); echo $chunk; $left -= strlen($chunk); }
    fclose($fh); exit;
  }
  header('Content-Length: ' . $size);
  readfile($file); exit;
}

/* ---------- API ---------- */
ensure_dirs();
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('siteadmin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && !in_array($action, ['login', 'setup'], true)) need_csrf();

switch ($action) {

  case 'me':
    out(200, ['ok' => true, 'loggedIn' => logged_in(), 'needsSetup' => auth() === null, 'csrf' => logged_in() ? $_SESSION['csrf'] : null]);

  case 'data':
    $site = load_site();
    if (!logged_in()) foreach (PRIVATE_COLS as $c) $site[$c] = [];
    foreach (COLLECTIONS as $c) $site[$c] = (object)$site[$c];
    out(200, ['ok' => true, 'data' => $site]);

  case 'setup':
    if ($method !== 'POST') fail(405, 'Use POST.');
    if (auth() !== null) fail(409, 'The admin password is already set. Log in instead.', 'exists');
    throttle_check();
    $b = body();
    $key = (string)($b['setupKey'] ?? ''); $pw = (string)($b['password'] ?? '');
    if (!hash_equals(SETUP_KEY_SHA256, hash('sha256', trim($key)))) { throttle_fail(); fail(403, "That setup key isn't right. It's in the README that came with the site.", 'badkey'); }
    if (strlen($pw) < 8) fail(400, 'Choose a password of at least 8 characters.', 'weak');
    write_guarded(auth_file(), ['hash' => password_hash($pw, PASSWORD_DEFAULT), 'created' => date('c')]);
    start_admin_session();
    out(200, ['ok' => true, 'csrf' => $_SESSION['csrf']]);

  case 'login':
    if ($method !== 'POST') fail(405, 'Use POST.');
    $a = auth();
    if ($a === null) fail(409, 'Set up the admin password first.', 'needsSetup');
    throttle_check();
    $pw = (string)(body()['password'] ?? '');
    if (!password_verify($pw, $a['hash'])) { throttle_fail(); fail(403, "That password isn't right.", 'badpassword'); }
    start_admin_session();
    out(200, ['ok' => true, 'csrf' => $_SESSION['csrf']]);

  case 'logout':
    $_SESSION = []; session_destroy();
    out(200, ['ok' => true]);

  case 'password':
    need_login();
    $b = body(); $a = auth();
    if (!$a || !password_verify((string)($b['current'] ?? ''), $a['hash'])) { throttle_fail(); fail(403, "Your current password isn't right.", 'badpassword'); }
    $pw = (string)($b['password'] ?? '');
    if (strlen($pw) < 8) fail(400, 'Choose a password of at least 8 characters.', 'weak');
    write_guarded(auth_file(), ['hash' => password_hash($pw, PASSWORD_DEFAULT), 'created' => date('c')]);
    out(200, ['ok' => true]);

  case 'set':
    need_login();
    $b = body(); $col = $b['col'] ?? ''; $id = $b['id'] ?? ''; $doc = $b['doc'] ?? null;
    if (!in_array($col, COLLECTIONS, true) || !valid_id($id)) fail(400, 'Invalid item.', 'invalid');
    if (!is_array($doc) || array_is_list_compat($doc) && count($doc) > 0) fail(400, 'Invalid item.', 'invalid');
    if (strlen(json_encode($doc)) > MAX_DOC_BYTES) fail(413, 'That item is too large to save.', 'too_large');
    with_site_lock(function (&$site) use ($col, $id, $doc) { $site[$col][$id] = $doc; });
    out(200, ['ok' => true]);

  case 'delete':
    need_login();
    $b = body(); $col = $b['col'] ?? ''; $id = $b['id'] ?? '';
    if (!in_array($col, COLLECTIONS, true) || !valid_id($id)) fail(400, 'Invalid item.', 'invalid');
    with_site_lock(function (&$site) use ($col, $id) { unset($site[$col][$id]); });
    out(200, ['ok' => true]);

  case 'upload':
    need_login();
    if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'] ?? '')) {
      $err = $_FILES['file']['error'] ?? null;
      if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) fail(413, 'That file is larger than your web host allows. Ask your host to raise upload_max_filesize, or use a smaller file.', 'too_large');
      fail(400, 'No file was received.', 'invalid');
    }
    $f = $_FILES['file'];
    if ($f['size'] > MAX_UPLOAD_BYTES) fail(413, 'Files must be under 20 MB.', 'too_large');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if ($mime === 'text/xml' || $mime === 'application/xml' || $mime === 'text/plain') {
      $head = file_get_contents($f['tmp_name'], false, null, 0, 1024);
      if (stripos($head, '<svg') !== false) $mime = 'image/svg+xml';
    }
    if (!isset(UPLOAD_TYPES[$mime])) fail(415, 'That file type isn\'t supported. Use PNG, JPG, WebP, GIF, SVG, MP4 or WebM.', 'unsupported_type');
    $id = bin2hex(random_bytes(16));
    if (!move_uploaded_file($f['tmp_name'], "$UPLOADS/$id." . UPLOAD_TYPES[$mime])) fail(500, 'Could not store the file. Check that the uploads folder is writable.');
    out(200, ['ok' => true, 'id' => $id, 'url' => 'api.php?f=' . $id, 'contentType' => $mime, 'sizeBytes' => $f['size']]);

  case 'media':
    need_login();
    $list = []; $bytes = 0;
    foreach (glob("$UPLOADS/*.*") ?: [] as $file) {
      $name = basename($file); $dot = strrpos($name, '.');
      $id = substr($name, 0, $dot); $ext = substr($name, $dot + 1);
      $mime = array_search($ext, UPLOAD_TYPES, true);
      if (!$mime || !preg_match('/^[a-f0-9]{32}$/', $id)) continue;
      $size = filesize($file); $bytes += $size;
      $list[] = ['id' => $id, 'url' => 'api.php?f=' . $id, 'contentType' => $mime, 'sizeBytes' => $size, 'createdAt' => date('c', filemtime($file))];
    }
    usort($list, fn($a, $b) => strcmp($a['createdAt'], $b['createdAt']));
    $free = @disk_free_space($UPLOADS);
    out(200, ['ok' => true, 'assets' => $list, 'usage' => ['files' => count($list), 'bytes' => $bytes, 'maxFiles' => 100000, 'maxBytes' => $free ? $bytes + (int)$free : 0]]);

  case 'deleteMedia':
    need_login();
    $id = (string)(body()['id'] ?? '');
    $hit = find_upload($id);
    if ($hit) @unlink($hit[0]);
    out(200, ['ok' => true, 'deleted' => (bool)$hit]);

  default:
    fail(404, 'Unknown action.');
}

function array_is_list_compat(array $a) { $i = 0; foreach ($a as $k => $_) { if ($k !== $i++) return false; } return true; }

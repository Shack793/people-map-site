<?php
/*
 * Shared helpers for the public pages (index.php, sitemap.php, robots.php, favicon.php).
 * Reads the content saved by the admin (data/site.php), or seed.json before the first save.
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(403); exit; }

const SITE_DEFAULTS = [
  'siteName' => 'Acme Co.', 'pageTitle' => 'Organization chart', 'intro' => 'Click anyone to see their profile.',
  'footer' => '', 'logoMode' => 'glyph', 'logoText' => 'AC', 'logoColor' => '#0f6e6a', 'logoImageId' => '',
  'accent' => '#0f6e6a', 'showBlog' => true, 'homeLayout' => 'chart', 'chartLayout' => 'grid', 'cardStyle' => 'photo',
  'showIntro' => true, 'seoDescription' => '', 'shareImageId' => '', 'navTeam' => 'Team', 'navBlog' => 'Updates',
  'blogTitle' => 'Updates & announcements', 'blogIntro' => 'News, announcements and updates from the team.',
];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function site_read_guarded($file) {
  if (!is_file($file)) return null;
  $raw = file_get_contents($file);
  $nl = strpos($raw, "\n");
  $v = json_decode($nl === false ? '' : substr($raw, $nl + 1), true);
  return is_array($v) ? $v : null;
}

/* One-time change shipped with an update: makes the team chart the home page on an existing site.
   Runs once (a marker file records it), so choosing another home page in Settings afterwards sticks. */
function site_apply_updates() {
  $dir = __DIR__ . '/data'; $flag = "$dir/.home-team-applied"; $file = "$dir/site.php";
  if (is_file($flag) || !is_dir($dir) || !is_writable($dir)) return;
  $lock = @fopen("$dir/.lock", 'c');
  if (!$lock || !flock($lock, LOCK_EX)) return;
  try {
    if (is_file($flag)) return;
    $site = site_read_guarded($file);
    if ($site !== null) {
      if (!isset($site['config']) || !is_array($site['config'])) $site['config'] = [];
      if (!isset($site['config']['settings']) || !is_array($site['config']['settings'])) $site['config']['settings'] = [];
      $site['config']['settings']['homeLayout'] = 'chart';
      $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
      if (file_put_contents($tmp, "<?php http_response_code(403); exit; ?>\n" . json_encode($site, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false || !rename($tmp, $file)) { @unlink($tmp); return; }
    }
    @file_put_contents($flag, date('c'));
  } finally { flock($lock, LOCK_UN); fclose($lock); }
}

/* Public content only: people, published posts, settings. Drafts and media stay private. */
function site_data() {
  static $d = null;
  if ($d !== null) return $d;
  $site = site_read_guarded(__DIR__ . '/data/site.php');
  if ($site === null && is_file(__DIR__ . '/seed.json')) $site = json_decode(file_get_contents(__DIR__ . '/seed.json'), true);
  if (!is_array($site)) $site = [];
  $people = is_array($site['people'] ?? null) ? $site['people'] : [];
  $posts = is_array($site['posts'] ?? null) ? $site['posts'] : [];
  $settings = is_array($site['config']['settings'] ?? null) ? $site['config']['settings'] : [];
  $cfg = array_merge(SITE_DEFAULTS, $settings);
  return $d = ['people' => $people, 'posts' => $posts, 'settings' => $settings, 'cfg' => $cfg];
}

function site_layout($cfg) { $l = $cfg['homeLayout'] ?? 'chart'; return in_array($l, ['combined', 'chart'], true) ? $l : 'updates'; }

function site_https() {
  return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}
function site_origin() {
  $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
  if (!preg_match('/^[A-Za-z0-9.-]+(:\d{1,5})?$/', $host)) $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
  return (site_https() ? 'https' : 'http') . '://' . $host;
}
function site_base() {
  $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
  return rtrim($dir, '/') . '/';
}
/* Clean URLs need the .htaccess rules; without them we fall back to ?post= links that work anywhere. */
function site_pretty() { return !empty($_SERVER['SITE_REWRITE']) || !empty($_SERVER['REDIRECT_SITE_REWRITE']); }

function site_slug($t) {
  $t = (string)$t;
  if (function_exists('iconv')) { $x = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t); if ($x !== false) $t = $x; }
  $t = strtolower($t);
  $t = trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-');
  $t = rtrim(substr($t, 0, 80), '-');
  return $t !== '' ? $t : 'post';
}

/* Same rules as the site's own links (href() in the page script). */
function site_path($page, $id = '', $title = '') {
  $d = site_data(); $L = site_layout($d['cfg']); $b = site_base(); $P = site_pretty();
  switch ($page) {
    case 'post': return $P ? $b . 'updates/' . rawurlencode($id) . '/' . site_slug($title) : $b . '?post=' . rawurlencode($id);
    case 'blog': return $L === 'updates' ? $b : ($P ? $b . 'updates' : $b . '?page=updates');
    case 'updates': return $P ? $b . 'updates' : $b . '?page=updates';
    case 'team': return $L === 'chart' ? $b : ($P ? $b . 'team' : $b . '?page=team');
    default: return $b;
  }
}
function site_url($page, $id = '', $title = '') { return site_origin() . site_path($page, $id, $title); }
function site_file_url($id) { return $id ? site_origin() . site_base() . 'api.php?f=' . rawurlencode($id) : ''; }

function site_posts_sorted() {
  $posts = [];
  foreach (site_data()['posts'] as $id => $p) if (is_array($p)) { $p['id'] = (string)$id; $posts[] = $p; }
  usort($posts, function ($a, $b) {
    $c = strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
    return $c ?: strcmp((string)($b['updatedAt'] ?? ''), (string)($a['updatedAt'] ?? ''));
  });
  return $posts;
}

/* People top-down: the top of the chart first, then each level in turn (as the grid shows them). */
function site_people_ordered() {
  $people = [];
  foreach (site_data()['people'] as $id => $p) if (is_array($p)) { $p['id'] = (string)$id; $people[(string)$id] = $p; }
  $by = function ($a, $b) { return (($a['sort'] ?? 999) <=> ($b['sort'] ?? 999)) ?: strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')); };
  $kids = function ($id) use ($people, $by) { $k = array_values(array_filter($people, fn($p) => ($p['managerId'] ?? '') === $id && $p['id'] !== $id)); usort($k, $by); return $k; };
  $roots = array_values(array_filter($people, fn($p) => empty($p['managerId']) || !isset($people[$p['managerId']]) || $p['managerId'] === $p['id']));
  usort($roots, $by);
  $out = []; $done = []; $q = $roots;
  while ($q) { $p = array_shift($q); if (isset($done[$p['id']])) continue; $done[$p['id']] = 1; $out[] = $p; foreach ($kids($p['id']) as $k) $q[] = $k; }
  foreach ($people as $p) if (!isset($done[$p['id']])) $out[] = $p;
  return $out;
}

/* ---------- post formatting (mirrors the editor's formatting rules) ---------- */
function site_safe_url($u) {
  $u = trim((string)$u);
  if ($u === '') return '';
  if (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $u)) $u = 'https://' . $u;
  $p = parse_url($u);
  if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) return '';
  return $u;
}
function site_video_host($u) {
  $h = strtolower(parse_url($u, PHP_URL_HOST) ?: '');
  $h = preg_replace('/^(www\.|m\.)/', '', $h);
  if (preg_match('/(^|\.)youtube\.com$|^youtu\.be$/', $h)) return 'YouTube';
  if (preg_match('/(^|\.)vimeo\.com$/', $h)) return 'Vimeo';
  if (preg_match('/(^|\.)loom\.com$/', $h)) return 'Loom';
  if (preg_match('/(^|\.)tiktok\.com$/', $h)) return 'TikTok';
  return '';
}
function site_inline($s) {
  $s = h($s);
  $s = preg_replace_callback('/\[([^\]]+)\]\(((?:[^()\s]|\([^()\s]*\))+)\)/', function ($m) {
    $x = site_safe_url(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
    return $x ? '<a href="' . h($x) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>' : $m[1];
  }, $s);
  $s = preg_replace_callback('/(^|[\s(])(https?:\/\/[^\s<)]+)/', function ($m) {
    $u = $m[2]; $tail = '';
    if (preg_match('/[.,;:!?]+$/', $u, $t)) { $tail = $t[0]; $u = substr($u, 0, -strlen($tail)); }
    $x = site_safe_url(html_entity_decode($u, ENT_QUOTES, 'UTF-8'));
    return $x ? $m[1] . '<a href="' . h($x) . '" target="_blank" rel="noopener noreferrer">' . $u . '</a>' . $tail : $m[0];
  }, $s);
  $s = preg_replace('/\*\*([^*]+?)\*\*/', '<strong>$1</strong>', $s);
  $s = preg_replace('/(^|[^*\w])\*(?!\s)([^*]+?)\*(?!\w)/u', '$1<em>$2</em>', $s);
  $s = preg_replace('/~~([^~]+?)~~/', '<del>$1</del>', $s);
  return $s;
}
function site_parse($src) {
  $lines = explode("\n", str_replace("\r", '', (string)$src)); $out = []; $cur = null;
  $flush = function () use (&$out, &$cur) { if ($cur) { $out[] = $cur; $cur = null; } };
  foreach ($lines as $raw) {
    $l = rtrim($raw);
    if (trim($l) === '') { $flush(); continue; }
    if (preg_match('/^!\[([^\]]*)\]\(asset:([A-Za-z0-9_-]+)\)\s*$/', $l, $m)) { $flush(); $out[] = ['t' => 'img', 'cap' => $m[1], 'id' => $m[2]]; continue; }
    if (preg_match('/^!video\(asset:([A-Za-z0-9_-]+)\)\s*$/', $l, $m)) { $flush(); $out[] = ['t' => 'vid', 'id' => $m[1]]; continue; }
    if (preg_match('/^\[([^\]]+)\]\(([^)\s]+)\)\{button\}\s*$/', $l, $m)) { $flush(); $out[] = ['t' => 'btn', 'text' => $m[1], 'url' => $m[2]]; continue; }
    if (preg_match('/^(https?:\/\/\S+)\s*$/', trim($l), $m) && site_video_host($m[1]) && (!$cur || $cur['t'] !== 'p')) { $flush(); $out[] = ['t' => 'embed', 'url' => $m[1]]; continue; }
    if (preg_match('/^\s*(-{3,}|\*{3,})\s*$/', $l)) { $flush(); $out[] = ['t' => 'hr']; continue; }
    if (preg_match('/^(#{1,3})\s+(.+)$/', $l, $m)) { $flush(); $out[] = ['t' => strlen($m[1]) >= 3 ? 'h4' : 'h3', 'text' => $m[2]]; continue; }
    if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $l, $m)) { if (!$cur || $cur['t'] !== 'ol') { $flush(); $cur = ['t' => 'ol', 'items' => []]; } $cur['items'][] = $m[1]; continue; }
    if (preg_match('/^\s*[-*•]\s+(.*)$/u', $l, $m)) { if (!$cur || $cur['t'] !== 'ul') { $flush(); $cur = ['t' => 'ul', 'items' => []]; } $cur['items'][] = $m[1]; continue; }
    if (preg_match('/^>\s?(.*)$/', $l, $m)) { if (!$cur || $cur['t'] !== 'q') { $flush(); $cur = ['t' => 'q', 'lines' => []]; } $cur['lines'][] = $m[1]; continue; }
    if (!$cur || $cur['t'] !== 'p') { $flush(); $cur = ['t' => 'p', 'lines' => []]; }
    $cur['lines'][] = $l;
  }
  $flush();
  return $out;
}
function site_md($src, $alt = '') {
  $html = '';
  foreach (site_parse($src) as $b) {
    switch ($b['t']) {
      case 'p': $html .= '<p>' . implode('<br>', array_map('site_inline', $b['lines'])) . '</p>'; break;
      case 'h3': $html .= '<h3>' . site_inline($b['text']) . '</h3>'; break;
      case 'h4': $html .= '<h4>' . site_inline($b['text']) . '</h4>'; break;
      case 'hr': $html .= '<hr>'; break;
      case 'ul': $html .= '<ul>' . implode('', array_map(fn($x) => '<li>' . site_inline($x) . '</li>', $b['items'])) . '</ul>'; break;
      case 'ol': $html .= '<ol>' . implode('', array_map(fn($x) => '<li>' . site_inline($x) . '</li>', $b['items'])) . '</ol>'; break;
      case 'q': $html .= '<blockquote>' . implode('<br>', array_map('site_inline', $b['lines'])) . '</blockquote>'; break;
      case 'img': $html .= '<figure><img src="api.php?f=' . h($b['id']) . '" alt="' . h($b['cap'] !== '' ? $b['cap'] : $alt) . '" loading="lazy">' . ($b['cap'] !== '' ? '<figcaption>' . site_inline($b['cap']) . '</figcaption>' : '') . '</figure>'; break;
      case 'vid': $html .= '<figure><video controls preload="metadata" playsinline src="api.php?f=' . h($b['id']) . '"></video></figure>'; break;
      case 'btn': $x = site_safe_url($b['url']); $html .= $x ? '<p><a class="btn primary post-btn" href="' . h($x) . '" target="_blank" rel="noopener noreferrer">' . site_inline($b['text']) . '</a></p>' : '<p>' . site_inline($b['text']) . '</p>'; break;
      case 'embed': $x = site_safe_url($b['url']); if ($x) $html .= '<a class="media-card" href="' . h($x) . '" target="_blank" rel="noopener noreferrer"><span class="play" aria-hidden="true">▶</span><span><b>Watch on ' . h(site_video_host($x)) . '</b><span class="mc-url">' . h($x) . '</span></span></a>'; break;
    }
  }
  return $html;
}
function site_plain($src) {
  $paras = [];
  foreach (site_parse($src) as $b) if ($b['t'] === 'p') $paras[] = implode(' ', $b['lines']);
  $t = implode(' ', $paras);
  $t = preg_replace('/\[([^\]]+)\]\(((?:[^()\s]|\([^()\s]*\))+)\)/', '$1', $t);
  $t = preg_replace(['/\*\*([^*]+?)\*\*/', '/(^|[^*\w])\*(?!\s)([^*]+?)\*(?!\w)/u', '/~~([^~]+?)~~/'], ['$1', '$1$2', '$1'], $t);
  return trim(preg_replace('/\s+/u', ' ', $t));
}
function site_cut($t, $n) {
  $t = trim(preg_replace('/\s+/u', ' ', (string)$t));
  if (mb_strlen($t, 'UTF-8') <= $n) return $t;
  $c = mb_substr($t, 0, $n, 'UTF-8');
  $c = preg_replace('/\s+\S*$/u', '', $c);
  return rtrim($c, " ,;:-") . '…';
}
function site_first_image($src) { foreach (site_parse($src) as $b) if ($b['t'] === 'img') return $b['id']; return ''; }
function site_date($d) { $t = strtotime((string)$d); return $t ? date('M j, Y', $t) : h($d); }

/* ---------- logo (same drawing as the page script) ---------- */
function site_logo_svg($c) {
  $col = preg_match('/^#[0-9a-f]{6}$/i', (string)($c['logoColor'] ?? '')) ? $c['logoColor'] : '#0f6e6a';
  if (($c['logoMode'] ?? '') === 'monogram') {
    $t = h(strtoupper(mb_substr((string)($c['logoText'] ?? ''), 0, 3, 'UTF-8')) ?: '?');
    $len = mb_strlen(html_entity_decode($t), 'UTF-8'); $fs = $len > 2 ? 22 : ($len > 1 ? 27 : 34);
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="' . $col . '"/><text x="32" y="33" text-anchor="middle" dominant-baseline="central" font-family="Arial,sans-serif" font-weight="700" font-size="' . $fs . '" fill="#fff">' . $t . '</text></svg>';
  }
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="' . $col . '"/><path d="M32 21v9M32 30H19v9M32 30h13v9M32 30v9" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/><circle cx="32" cy="18" r="6" fill="#fff"/><circle cx="19" cy="44" r="5" fill="#fff" fill-opacity=".85"/><circle cx="32" cy="44" r="5" fill="#fff" fill-opacity=".85"/><circle cx="45" cy="44" r="5" fill="#fff" fill-opacity=".85"/></svg>';
}

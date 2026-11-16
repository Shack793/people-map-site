<?php
/*
 * Builds each public page on the server: the content itself (so search engines and
 * link previews can read it without running JavaScript), plus its title, description,
 * canonical link, social-preview tags and structured data. The page script then takes over.
 */
require __DIR__ . '/site-lib.php';
site_apply_updates();

$d = site_data(); $cfg = $d['cfg']; $L = site_layout($cfg);
$site = trim((string)$cfg['siteName']);
$blogTitle = trim((string)($cfg['blogTitle'] ?: $cfg['navBlog']));
$teamTitle = trim((string)$cfg['pageTitle']);

/* ---------- which page ---------- */
$postId = isset($_GET['post']) ? (string)$_GET['post'] : '';
$want = isset($_GET['page']) ? (string)$_GET['page'] : '';
$status = 200; $post = null; $routeKey = '';
if (empty($cfg['showBlog'])) { $page = 'team'; }
elseif ($postId !== '') {
  $page = 'post'; $routeKey = 'post-' . $postId;
  if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $postId) && isset($d['posts'][$postId]) && is_array($d['posts'][$postId])) { $post = $d['posts'][$postId]; $post['id'] = $postId; }
  else $status = 404;
}
elseif ($want === 'updates') { $page = 'blog'; $routeKey = 'updates'; }
elseif ($want === 'team') { $page = $L === 'combined' ? 'home' : 'team'; $routeKey = 'team'; }
else { $page = $L === 'combined' ? 'home' : ($L === 'chart' ? 'team' : 'blog'); }

// An old pretty link whose title part changed still finds the post; point it at the current address.
if ($post && site_pretty() && isset($_GET['post'])) {
  $want_path = site_path('post', $postId, $post['title'] ?? '');
  $have = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
  if (rawurldecode($have) !== rawurldecode($want_path) && rtrim(rawurldecode($have), '/') !== rawurldecode($want_path)) { header('Location: ' . $want_path, true, 301); exit; }
}

/* ---------- title, description, image, canonical ---------- */
$join = fn(...$p) => implode(' · ', array_values(array_filter(array_map('trim', $p), 'strlen')));
$siteDesc = trim((string)($cfg['seoDescription'] ?: ($cfg['blogIntro'] ?: $cfg['intro'])));
$image = $cfg['shareImageId'] ?: (($cfg['logoMode'] ?? '') === 'image' ? $cfg['logoImageId'] : '');
$type = 'website'; $robots = 'index,follow';
$posts = site_posts_sorted(); $people = site_people_ordered();
$ld = [];
$org = ['@type' => 'Organization', '@id' => site_url('home') . '#org', 'name' => $site ?: $blogTitle, 'url' => site_url('home')];
$logoUrl = (($cfg['logoMode'] ?? '') === 'image' && $cfg['logoImageId']) ? site_file_url($cfg['logoImageId']) : site_origin() . site_base() . 'favicon.php';
$org['logo'] = $logoUrl;

if ($page === 'post' && $post) {
  $title = $join($post['title'] ?? 'Post', $site);
  $desc = trim((string)($post['summary'] ?? '')) ?: site_cut(site_plain($post['body'] ?? ''), 160);
  $img = ($post['coverId'] ?? '') ?: site_first_image($post['body'] ?? '');
  if ($img) $image = $img;
  $canonical = site_url('post', $postId, $post['title'] ?? '');
  $type = 'article';
  $art = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => (string)($post['title'] ?? ''), 'description' => $desc,
    'mainEntityOfPage' => $canonical, 'url' => $canonical, 'publisher' => $org];
  if (!empty($post['date'])) $art['datePublished'] = (string)$post['date'];
  $mod = $post['updatedAt'] ?? ($post['date'] ?? ''); if ($mod) $art['dateModified'] = (string)$mod;
  if (!empty($post['author'])) $art['author'] = ['@type' => 'Person', 'name' => (string)$post['author']];
  else $art['author'] = $org;
  if ($image) $art['image'] = site_file_url($image);
  $ld[] = $art;
  $ld[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => $site ?: 'Home', 'item' => site_url('home')],
    ['@type' => 'ListItem', 'position' => 2, 'name' => $cfg['navBlog'] ?: 'Updates', 'item' => site_url('updates')],
    ['@type' => 'ListItem', 'position' => 3, 'name' => (string)($post['title'] ?? ''), 'item' => $canonical]]];
} elseif ($page === 'post') {
  $title = $join('Post not found', $site); $desc = $siteDesc; $canonical = site_url('home'); $robots = 'noindex,follow';
} elseif ($page === 'team') {
  $title = $join($teamTitle, $site);
  $names = array_map(fn($p) => trim(($p['name'] ?? '') . (!empty($p['role']) ? ' (' . $p['role'] . ')' : '')), array_slice($people, 0, 6));
  $desc = $cfg['seoDescription'] && $L === 'chart' ? $cfg['seoDescription'] : site_cut('Meet the team' . ($site ? ' at ' . $site : '') . ': ' . implode(', ', $names) . (count($people) > 6 ? ' and more.' : '.'), 160);
  $canonical = site_url('team');
  $ld[] = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => $teamTitle, 'itemListElement' => array_values(array_map(function ($p, $i) use ($org) {
    $person = ['@type' => 'Person', 'name' => (string)($p['name'] ?? ''), 'worksFor' => ['@id' => $org['@id']]];
    if (!empty($p['role'])) $person['jobTitle'] = (string)$p['role'];
    if (!empty($p['photoId'])) $person['image'] = site_file_url($p['photoId']);
    return ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $person];
  }, $people, array_keys($people)))];
} else { // blog list or combined home
  $isHome = ($page === 'home') || ($page === 'blog' && $L === 'updates');
  $title = $isHome ? $join($site ?: $blogTitle, $site ? $blogTitle : '') : $join($blogTitle, $site);
  $desc = $siteDesc;
  if (!$image) foreach ($posts as $p) { $i = ($p['coverId'] ?? '') ?: site_first_image($p['body'] ?? ''); if ($i) { $image = $i; break; } }
  $canonical = $isHome ? site_url('home') : site_url('updates');
}
if ($page !== 'post' || $post) {
  $ld[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site ?: $blogTitle, 'url' => site_url('home'), 'description' => $siteDesc, 'publisher' => ['@id' => $org['@id']]];
  $ld[] = ['@context' => 'https://schema.org'] + $org;
}
$desc = site_cut($desc ?: $siteDesc, 200);
$imgUrl = $image ? site_file_url($image) : '';

/* ---------- page content for readers without JavaScript ---------- */
function ssr_post_row($p) {
  $th = ($p['coverId'] ?? '') ?: site_first_image($p['body'] ?? '');
  $url = h(site_path('post', $p['id'], $p['title'] ?? ''));
  return '<article class="prow' . ($th ? ' has-th' : '') . '"><div class="pdate">' . site_date($p['date'] ?? '') . '</div><div class="pbody"><div class="pmeta">'
    . (!empty($p['category']) ? '<span class="cat">' . h($p['category']) . '</span>' : '') . (!empty($p['author']) ? '<span>by ' . h($p['author']) . '</span>' : '')
    . '</div><h2 class="ptitle"><a href="' . $url . '">' . h($p['title'] ?? '') . '</a></h2><p class="pex">' . h(site_cut(trim((string)($p['summary'] ?? '')) ?: site_plain($p['body'] ?? ''), 190))
    . '</p><div class="admin-row"><a class="more" href="' . $url . '">Read more →</a></div></div>'
    . ($th ? '<a class="pthumb" href="' . $url . '" tabindex="-1" aria-hidden="true"><img src="api.php?f=' . h($th) . '" alt="" loading="lazy"></a>' : '') . '</article>';
}
function ssr_blog($posts, $cfg, $compact) {
  $h = !empty($cfg['blogIntro']) ? '<p class="blog-intro">' . h($cfg['blogIntro']) . '</p>' : '';
  if (!$posts) return $h . '<div class="empty">No posts yet.</div>';
  $feat = null; foreach ($posts as $p) if (!empty($p['pinned'])) { $feat = $p; break; }
  $rest = array_values(array_filter($posts, fn($p) => !$feat || $p['id'] !== $feat['id']));
  if ($compact) $rest = array_slice($rest, 0, $feat ? 3 : 4);
  if ($feat) {
    $img = ($feat['coverId'] ?? '') ?: site_first_image($feat['body'] ?? ''); $url = h(site_path('post', $feat['id'], $feat['title'] ?? ''));
    $h .= '<article class="feature">' . ($img ? '<div class="cover"><img src="api.php?f=' . h($img) . '" alt="' . h($feat['title'] ?? '') . '"></div>' : '')
      . '<div class="fb"><span class="pin">Pinned</span><div class="pmeta">' . (!empty($feat['category']) ? '<span class="cat">' . h($feat['category']) . '</span>' : '')
      . '<span>' . site_date($feat['date'] ?? '') . '</span>' . (!empty($feat['author']) ? '<span>by ' . h($feat['author']) . '</span>' : '') . '</div>'
      . '<h2 class="ptitle"><a href="' . $url . '">' . h($feat['title'] ?? '') . '</a></h2><p class="pex">' . h(site_cut(trim((string)($feat['summary'] ?? '')) ?: site_plain($feat['body'] ?? ''), 260))
      . '</p><div class="admin-row"><a class="more" href="' . $url . '">Read more →</a></div></div></article>';
  }
  if ($rest) $h .= '<div class="plist">' . implode('', array_map('ssr_post_row', $rest)) . '</div>';
  if ($compact) $h .= '<div class="blog-more"><a class="more" href="' . h(site_path('updates')) . '">All ' . h(strtolower($cfg['navBlog'] ?: 'updates')) . ' →</a></div>';
  return $h;
}
function ssr_post($p, $cfg, $L) {
  $back = '<a class="back" href="' . h($L === 'combined' ? site_path('home') : site_path('blog')) . '">← All ' . h(strtolower($cfg['navBlog'] ?: 'updates')) . '</a>';
  if (!$p) return $back . '<div class="empty">This post isn\'t available. It may have been removed.</div>';
  return '<article class="post">' . $back . (!empty($p['coverId']) ? '<div class="cover"><img src="api.php?f=' . h($p['coverId']) . '" alt="' . h($p['title'] ?? '') . '"></div>' : '')
    . '<div class="inline"><div class="pmeta">' . (!empty($p['category']) ? '<span class="cat">' . h($p['category']) . '</span>' : '')
    . (!empty($p['date']) ? '<time datetime="' . h($p['date']) . '">' . site_date($p['date']) . '</time>' : '') . (!empty($p['author']) ? '<span>by ' . h($p['author']) . '</span>' : '') . '</div></div>'
    . '<h1 class="post-title">' . h($p['title'] ?? '') . '</h1><div class="prose">' . site_md($p['body'] ?? '', $p['title'] ?? '') . '</div></article>';
}
function ssr_team($people) {
  if (!$people) return '<div class="empty">No one on the chart yet.</div>';
  return '<div class="pgrid">' . implode('', array_map(function ($p) {
    $img = !empty($p['photoId']) ? '<img src="api.php?f=' . h($p['photoId']) . '" alt="' . h(($p['name'] ?? '') . (!empty($p['role']) ? ', ' . $p['role'] : '')) . '" loading="lazy">'
      : '<span class="ph-empty"><b>' . h(implode('', array_map(fn($w) => mb_substr($w, 0, 1, 'UTF-8'), array_slice(preg_split('/\s+/u', trim((string)($p['name'] ?? '?'))), 0, 2)))) . '</b></span>';
    return '<div class="card photo"><span class="ph">' . $img . '</span><span class="cap"><span class="nm">' . h($p['name'] ?? '') . '</span><span class="rl">' . h($p['role'] ?? '') . '</span></span></div>';
  }, $people)) . '</div>';
}

$showBlog = in_array($page, ['blog', 'post', 'home'], true);
$showTeam = in_array($page, ['team', 'home'], true);
$blogHtml = $page === 'post' ? ssr_post($post, $cfg, $L) : ($showBlog ? ssr_blog($posts, $cfg, $page === 'home') : '');
$headTitle = $page === 'team' ? $teamTitle : $blogTitle;

/* ---------- fill the page template ---------- */
$tpl = file_get_contents(__DIR__ . '/app-template.php');
$tpl = substr($tpl, strpos($tpl, "\n") + 1); // drop the line that blocks direct access

$jsonOpts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$siteJs = ['base' => site_base(), 'pretty' => site_pretty(), 'routeKey' => $routeKey,
  'data' => ['people' => (object)$d['people'], 'posts' => (object)$d['posts'], 'settings' => (object)$d['settings']]];
$head = '<meta name="description" content="' . h($desc) . '">' . "\n"
  . '<link rel="canonical" href="' . h($canonical) . '">' . "\n"
  . '<meta name="robots" content="' . $robots . '">' . "\n"
  . '<base href="' . h(site_base()) . '">' . "\n"
  . '<meta name="theme-color" content="' . h(preg_match('/^#[0-9a-f]{6}$/i', (string)$cfg['accent']) ? $cfg['accent'] : '#0f6e6a') . '">' . "\n"
  . '<meta property="og:type" content="' . $type . '">' . "\n"
  . '<meta property="og:site_name" content="' . h($site) . '">' . "\n"
  . '<meta property="og:title" content="' . h($page === 'post' && $post ? ($post['title'] ?? '') : $title) . '">' . "\n"
  . '<meta property="og:description" content="' . h($desc) . '">' . "\n"
  . '<meta property="og:url" content="' . h($canonical) . '">' . "\n"
  . ($imgUrl ? '<meta property="og:image" content="' . h($imgUrl) . '">' . "\n" : '')
  . ($type === 'article' && !empty($post['date']) ? '<meta property="article:published_time" content="' . h($post['date']) . '">' . "\n" : '')
  . '<meta name="twitter:card" content="' . ($imgUrl ? 'summary_large_image' : 'summary') . '">' . "\n"
  . '<meta name="twitter:title" content="' . h($page === 'post' && $post ? ($post['title'] ?? '') : $title) . '">' . "\n"
  . '<meta name="twitter:description" content="' . h($desc) . '">' . "\n"
  . ($imgUrl ? '<meta name="twitter:image" content="' . h($imgUrl) . '">' . "\n" : '')
  . '<link rel="alternate" type="application/rss+xml" title="' . h($join($blogTitle, $site)) . '" href="' . h(site_origin() . site_base()) . 'feed.php">' . "\n"
  . implode("\n", array_map(fn($x) => '<script type="application/ld+json">' . json_encode($x, $jsonOpts) . '</script>', $ld)) . "\n"
  . '<script>window.SITE=' . json_encode($siteJs, $jsonOpts) . ';</script>';

$rep = [
  '#<title>.*?</title>#s' => '<title>' . h($title) . '</title>' . "\n" . $head,
  '#<link rel="icon" id="favicon" href="[^"]*">#' => '<link rel="icon" id="favicon" href="favicon.php" type="image/svg+xml">',
];
foreach ($rep as $re => $to) $tpl = preg_replace_callback($re, fn() => $to, $tpl, 1);
$swap = [
  '<a class="brand" id="brand" href="#">' => '<a class="brand" id="brand" href="' . h(site_path('home')) . '">',
  '<span class="site" id="site-name">Acme Co.</span><h1 id="title">Organization chart</h1>' => '<span class="site" id="site-name">' . h($site) . '</span>' . ($page === 'post' ? '<p class="h1" id="title">' : '<h1 id="title">') . h($headTitle) . ($page === 'post' ? '</p>' : '</h1>'),
  '<a id="nav-team" href="#team">' => '<a id="nav-team" href="' . h(site_path('team')) . '"' . ($page === 'team' || empty($cfg['showBlog']) ? ' hidden' : '') . '>',
  '<a id="nav-blog" href="#updates">' => '<a id="nav-blog" href="' . h($page === 'team' && $L === 'updates' ? site_path('home') : site_path('blog')) . '"' . ($page === 'team' && !empty($cfg['showBlog']) ? '' : ' hidden') . '>',
  '<span class="logo" id="logo" aria-hidden="true"></span>' => '<span class="logo" id="logo" aria-hidden="true">' . ((($cfg['logoMode'] ?? '') === 'image' && $cfg['logoImageId']) ? '<img src="api.php?f=' . h($cfg['logoImageId']) . '" alt="">' : site_logo_svg($cfg)) . '</span>',
  '<div class="tools" id="tools">' => '<div class="tools" id="tools" hidden>',
  '<main id="blog-view" class="blog" hidden></main>' => '<main id="blog-view" class="blog"' . ($showBlog ? '' : ' hidden') . '>' . $blogHtml . '</main>',
  '<section class="intro" id="team-intro">' => '<section class="intro" id="team-intro"' . ($showTeam ? '' : ' hidden') . '>',
  '<h2 class="sec-h" id="team-h" hidden></h2>' => '<h2 class="sec-h" id="team-h"' . ($page === 'home' ? '' : ' hidden') . '>' . h($teamTitle) . '</h2>',
  '<p id="intro-text">Click anyone to see their profile.</p>' => '<p id="intro-text"' . (!empty($cfg['showIntro']) && $cfg['intro'] ? '' : ' hidden') . '>' . h($cfg['intro']) . '</p>',
  '<main id="team-main">' => '<main id="team-main"' . ($showTeam ? '' : ' hidden') . '>',
  '<div id="tree-view" class="chart-wrap"><div class="tree" id="tree"></div></div>' => '<div id="tree-view" class="chart-wrap grid-mode"><div class="tree" id="tree">' . ($showTeam ? ssr_team($people) : '') . '</div></div>',
];
$fm = '#<footer><div id="footer-text">[^<]*</div></footer>#';
$tpl = preg_replace_callback($fm, fn() => '<footer' . ($cfg['footer'] ? '' : ' hidden') . '><div id="footer-text">' . h($cfg['footer']) . '</div></footer>', $tpl, 1);
foreach ($swap as $a => $b) $tpl = str_replace($a, $b, $tpl);
if ($page === 'home') $tpl = str_replace('<html lang="en">', '<html lang="en" class="combined">', $tpl);

http_response_code($status);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
echo $tpl;

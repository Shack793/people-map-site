<?php
/* sitemap.xml: every public page, so search engines find all your posts. */
require __DIR__ . '/site-lib.php';
$d = site_data(); $cfg = $d['cfg']; $L = site_layout($cfg);
$urls = [];
$latest = '';
foreach (site_posts_sorted() as $p) { $m = (string)($p['updatedAt'] ?? ($p['date'] ?? '')); if ($m > $latest) $latest = $m; }
$urls[] = [site_url('home'), $latest, '1.0'];
if (!empty($cfg['showBlog'])) {
  if ($L !== 'updates') $urls[] = [site_url('updates'), $latest, '0.8'];
  if ($L === 'updates') $urls[] = [site_url('team'), '', '0.7'];
  foreach (site_posts_sorted() as $p) $urls[] = [site_url('post', $p['id'], $p['title'] ?? ''), (string)($p['updatedAt'] ?? ($p['date'] ?? '')), '0.6'];
}
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-cache');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $pri]) {
  $t = $mod ? strtotime($mod) : false;
  echo '  <url><loc>' . h($loc) . '</loc>' . ($t ? '<lastmod>' . date('Y-m-d', $t) . '</lastmod>' : '') . '<priority>' . $pri . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";

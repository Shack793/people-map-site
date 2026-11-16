<?php
/* RSS feed of your posts (news readers and some search tools use it). */
require __DIR__ . '/site-lib.php';
$d = site_data(); $cfg = $d['cfg'];
$site = trim((string)$cfg['siteName']); $title = trim((string)($cfg['blogTitle'] ?: 'Updates'));
header('Content-Type: application/rss+xml; charset=utf-8');
header('Cache-Control: no-cache');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>';
echo '<title>' . h(trim($title . ($site ? ' · ' . $site : ''))) . '</title><link>' . h(site_url('home')) . '</link>';
echo '<description>' . h($cfg['seoDescription'] ?: $cfg['blogIntro']) . '</description>';
echo '<atom:link href="' . h(site_origin() . site_base() . 'feed.php') . '" rel="self" type="application/rss+xml"/>';
if (!empty($cfg['showBlog'])) foreach (array_slice(site_posts_sorted(), 0, 30) as $p) {
  $url = site_url('post', $p['id'], $p['title'] ?? ''); $t = strtotime((string)($p['date'] ?? ''));
  echo '<item><title>' . h($p['title'] ?? '') . '</title><link>' . h($url) . '</link><guid isPermaLink="true">' . h($url) . '</guid>'
    . ($t ? '<pubDate>' . date(DATE_RSS, $t) . '</pubDate>' : '')
    . '<description>' . h(trim((string)($p['summary'] ?? '')) ?: site_cut(site_plain($p['body'] ?? ''), 300)) . '</description></item>';
}
echo '</channel></rss>';

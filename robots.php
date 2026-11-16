<?php
/* robots.txt: lets search engines in, keeps the admin and back end out, points to the sitemap. */
require __DIR__ . '/site-lib.php';
header('Content-Type: text/plain; charset=utf-8');
$b = site_base();
echo "User-agent: *\nAllow: /\nDisallow: {$b}admin/\nDisallow: {$b}api.php?action=\nDisallow: {$b}data/\n\n";
echo 'Sitemap: ' . site_origin() . $b . (site_pretty() ? 'sitemap.xml' : 'sitemap.php') . "\n";

<?php
/* The browser-tab and search-result icon: your logo, drawn from your Settings. */
require __DIR__ . '/site-lib.php';
$cfg = site_data()['cfg'];
if (($cfg['logoMode'] ?? '') === 'image' && !empty($cfg['logoImageId']) && preg_match('/^[a-f0-9]{32}$/', $cfg['logoImageId'])) {
  header('Location: api.php?f=' . $cfg['logoImageId'], true, 302); exit;
}
header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=3600');
echo site_logo_svg($cfg);

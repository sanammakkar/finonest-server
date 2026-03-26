<?php
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$today = date('Y-m-d');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc>https://finonest.com/api/sitemap</loc>
    <lastmod><?= $today ?></lastmod>
  </sitemap>
  <sitemap>
    <loc>https://finonest.com/api/sitemap-blogs</loc>
    <lastmod><?= $today ?></lastmod>
  </sitemap>
</sitemapindex>

<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $stmt = $pdo->prepare("SELECT slug, id, updated_at FROM blogs WHERE status = 'published' ORDER BY updated_at DESC");
    $stmt->execute();
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blogs = [];
}

$baseUrl = 'https://finonest.com';
$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <!-- Static Pages -->
  <url><loc><?= $baseUrl ?>/</loc><lastmod><?= $today ?></lastmod><changefreq>daily</changefreq><priority>1.0</priority></url>
  <url><loc><?= $baseUrl ?>/about</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/services</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/contact</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>
  <url><loc><?= $baseUrl ?>/apply</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>

  <!-- Service Pages -->
  <url><loc><?= $baseUrl ?>/services/home-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/services/personal-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/services/business-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/services/car-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/services/used-car-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/services/loan-against-property</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/credit-cards</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/services/finobizz-learning</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>

  <!-- Tool Pages -->
  <url><loc><?= $baseUrl ?>/emi-calculator</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/credit-score</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>

  <!-- Info Pages -->
  <url><loc><?= $baseUrl ?>/blog</loc><lastmod><?= $today ?></lastmod><changefreq>weekly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/faqs</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/banking-partners</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>
  <url><loc><?= $baseUrl ?>/careers</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>
  <url><loc><?= $baseUrl ?>/branches</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>
  <url><loc><?= $baseUrl ?>/dsa-partner</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>
  <url><loc><?= $baseUrl ?>/dsa-registration</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>
  <url><loc><?= $baseUrl ?>/terms-and-conditions</loc><lastmod><?= $today ?></lastmod><changefreq>yearly</changefreq><priority>0.3</priority></url>
  <url><loc><?= $baseUrl ?>/privacy-policy</loc><lastmod><?= $today ?></lastmod><changefreq>yearly</changefreq><priority>0.3</priority></url>

  <!-- Dynamic Blog Posts -->
<?php foreach ($blogs as $blog): ?>
  <url>
    <loc><?= $baseUrl ?>/blog/<?= htmlspecialchars($blog['slug'] ?: $blog['id']) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($blog['updated_at'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
<?php endforeach; ?>

</urlset>

<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $blogStmt = $pdo->prepare("SELECT * FROM blogs WHERE status = 'published' ORDER BY updated_at DESC");
    $blogStmt->execute();
    $blogs = $blogStmt->fetchAll(PDO::FETCH_ASSOC);

    $faqStmt = $pdo->prepare("SELECT * FROM faqs ORDER BY page ASC, sort_order ASC, id ASC");
    $faqStmt->execute();
    $faqs = $faqStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blogs = [];
    $faqs = [];
}

$baseUrl = 'https://finonest.com';
$today = date('Y-m-d');

function e($text) {
    return htmlspecialchars(strip_tags(preg_replace('/\s+/', ' ', trim($text))), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// Group FAQs by page
$faqsByPage = [];
foreach ($faqs as $faq) {
    $faqsByPage[$faq['page']][] = $faq;
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">

  <!-- Static Pages -->
  <url><loc><?= $baseUrl ?>/</loc><lastmod><?= $today ?></lastmod><changefreq>daily</changefreq><priority>1.0</priority></url>
  <url><loc><?= $baseUrl ?>/about</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/services</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/contact</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>
  <url><loc><?= $baseUrl ?>/apply</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/blog</loc><lastmod><?= $today ?></lastmod><changefreq>weekly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/faqs</loc><lastmod><?= $today ?></lastmod><changefreq>weekly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/emi-calculator</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/credit-score</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/banking-partners</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>
  <url><loc><?= $baseUrl ?>/branches</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>
  <url><loc><?= $baseUrl ?>/careers</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>
  <url><loc><?= $baseUrl ?>/dsa-partner</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/dsa-registration</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>

  <!-- Service Pages -->
  <url><loc><?= $baseUrl ?>/services/home-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/services/personal-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/services/business-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.9</priority></url>
  <url><loc><?= $baseUrl ?>/services/car-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/services/used-car-loan</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/services/loan-against-property</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/credit-cards</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.8</priority></url>
  <url><loc><?= $baseUrl ?>/services/finobizz-learning</loc><lastmod><?= $today ?></lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>
  <url><loc><?= $baseUrl ?>/terms-and-conditions</loc><lastmod><?= $today ?></lastmod><changefreq>yearly</changefreq><priority>0.3</priority></url>
  <url><loc><?= $baseUrl ?>/privacy-policy</loc><lastmod><?= $today ?></lastmod><changefreq>yearly</changefreq><priority>0.3</priority></url>

  <!-- Blog Posts -->
<?php
$sectionKeys = [
    'introduction','quick_info_box','emi_example','what_is_loan','benefits','who_should_apply',
    'eligibility_criteria','documents_required','interest_rates','finonest_process','why_choose_finonest',
    'customer_testimonials','common_mistakes','faqs','service_areas','disclaimer','trust_footer'
];

foreach ($blogs as $blog):
    $slug = $blog['slug'] ?: $blog['id'];
    $lastmod = date('Y-m-d', strtotime($blog['updated_at']));
    $pubDate = date('Y-m-d', strtotime($blog['created_at']));
    $loc = $baseUrl . '/blog/' . rawurlencode($slug);

    $visibility = [];
    if (!empty($blog['section_visibility'])) {
        $visibility = json_decode($blog['section_visibility'], true) ?: [];
    }

    // Build full content from all sections for SEO
    $allSectionText = $blog['excerpt'] . ' ' . $blog['content'];
    foreach ($sectionKeys as $key) {
        $inSitemap = !isset($visibility[$key]['sitemap']) || $visibility[$key]['sitemap'] !== false;
        if ($inSitemap && !empty($blog[$key])) {
            $allSectionText .= ' ' . $blog[$key];
        }
    }
    $fullDescription = e(mb_substr(preg_replace('/\s+/', ' ', strip_tags($allSectionText)), 0, 500));

    $imgUrl = !empty($blog['image_url'])
        ? (strpos($blog['image_url'], 'http') === 0 ? $blog['image_url'] : 'https://api.finonest.com' . $blog['image_url'])
        : '';
?>
  <url>
    <loc><?= e($loc) ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
    <news:news>
      <news:publication>
        <news:name>Finonest Blog</news:name>
        <news:language>en</news:language>
      </news:publication>
      <news:publication_date><?= $pubDate ?></news:publication_date>
      <news:title><?= e($blog['title']) ?></news:title>
      <news:keywords><?= e(($blog['meta_tags'] ?: '') . ', ' . $blog['category'] . ', finonest, loan india') ?></news:keywords>
    </news:news>
<?php if ($imgUrl): ?>
    <image:image>
      <image:loc><?= e($imgUrl) ?></image:loc>
      <image:title><?= e($blog['title']) ?></image:title>
      <image:caption><?= $fullDescription ?></image:caption>
    </image:image>
<?php endif; ?>
  </url>
<?php endforeach; ?>

</urlset>

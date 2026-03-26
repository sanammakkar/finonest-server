<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=1800');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    $stmt = $pdo->prepare("SELECT * FROM blogs WHERE status = 'published' ORDER BY updated_at DESC");
    $stmt->execute();
    $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $blogs = [];
}

$baseUrl = 'https://finonest.com';

$sectionKeys = [
    'introduction', 'quick_info_box', 'emi_example', 'what_is_loan',
    'benefits', 'who_should_apply', 'eligibility_criteria', 'documents_required',
    'interest_rates', 'finonest_process', 'why_choose_finonest', 'customer_testimonials',
    'common_mistakes', 'faqs', 'service_areas', 'disclaimer', 'trust_footer'
];

function e($text) {
    return htmlspecialchars(strip_tags(preg_replace('/\s+/', ' ', trim($text))), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

<?php foreach ($blogs as $blog):
    $slug = $blog['slug'] ?: $blog['id'];
    $lastmod = date('Y-m-d', strtotime($blog['updated_at']));
    $pubDate = date('Y-m-d', strtotime($blog['created_at']));
    $loc = $baseUrl . '/blog/' . rawurlencode($slug);

    // Parse section_visibility
    $visibility = [];
    if (!empty($blog['section_visibility'])) {
        $visibility = json_decode($blog['section_visibility'], true) ?: [];
    }

    // Build full text from all sections allowed in sitemap
    $parts = [e($blog['title']), e($blog['excerpt']), e($blog['content'])];
    foreach ($sectionKeys as $key) {
        $inSitemap = !isset($visibility[$key]['sitemap']) || $visibility[$key]['sitemap'] !== false;
        if ($inSitemap && !empty($blog[$key])) {
            $parts[] = e($blog[$key]);
        }
    }
    $fullText = implode(' ', array_filter($parts));

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
      <news:keywords><?= e($blog['meta_tags'] ?? $blog['category']) ?></news:keywords>
    </news:news>
<?php if ($imgUrl): ?>
    <image:image>
      <image:loc><?= e($imgUrl) ?></image:loc>
      <image:title><?= e($blog['title']) ?></image:title>
      <image:caption><?= e(mb_substr($blog['excerpt'], 0, 200)) ?></image:caption>
    </image:image>
<?php endif; ?>
  </url>
<?php endforeach; ?>

</urlset>

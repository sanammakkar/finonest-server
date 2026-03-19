<?php
require_once __DIR__ . '/../cors-handler.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) throw new Exception('Database connection failed');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Create table with page column
$db->exec("CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page VARCHAR(100) NOT NULL DEFAULT 'home',
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Add columns if upgrading from old schema
try { $db->exec("ALTER TABLE faqs ADD COLUMN IF NOT EXISTS page VARCHAR(100) NOT NULL DEFAULT 'home'"); } catch (Exception $e) {}
try { $db->exec("ALTER TABLE faqs ADD COLUMN IF NOT EXISTS category VARCHAR(100) NOT NULL DEFAULT ''"); } catch (Exception $e) {}

function requireAdmin() {
    $headers = getallheaders() ?: [];
    $token = null;
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            if (preg_match('/Bearer\s(\S+)/', $value, $matches)) $token = $matches[1];
            break;
        }
    }
    if (!$token) { http_response_code(401); echo json_encode(['error' => 'No token provided']); exit(); }
    try {
        $decoded = JWT::decode($token);
        if (!$decoded || !isset($decoded['role']) || $decoded['role'] !== 'ADMIN') {
            http_response_code(403); echo json_encode(['error' => 'Admin access required']); exit();
        }
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401); echo json_encode(['error' => 'Invalid token']); exit();
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// ID and action passed via query string from htaccess rewrite
$faqId = isset($_GET['_id']) ? (int)$_GET['_id'] : null;
$isReorder = isset($_GET['_action']) && $_GET['_action'] === 'reorder';

// POST /api/faqs/reorder
if ($method === 'POST' && $isReorder) {
    requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    foreach ($ids as $order => $id) {
        $stmt = $db->prepare("UPDATE faqs SET sort_order = ? WHERE id = ?");
        $stmt->execute([$order, $id]);
    }
    echo json_encode(['success' => true]);
    exit();
}

// Routes with ID: /api/faqs/{id}
if ($faqId !== null) {
    $id = $faqId;
    switch ($method) {
        case 'PUT':
            requireAdmin();
            $input = json_decode(file_get_contents('php://input'), true);
            $question = trim($input['question'] ?? '');
            $answer = trim($input['answer'] ?? '');
            $page = trim($input['page'] ?? 'home');
            $category = trim($input['category'] ?? '');
            if (!$question || !$answer) { http_response_code(400); echo json_encode(['error' => 'Question and answer required']); exit(); }
            $stmt = $db->prepare("UPDATE faqs SET question = ?, answer = ?, page = ?, category = ? WHERE id = ?");
            $stmt->execute([$question, $answer, $page, $category, $id]);
            echo json_encode(['success' => true, 'message' => 'FAQ updated']);
            break;
        case 'DELETE':
            requireAdmin();
            $stmt = $db->prepare("DELETE FROM faqs WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'FAQ deleted']);
            break;
        default:
            http_response_code(405); echo json_encode(['error' => 'Method not allowed']);
    }
    exit();
}

// Routes: /api/faqs
switch ($method) {
    case 'GET':
        // Optional ?page= filter for public use
        $pageFilter = $_GET['page'] ?? null;
        if ($pageFilter) {
            $stmt = $db->prepare("SELECT * FROM faqs WHERE page = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$pageFilter]);
        } else {
            $stmt = $db->query("SELECT * FROM faqs ORDER BY page ASC, sort_order ASC, id ASC");
        }
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'faqs' => $faqs]);
        break;
    case 'POST':
        requireAdmin();
        $input = json_decode(file_get_contents('php://input'), true);
        $question = trim($input['question'] ?? '');
        $answer = trim($input['answer'] ?? '');
        $page = trim($input['page'] ?? 'home');
        $category = trim($input['category'] ?? '');
        if (!$question || !$answer) { http_response_code(400); echo json_encode(['error' => 'Question and answer required']); exit(); }
        $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM faqs WHERE page = ?");
        $stmt->execute([$page]);
        $maxOrder = ($stmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? -1) + 1;
        $stmt = $db->prepare("INSERT INTO faqs (page, question, answer, sort_order, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$page, $question, $answer, $maxOrder, $category]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'FAQ created']);
        break;
    default:
        http_response_code(405); echo json_encode(['error' => 'Method not allowed']);
}
?>

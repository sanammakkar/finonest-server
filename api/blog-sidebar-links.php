<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit(); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';

function authenticate() {
    $headers = getallheaders();
    $token = null;
    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) $token = $m[1];
    if (!$token) { http_response_code(401); echo json_encode(['error' => 'No token']); exit(); }
    $decoded = JWT::decode($token);
    if (!$decoded) { http_response_code(401); echo json_encode(['error' => 'Invalid token']); exit(); }
    return $decoded;
}

$method = $_SERVER['REQUEST_METHOD'];
$parts  = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_sidebar_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            keyword VARCHAR(255) NOT NULL,
            link VARCHAR(500) NOT NULL,
            position INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT * FROM blog_sidebar_links WHERE is_active = 1 ORDER BY position ASC, id ASC");
            $stmt->execute();
            echo json_encode(['links' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'POST':
            $user = authenticate();
            if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($input['keyword']) || empty($input['link'])) { http_response_code(400); echo json_encode(['error' => 'keyword and link required']); exit(); }
            $stmt = $pdo->prepare("INSERT INTO blog_sidebar_links (keyword, link, position, is_active) VALUES (?,?,?,?)");
            $stmt->execute([$input['keyword'], $input['link'], $input['position'] ?? 0, $input['is_active'] ?? 1]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'PUT':
            $user = authenticate();
            if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }
            $id = $parts[array_search('blog-sidebar-links', $parts) + 1] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit(); }
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $fields = []; $params = [];
            foreach (['keyword','link','position','is_active'] as $f) {
                if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
            }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'Nothing to update']); exit(); }
            $params[] = $id;
            $pdo->prepare("UPDATE blog_sidebar_links SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $user = authenticate();
            if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }
            $id = $parts[array_search('blog-sidebar-links', $parts) + 1] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit(); }
            $pdo->prepare("DELETE FROM blog_sidebar_links WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405); echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
}
?>

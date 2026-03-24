<?php
require_once __DIR__ . '/../middleware/cors_secure.php';
SecureCorsMiddleware::handle();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';

function authenticate() {
    $headers = getallheaders();
    $token = null;
    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) {
        $token = $m[1];
    }
    if (!$token) { http_response_code(401); echo json_encode(['error' => 'No token']); exit(); }
    $decoded = JWT::decode($token);
    if (!$decoded) { http_response_code(401); echo json_encode(['error' => 'Invalid token']); exit(); }
    return $decoded;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            youtube_url VARCHAR(500) NOT NULL,
            thumbnail_url VARCHAR(500),
            description TEXT,
            position INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    switch ($method) {
        case 'GET':
            // Public — only active videos ordered by position
            $stmt = $pdo->prepare("SELECT * FROM blog_videos WHERE is_active = 1 ORDER BY position ASC, created_at DESC");
            $stmt->execute();
            echo json_encode(['videos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'POST':
            $user = authenticate();
            if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['title']) || empty($input['youtube_url'])) {
                http_response_code(400); echo json_encode(['error' => 'title and youtube_url required']); exit();
            }
            $stmt = $pdo->prepare("INSERT INTO blog_videos (title, youtube_url, thumbnail_url, description, position, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                $input['title'],
                $input['youtube_url'],
                $input['thumbnail_url'] ?? null,
                $input['description'] ?? null,
                $input['position'] ?? 0,
                $input['is_active'] ?? 1,
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'PUT':
            $user = authenticate();
            if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }
            $id = $pathParts[array_search('blog-videos', $pathParts) + 1] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit(); }
            $input = json_decode(file_get_contents('php://input'), true);
            $fields = []; $params = [];
            foreach (['title','youtube_url','thumbnail_url','description','position','is_active'] as $f) {
                if (isset($input[$f])) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
            }
            if (empty($fields)) { http_response_code(400); echo json_encode(['error' => 'Nothing to update']); exit(); }
            $params[] = $id;
            $pdo->prepare("UPDATE blog_videos SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $user = authenticate();
            if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }
            $id = $pathParts[array_search('blog-videos', $pathParts) + 1] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit(); }
            $pdo->prepare("DELETE FROM blog_videos WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405); echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
}
?>

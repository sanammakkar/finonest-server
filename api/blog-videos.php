<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

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

$uploadDir = __DIR__ . '/../uploads/blog-videos/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Cannot create upload directory']);
        exit();
    }
}

// Override PHP upload limits for this endpoint
@ini_set('upload_max_filesize', '10M');
@ini_set('post_max_size', '12M');
@ini_set('max_execution_time', '60');

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            youtube_url VARCHAR(500),
            video_url VARCHAR(500),
            thumbnail_url VARCHAR(500),
            description TEXT,
            position INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Add video_url column if missing (migration)
    try {
        $pdo->exec("ALTER TABLE blog_videos ADD COLUMN video_url VARCHAR(500) AFTER youtube_url");
    } catch (Exception $e) { /* already exists */ }

    // Make youtube_url nullable
    try {
        $pdo->exec("ALTER TABLE blog_videos MODIFY youtube_url VARCHAR(500) NULL");
    } catch (Exception $e) { /* ignore */ }

    // Handle file upload endpoint: POST /api/blog-videos/upload
    $isUploadEndpoint = in_array('upload', $pathParts);

    if ($method === 'POST' && $isUploadEndpoint) {
        $user = authenticate();
        if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }

        if (empty($_FILES['video'])) {
            http_response_code(400); echo json_encode(['error' => 'No video file uploaded']); exit();
        }

        $file = $_FILES['video'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        $allowed = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo'];

        if ($file['size'] > $maxSize) {
            http_response_code(400); echo json_encode(['error' => 'File exceeds 10MB limit']); exit();
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
            http_response_code(400); echo json_encode(['error' => 'Only MP4, WebM, MOV, AVI allowed']); exit();
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('vid_') . '.' . strtolower($ext);
        $dest = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            http_response_code(500); echo json_encode(['error' => 'Failed to save file']); exit();
        }

        echo json_encode(['success' => true, 'video_url' => '/uploads/blog-videos/' . $filename]);
        exit();
    }

    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT * FROM blog_videos WHERE is_active = 1 ORDER BY position ASC, created_at DESC");
            $stmt->execute();
            echo json_encode(['videos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'POST':
            $user = authenticate();
            if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['title'])) {
                http_response_code(400); echo json_encode(['error' => 'title required']); exit();
            }
            if (empty($input['youtube_url']) && empty($input['video_url'])) {
                http_response_code(400); echo json_encode(['error' => 'youtube_url or video_url required']); exit();
            }
            $stmt = $pdo->prepare("INSERT INTO blog_videos (title, youtube_url, video_url, thumbnail_url, description, position, is_active) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $input['title'],
                $input['youtube_url'] ?? null,
                $input['video_url'] ?? null,
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
            foreach (['title','youtube_url','video_url','thumbnail_url','description','position','is_active'] as $f) {
                if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
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
            // Delete local file if exists
            $row = $pdo->prepare("SELECT video_url FROM blog_videos WHERE id = ?");
            $row->execute([$id]);
            $r = $row->fetch(PDO::FETCH_ASSOC);
            if ($r && $r['video_url']) {
                $filePath = __DIR__ . '/../' . ltrim($r['video_url'], '/');
                if (file_exists($filePath)) unlink($filePath);
            }
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

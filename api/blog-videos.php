<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit(); }

// Catch fatal errors and return JSON instead of empty body
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'PHP fatal: ' . $err['message']]);
    }
});

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

$method   = $_SERVER['REQUEST_METHOD'];
$path     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts    = explode('/', trim($path, '/'));

$uploadDir = __DIR__ . '/../uploads/blog-videos/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    http_response_code(500); echo json_encode(['error' => 'Cannot create upload directory']); exit();
}

try {
    $db  = new Database();
    $pdo = $db->getConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            youtube_url VARCHAR(500) NULL,
            video_url VARCHAR(500) NULL,
            thumbnail_url VARCHAR(500) NULL,
            description TEXT,
            position INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Migrations — ignore if column already exists
    foreach ([
        "ALTER TABLE blog_videos ADD COLUMN video_url VARCHAR(500) NULL AFTER youtube_url",
        "ALTER TABLE blog_videos MODIFY youtube_url VARCHAR(500) NULL"
    ] as $sql) {
        try { $pdo->exec($sql); } catch (Exception $e) {}
    }

    // ── Upload endpoint ──────────────────────────────────────────────────────
    $isUpload = in_array('upload', $parts) || isset($_GET['action']) && $_GET['action'] === 'upload';

    if ($method === 'POST' && $isUpload) {
        $user = authenticate();
        if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }

        if (empty($_FILES['video'])) {
            http_response_code(400); echo json_encode(['error' => 'No file received. Check multipart/form-data and server upload_max_filesize']); exit();
        }

        $file = $_FILES['video'];

        // Detailed upload error
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder on server',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension',
        ];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => $uploadErrors[$file['error']] ?? 'Upload error ' . $file['error']]);
            exit();
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            http_response_code(400); echo json_encode(['error' => 'File exceeds 10MB limit']); exit();
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'webm', 'mov', 'avi'])) {
            http_response_code(400); echo json_encode(['error' => 'Only MP4, WebM, MOV, AVI allowed']); exit();
        }

        $filename = uniqid('vid_') . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            http_response_code(500); echo json_encode(['error' => 'move_uploaded_file failed. Check folder permissions on uploads/blog-videos/']); exit();
        }

        echo json_encode(['success' => true, 'video_url' => '/uploads/blog-videos/' . $filename]);
        exit();
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare("SELECT * FROM blog_videos WHERE is_active = 1 ORDER BY position ASC, created_at DESC");
            $stmt->execute();
            echo json_encode(['videos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'POST':
            $user = authenticate();
            if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($input['title'])) { http_response_code(400); echo json_encode(['error' => 'title required']); exit(); }
            if (empty($input['youtube_url']) && empty($input['video_url'])) {
                http_response_code(400); echo json_encode(['error' => 'youtube_url or video_url required']); exit();
            }
            $stmt = $pdo->prepare("INSERT INTO blog_videos (title, youtube_url, video_url, thumbnail_url, description, position, is_active) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $input['title'],
                $input['youtube_url'] ?? null,
                $input['video_url']   ?? null,
                $input['thumbnail_url'] ?? null,
                $input['description'] ?? null,
                $input['position']    ?? 0,
                $input['is_active']   ?? 1,
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'PUT':
            $user = authenticate();
            if ($user['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['error' => 'Admin only']); exit(); }
            $id = $parts[array_search('blog-videos', $parts) + 1] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit(); }
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
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
            $id = $parts[array_search('blog-videos', $parts) + 1] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit(); }
            $row = $pdo->prepare("SELECT video_url FROM blog_videos WHERE id = ?");
            $row->execute([$id]);
            $r = $row->fetch(PDO::FETCH_ASSOC);
            if ($r && $r['video_url']) {
                $fp = __DIR__ . '/../' . ltrim($r['video_url'], '/');
                if (file_exists($fp)) unlink($fp);
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

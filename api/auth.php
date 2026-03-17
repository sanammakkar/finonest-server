<?php
error_reporting(0);
ini_set('display_errors', 0);

// Only set CORS/content-type headers if not already sent by router
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/jwt.php';
    require_once __DIR__ . '/../models/User.php';

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }

    $userModel = new User($db);

    $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $request = explode('/', trim($pathInfo, '/'));
    $action = $request[0] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST' && $action === 'register') {
        handleRegister($userModel);
    } elseif ($method === 'POST' && $action === 'login') {
        handleLogin($userModel);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found', 'action' => $action]);
    }

} catch (Throwable $e) {
    error_log('Auth fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}

function handleRegister($userModel) {
    try {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!$data || !isset($data['name'], $data['email'], $data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields: name, email, password']);
            return;
        }

        $mobile = isset($data['mobile']) ? $data['mobile'] : null;

        if ($userModel->emailExists($data['email'])) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already exists']);
            return;
        }

        $user_id = $userModel->create($data['name'], $data['email'], $data['password'], $mobile);

        if (!$user_id) {
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed, could not create user']);
            return;
        }

        // Send SMS non-blocking — ignore any failure
        if ($mobile) {
            try {
                $otp = rand(100000, 999999);
                $url = "https://m1.sarv.com/api/v2.0/sms_campaign.php?token=1507603797696c62b571b953.18331010&user_id=50962153&route=OT&template_id=16249&sender_id=FINOST&language=EN&template=Hi%21+%0D%0AYour+authentication+code+to+login+in+Finonest+Pro+is+{$otp}.+This+code+is+valid+for+2+mins.+%0D%0A-Team+Finonest&contact_numbers={$mobile}";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
                curl_exec($ch);
                curl_close($ch);
            } catch (Throwable $e) {
                error_log('SMS failed: ' . $e->getMessage());
            }
        }

        $payload = [
            'user_id' => $user_id,
            'email'   => $data['email'],
            'role'    => 'USER',
            'exp'     => time() + 86400
        ];

        $token = JWT::encode($payload);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'     => $user_id,
                'name'   => $data['name'],
                'email'  => $data['email'],
                'mobile' => $mobile,
                'role'   => 'USER'
            ]
        ]);

    } catch (Throwable $e) {
        error_log('Register error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Registration error', 'message' => $e->getMessage()]);
    }
}

function handleLogin($userModel) {
    try {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!$data || !isset($data['email'], $data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password required']);
            return;
        }

        $user_data = $userModel->findByEmail($data['email']);

        if (!$user_data || !$userModel->verifyPassword($data['password'], $user_data['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            return;
        }

        $payload = [
            'user_id' => $user_data['id'],
            'email'   => $user_data['email'],
            'role'    => $user_data['role'],
            'exp'     => time() + 86400
        ];

        $token = JWT::encode($payload);

        echo json_encode([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'    => $user_data['id'],
                'name'  => $user_data['name'],
                'email' => $user_data['email'],
                'role'  => $user_data['role']
            ]
        ]);

    } catch (Throwable $e) {
        error_log('Login error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Login error', 'message' => $e->getMessage()]);
    }
}
?>

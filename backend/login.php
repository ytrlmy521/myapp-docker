<?php


header("Access-Control-Allow-Origin: http://10.0.63.120:8089");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, userid");
header("Content-Type: application/json");
header("Access-Control-Allow-Credentials: true"); // 允许携带 Cookie

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = require_once 'db.php';

$data = file_get_contents('php://input');
if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No input received']);
    exit;
}

$data = json_decode($data, true);
if (!$data || !isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing username or password']);
    exit;
}

$username = $data['username'];
$password = $data['password'];

$sql = "SELECT id, username, password FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if ($password === $user['password']) { // 明文密码比较

        $userid=$user['id'];
        // 设置过期时间为1天（86400秒）
        $expires_at = time() + 86400;
        $token = bin2hex(random_bytes(32));  // 生成随机 Token
        // 动态设置 Token 文件存储路径
           if (PHP_OS === 'WINNT') {
           // 开发环境 (Windows)
           $tmp_dir = 'E:\\XAMPP\\tmp';  // Windows路径
         } else {
             // 生产环境 (Linux / Ubuntu)
             $tmp_dir = '/var/www'; // 在生产环境中使用 Linux 的默认临时目录
           }
        $file_path = $tmp_dir . DIRECTORY_SEPARATOR . 'user_token_' . $userid . '.txt';
        // 将Token和过期时间存储到文件中
       $data = [
         'token' => $token,
         'expires_at' => $expires_at
             ];
        file_put_contents($file_path, json_encode($data));  // 将数据以JSON格式存储
        
        // 在这里你可以返回其他信息，例如用户的 ID 或者 Token
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
             'userid' => $userid,
             'username' => $username,
            'token' => $token,
            
              
            ]
            
        );
        
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    }
} else {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
}

$conn->close();
?>

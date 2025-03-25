<?php


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, userid");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 获取请求头中的token
$headers = getallheaders();

error_log(print_r($headers, true));
$user_id_from_request = isset($headers['Userid']) ? $headers['Userid'] : '';
// $token_from_request = isset($headers['Authorization']) ? $headers['Authorization'] : '';


// 从 $_SERVER 获取 Authorization 头
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token_from_request = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    // 某些服务器可能使用 REDIRECT_HTTP_AUTHORIZATION
    $token_from_request = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

error_log(print_r($token_from_request, true));

// 检查 user_id 和 token 是否存在
// if (!empty($user_id_from_request) && !empty($token_from_request)) {
    // 定义存储Token的路径
    if (PHP_OS === 'WINNT') {
        // 开发环境 (Windows)
        $tmp_dir = 'E:\\XAMPP\\tmp';  // Windows路径
      } else {
          // 生产环境 (Linux / Ubuntu)
          $tmp_dir = '/var/www'; // 在生产环境中使用 Linux 的默认临时目录
        }
       // $file_path = $tmp_dir . DIRECTORY_SEPARATOR . 'user_token_' . $user_id_from_request . '.txt';  // 存储Token的文件路径
        $file_path = $tmp_dir . DIRECTORY_SEPARATOR . 'user_token_' . $user_id_from_request . '.txt';
        error_log('Token file path: ' . $file_path);
 // 检查Token文件是否存在
 if (file_exists($file_path)) {
    // 读取文件内容
    $data = json_decode(file_get_contents($file_path), true);

    // 检查文件中的数据是否正确
    if ($data && isset($data['token'], $data['expires_at'])) {
        $stored_token = $data['token'];
        $expires_at = $data['expires_at'];

        // 检查Token是否过期
        if ($token_from_request === $stored_token) {
            if (time() > $expires_at) {
                // Token已过期
                http_response_code(401);  // Unauthorized
                echo json_encode(['status' => 'error',
            'message' => 'Token expired.', ]);
            } else {
                // Token有效，延长过期时间
                $new_expires_at = time() + 86400;
                $data['expires_at'] = $new_expires_at;

                // 更新文件内容
                file_put_contents($file_path, json_encode($data));

                // 返回成功消息
                echo json_encode(['status' => 'success',
            'message' => 'Logged in',]);
            }
        } else {
            // Token无效
            http_response_code(401);  // Unauthorized
            echo json_encode(['status' => 'error',
            'message' => 'Invalid token',]);
        }
       } else {
        // 文件内容格式错误
        http_response_code(500);  // Internal Server Error
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid token file structure.',
           ]);
    }
} else {
    // Token文件不存在
    http_response_code(401);  // Unauthorized
    echo json_encode([
        'status' => 'error',
            'message' => 'Token not found.',
        ]);
}
// } 
//  else {
//  // 缺少 user_id 或 token
//  http_response_code(400);  // Bad Request
//  echo json_encode([
//      'status' => 'error',
//              'message' => 'Missing userid or token.',
//     ]);
//  }








?>

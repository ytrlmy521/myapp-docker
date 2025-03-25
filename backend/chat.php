<?php


// 允许所有域名发起请求
header("Access-Control-Allow-Origin: http://10.0.63.120:8089");
// 允许的 HTTP 方法
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
// 允许的请求头
header('Access-Control-Allow-Headers: Content-Type, Authorization, userid');
// 允许跨域时携带 Cookie
header('Access-Control-Allow-Credentials: true');

// 处理预检请求（OPTIONS 请求）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
header('Content-Type: application/json');

// 获取前端发送的数据
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// 检查用户是否发送了 messages
if (!isset($data['messages']) || !is_array($data['messages'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// 准备远程接口的请求数据
$payload = json_encode([
    'model' => 'qwen2.5-coder-instruct',
    'messages' => $data['messages'],
]);

// 调用远程接口
$url = 'http://8843843nmph5.vicp.fun/v1/chat/completions';
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'accept: application/json',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 检查远程接口响应
if ($httpCode === 200) {
    $responseData = json_decode($response, true);
    echo json_encode([
        'message' => $responseData['choices'][0]['message']['content'] ?? '助手未返回内容',
    ]);
} else {
    echo json_encode(['error' => '请求远程接口失败']);
}

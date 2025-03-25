<?php
session_start(); // 启动 Session
// 设置跨域请求头
header("Access-Control-Allow-Origin: http://10.0.63.120:8089"); // 允许指定来源的请求
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // 允许的 HTTP 方法
header("Access-Control-Allow-Headers: Content-Type, Authorization, userid"); // 允许的请求头
header("Access-Control-Allow-Credentials: true"); // 允许携带凭证

// 销毁 Session
session_unset();
session_destroy();

// 返回成功消息
echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
?>

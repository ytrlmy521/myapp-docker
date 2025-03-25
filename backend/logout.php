<?php
session_start(); // 启动 Session

// 注意：跨域(CORS)相关设置已移至nginx配置

// 销毁 Session
session_unset();
session_destroy();

// 返回成功消息
echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
?>

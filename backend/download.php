<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'db.php'; // 数据库连接文件

$upload_id = $_GET['upload_id'] ?? null;

if (!$upload_id) {
    http_response_code(400);
    header("Content-Type: application/json; charset=UTF-8");// 错误时才设置 JSON 头
    echo json_encode([
        'code' => 400,
        'message' => 'upload_id 不能为空',
        'data' => null,
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT file_name, file_content, input_data FROM code WHERE upload_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare 语句执行失败: ' . $conn->error);
    }

    $stmt->bind_param("s", $upload_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $files = [];

    while ($row = $result->fetch_assoc()) {
        $file_name = $row['file_name'] ?? '文本输入.txt';
        $file_content = $row['file_content'] ?? $row['input_data']; // 如果 file_content 为空，使用 input_data

        // 如果文件内容为空，返回错误
        if (empty($file_content)) {
            http_response_code(400); // 设置状态码为 400
            echo json_encode([
                'code' => 400,
                'message' => '文件内容为空',
                'data' => null,
            ]);
            exit;
        }

        // 保存文件名和内容
        $files[] = [
            'file_name' => $file_name,
            'file_content' => $file_content,
        ];
    }

    if (empty($files)) {
        http_response_code(404);
        echo json_encode([
            'code' => 404,
            'message' => '文件未找到',
            'data' => null,
        ]);
        exit;
    }

   // 单文件下载
if (count($files) === 1) {
    $file = $files[0];
    $file_name = $file['file_name'];
    
    // 确保文件名包含扩展名
    if (!pathinfo($file_name, PATHINFO_EXTENSION)) {
        $file_name .= '.txt'; // 如果没有扩展名，默认为 .txt
    }

     // 清理输出缓冲区并设置下载头
     while (ob_get_level()) ob_end_clean(); // 彻底清理缓冲区
     http_response_code(200); // 明确成功状态码
     header('Content-Type: application/octet-stream');
     header('Content-Disposition: attachment; filename="' . $file_name . '"');
     header('Content-Length: ' . strlen($file['file_content']));
     echo $file['file_content'];
     exit;
}

// 多文件下载
$zip = new ZipArchive();
$zip_name = 'files_' . $upload_id . '.zip'; // 使用 upload_id 作为 ZIP 文件名

if ($zip->open($zip_name, ZipArchive::CREATE) === true) {
    foreach ($files as $file) {
        $file_name = $file['file_name'];
        // 确保文件名包含扩展名
        if (!pathinfo($file_name, PATHINFO_EXTENSION)) {
            $file_name .= '.txt'; // 如果没有扩展名，默认为 .txt
        }
        $zip->addFromString($file_name, $file['file_content']);
    }
    $zip->close();

     // [新增] 验证文件是否存在
     if (!file_exists($zip_name) || filesize($zip_name) === 0) {
        throw new Exception('ZIP 文件生成失败');
    }

    // 设置下载头
    while (ob_get_level()) ob_end_clean(); // 再次清理缓冲区
    http_response_code(200);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_name . '"');
    header('Content-Length: ' . filesize($zip_name));
    readfile($zip_name);
    unlink($zip_name); // 删除临时文件
    exit;
} else {
    throw new Exception('无法创建 ZIP 文件');
}

} catch (Exception $e) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        'code' => 500,
        'message' => '服务器错误: ' . $e->getMessage(),
        'data' => null,
    ]);
}

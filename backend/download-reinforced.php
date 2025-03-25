<?php
header("Content-Type: application/json; charset=UTF-8");

// 注意：跨域(CORS)相关设置已移至nginx配置

require_once 'db.php'; // 数据库连接文件

$upload_id = $_GET['upload_id'] ?? null;

if (!$upload_id) {
    http_response_code(400);
    echo json_encode([
        'code' => 400,
        'message' => 'upload_id 不能为空',
        'data' => null,
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT file_name, file_content_api, input_data FROM code WHERE upload_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare 语句执行失败: ' . $conn->error);
    }

    $stmt->bind_param("s", $upload_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $files = [];

    while ($row = $result->fetch_assoc()) {
        $file_name = $row['file_name'] ?? '文本输入.txt';
        $file_content = $row['file_content_api'] ?? $row['input_data']; // 如果 file_content 为空，使用 input_data

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

    header('Content-Type: application/octet-stream'); // 设置正确的 Content-Type
    header('Content-Disposition: attachment; filename="' . $file_name . '"'); // 确保文件名包含扩展名
    echo $file['file_content']; // 直接输出文件内容
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

    header('Content-Type: application/zip'); // 设置正确的 Content-Type
    header('Content-Disposition: attachment; filename="' . $zip_name . '"'); // 确保 ZIP 文件名正确
    readfile($zip_name);
    unlink($zip_name); // 删除临时文件
    exit;
} else {
    throw new Exception('无法创建 ZIP 文件');
}

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'code' => 500,
        'message' => $e->getMessage(),
        'data' => null,
    ]);
}

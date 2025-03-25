<?php


header("Content-Type: application/json; charset=UTF-8");

// 注意：跨域(CORS)相关设置已移至nginx配置

// 数据库配置
// $servername = "localhost";
// $username = "your_db_username";
// $password = "your_db_password";
// $dbname = "your_db_name";

// 创建连接
$conn = require_once 'db.php';

// 检查连接
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "数据库连接失败: " . $conn->connect_error]);
    exit();
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "仅支持 POST 请求"]);
    exit();
}

// 获取表单数据
$submissionType = isset($_POST['submissionType']) ? $_POST['submissionType'] : '';
$option = isset($_POST['option']) ? $_POST['option'] : '';
$additionalOption = isset($_POST['additionalOption']) ? $_POST['additionalOption'] : '';
$upload_id = isset($_POST['upload_id']) ? $_POST['upload_id'] : '';

// 验证必填字段
if (empty($submissionType) || empty($option) || empty($additionalOption) || empty($upload_id)) {
    http_response_code(400);
    echo json_encode(["message" => "缺少必填字段"]);
    exit();
}

$response_messages = [];

// 处理文本输入
if ($submissionType === 'text') {
    $input = isset($_POST['input']) ? $_POST['input'] : '';

    if (empty($input)) {
        http_response_code(400);
        echo json_encode(["message" => "未提供输入文本"]);
        exit();
    }

    // 使用预处理语句插入数据（不涉及文件）
    $stmt = $conn->prepare("INSERT INTO code (upload_id, input_data, language, vulnerability_type, file_name, file_type, file_content) VALUES (?, ?, ?, ?, '', '', NULL)");
    if ($stmt === false) {
        $response_messages[] = "数据库准备语句失败: " . $conn->error;
    } else {
        // 绑定参数
        $stmt->bind_param("ssss", $upload_id, $input, $option, $additionalOption);

        // 执行语句
        if ($stmt->execute()) {
            $response_messages[] = "文本提交成功";
        } else {
            $response_messages[] = "文本提交失败: " . $stmt->error;
        }

        // 关闭语句
        $stmt->close();
    }
}
// 处理文件上传

elseif ($submissionType === 'file') {
    if (!isset($_FILES['files'])) {
        http_response_code(400);
        echo json_encode(["message" => "未检测到上传文件"]);
        exit();
    }

    $files = $_FILES['files'];
    $file_count = count($files['name']);

    if ($file_count == 0) {
        http_response_code(400);
        echo json_encode(["message" => "未上传任何文件"]);
        exit();
    }

    for ($i = 0; $i < $file_count; $i++) {
        $file_name = $files['name'][$i];
        $file_type = $files['type'][$i];
        $file_tmp = $files['tmp_name'][$i];
        $file_error = $files['error'][$i];
        $file_size = $files['size'][$i];

        if ($file_error !== UPLOAD_ERR_OK) {
            $response_messages[] = "文件 {$file_name} 上传失败，错误代码：{$file_error}";
            continue;
        }

        // 限制文件大小（例如 50MB）
        if ($file_size > 50 * 1024 * 1024) {
            $response_messages[] = "文件 {$file_name} 超过了 50MB 的限制";
            continue;
        }

        // 移除文件类型验证
        /*
        if (!in_array($file_type, $allowed_types)) {
            $response_messages[] = "文件 {$file_name} 的类型不被允许";
            continue;
        }
        */

        // 读取文件内容
        $file_content = file_get_contents($file_tmp);
        if ($file_content === false) {
            $response_messages[] = "无法读取文件 {$file_name}";
            continue;
        }

        // 使用预处理语句插入数据
        $stmt = $conn->prepare("INSERT INTO code (upload_id, input_data, language, vulnerability_type, file_name, file_type, file_content) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $response_messages[] = "数据库准备语句失败: " . $conn->error;
            continue;
        }

        // 对于文件上传，input_data 为空字符串
        $input = '';

        // 初始化用于绑定 BLOB 数据的变量
        $file_content_placeholder = '';

        // 绑定参数，类型字符串改为 "ssssssb"（最后一个参数为 BLOB）
        $stmt->bind_param(
            "ssssssb",
            $upload_id,
            $input,
            $option,
            $additionalOption,
            $file_name,
            $file_type,
            $file_content_placeholder
        );

        // 发送 BLOB 数据
        $stmt->send_long_data(6, $file_content); // 'file_content' 是第7个参数，索引从0开始

        // 执行语句
        if ($stmt->execute()) {
            $response_messages[] = "文件 {$file_name} 上传成功";
            // 返回 upload_id
            echo json_encode(["message" => "文件上传成功", "upload_id" => $upload_id]);
        } else {
            $response_messages[] = "文件 {$file_name} 上传失败: " . $stmt->error;
        }

        // 关闭语句
        $stmt->close();
    }
}

    



    
 else {
    http_response_code(400);
    echo json_encode(["message" => "无效的提交类型"]);
    exit();
}

// 关闭数据库连接
$conn->close();

// 返回响应
echo json_encode(["message" => implode("; ", $response_messages)]);
?>

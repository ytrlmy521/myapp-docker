<?php
header("Content-Type: application/json; charset=UTF-8");

// 注意：跨域(CORS)相关设置已移至nginx配置

require_once 'db.php'; // 数据库连接文件

// 获取查询参数
$page = $_GET['page'] ?? 1; // 当前页码，默认第一页
$pageSize = $_GET['page_size'] ?? 20; // 每页条数，默认20条
$uploaded_at_start = $_GET['uploaded_at'][0] ?? null;
$uploaded_at_end = $_GET['uploaded_at'][1] ?? null;
$file_name = $_GET['file_name'] ?? null;
$language = $_GET['language'] ?? null;
$vulnerability_type = $_GET['vulnerability_type'] ?? null;

try {
    // 构建 SQL 查询
    $sql = "SELECT upload_id, uploaded_at, file_name, language, vulnerability_type FROM code WHERE 1=1";
    if ($uploaded_at_start && $uploaded_at_end) {
        $sql .= " AND uploaded_at BETWEEN '$uploaded_at_start' AND '$uploaded_at_end'";
    }
    if ($file_name) {
        $sql .= " AND file_name LIKE '%$file_name%'";
    }
    if ($language) {
        $sql .= " AND language = '$language'";
    }
    if ($vulnerability_type) {
        $sql .= " AND vulnerability_type = '$vulnerability_type'";
    }
    // 添加排序条件，按照 uploaded_at 倒序排序
    $sql .= " ORDER BY uploaded_at DESC";

    // 执行查询
    $result = $conn->query($sql);
    if ($result === false) {
        // 查询失败，返回错误信息
        throw new Exception('查询失败: ' . $conn->error);
    }

    $data = [];
    $file_names = []; // 用于存储文件名

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $upload_id = $row['upload_id'];

            // 如果文件名为空，重新赋值为 '文本输入'
            if (empty($row['file_name'])) {
                $row['file_name'] = '文本输入.txt';
            }

            // 按 upload_id 分组存储文件名
            if (!empty($row['file_name'])) {
                $file_names[$upload_id][] = $row['file_name'];
            }

            // 只保存第一条记录，后续相同的 upload_id 不重复保存
            if (!isset($data[$upload_id])) {
                $data[$upload_id] = $row;
            }
        }
    }

    // 将文件名数组转换为以逗号分隔的字符串
    foreach ($data as &$item) {
        $upload_id = $item['upload_id'];
        if (isset($file_names[$upload_id])) {
            $item['file_name'] = implode(', ', $file_names[$upload_id]);
        }
    }

    // 将关联数组转换为索引数组
    $data = array_values($data);

    // 获取总数（去重后的总数）
    $total = count($data);

    // 分页逻辑
    $offset = ($page - 1) * $pageSize;
    $data = array_slice($data, $offset, $pageSize); // 分页截取

    // 成功返回
    echo json_encode([
        'code' => 200,
        'message'=> '请求成功',
        'total'=> $total, // 返回总条数
        'data'=> $data,
    ]);
} catch (Exception $e) {
    // 捕获异常，返回错误信息
    echo json_encode([
        'code'=> 500,
        'message'=> $e->getMessage(),
        'data'=> null,
    ]);
}

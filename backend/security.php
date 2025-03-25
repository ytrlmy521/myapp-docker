<?php
// security.php

header("Content-Type: application/json; charset=UTF-8");

// 允许跨域请求（根据需要调整）
header("Access-Control-Allow-Origin: http://10.0.63.120:8089");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, userid");
header("Access-Control-Allow-Credentials: true");

set_time_limit(0);
// 初始化响应数组
$response = [
    'status' => 'error',
    'message' => '未知错误'
];

$max_file_size = 50 * 1024 * 1024; // 50MB

// 禁止显示 PHP 错误信息（转而记录到日志）
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt'); // 错误日志文件路径，请确保此文件可写

try {
    // 确保请求方法为 POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('仅支持 POST 请求');
    }

    // 检查 submissionType
    if (!isset($_POST['submissionType'])) {
        throw new Exception('缺少 submissionType 参数');
    }

    // 解析 option 和 additionalOption
    if (!isset($_POST['option']) || !isset($_POST['additionalOption'])) {
        throw new Exception('缺少 option 或 additionalOption 参数');
    }

    $option = $_POST['option'];
    $additionalOption = $_POST['additionalOption'];
    $upload_id = $_POST['upload_id'];

    // 拼接 prompt
    $prompt = "You are a coding master, please perform {$additionalOption} on the {$option} code. The returned results only contain code results, do not return anything else.";

    $submissionType = $_POST['submissionType'];

    if ($submissionType === 'text') {
        // 处理文本提交
        if (!isset($_POST['input'])) {
            throw new Exception('缺少 input 参数');
        }

        $input = trim($_POST['input']);

        if (empty($input)) {
            throw new Exception('输入内容不能为空');
        }

        // 调用外部 API
        $apiResponse = callExternalAPI($prompt, $input);

        $txtFileName = '文本输入.txt'; // 指定文件名为 "文本输入.txt"
        file_put_contents($txtFileName, $apiResponse); // 将 $apiResponse 写入文件
        
        // 读取文件内容为二进制数据
        $fileContent = file_get_contents($txtFileName);
        
        // 将文件内容保存到数据库
        saveToDatabase(upload_id: $upload_id, input_data: $input, content: $fileContent);

        unlink($txtFileName); // 删除临时文件
        // 返回响应
        $response = [
            'status' => 'success',
            'option' => $option,
            'additionalOption' => $additionalOption,
            'data' => [
                'type' => 'text',
                'files' => [
                    [
                        'fileName' => '文本输入.txt',
                        'filePath' => '/文本输入.txt',
                        'isdir' => '0',
                        'fileContent' => $input,
                        'fileContent2' => $apiResponse // 保存 API 返回结果
                    ]
                ]
            ]
        ];

    } elseif ($submissionType === 'file') {
        // 处理文件提交
        if (!isset($_FILES['files'])) {
            throw new Exception('缺少文件上传');
        }

        $files = $_FILES['files'];

        // 处理单个文件上传的情况
        if (!is_array($files['name'])) {
            // 将单个文件转换为数组形式
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']],
            ];
        }

        $file_count = count($files['name']);

        // 检查每个文件
        $uploaded_files = [];

        for ($i = 0; $i < $file_count; $i++) {
            $error = $files['error'][$i];
            if ($error !== UPLOAD_ERR_OK) {
                throw new Exception("文件上传错误：{$files['name'][$i]}");
            }

            $size = $files['size'][$i];
            if ($size > $max_file_size) {
                throw new Exception("文件 {$files['name'][$i]} 大小超过 50MB 限制");
            }

            $filename = $files['name'][$i];
            $tmp_name = $files['tmp_name'][$i];

            $uploaded_files[] = [
                'name' => $filename,
                'tmp_name' => $tmp_name,
                'type' => $files['type'][$i]
            ];
        }

        // 处理文件
        $all_files = [];

        foreach ($uploaded_files as $file) {
            $filename = $file['name'];
            $tmp_name = $file['tmp_name'];
            $file_type = $file['type'];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            //临时储存全名
            $apiFileName = $filename;
            // 检测文件是否为二进制文件（如图片）
            $is_binary = strpos($file_type, 'text') === false && strpos($file_type, 'application') === false;

            if ($file_ext === 'zip') {
                // 检查 ZipArchive 类是否存在
                if (!class_exists('ZipArchive')) {
                    throw new Exception("服务器未启用 ZipArchive 类，无法处理 ZIP 文件：{$filename}");
                }

                // 处理 ZIP 压缩文件
                $zip = new ZipArchive();
                if ($zip->open($tmp_name) === TRUE) {
                    $zip_files = [];

                    for ($j = 0; $j < $zip->numFiles; $j++) {
                        $stat = $zip->statIndex($j);
                        $file_path = $stat['name'];

                        // 如果是目录，文件路径以 '/' 结尾
                        if (substr($file_path, -1) === '/') {
                            // 目录路径以 '/' 结尾
                            $zip_files[] = [
                                'fileName' => basename($file_path),
                                'filePath' => '/' . $file_path,
                                'isdir' => '1',
                                'fileContent' => '',
                                'children' => []
                            ];
                        } else {
                            // 如果是文件，处理文件内容
                            $file_content = $zip->getFromIndex($j);
                            if ($file_content === FALSE) {
                                continue; // 如果无法读取文件内容，跳过该文件
                            }

                            $filename = basename($file_path);

                            // 判断是否为图片文件
                            $is_image = isImageFile($filename);

                            // 判断是否为代码文件
                            $is_code = isCodeFile($filename);

                            // 如果是图片或非代码文件，跳过 API 调用
                            $is_binary = $is_image || !$is_code;

                            // 将文件路径加上根目录 '/'
                            $zip_files[] = [
                                'fileName' => $filename,
                                'filePath' => '/' . $file_path,
                                'isdir' => '0',
                                'fileContent' => $is_binary ? '非代码文件，未处理'  : $file_content,
                                'fileContent2' => $is_binary ? '非代码文件，未处理' : callExternalAPI($prompt, $file_content),
                                'isBinary' => $is_binary
                            ];
                        }
                    }

                    // 构建文件夹结构
                    $current_zip_tree = buildFileTree($zip_files);

                   
                    
                    // 在使用 $current_zip_tree 的地方调用 saveTreeAsZip 函数
                    $zipFileName = 'reconstructed-' . $apiFileName . '.zip'; // 使用 . 拼接字符串

                    // 读取文件内容为二进制数据
                    saveTreeAsZip($current_zip_tree, $zipFileName);
                    $apifileContent = file_get_contents($zipFileName);
                   // 保存到数据库
                   saveToDatabaseZip($upload_id,  $apiFileName,   $apifileContent);
                   unlink($zipFileName);


                
                    // 合并当前 ZIP 文件的内容到 $all_files
                    $all_files = array_merge($all_files, $current_zip_tree);

                    $zip->close();
                } else {
                    throw new Exception("无法打开压缩文件：{$filename}");
                }
            } else {
                // 处理普通文件
                $file_content = file_get_contents($tmp_name);
                if ($file_content === FALSE) {
                    continue; // 如果无法读取文件内容，跳过该文件
                }

                // 检测文件是否为二进制文件
                $is_binary = strpos(mime_content_type($tmp_name), 'text') === false && strpos(mime_content_type($tmp_name), 'application') === false;

                // 如果是二进制文件，直接返回二进制内容，不调用 API
                $apiResponse = $is_binary ? '非代码文件，未处理' : callExternalAPI($prompt, $file_content);

              
                file_put_contents('reconstructed-' .$apiFileName, $apiResponse); // 将 $apiResponse 写入文件
                
                // 读取文件内容为二进制数据
                $apifileContent = file_get_contents('reconstructed-' .$apiFileName);
                
                // 保存到数据库
                saveToDatabaseZip($upload_id, $filename,  $apifileContent);
                
                    unlink('reconstructed-' .$apiFileName);
                    
                

                $all_files[] = [
                    'fileName' => $filename,
                    'filePath' => '/' . $filename,
                    'isdir' => '0',
                    'fileContent' => $is_binary ? '非代码文件，未处理'  : $file_content,
                    'fileContent2' => $apiResponse,
                    'isBinary' => $is_binary
                ];
            }
        }

        // 返回响应
        $response = [
            'status' => 'success',
            'option' => $option,
            'additionalOption' => $additionalOption,
            'data' => [
                'type' => 'file',
                'files' => $all_files
            ]
        ];

    } else {
        throw new Exception('无效的 submissionType 参数');
    }

} catch (Exception $e) {
    // 捕获并设置错误信息
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// 输出 JSON 响应
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * 构建文件夹结构
 *
 * @param array $files 文件列表
 * @return array 构建好的文件夹结构
 */
function buildFileTree(array $files): array {
    $tree = [];

    foreach ($files as $file) {
        $path = explode('/', trim($file['filePath'], '/'));
        $current = &$tree;

        foreach ($path as $index => $part) {
            if ($part === '') {
                continue;
            }

            // 如果是最后一个部分且是文件，直接添加到当前层级
            if ($index === count($path) - 1 && $file['isdir'] === '0') {
                $current[] = $file;
                break;
            }

            $found = false;
            foreach ($current as &$item) {
                if ($item['fileName'] === $part) {
                    $current = &$item['children'];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $new_item = [
                    'fileName' => $part,
                    'filePath' => '/' . implode('/', array_slice($path, 0, $index + 1)),
                    'isdir' => '1',
                    'fileContent' => '',
                    'children' => []
                ];
                $current[] = $new_item;
                $current = &$current[count($current) - 1]['children'];
            }
        }
    }

    return $tree;
}

/**
 * 调用外部 API
 *
 * @param string $prompt 系统提示词
 * @param string $content 用户内容
 * @return string API 返回结果
 */
function callExternalAPI(string $prompt, string $content): string {
    $url = 'http://8843843nmph5.vicp.fun/v1/chat/completions';
    $data = [
        'model' => 'qwen2.5-coder-instruct',
        'messages' => [
            [
                'role' => 'system',
                'content' => $prompt
            ],
            [
                'role' => 'user',
                'content' => $content
            ]
        ]
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === FALSE) {
        throw new Exception('调用外部 API 失败');
    }

    $response = json_decode($result, true);
    return $response['choices'][0]['message']['content'] ?? 'API 返回结果解析失败';
}

/**
 * 通过文件扩展名判断是否为图片文件
 *
 * @param string $filename 文件名
 * @return bool 是否为图片文件
 */
function isImageFile(string $filename): bool {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'];
    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($fileExt, $imageExtensions);
}

/**
 * 通过文件扩展名判断是否为代码文件
 *
 * @param
 * @param string $filename 文件名
 * @return bool 是否为代码文件
 */
function isCodeFile(string $filename): bool {
    $codeExtensions = [
        // 前端代码
        'html', 'css', 'scss', 'less', 'js', 'jsx', 'ts', 'tsx', 'vue',
        // 后端代码
        'php', 'py', 'java', 'c', 'cpp', 'go', 'rb', 'cs', 'swift',
        // 配置文件
        'json', 'xml', 'yml', 'yaml', 'ini', 'env',
        // 其他
        'md', 'txt', 'log'
    ];
    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($fileExt, $codeExtensions);
}

/**
 * 将文件内容保存到数据库
 *
 * @param string $upload_id 上传记录的ID
 * @param string $filename 文件名
 * @param string $content 文件内容
 */
function saveToDatabase(string $upload_id, string $input_data, string $content): void {
    // 数据库连接
    $conn = require_once 'db.php';

    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }

    // 更新数据库中匹配 upload_id 和 filename 的记录
    $stmt = $conn->prepare("UPDATE code SET file_content_api = ? WHERE upload_id = ? AND input_data = ?");
    if (!$stmt) {
        throw new Exception("数据库准备语句失败: " . $conn->error);
    }

    $stmt->bind_param("sss", $content, $upload_id, $input_data);

    if (!$stmt->execute()) {
        throw new Exception("数据库更新失败: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
}


function saveToDatabaseZip(string $upload_id, string $file_name, string $content): void {
    // 数据库连接
    // $conn = require_once 'db.php';
    // 创建新的数据库连接
    $servername = "localhost";
$username = "root"; // 数据库用户名
$password = "123456"; // 数据库密码
$dbname = "myapp"; // 数据库名

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("数据库连接失败: " . $conn->connect_error);
    }

    // 更新数据库中匹配 upload_id 和 filename 的记录
    $stmt = $conn->prepare("UPDATE code SET file_content_api = ? WHERE upload_id = ? AND file_name = ?");
    if (!$stmt) {
        throw new Exception("数据库准备语句失败: " . $conn->error);
    }

    $stmt->bind_param("sss", $content, $upload_id, $file_name);

    if (!$stmt->execute()) {
        throw new Exception("数据库更新失败: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
}

//重构树结构
function addToZip(ZipArchive $zip, array $tree, string $basePath = '') {
    foreach ($tree as $item) {
        $filePath = $basePath . $item['fileName'];
        if ($item['isdir'] === '1') {
            // 如果是目录，先在 ZIP 文件中创建一个目录
            $zip->addEmptyDir($filePath);
            // 递归处理子文件
            if (!empty($item['children'])) {
                addToZip($zip, $item['children'], $filePath . '/');
            }
        } else {
            // 如果是文件，将文件内容添加到 ZIP 文件中
            $zip->addFromString($filePath, $item['fileContent2']);
        }
    }
}

function saveTreeAsZip(array $tree, string $zipPath): void {
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("无法创建 ZIP 文件: $zipPath");
    }

    addToZip($zip, $tree); // 调用独立的 addToZip 函数
    $zip->close();
}
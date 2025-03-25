<?php
$servername = "mysql";
$username = "root"; // 数据库用户名
$password = "123456"; // 数据库密码
$dbname = "myapp"; // 数据库名

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接是否成功
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 设置字符集
$conn->set_charset("utf8");

// 返回数据库连接
return $conn;
?>

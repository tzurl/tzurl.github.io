步骤一：设置数据库
1. 安装和配置MySQL
安装MySQL服务器并创建数据库和表：

sudo apt-get update
sudo apt-get install mysql-server
登录MySQL命令行并创建数据库和表：

CREATE DATABASE url_mapping_db;

USE url_mapping_db;

CREATE TABLE url_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(255) UNIQUE NOT NULL,
    url TEXT NOT NULL,
    allowed_users TEXT
);
2. 插入示例数据
插入一些初始数据，用于测试：

INSERT INTO url_mapping (`key`, url, allowed_users)
VALUES ('abc123', 'https://www.example.com', 'user1,user2,user3');

INSERT INTO url_mapping (`key`, url, allowed_users)
VALUES ('def456', 'https://www.test.com', 'user4,user2');

INSERT INTO url_mapping (`key`, url, allowed_users)
VALUES ('ghi789', 'https://www.demo.com', 'user1,user4');
步骤二：设置Redis
1. 安装和配置Redis
安装Redis服务器：

sudo apt-get install redis-server
启动Redis服务：

sudo service redis-server start
确保Redis正在运行：

redis-cli ping
步骤三：后端开发
1. 项目目录结构
创建项目目录结构：

url_mapping_project/
│
├── api/
│   └── api.php
│
├── config/
│   └── config.php
│
├── utils/
│   └── key_generator.php
│
└── web/
    ├── index.html
    └── script.js
2. 数据库配置文件
在config/config.php中存储数据库配置信息：

<?php
$servername = getenv('DB_SERVERNAME'); 
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
?>
确保在服务器环境中设置环境变量：

export DB_SERVERNAME="localhost"
export DB_USERNAME="your_username"
export DB_PASSWORD="your_password"
export DB_NAME="url_mapping_db"
3. 密钥生成工具
在utils/key_generator.php中创建密钥生成工具：

<?php
function generateKey($length = 10) {
    return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
}
?>
4. API开发
在api/api.php中编写API服务，用于处理URL查询：

<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include '../config/config.php';
include('../utils/key_generator.php');

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$cacheKeyPrefix = "url_mapping_";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "连接数据库失败: " . $conn->connect_error]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['key']) && isset($_POST['user'])) {
    $key = $_POST['key'];
    $user = $_POST['user'];

    // 检查 Redis 缓存
    $cacheKey = $cacheKeyPrefix . $key;
    $cachedData = $redis->get($cacheKey);

    if ($cachedData) {
        $mappingData = json_decode($cachedData, true);
    } else {
        $stmt = $conn->prepare("SELECT url, allowed_users FROM url_mapping WHERE `key` = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($targetUrl, $allowedUsers);
        $stmt->fetch();

        if ($stmt->num_rows > 0) {
            $mappingData = ['url' => $targetUrl, 'allowed_users' => $allowedUsers];
            // 缓存数据到 Redis
            $redis->setex($cacheKey, 3600, json_encode($mappingData));
        } else {
            echo json_encode(["error" => "无效的密钥"]);
            exit();
        }
    }

    $allowedUsersArray = explode(',', $mappingData['allowed_users']);
    if (in_array($user, $allowedUsersArray)) {
        echo json_encode(["url" => $mappingData['url']]);
    } else {
        echo json_encode(["error" => "用户未被授权访问此URL"]);
    }
} else {
    echo json_encode(["error" => "未提供密钥或用户信息"]);
}

$conn->close();
?>
步骤四：前端开发
1. HTML文件
在web/index.html中创建前端页面：

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Mapping Service</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 50px auto; text-align: center; }
        .form-group { margin-bottom: 15px; }
        input[type="text"], input[type="submit"] { padding: 10px; width: 80%; }
        input[type="submit"] { cursor: pointer; }
        .result { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>URL Mapping Service</h1>
        <div class="form-group">
            <input type="text" id="key" placeholder="Enter your key">
        </div>
        <div class="form-group">
            <input type="text" id="user" placeholder="Enter your username">
        </div>
        <div class="form-group">
            <input type="submit" id="submit" value="Get URL">
        </div>
        <div class="result" id="result"></div>
    </div>
    <script src="script.js"></script>
</body>
</html>
2. JavaScript文件
在web/script.js中编写JavaScript代码：

document.getElementById('submit').addEventListener('click', function () {
    const key = document.getElementById('key').value;
    const user = document.getElementById('user').value;
    const resultDiv = document.getElementById('result');

    if (key === '' || user === '') {
        resultDiv.innerHTML = 'Please provide a key and a username.';
        return;
    }

    fetch('http://yourdomain.com/api/api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `key=${key}&user=${user}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            resultDiv.innerHTML = `Error: ${data.error}`;
        } else {
            resultDiv.innerHTML = `Mapped URL: <a href="${data.url}" target="_blank">${data.url}</a>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        resultDiv.innerHTML = 'An error occurred. Please try again later.';
    });
});
步骤五：部署和测试
部署到服务器： 将项目文件上传到你的服务器，并确保PHP代码和Apache/Nginx配置正确。
设置环境变量： 在服务器上设置环境变量，如数据库配置信息。
测试API: 使用Postman或浏览器进行API测试，确保功能正常。
前端测试: 通过浏览器访问前端页面，输入密钥和用户名，测试URL映射功能是否正常工作。
密钥生成和管理
要生成新的密钥，可以在数据库手动插入新的记录，也可以扩展API功能来自动生成和插入密钥。

在utils/key_generator.php中调用generateKey()函数并插入生成的密钥到数据库：

include '../config/config.php';
include('../utils/key_generator.php');

$conn = new mysqli($servername, $username, $password, $dbname);
$key = generateKey();
$url = 'https://www.newurl.com';
$allowed_users = 'user1,user2';

$stmt = $conn->prepare("INSERT INTO url_mapping (`key`, url, allowed_users) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $key, $url, $allowed_users);
$stmt->execute();

echo "Generated key: " . $key;

$conn->close();
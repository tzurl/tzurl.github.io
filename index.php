<?php
if (!isset($_GET['url']) || !isset($_GET['type'])) {
    $responseArray = [
        'code' => 0,
        'msg' => '未提供 URL 或类型参数'
    ];

    header('Content-Type: application/json');
    echo json_encode($responseArray);
    exit;
}

$urlToEncode = $_GET['url'];
$type = $_GET['type'];

$encodedUrl = base64_encode($urlToEncode);

if ($type === 'open') {
    $fanghUrl = 'http://openurl.zeabur.app/?url=' . $encodedUrl;
} elseif ($type === 'jump') {
    $fanghUrl = 'http://jumping.zeabur.app/?url=' . $encodedUrl;
} else {
    $responseArray = [
        'code' => 0,
        'msg' => '无效的类型参数'
    ];
    
    header('Content-Type: application/json');
    echo json_encode($responseArray);
    exit;
}

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $fanghUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec($ch);

if ($response === false) {
    $responseArray = [
        'code' => 0,
        'msg' => curl_error($ch)
    ];
} else {
    $responseArray = [
        'code' => 1,
        'msg' => '成功',
        'url' => $fanghUrl
    ];
}

curl_close($ch);

header('Content-Type: application/json');
echo json_encode($responseArray);
?>

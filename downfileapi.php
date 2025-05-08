<?php
header('Content-Type: application/json; charset=utf-8');

// 获取请求参数
$fileUrl = isset($_GET['url']) ? trim($_GET['url']) : '';

if (empty($fileUrl)) {
    http_response_code(400);
    echo json_encode(['error' => '请提供URL参数'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($fileUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => '无效的URL格式'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 初始化cURL获取头部信息
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $fileUrl,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERAGENT => 'PHP File Downloader',
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(404);
    echo json_encode([
        'error' => '无法访问该URL',
        'curl_error' => curl_error($ch)
    ], JSON_UNESCAPED_UNICODE);
    curl_close($ch);
    exit;
}

$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($statusCode != 200) {
    http_response_code($statusCode);
    echo json_encode([
        'error' => '文件不可用，HTTP状态码: ' . $statusCode,
        'final_url' => $finalUrl
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查可下载文件类型
$isDownloadable = false;
$downloadableTypes = [
    'application/octet-stream', 'application/pdf', 'application/zip',
    'application/x-rar-compressed', 'application/x-zip-compressed',
    'audio/', 'video/', 'image/', 'text/plain',
    'application/msword', 'application/vnd.ms-excel',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.'
];

foreach ($downloadableTypes as $type) {
    if (strpos($contentType, $type) === 0) {
        $isDownloadable = true;
        break;
    }
}

if (!$isDownloadable) {
    http_response_code(415);
    echo json_encode([
        'error' => '该URL不是可下载文件',
        'content_type' => $contentType
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 改进的文件名提取逻辑
$filename = basename(parse_url($finalUrl, PHP_URL_PATH));

// 如果basename无法获取有效文件名，再尝试其他方式
if (empty($filename) || $filename === '/' || strpos($filename, '.') === false) {
    // 尝试从Content-Disposition头获取文件名
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $fileUrl,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'PHP File Downloader'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (preg_match('/filename=["\']?([^"\'\s;]+)/i', $response, $matches)) {
        $filename = $matches[1];
    } else {
        $filename = 'download_' . time();
    }
}

// 清理文件名中的查询参数
$filename = preg_replace('/\?.*$/', '', $filename);
$filename = preg_replace('/[\/\\\\:*?"<>|]/', '_', $filename); // 替换非法字符

// 设置下载头部
header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

// 下载文件
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $fileUrl,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_HEADER => false,
    CURLOPT_USERAGENT => 'PHP File Downloader',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_BUFFERSIZE => 8192
]);

$result = curl_exec($ch);
if ($result === false) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'error' => '文件下载失败',
        'curl_error' => curl_error($ch),
        'final_url' => $finalUrl
    ], JSON_UNESCAPED_UNICODE);
}

curl_close($ch);
exit;
?>
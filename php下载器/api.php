<?php
header('Content-Type: application/json');
require_once 'download.class.php';

try {
    $action = $_GET['action'] ?? '';
    $downloader = new SecureDownloader();
    
    switch ($action) {
        case 'fileinfo':
            $url = $_POST['url'] ?? '';
            if (empty($url)) {
                throw new InvalidArgumentException("URL参数不能为空");
            }
            
            $downloader->setUrl($url);
            $info = $downloader->getRemoteFileInfo();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'size' => $info['size'],
                    'resumable' => $info['accept_ranges']
                ]
            ]);
            break;
            
        case 'download':
            $url = $_POST['url'] ?? '';
            $filename = $_POST['filename'] ?? 'download_' . time();
            $chunkSize = isset($_POST['chunkSize']) ? (int)$_POST['chunkSize'] : 1048576;
            
            if (empty($url)) {
                throw new InvalidArgumentException("URL参数不能为空");
            }
            
            // 创建下载目录
            if (!file_exists(__DIR__ . '/downloads')) {
                mkdir(__DIR__ . '/downloads', 0755, true);
            }
            
            $savePath = __DIR__ . '/downloads/' . basename($filename);
            $downloader->setUrl($url)
                      ->setChunkSize($chunkSize);
            
            // 进度回调
            $downloader->setProgressCallback(function($downloaded, $total) {
                file_put_contents(__DIR__ . '/progress.json', json_encode([
                    'downloaded' => $downloaded,
                    'total' => $total,
                    'percentage' => $total > 0 ? round(($downloaded / $total) * 100, 2) : 0
                ]));
            });
            
            $downloader->download($savePath);
            
            echo json_encode([
                'success' => true,
                'path' => str_replace(__DIR__, '', $savePath)
            ]);
            break;
            
        case 'progress':
            $progress = file_exists(__DIR__ . '/progress.json') 
                ? json_decode(file_get_contents(__DIR__ . '/progress.json'), true)
                : ['downloaded' => 0, 'total' => 0, 'percentage' => 0];
            
            echo json_encode($progress);
            break;
            
        case 'list':
            $downloadDir = __DIR__ . '/downloads';
            $files = [];
            
            if (file_exists($downloadDir)) {
                foreach (scandir($downloadDir) as $file) {
                    if ($file === '.' || $file === '..') continue;
                    
                    $filePath = $downloadDir . '/' . $file;
                    if (is_file($filePath)) {
                        $files[] = [
                            'name' => $file,
                            'size' => filesize($filePath),
                            'mtime' => filemtime($filePath)
                        ];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'files' => $files
            ]);
            break;
            
        default:
            throw new InvalidArgumentException("无效的操作类型");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
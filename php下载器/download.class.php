<?php
class SecureDownloader {
    private $url;
    private $headers = [];
    private $chunkSize = 1048576; // 默认1MB
    private $timeout = 30;
    private $maxRedirects = 5;
    private $progressCallback = null;

    public function setUrl($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("无效的URL格式");
        }
        $this->url = $url;
        return $this;
    }

    public function setChunkSize($bytes) {
        $this->chunkSize = max(1024, (int)$bytes);
        return $this;
    }

    public function setHeaders($headers) {
        if (!is_array($headers)) {
            throw new InvalidArgumentException("请求头必须是数组");
        }
        $this->headers = $headers;
        return $this;
    }

    public function setProgressCallback(callable $callback) {
        $this->progressCallback = $callback;
        return $this;
    }

    public function getRemoteFileInfo() {
        $this->validateUrl();
        
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $this->headers
        ]);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new RuntimeException("CURL错误: " . curl_error($ch));
        }
        
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $acceptRanges = strpos($response, 'Accept-Ranges: bytes') !== false;
        curl_close($ch);
        
        return [
            'size' => $size,
            'accept_ranges' => $acceptRanges,
            'status' => $status
        ];
    }

    public function download($savePath) {
        $this->validateUrl();
        $fileInfo = $this->getRemoteFileInfo();
        
        if ($fileInfo['status'] != 200) {
            throw new RuntimeException("服务器返回异常状态码: {$fileInfo['status']}");
        }
        
        $savePath = $this->prepareSavePath($savePath);
        $tempPath = $savePath . '.tmp';
        $fp = null;
        
        try {
            $fileSize = file_exists($tempPath) ? filesize($tempPath) : 0;
            $fp = fopen($tempPath, $fileSize ? 'ab' : 'wb');
            
            if (!$fp) {
                throw new RuntimeException("无法打开文件: $tempPath");
            }
            
            while (true) {
                $downloaded = ftell($fp);
                if ($fileInfo['size'] > 0 && $downloaded >= $fileInfo['size']) {
                    break;
                }
                
                $chunk = $this->downloadChunk($downloaded, $fileInfo['size']);
                $written = fwrite($fp, $chunk);
                
                if ($written === false || $written != strlen($chunk)) {
                    throw new RuntimeException("文件写入失败");
                }
                
                if ($this->progressCallback) {
                    call_user_func($this->progressCallback, $downloaded + $written, $fileInfo['size']);
                }
                
                if ($this->chunkSize <= 0) break;
            }
            
            fclose($fp);
            if (!rename($tempPath, $savePath)) {
                throw new RuntimeException("文件重命名失败");
            }
            
            return true;
        } catch (Exception $e) {
            if ($fp) fclose($fp);
            if (file_exists($tempPath)) @unlink($tempPath);
            throw $e;
        }
    }

    private function downloadChunk($start, $totalSize) {
        $ch = curl_init();
        $end = ($this->chunkSize > 0) ? min($start + $this->chunkSize - 1, $totalSize - 1) : '';
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RANGE => ($this->chunkSize > 0) ? "$start-$end" : '',
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $this->headers
        ]);
        
        $data = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status == 416) {
            return '';
        } elseif ($status != 200 && $status != 206) {
            throw new RuntimeException("分片下载失败 (HTTP $status)");
        }
        return $data;
    }

    private function prepareSavePath($path) {
        $dir = dirname($path);
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException("无法创建目录: $dir");
            }
        }
        
        if (!is_writable($dir)) {
            throw new RuntimeException("目录不可写: $dir");
        }
        
        return rtrim($dir, '/') . '/' . basename($path);
    }

    private function validateUrl() {
        if (empty($this->url)) {
            throw new InvalidArgumentException("URL不能为空");
        }
    }
}
<?php
// /game/persona/kinescope_api.php
// Модуль для работы с Kinescope API (официальная документация)

require_once __DIR__ . '/utils.php';

class KinescopeAPI {
    private $apiKey;
    private $baseUrl = 'https://api.kinescope.io/v1';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Извлекает segment из URL Kinescope
     */
    public function extractSegmentFromUrl($url) {
        if (preg_match('/app\.kinescope\.io\/video\?segment=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Извлекает ID видео из URL Kinescope (обновленная версия с поддержкой сегментов)
     */
    public function extractVideoId($url) {
        // Поддерживаем форматы:
        // https://kinescope.io/202339654 (числовой ID)
        // https://kinescope.io/video/202339654
        // https://kinescope.io/5NRs6UHWgMX9RtHqxNGy8j (строковый ID)
        // https://kinescope.io/video/5NRs6UHWgMX9RtHqxNGy8j
        // https://app.kinescope.io/video?segment=eyJwYXJlbnRfaWQiOiI2ODJiNmVjZi05NjA1LTQ2NTktYmYxMi0xODFhYzIzMWYyZGQifQ (новый формат с сегментом)
        
        // Проверяем новый формат с сегментом
        if (preg_match('/app\.kinescope\.io\/video\?segment=([^&]+)/', $url, $matches)) {
            $segment = $matches[1];
            echo "Обнаружен сегмент: $segment\n";
            
            // Декодируем base64 сегмент
            $decoded = base64_decode($segment);
            if ($decoded) {
                $data = json_decode($decoded, true);
                if ($data && isset($data['parent_id'])) {
                    echo "Найден parent_id: {$data['parent_id']}\n";
                    return $data['parent_id'];
                }
            }
            
            // Если декодирование не удалось, пробуем использовать сегмент как есть
            echo "Используем сегмент как ID: $segment\n";
            return $segment;
        }
        
        // Старые форматы
        if (preg_match('/kinescope\.io\/(?:video\/)?([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Получает информацию о видео (согласно официальной документации)
     */
    public function getVideoInfo($videoId) {
        $url = $this->baseUrl . '/videos/' . $videoId;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API Error: HTTP " . $httpCode . " - " . $response);
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Invalid JSON response");
        }
        
        return $data;
    }
    
    /**
     * Получает список видео с полными параметрами (согласно официальной документации)
     */
    public function getVideosList($params = []) {
        $defaultParams = [
            'page' => 1,
            'per_page' => 100,
            'order' => 'created_at.desc,title.asc',
            'status' => [],
            'folder_id' => '',
            'project_id' => '',
            'video_ids' => [],
            'q' => '',
            'without_folder' => false
        ];
        
        $params = array_merge($defaultParams, $params);
        
        // Строим URL с параметрами
        $url = $this->baseUrl . '/videos';
        $queryParams = [];
        
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $queryParams[] = $key . '[]=' . urlencode($item);
                }
            } elseif ($value !== '' && $value !== null) {
                $queryParams[] = $key . '=' . urlencode($value);
            }
        }
        
        if (!empty($queryParams)) {
            $url .= '?' . implode('&', $queryParams);
        }
        
        echo "Запрос к API: $url\n";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API Error: HTTP " . $httpCode . " - " . $response);
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Invalid JSON response");
        }
        
        return $data;
    }
    
    /**
     * Получает информацию о сегменте (согласно официальной документации)
     */
    public function getSegmentInfo($segment) {
        $url = $this->baseUrl . '/segments/' . $segment;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API Error: HTTP " . $httpCode . " - " . $response);
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Invalid JSON response");
        }
        
        return $data;
    }
    
    /**
     * Получает список видео в сегменте (согласно официальной документации)
     */
    public function getSegmentVideos($segment) {
        $url = $this->baseUrl . '/segments/' . $segment . '/videos';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API Error: HTTP " . $httpCode . " - " . $response);
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Invalid JSON response");
        }
        
        return $data;
    }
    
    /**
     * Получает ссылку на скачивание видео в указанном качестве
     */
    public function getVideoDownloadUrl($videoId, $quality = '360p') {
        $videoInfo = $this->getVideoInfo($videoId);
        
        if (!isset($videoInfo['data']['assets']) || !is_array($videoInfo['data']['assets'])) {
            throw new Exception("No video files found");
        }
        
        $targetFile = null;
        foreach ($videoInfo['data']['assets'] as $file) {
            if (isset($file['quality']) && $file['quality'] === $quality) {
                $targetFile = $file;
                break;
            }
        }
        
        if (!$targetFile) {
            $qualities = ['360p', '480p', '720p', '1080p', 'original'];
            foreach ($qualities as $q) {
                foreach ($videoInfo['data']['assets'] as $file) {
                    if (isset($file['quality']) && $file['quality'] === $q) {
                        $targetFile = $file;
                        break 2;
                    }
                }
            }
        }
        
        if (!$targetFile) {
            throw new Exception("No suitable video file found");
        }
        
        return [
            'url' => $targetFile['url'],
            'quality' => $targetFile['quality'],
            'size' => $targetFile['file_size'] ?? null,
            'duration' => $videoInfo['data']['duration'] ?? null,
            'title' => $videoInfo['data']['title'] ?? 'Untitled'
        ];
    }
    
    /**
     * Скачивает видео с повторными попытками и улучшенным контролем
     */
    public function downloadVideoWithRetry($downloadUrl, $outputPath, $maxRetries = 3) {
        // Убеждаемся, что папка существует
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        echo "Скачивание с URL: " . substr($downloadUrl, 0, 100) . "...\n";
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            echo " Попытка скачивания $attempt/$maxRetries...\n";
            
            try {
                // Пробуем разные методы скачивания
                if ($attempt === 1) {
                    // Метод 1: Улучшенный cURL с большими таймаутами
                    $result = $this->downloadWithCurlImproved($downloadUrl, $outputPath);
                } elseif ($attempt === 2) {
                    // Метод 2: cURL с альтернативными настройками
                    $result = $this->downloadWithCurlAlternative($downloadUrl, $outputPath);
                } else {
                    // Метод 3: Используем wget/curl через shell
                    $result = $this->downloadWithShell($downloadUrl, $outputPath);
                }
                
                if ($result) {
                    $fileSize = filesize($outputPath);
                    if ($fileSize > 1024) { // Больше 1KB
                        echo "✅ Скачивание успешно: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
                        return $fileSize;
                    } else {
                        throw new Exception("Downloaded file too small: " . $fileSize . " bytes");
                    }
                }
                
            } catch (Exception $e) {
                echo "❌ Попытка $attempt не удалась: " . $e->getMessage() . "\n";
                
                if ($attempt < $maxRetries) {
                    echo "⏳ Ожидание 5 секунд перед повторной попыткой...\n";
                    sleep(5);
                } else {
                    throw new Exception("Все попытки скачивания исчерпаны: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Улучшенный метод скачивания через cURL с большими таймаутами
     */
    private function downloadWithCurlImproved($downloadUrl, $outputPath) {
        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600, // 10 минут
            CURLOPT_CONNECTTIMEOUT => 60, // 1 минута на подключение
            CURLOPT_FILE => fopen($outputPath, 'w'),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 10,
            CURLOPT_TCP_KEEPINTVL => 1,
            CURLOPT_LOW_SPEED_LIMIT => 1024, // Минимум 1KB/сек
            CURLOPT_LOW_SPEED_TIME => 300, // В течение 5 минут
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Connection: keep-alive',
                'Cache-Control: no-cache'
            ]
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $downloadedSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Improved Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Download failed: HTTP " . $httpCode);
        }
        
        // Проверяем, что файл скачался полностью
        if (file_exists($outputPath)) {
            $fileSize = filesize($outputPath);
            if ($fileSize < 1024) {
                throw new Exception("Downloaded file too small: " . $fileSize . " bytes");
            }
            echo "Скачано: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
        }
        
        return $result;
    }
    
    /**
     * Альтернативный метод скачивания через cURL
     */
    private function downloadWithCurlAlternative($downloadUrl, $outputPath) {
        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FILE => fopen($outputPath, 'w'),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_USERAGENT => 'curl/7.68.0',
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Connection: keep-alive'
            ]
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Alternative Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Download failed: HTTP " . $httpCode);
        }
        
        return $result;
    }
    
    /**
     * Метод скачивания через shell (wget/curl)
     */
    private function downloadWithShell($downloadUrl, $outputPath) {
        // Пробуем wget с повторными попытками
        $wgetCommand = "wget --timeout=600 --tries=3 --continue -O " . escapeshellarg($outputPath) . " " . escapeshellarg($downloadUrl) . " 2>&1";
        $wgetOutput = [];
        $wgetReturnCode = 0;
        exec($wgetCommand, $wgetOutput, $wgetReturnCode);
        
        if ($wgetReturnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 1024) {
            return true;
        }
        
        // Если wget не сработал, пробуем curl через shell
        $curlCommand = "curl -L --connect-timeout 60 --max-time 600 --retry 3 --retry-delay 5 -o " . escapeshellarg($outputPath) . " " . escapeshellarg($downloadUrl) . " 2>&1";
        $curlOutput = [];
        $curlReturnCode = 0;
        exec($curlCommand, $curlOutput, $curlReturnCode);
        
        if ($curlReturnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 1024) {
            return true;
        }
        
        throw new Exception("Shell download failed. Wget: " . implode("\n", $wgetOutput) . " | Curl: " . implode("\n", $curlOutput));
    }
}
?>
<?php
// /game/persona/whisper_api.php
// Модуль для работы с OpenAI Whisper API

require_once __DIR__ . '/utils.php';

class WhisperAPI {
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Извлекает аудио из видео файла
     */
    public function extractAudio($videoFile) {
        if (!file_exists($videoFile)) {
            throw new Exception("Video file not found: " . $videoFile);
        }
        
        $audioFile = str_replace('.mp4', '.wav', $videoFile);
        
        // Команда ffmpeg для извлечения аудио
        $command = "ffmpeg -i " . escapeshellarg($videoFile) . " -vn -acodec pcm_s16le -ar 16000 -ac 1 " . escapeshellarg($audioFile) . " 2>&1";
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Ошибка извлечения аудио из видео. Команда не удалась (код $returnCode): " . implode("\n", $output));
        }
        
        if (!file_exists($audioFile)) {
            throw new Exception("Аудио файл не был создан");
        }
        
        return $audioFile;
    }
    
    /**
     * Сжимает аудио файл для Whisper API (агрессивное сжатие)
     */
    public function compressAudio($audioFile) {
        if (!file_exists($audioFile)) {
            throw new Exception("Audio file not found: " . $audioFile);
        }
        
        $compressedFile = str_replace('.wav', '_compressed.wav', $audioFile);
        
        // Агрессивное сжатие: понижаем частоту дискретизации до 8kHz и используем более низкий битрейт
        $command = "ffmpeg -i " . escapeshellarg($audioFile) . " -ar 8000 -ac 1 -acodec pcm_s16le -af 'volume=0.8,highpass=f=80,lowpass=f=3400' " . escapeshellarg($compressedFile) . " 2>&1";
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Ошибка сжатия аудио: " . implode("\n", $output));
        }
        
        if (!file_exists($compressedFile)) {
            throw new Exception("Сжатый аудио файл не был создан");
        }
        
        $originalSize = filesize($audioFile);
        $compressedSize = filesize($compressedFile);
        $compressionRatio = round((1 - $compressedSize / $originalSize) * 100, 1);
        
        echo "Сжатие: " . round($originalSize / 1024 / 1024, 2) . " MB → " . round($compressedSize / 1024 / 1024, 2) . " MB (-{$compressionRatio}%)\n";
        
        return $compressedFile;
    }
    
    /**
     * Разбивает длинный аудио файл на части для обработки
     */
    public function splitAudio($audioFile, $maxDurationMinutes = null) {
        if (!file_exists($audioFile)) {
            throw new Exception("Audio file not found: " . $audioFile);
        }
        
        // Получаем длительность файла
        $command = "ffprobe -v quiet -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($audioFile);
        $duration = floatval(trim(shell_exec($command)));
        
        // Динамически определяем максимальную длительность части
        // Цель: каждая часть должна быть меньше 25MB
        $fileSize = filesize($audioFile);
        $estimatedSizePerMinute = $fileSize / ($duration / 60); // размер в MB на минуту
        $maxSizePerPart = 20 * 1024 * 1024; // 20MB на часть (с запасом)
        $maxDurationMinutes = floor(($maxSizePerPart / $estimatedSizePerMinute) * 60); // в секундах
        
        if ($maxDurationMinutes === null) {
            $maxDurationMinutes = 15 * 60; // 15 минут по умолчанию
        }
        
        if ($duration <= $maxDurationMinutes) {
            return [$audioFile]; // Файл не нужно разбивать
        }
        
        echo "Файл слишком длинный ({$duration} сек), разбиваем на части по {$maxDurationMinutes} сек...\n";
        
        $parts = [];
        $partDuration = $maxDurationMinutes; // в секундах
        $partNumber = 1;
        
        for ($start = 0; $start < $duration; $start += $partDuration) {
            $end = min($start + $partDuration, $duration);
            $partFile = str_replace('.wav', "_part{$partNumber}.wav", $audioFile);
            
            $command = "ffmpeg -i " . escapeshellarg($audioFile) . " -ss $start -t " . ($end - $start) . " -c copy " . escapeshellarg($partFile) . " 2>&1";
            
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($partFile)) {
                $parts[] = $partFile;
                $partSize = filesize($partFile);
                echo "Создана часть $partNumber: " . basename($partFile) . " (" . round($partSize / 1024 / 1024, 2) . " MB)\n";
            } else {
                throw new Exception("Ошибка создания части $partNumber: " . implode("\n", $output));
            }
            
            $partNumber++;
        }
        
        return $parts;
    }
    
    /**
     * Транскрибирует аудио/видео файл через Whisper
     */
    public function transcribe($filePath, $language = 'ru') {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . $filePath);
        }
        
        $originalFilePath = $filePath;
        $tempAudioFile = null;
        
        // Если это видео файл, извлекаем аудио
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'mp4') {
            $tempAudioFile = $this->extractAudio($filePath);
            $filePath = $tempAudioFile;
        }
        
        // Проверяем размер файла (Whisper имеет лимит 25MB)
        $fileSize = filesize($filePath);
        echo "Размер аудио файла: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
        
        if ($fileSize > 25 * 1024 * 1024) {
            echo "Файл слишком большой, сжимаем...\n";
            $compressedFile = $this->compressAudio($filePath);
            
            // Проверяем размер после сжатия
            $compressedSize = filesize($compressedFile);
            if ($compressedSize > 25 * 1024 * 1024) {
                echo "Файл все еще слишком большой, разбиваем на части...\n";
                $parts = $this->splitAudio($compressedFile);
                
                $fullTranscript = "";
                foreach ($parts as $i => $part) {
                    echo "Обрабатываем часть " . ($i + 1) . " из " . count($parts) . "...\n";
                    $partTranscript = $this->transcribePart($part);
                    $fullTranscript .= $partTranscript . " ";
                    
                    // Удаляем временную часть
                    if (file_exists($part)) {
                        unlink($part);
                    }
                }
                
                // Удаляем сжатый файл
                if (file_exists($compressedFile)) {
                    unlink($compressedFile);
                }
                
                return trim($fullTranscript);
            } else {
                $filePath = $compressedFile;
                $tempAudioFile = $compressedFile;
            }
        }
        
        return $this->transcribePart($filePath, $tempAudioFile);
    }
    
    /**
     * Транскрибирует одну часть аудио файла
     */
    private function transcribePart($filePath, $tempAudioFile = null) {
        $url = $this->baseUrl . '/audio/transcriptions';
        
        $ch = curl_init($url);
        
        $postData = [
            'file' => new CURLFile($filePath),
            'model' => 'whisper-1',
            'language' => 'ru',
            'response_format' => 'text'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600, // 10 минут для больших файлов
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        // Удаляем временные файлы
        if ($tempAudioFile && file_exists($tempAudioFile) && $tempAudioFile !== $filePath) {
            unlink($tempAudioFile);
        }
        
        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }
        
        if ($httpCode !== 200) {
            $errorMessage = "Whisper API Error: HTTP " . $httpCode;
            if ($response) {
                $errorMessage .= " - " . $response;
            }
            if ($contentType) {
                $errorMessage .= " (Content-Type: " . $contentType . ")";
            }
            throw new Exception($errorMessage);
        }
        
        return trim($response);
    }
    
    /**
     * Получает статус API (проверка доступности)
     */
    public function checkStatus() {
        $url = $this->baseUrl . '/models';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
}
?>

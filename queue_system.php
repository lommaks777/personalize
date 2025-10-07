<?php
// queue_system.php - Система очереди для обработки уроков
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/utils.php';

// Проверяем, запущен ли скрипт из командной строки
$isCLI = php_sapi_name() === 'cli';

// Файлы для хранения очереди и статуса
$queueFile = __DIR__ . '/store/processing_queue.json';
$statusFile = __DIR__ . '/store/processing_status.json';

// Создаем папку store, если её нет
if (!is_dir(__DIR__ . '/store')) {
    mkdir(__DIR__ . '/store', 0755, true);
}

// Если это веб-запрос, устанавливаем CORS заголовки
if (!$isCLI) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }
}

// Функция для загрузки очереди
function getProcessingQueue() {
    global $queueFile;
    
    if (!file_exists($queueFile)) {
        return [];
    }
    
    $content = file_get_contents($queueFile);
    if (empty($content)) {
        return [];
    }
    
    $queue = json_decode($content, true);
    return is_array($queue) ? $queue : [];
}

// Функция для сохранения очереди
function saveProcessingQueue($queue) {
    global $queueFile;
    
    $result = file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $result !== false;
}

// Функция для загрузки статуса
function getProcessingStatus() {
    global $statusFile;
    
    if (!file_exists($statusFile)) {
        return [];
    }
    
    $content = file_get_contents($statusFile);
    if (empty($content)) {
        return [];
    }
    
    $status = json_decode($content, true);
    return is_array($status) ? $status : [];
}

// Функция для сохранения статуса
function saveProcessingStatus($status) {
    global $statusFile;
    
    $result = file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $result !== false;
}

// Функция для добавления урока в очередь
function addLessonToQueue($url, $lessonNumber, $courseName) {
    $queue = getProcessingQueue();
    
    // Проверяем на дубликаты
    foreach ($queue as $item) {
        if ($item['url'] === $url || 
            ($item['lesson_number'] == $lessonNumber && $item['course'] === $courseName)) {
            return [
                'success' => false,
                'message' => 'Урок уже добавлен в очередь или уже обработан'
            ];
        }
    }
    
    // Добавляем новый урок (сохраняем номер как есть, не приводим к int)
    $newLesson = [
        'id' => uniqid('lesson_', true),
        'url' => $url,
        'lesson_number' => $lessonNumber, // Оставляем как есть (может быть дробным)
        'course' => $courseName,
        'added_at' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ];
    
    $queue[] = $newLesson;
    
    if (saveProcessingQueue($queue)) {
        return [
            'success' => true,
            'message' => 'Урок добавлен в очередь',
            'lesson' => $newLesson
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Ошибка при сохранении очереди'
        ];
    }
}

// Функция для получения следующего урока из очереди
function getNextLessonFromQueue() {
    $queue = getProcessingQueue();
    
    foreach ($queue as $index => $lesson) {
        if ($lesson['status'] === 'pending') {
            return [
                'lesson' => $lesson,
                'index' => $index
            ];
        }
    }
    
    return null;
}

// Функция для обновления статуса урока
function updateLessonStatus($lessonId, $status, $details = null) {
    $queue = getProcessingQueue();
    $statusData = getProcessingStatus();
    
    // Обновляем очередь
    foreach ($queue as &$lesson) {
        if ($lesson['id'] === $lessonId) {
            $lesson['status'] = $status;
            $lesson['updated_at'] = date('Y-m-d H:i:s');
            if ($details) {
                $lesson['details'] = $details;
            }
            break;
        }
    }
    
    // Обновляем статус
    $statusData[$lessonId] = [
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s'),
        'details' => $details
    ];
    
    saveProcessingQueue($queue);
    saveProcessingStatus($statusData);
}

// Функция для очистки очереди
function clearQueue() {
    saveProcessingQueue([]);
    saveProcessingStatus([]);
}

// Функция для получения статистики
function getQueueStats() {
    $queue = getProcessingQueue();
    $status = getProcessingStatus();
    
    $stats = [
        'total' => count($queue),
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0
    ];
    
    foreach ($queue as $lesson) {
        switch ($lesson['status']) {
            case 'pending':
                $stats['pending']++;
                break;
            case 'processing':
                $stats['processing']++;
                break;
            case 'completed':
                $stats['completed']++;
                break;
            case 'failed':
                $stats['failed']++;
                break;
        }
    }
    
    return $stats;
}

// Если это веб-запрос, обрабатываем API
if (!$isCLI) {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'queue':
                        echo json_encode(getProcessingQueue());
                        break;
                    case 'status':
                        echo json_encode(getProcessingStatus());
                        break;
                    case 'stats':
                        echo json_encode(getQueueStats());
                        break;
                    default:
                        echo json_encode(['error' => 'Неизвестное действие']);
                }
            } else {
                echo json_encode(getProcessingQueue());
            }
            break;
            
        case 'POST':
            if (isset($input['action'])) {
                switch ($input['action']) {
                    case 'add':
                        if (isset($input['url']) && isset($input['lesson_number']) && isset($input['course'])) {
                            $result = addLessonToQueue($input['url'], $input['lesson_number'], $input['course']);
                            echo json_encode($result);
                        } else {
                            echo json_encode(['error' => 'Не указаны обязательные параметры']);
                        }
                        break;
                    case 'clear':
                        clearQueue();
                        echo json_encode(['success' => true, 'message' => 'Очередь очищена']);
                        break;
                    default:
                        echo json_encode(['error' => 'Неизвестное действие']);
                }
            } else {
                echo json_encode(['error' => 'Не указано действие']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Метод не поддерживается']);
    }
}
?>

<?php
// process_survey.php - Обработка анкеты пользователя
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Увеличиваем время выполнения
set_time_limit(300); // 5 минут
ini_set('max_execution_time', 300);

// Отключаем отображение ошибок в браузере
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once __DIR__ . '/utils.php';
    require_once __DIR__ . '/user_profiles.php';
    require_once __DIR__ . '/personalize_lesson_descriptions.php';
    
    // Получаем данные из POST запроса
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception("Не удалось получить данные анкеты");
    }
    
    // Валидация обязательных полей
    if (empty($data['course'])) {
        throw new Exception("Поле 'course' обязательно для заполнения");
    }
    
    if (empty($data['uid'])) {
        throw new Exception("Поле 'uid' обязательно для заполнения");
    }
    
    // Создаем профиль пользователя
    $userId = $data['uid'];
    $userProfile = [
        'user_id' => $userId,
        'name' => $data['real_name'] ?? 'Пользователь',
        'course' => $data['course'],
        'created_at' => date('Y-m-d H:i:s'),
        
        // Данные из анкеты
        'experience' => 'self_taught', // По умолчанию
        'motivation' => $data['motivation'] ?? [],
        'motivation_other' => $data['motivation_other'] ?? '',
        'target_clients' => $data['target_clients'] ?? '',
        'skills_wanted' => $data['skills_wanted'] ?? '',
        'fears' => $data['fears'] ?? [],
        'fears_other' => $data['fears_other'] ?? '',
        'wow_result' => $data['wow_result'] ?? '',
        'practice_model' => $data['practice_model'] ?? '',
        
        // Дополнительные поля для персонализации
        'age' => 'Не указан',
        'massage_experience' => 'self_taught',
        'skill_level' => 'Начинающий',
        'goals' => $data['skills_wanted'] ?? 'Изучить основы массажа',
        'problems' => implode(', ', $data['fears'] ?? []),
        'available_time' => '20-30 минут в день',
        'preferences' => $data['wow_result'] ?? ''
    ];
    
    // Сохраняем профиль пользователя
    createUserProfile($userId, $userProfile);
    
    // Проверяем, что курс существует
    $courseDir = __DIR__ . '/store/' . $data['course'];
    if (!is_dir($courseDir)) {
        throw new Exception("Курс '{$data['course']}' не найден");
    }
    
    // Проверяем, что есть описания уроков
    $descriptionFiles = glob($courseDir . '/*-final.json');
    if (empty($descriptionFiles)) {
        throw new Exception("Описания уроков для курса '{$data['course']}' не найдены. Сначала запустите генерацию описаний.");
    }
    
    // Создаем папку для персонализированных описаний
    $personalizedDir = $courseDir . '/personalize/' . $userId;
    if (!is_dir($personalizedDir)) {
        mkdir($personalizedDir, 0755, true);
    }
    
    // Персонализируем описания уроков
    $processed = 0;
    $errors = 0;
    
    // Сортируем файлы по номеру урока
    usort($descriptionFiles, function($a, $b) {
        $aNum = (int)preg_replace('/\D/', '', basename($a));
        $bNum = (int)preg_replace('/\D/', '', basename($b));
        return $aNum - $bNum;
    });
    
    foreach ($descriptionFiles as $descriptionFile) {
        try {
            $filename = basename($descriptionFile);
            
            // Извлекаем номер урока и ID видео
            if (preg_match('/(\d+)-(.+)-final\.json/', $filename, $matches)) {
                $lessonNumber = $matches[1];
                $videoId = $matches[2];
            } else {
                continue; // Пропускаем файлы с неправильным форматом
            }
            
            // Читаем исходное описание
            $lessonDescription = json_decode(file_get_contents($descriptionFile), true);
            if (!$lessonDescription) {
                continue;
            }
            
            // Персонализируем описание
            $personalizedContent = personalizeDescription($lessonDescription, $userProfile, $personalizationPrompt);
            
            // Парсим JSON ответ
            $personalizedData = json_decode($personalizedContent, true);
            if (!$personalizedData) {
                // Пытаемся исправить JSON
                $personalizedContent = fixJsonResponse($personalizedContent);
                $personalizedData = json_decode($personalizedContent, true);
                
                if (!$personalizedData) {
                    throw new Exception("Не удалось распарсить персонализированный JSON для урока $lessonNumber");
                }
            }
            
            // Создаем имя файла: номерурока-idvideo-idпользователя.json
            $personalizedFilename = $lessonNumber . '-' . $videoId . '-' . $userId . '.json';
            $personalizedFile = $personalizedDir . '/' . $personalizedFilename;
            
            // Сохраняем персонализированное описание
            file_put_contents($personalizedFile, json_encode($personalizedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $processed++;
            
        } catch (Exception $e) {
            $errors++;
            error_log("Ошибка персонализации урока: " . $e->getMessage());
        }
    }
    
    if ($processed === 0) {
        throw new Exception("Не удалось обработать ни одного урока");
    }
    
    // Возвращаем упрощенный результат
    echo json_encode([
        'success' => true,
        'message' => 'Персонализированный курс успешно создан'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error: ' . $e->getMessage()
    ]);
}

// Функция для исправления JSON ответа от LLM
function fixJsonResponse($content) {
    // Убираем возможные markdown блоки
    $content = preg_replace('/```json\s*/', '', $content);
    $content = preg_replace('/```\s*$/', '', $content);
    
    // Убираем лишние пробелы и переносы строк
    $content = trim($content);
    
    // Пытаемся найти JSON в тексте
    if (preg_match('/\{.*\}/s', $content, $matches)) {
        return $matches[0];
    }
    
    return $content;
}
?>

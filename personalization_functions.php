<?php
// personalization_functions.php - Функции персонализации
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/user_profiles.php';

// Промт для персонализации
$personalizationPrompt = 'Ты - эксперт по персонализации контента. Твоя задача - адаптировать описание урока по массажу под конкретного пользователя.

ВАЖНО: Ответь ТОЛЬКО валидным JSON в том же формате, что и исходное описание. Не добавляй никаких комментариев, объяснений или markdown разметки.

Требования к персонализации:
- Используй данные из анкеты пользователя для адаптации описания
- Сохрани структуру JSON и все поля
- Используй обращение "ты" и персональный подход
- Адаптируй сложность под уровень пользователя
- Учитывай цели, проблемы и опыт пользователя

Данные пользователя:
- Опыт: {experience}
- Мотивация: {motivation}
- Целевые клиенты: {target_clients}
- Желаемые навыки: {skills_wanted}
- Страхи: {fears}
- Желаемый результат: {wow_result}
- Модель для практики: {practice_model}

Поля для адаптации:
- "summary_short": Адаптируй под уровень и цели
- "prev_lessons": Оставь как есть
- "why_watch": Персонализируй под мотивацию и страхи
- "quick_action": Адаптируй под уровень и модель, но БЕЗ упоминания конкретных моделей практики
- "social_share": Сделай личным и релевантным
- "homework_20m": Адаптируй под модель и навыки, но БЕЗ упоминания конкретных моделей практики

ВАЖНЫЕ ПРАВИЛА для quick_action и homework_20m:
- НЕ упоминай конкретные модели практики (кошки, собаки, президенты, семья, друзья и т.д.)
- Фокусируйся на ТЕХНИКЕ и ПРИНЦИПАХ массажа
- Давай универсальные советы, которые применимы к любой модели
- Используй общие формулировки: "на практике", "при работе с клиентами", "в реальных условиях"
- Адаптируй сложность и детализацию под уровень пользователя

ОТВЕТЬ ТОЛЬКО JSON БЕЗ ДОПОЛНИТЕЛЬНОГО ТЕКСТА:';

function personalizeDescription($lessonDescription, $userProfile, $prompt) {
    $conf = cfg();
    $apiKey = $conf['apis']['openai']['api_key'];
    
    if (!$apiKey) {
        throw new Exception('OpenAI API ключ не найден');
    }
    
    // Подготавливаем данные пользователя для промпта
    $userData = [
        'experience' => $userProfile['experience'] ?? 'beginner',
        'motivation' => $userProfile['goals'] ?? ['general_learning'],
        'target_clients' => $userProfile['preferences']['focus_areas'] ?? ['general'],
        'skills_wanted' => $userProfile['goals'] ?? ['general_skills'],
        'fears' => $userProfile['fears'] ?? ['making_mistakes'],
        'wow_result' => $userProfile['goals'] ?? ['improved_skills'],
        'practice_model' => $userProfile['preferences']['practice_model'] ?? 'general'
    ];
    
    // Заменяем плейсхолдеры в промпте
    $fullPrompt = $prompt;
    foreach ($userData as $key => $value) {
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        $fullPrompt = str_replace('{' . $key . '}', $value, $fullPrompt);
    }
    
    // Добавляем оригинальное описание
    $fullPrompt .= "\n\nИсходное описание урока:\n" . json_encode($lessonDescription, JSON_UNESCAPED_UNICODE);
    
    // Вызываем OpenAI API
    $response = callOpenAI($apiKey, $fullPrompt);
    
    if (!$response) {
        throw new Exception('Ошибка при вызове OpenAI API');
    }
    
    // Парсим JSON ответ
    $personalizedDescription = json_decode($response, true);
    
    if (!$personalizedDescription) {
        throw new Exception('Не удалось распарсить персонализированное описание');
    }
    
    return $personalizedDescription;
}

function callOpenAI($apiKey, $prompt) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 2000
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("OpenAI API вернул код $httpCode: $response");
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Неожиданный формат ответа от OpenAI API');
    }
    
    return $result['choices'][0]['message']['content'];
}
?>

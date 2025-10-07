<?php
// personalize_lesson.php - Персонализация отдельного урока
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/user_profiles.php';
require_once __DIR__ . '/personalization_functions.php';

// Включаем CORS
cors();

$courseName = $_GET['course'] ?? 'Массаж ШВЗ';
$lessonNumber = $_GET['lesson'] ?? '1';
$userId = $_GET['user'] ?? 'guest_' . time();

// Если параметры не переданы, показываем форму выбора
if (!$courseName || !$lessonNumber) {
    $courseName = 'Массаж ШВЗ';
    $lessonNumber = '1';
}

$courseDir = __DIR__ . '/store/' . $courseName;

// Загружаем оригинальное описание урока
$descriptionFile = $courseDir . "/descriptions/$lessonNumber-*-final.json";
$descriptionFiles = glob($descriptionFile);

if (empty($descriptionFiles)) {
    http_response_code(404);
    json_out(['error' => 'Описание урока не найдено']);
    exit;
}

$originalDescription = json_decode(file_get_contents($descriptionFiles[0]), true);

// Загружаем или создаем профиль пользователя
$userProfile = loadUserProfile($userId);
if (!$userProfile) {
    // Создаем базовый профиль для гостя
    $userProfile = [
        'name' => 'Гость',
        'experience' => 'beginner',
        'goals' => ['general_learning'],
        'preferences' => [
            'session_duration' => '30_minutes',
            'intensity' => 'gentle',
            'focus_areas' => ['general']
        ],
        'created_at' => date('Y-m-d H:i:s')
    ];
    createUserProfile($userId, $userProfile);
}

// Если это AJAX запрос на персонализацию
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $personalizedDescription = personalizeDescription(
            $originalDescription,
            $userProfile,
            "Персонализируй это описание урока массажа для пользователя с опытом {$userProfile['experience']} и целями: " . implode(', ', $userProfile['goals'])
        );
        
        // Сохраняем персонализированное описание
        $personalizeDir = $courseDir . "/personalize/$userId";
        if (!is_dir($personalizeDir)) {
            mkdir($personalizeDir, 0755, true);
        }
        
        $personalizedFile = $personalizeDir . "/$lessonNumber-" . time() . "-$userId.json";
        file_put_contents($personalizedFile, json_encode($personalizedDescription, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        json_out([
            'success' => true,
            'personalized' => $personalizedDescription,
            'file' => $personalizedFile
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        json_out(['error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Персонализация урока <?= $lessonNumber ?> - <?= htmlspecialchars($courseName) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .description-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .description-card h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .description-card.original h3 {
            color: #666;
        }
        
        .description-card.personalized h3 {
            color: #667eea;
        }
        
        .field {
            margin-bottom: 20px;
        }
        
        .field-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .field-value {
            color: #666;
            line-height: 1.5;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .actions {
            text-align: center;
            margin: 40px 0;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 0 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .loading {
            display: none;
            text-align: center;
            color: white;
            margin: 20px 0;
        }
        
        .spinner {
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 3px solid white;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .user-info {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            color: white;
        }
        
        .user-info h3 {
            margin-bottom: 10px;
        }
        
        .user-info p {
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .comparison {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Персонализация урока <?= $lessonNumber ?></h1>
            <p>Адаптируем описание под ваш уровень и цели</p>
        </div>
        
        <div class="user-info">
            <h3>👤 Ваш профиль</h3>
            <p>Опыт: <?= htmlspecialchars($userProfile['experience']) ?> | Цели: <?= htmlspecialchars(implode(', ', $userProfile['goals'])) ?></p>
        </div>
        
        <div class="comparison">
            <div class="description-card original">
                <h3>📋 Оригинальное описание</h3>
                <div class="field">
                    <span class="field-label">Краткое резюме:</span>
                    <div class="field-value"><?= htmlspecialchars($originalDescription['summary_short']) ?></div>
                </div>
                <div class="field">
                    <span class="field-label">Зачем смотреть:</span>
                    <div class="field-value"><?= htmlspecialchars($originalDescription['why_watch']) ?></div>
                </div>
                <div class="field">
                    <span class="field-label">Быстрое действие:</span>
                    <div class="field-value"><?= htmlspecialchars($originalDescription['quick_action']) ?></div>
                </div>
                <div class="field">
                    <span class="field-label">Домашнее задание:</span>
                    <div class="field-value"><?= htmlspecialchars($originalDescription['homework_20m']) ?></div>
                </div>
            </div>
            
            <div class="description-card personalized" id="personalized-card">
                <h3>🎯 Персонализированное описание</h3>
                <div id="personalized-content">
                    <p style="text-align: center; color: #999; padding: 40px;">
                        Нажмите кнопку "Персонализировать" для создания адаптированного описания
                    </p>
                </div>
            </div>
        </div>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Создаем персонализированное описание...</p>
        </div>
        
        <div class="actions">
            <button class="btn btn-primary" onclick="personalizeLesson()" id="personalize-btn">
                🎯 Персонализировать описание
            </button>
            <a href="view_shvz_course.php" class="btn btn-secondary">
                ← Вернуться к курсу
            </a>
        </div>
    </div>
    
    <script>
        function personalizeLesson() {
            const loading = document.getElementById('loading');
            const personalizeBtn = document.getElementById('personalize-btn');
            const personalizedContent = document.getElementById('personalized-content');
            
            // Показываем загрузку
            loading.style.display = 'block';
            personalizeBtn.disabled = true;
            personalizeBtn.textContent = 'Персонализация...';
            
            // Отправляем запрос на персонализацию
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Показываем персонализированное описание
                    personalizedContent.innerHTML = `
                        <div class="field">
                            <span class="field-label">Краткое резюме:</span>
                            <div class="field-value">${data.personalized.summary_short}</div>
                        </div>
                        <div class="field">
                            <span class="field-label">Зачем смотреть:</span>
                            <div class="field-value">${data.personalized.why_watch}</div>
                        </div>
                        <div class="field">
                            <span class="field-label">Быстрое действие:</span>
                            <div class="field-value">${data.personalized.quick_action}</div>
                        </div>
                        <div class="field">
                            <span class="field-label">Домашнее задание:</span>
                            <div class="field-value">${data.personalized.homework_20m}</div>
                        </div>
                    `;
                } else {
                    alert('Ошибка персонализации: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при персонализации');
            })
            .finally(() => {
                // Скрываем загрузку
                loading.style.display = 'none';
                personalizeBtn.disabled = false;
                personalizeBtn.textContent = '🎯 Персонализировать описание';
            });
        }
    </script>
</body>
</html>

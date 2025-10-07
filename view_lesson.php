<?php
// view_lesson.php - Отображение одного урока (упрощенная версия)
header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/user_profiles.php';

// Получаем параметры
$uid = $_GET['uid'] ?? '';
$course = $_GET['course'] ?? '';
$lesson = (int)($_GET['lesson'] ?? 0);

if (empty($uid) || empty($course) || $lesson <= 0) {
    die('Ошибка: Не указан ID пользователя, курс или номер урока');
}

// Загружаем профиль пользователя
$userProfile = loadUserProfile($uid);
if (!$userProfile) {
    die('Ошибка: Профиль пользователя не найден');
}

// Путь к файлу урока
$personalizedDir = __DIR__ . '/store/' . $course . '/personalize/' . $uid;
$lessonFiles = glob($personalizedDir . '/*.json');

if (empty($lessonFiles)) {
    die('Ошибка: Уроки не найдены');
}

// Сортируем файлы по номеру урока
usort($lessonFiles, function($a, $b) {
    $aNum = (int)preg_replace('/\D/', '', basename($a));
    $bNum = (int)preg_replace('/\D/', '', basename($b));
    return $aNum - $bNum;
});

// Получаем нужный урок
if ($lesson > count($lessonFiles)) {
    die('Ошибка: Урок не найден');
}

$lessonFile = $lessonFiles[$lesson - 1];
$lessonData = json_decode(file_get_contents($lessonFile), true);

if (!$lessonData) {
    die('Ошибка: Не удалось загрузить данные урока');
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Урок <?= $lesson ?> - <?= htmlspecialchars($userProfile['name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            padding: 20px;
        }

        .lesson-container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .lesson-content {
            padding: 40px;
        }

        .content-section {
            margin-bottom: 30px;
            padding: 25px;
            background: #f8fafc;
            border-radius: 15px;
            border-left: 5px solid #667eea;
            transition: all 0.3s ease;
        }

        .content-section:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .content-section h3 {
            color: #2d3748;
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .content-section p {
            color: #4a5568;
            line-height: 1.7;
            font-size: 1rem;
        }

        .icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .lesson-content {
                padding: 20px;
            }
            
            .content-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="lesson-container">
        <div class="lesson-content">
            <div class="content-section">
                <p><?= htmlspecialchars($lessonData['summary_short'] ?? 'Описание не найдено') ?></p>
            </div>

            <div class="content-section">
                <h3>
                    <span class="icon">🎯</span>
                    Зачем смотреть этот урок
                </h3>
                <p><?= htmlspecialchars($lessonData['why_watch'] ?? 'Информация не найдена') ?></p>
            </div>

            <div class="content-section">
                <h3>
                    <span class="icon">⚡</span>
                    Быстрое действие
                </h3>
                <p><?= htmlspecialchars($lessonData['quick_action'] ?? 'Действие не указано') ?></p>
            </div>
        </div>
    </div>
</body>
</html>

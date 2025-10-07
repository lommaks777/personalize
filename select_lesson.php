<?php
// select_lesson.php - Выбор урока для персонализации
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/utils.php';

$courseName = 'Массаж ШВЗ';
$courseDir = __DIR__ . '/store/' . $courseName;

// Загружаем данные курса
$courseFile = $courseDir . '/course.json';
if (!file_exists($courseFile)) {
    die('Курс не найден');
}

$courseData = json_decode(file_get_contents($courseFile), true);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выбор урока для персонализации</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .lesson-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .lesson-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        
        .lesson-number {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 0 auto 15px;
        }
        
        .lesson-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .lesson-description {
            color: #666;
            font-size: 0.9rem;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #666;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .actions {
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Персонализация урока</h1>
            <p>Выберите урок для создания персонального описания</p>
        </div>
        
        <div class="lessons-grid">
            <?php for ($i = 1; $i <= $courseData['total_lessons']; $i++): ?>
            <div class="lesson-card" onclick="selectLesson(<?= $i ?>)">
                <div class="lesson-number"><?= $i ?></div>
                <div class="lesson-title">Урок <?= $i ?></div>
                <div class="lesson-description">Массаж ШВЗ</div>
            </div>
            <?php endfor; ?>
        </div>
        
        <div class="actions">
            <a href="view_shvz_course.php" class="btn btn-secondary">
                ← Вернуться к курсу
            </a>
        </div>
    </div>
    
    <script>
        function selectLesson(lessonNumber) {
            const userId = 'user_' + Date.now();
            window.location.href = `personalize_lesson.php?course=<?= urlencode($courseName) ?>&lesson=${lessonNumber}&user=${userId}`;
        }
    </script>
</body>
</html>

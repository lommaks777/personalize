<?php
// view_shvz_course.php - Веб-интерфейс для курса ШВЗ
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/utils.php';

// Включаем CORS
cors();

$courseName = 'Массаж ШВЗ';
$courseDir = __DIR__ . '/store/' . $courseName;

// Загружаем данные курса
$courseFile = $courseDir . '/course.json';
if (!file_exists($courseFile)) {
    http_response_code(404);
    json_out(['error' => 'Курс не найден']);
    exit;
}

$courseData = json_decode(file_get_contents($courseFile), true);

// Загружаем данные уроков
$lessons = [];
foreach ($courseData['lessons'] as $lessonNumber) {
    $lessonDir = $courseDir . '/lessons/' . sprintf('%02d', $lessonNumber);
    $lessonMetaFile = $lessonDir . '/lesson.json';
    
    if (file_exists($lessonMetaFile)) {
        $lessonData = json_decode(file_get_contents($lessonMetaFile), true);
        $lessons[] = $lessonData;
    }
}

// Если это AJAX запрос, возвращаем JSON
if (isset($_GET['ajax'])) {
    json_out([
        'course' => $courseData,
        'lessons' => $lessons
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($courseData['name']) ?></title>
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
            max-width: 1200px;
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
        
        .course-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            color: white;
            min-width: 150px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        
        .lesson-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .lesson-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .lesson-number {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .lesson-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .lesson-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .lesson-files {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .file-badge {
            background: #f0f0f0;
            color: #666;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .file-badge.video {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .file-badge.audio {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .file-badge.transcript {
            background: #e8f5e8;
            color: #388e3c;
        }
        
        .file-badge.description {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .lesson-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
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
        
        .personalization-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin: 40px 0;
            color: white;
            text-align: center;
        }
        
        .personalization-section h2 {
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        
        .personalization-section p {
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .course-stats {
                gap: 15px;
            }
            
            .lessons-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= htmlspecialchars($courseData['name']) ?></h1>
            <p><?= htmlspecialchars($courseData['description']) ?></p>
        </div>
        
        <div class="course-stats">
            <div class="stat-card">
                <div class="stat-number"><?= count($lessons) ?></div>
                <div class="stat-label">Уроков</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($lessons, function($lesson) { return isset($lesson['files']); })) ?></div>
                <div class="stat-label">С материалами</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= date('d.m.Y', strtotime($courseData['created_at'])) ?></div>
                <div class="stat-label">Создан</div>
            </div>
        </div>
        
        <div class="personalization-section">
            <h2>🎯 Персонализация курса</h2>
            <p>Получите персональные рекомендации и адаптированные описания уроков под ваш уровень и цели</p>
            <a href="survey_form.html" class="btn btn-primary">Пройти анкету</a>
        </div>
        
        <div class="lessons-grid">
            <?php foreach ($lessons as $lesson): ?>
            <div class="lesson-card" onclick="viewLesson(<?= $lesson['number'] ?>)">
                <div class="lesson-header">
                    <div class="lesson-number"><?= $lesson['number'] ?></div>
                    <div class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></div>
                </div>
                
                <?php if (isset($lesson['description'])): ?>
                <div class="lesson-description">
                    <?= htmlspecialchars($lesson['description']['summary_short'] ?? 'Описание недоступно') ?>
                </div>
                <?php endif; ?>
                
                <div class="lesson-files">
                    <?php if (isset($lesson['files'])): ?>
                        <?php foreach ($lesson['files'] as $file): ?>
                            <div class="file-badge <?= $file['type'] ?>">
                                <?php
                                $icons = [
                                    'video' => '🎥',
                                    'audio' => '🎵',
                                    'transcript' => '📝',
                                    'description' => '📋'
                                ];
                                echo $icons[$file['type']] ?? '📄';
                                ?>
                                <?= ucfirst($file['type']) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="lesson-actions">
                    <a href="view_lesson.php?course=<?= urlencode($courseName) ?>&lesson=<?= $lesson['number'] ?>" 
                       class="btn btn-primary">Открыть урок</a>
                    <button class="btn btn-secondary" onclick="event.stopPropagation(); personalizeLesson(<?= $lesson['number'] ?>)">
                        Персонализировать
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        function viewLesson(lessonNumber) {
            window.location.href = `view_lesson.php?course=<?= urlencode($courseName) ?>&lesson=${lessonNumber}`;
        }
        
        function personalizeLesson(lessonNumber) {
            // Перенаправляем на страницу выбора урока
            window.location.href = `select_lesson.php`;
        }
        
        // Загружаем данные курса через AJAX для обновления в реальном времени
        function loadCourseData() {
            fetch('?ajax=1')
                .then(response => response.json())
                .then(data => {
                    console.log('Данные курса загружены:', data);
                })
                .catch(error => {
                    console.error('Ошибка загрузки данных:', error);
                });
        }
        
        // Загружаем данные при загрузке страницы
        document.addEventListener('DOMContentLoaded', loadCourseData);
    </script>
</body>
</html>

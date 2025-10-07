<?php
// game/persona/config.php
return [
  // Разрешённые источники, чтобы CORS не ругался:
  'allowed_origins' => [
    'https://shkolamasterov.online', // твой домен GetCourse
    'https://*.shkolamasterov.online'
  ],

  // Провайдер LLM (можно начать с OpenAI; при желании сделаем абстракцию)
  'llm' => [
    'provider' => 'openai',
    'api_key'  => $_ENV['OPENAI_API_KEY'] ?? 'YOUR_OPENAI_API_KEY_HERE',
    'model'    => 'gpt-4o-mini', // или тот, что используешь
    'timeout'  => 15
  ],

  // API ключи для интеграций
  'apis' => [
    'kinescope' => [
      'api_key' => $_ENV['KINESCOPE_API_KEY'] ?? 'YOUR_KINESCOPE_API_KEY_HERE',
      'base_url' => 'https://kinescope.io/api/v1'
    ],
    'openai' => [
      'api_key' => $_ENV['OPENAI_API_KEY'] ?? 'YOUR_OPENAI_API_KEY_HERE',
      'base_url' => 'https://api.openai.com/v1'
    ],
    'whisper' => [
      'api_key' => $_ENV['OPENAI_API_KEY'] ?? 'YOUR_OPENAI_API_KEY_HERE',
      'base_url' => 'https://api.openai.com/v1'
    ]
  ],

  // Пути
  'paths' => [
    'store' => __DIR__ . '/store',
    'cache' => __DIR__ . '/cache'
  ],

  // TTL кеша (в секундах). Например, сутки:
  'cache_ttl' => 186400
];

<?php
/**
 * ШАБЛОН КОНФИГА.
 * Скопируйте этот файл в config.php (уже с реальными данными) и заполните значения ниже.
 * config.php НЕ должен попадать в публичный git-репозиторий или бэкапы, доступные извне.
 * На Beget: положите его ВНЕ public_html (например, на уровень выше), либо оставьте
 * внутри public_html, но убедитесь, что .htaccess из этого проекта запрещает доступ
 * к *.php напрямую в /includes/ и /data/ (правила уже добавлены в корневой .htaccess).
 */

// ---- База данных MySQL (создаётся в панели Beget -> Базы данных) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'ВАШ_БД_НАЗВАНИЕ');      // например beget_zakon
define('DB_USER', 'ВАШ_БД_ЛОГИН');
define('DB_PASS', 'ВАШ_БД_ПАРОЛЬ');

// ---- Telegram Bot API ----
// Создать бота: @BotFather -> /newbot -> получить токен.
// Бот из брифа: @PravKompas_bot
define('TELEGRAM_BOT_TOKEN', 'ВАШ_ТОКЕН_БОТА'); // формат: 123456789:AA...
// Chat ID администратора (Сергея), куда будут падать уведомления о новых вопросах.
// Узнать свой chat_id: написать боту @userinfobot
define('TELEGRAM_ADMIN_CHAT_ID', 'ВАШ_CHAT_ID');
// Секрет для проверки входящих запросов от Telegram (задаётся при setWebhook ?secret_token=)
define('TELEGRAM_WEBHOOK_SECRET', 'ПРИДУМАЙТЕ_СЛУЧАЙНУЮ_СТРОКУ');

// Группа, в которой бот автоматически отвечает на вопросы участников (без @)
define('TELEGRAM_GROUP_USERNAME', 'pravkomp');

// ---- LLM для автоответов в группе (OpenRouter, OpenAI-совместимый API) ----
define('OPENROUTER_API_KEY', 'ВАШ_OPENROUTER_ИЛИ_ANTHROPIC_КЛЮЧ');
define('OPENROUTER_MODEL', 'anthropic/claude-sonnet-5');

// ---- VK API ----
// Сообщество: prav_kompas, ID: 217637996
define('VK_GROUP_ID', '217637996');
// Ключ сообщества (Управление сообществом -> Работа с API -> Ключи доступа),
// нужны права: wall, messages
define('VK_COMMUNITY_TOKEN', 'ВАШ_VK_ACCESS_TOKEN');
define('VK_API_VERSION', '5.199');

// ---- Админ-панель новостей ----
// Пароль для входа в /admin. Храните здесь только ХЕШ, не сам пароль.
// Сгенерировать хеш: php -r "echo password_hash('ваш_пароль', PASSWORD_DEFAULT);"
define('ADMIN_PASSWORD_HASH', 'ВСТАВЬТЕ_РЕЗУЛЬТАТ_password_hash');

// ---- Прочее ----
define('SITE_URL', 'https://zakondemchenko.ru');
define('TELEGRAM_CHANNEL_URL', 'https://t.me/pravkomp');
define('VK_GROUP_URL', 'https://vk.com/prav_kompas');
define('TELEGRAM_BOT_USERNAME', 'PravKompas_bot'); // без @, используется в сообщениях об ошибке
// Адрес, с которого уходят автоответы на email (должен быть в домене сайта,
// иначе почтовые провайдеры будут чаще отправлять письма в спам)
define('MAIL_FROM', 'noreply@zakondemchenko.ru');

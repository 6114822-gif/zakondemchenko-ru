-- Схема БД для zakondemchenko.ru
-- Импортировать через phpMyAdmin в панели Beget (Базы данных -> ваша БД -> Импорт)

CREATE TABLE IF NOT EXISTS questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_type ENUM('email', 'telegram', 'vk', 'unknown') NOT NULL DEFAULT 'unknown',
    contact_value VARCHAR(255) NOT NULL,
    question TEXT NOT NULL,
    notified TINYINT(1) NOT NULL DEFAULT 0,
    notify_error VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Хранит соответствие telegram-username -> chat_id.
-- Заполняется автоматически telegram_webhook.php, когда пользователь жмёт /start у бота.
-- Это нужно, потому что Bot API не позволяет написать пользователю первым без chat_id.
CREATE TABLE IF NOT EXISTS telegram_subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    telegram_username VARCHAR(64) NOT NULL,
    chat_id BIGINT NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_username (telegram_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Простая защита от спама формы (rate limit по IP)
CREATE TABLE IF NOT EXISTS form_rate_limit (
    ip_address VARCHAR(45) NOT NULL PRIMARY KEY,
    last_submit DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

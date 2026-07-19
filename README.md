# zakondemchenko.ru — «Юрист в кармане»

## Структура проекта

```
index.html              главная страница
privacy.html             политика конфиденциальности
consent.html             согласие на обработку ПДн
.htaccess                правила Apache (Beget)

assets/css/tailwind-input.css  исходник для сборки Tailwind (правится редко, только тема/цвета)
assets/css/tailwind.min.css     СОБРАННЫЙ статический Tailwind — именно он подключён в index.html
assets/css/style.css            стили поверх Tailwind (анимации, кастомные компоненты)
assets/js/script.js             вся клиентская логика (форма, лента VK, новости, меню)
assets/img/                     сюда положить фото Сергея (sergey-demchenko.jpg)

package.json              npm-скрипты для пересборки CSS (npm run build:css)
node_modules/              ⚠️ НЕ заливать на хостинг — только для локальной сборки

includes/config.example.php   шаблон конфига — скопировать в config.php и заполнить
includes/config.php            (создать самостоятельно, в репозиторий/бэкапы не класть)
includes/db.php                 подключение к MySQL (PDO)
includes/functions.php          хелперы: санитизация, отправка в Telegram/VK

api/process_form.php     обработка формы вопроса (AJAX)
api/telegram_webhook.php  webhook бота — запоминает username -> chat_id после /start
api/vk_feed.php            отдаёт последние посты группы ВК (с кэшем на 15 мин)

data/news.json           новости («для бизнеса» / «для граждан»)
admin/                    простая админка для редактирования news.json (логин по паролю)

sql/schema.sql            схема таблиц MySQL
```

## Деплой на Beget — по шагам

1. **Загрузить файлы** через FTP/SSH в `public_html` домена zakondemchenko.ru.
   **Не заливайте** `node_modules/`, `tailwind-input.css` можно оставить или пропустить —
   на прод нужен только уже собранный `assets/css/tailwind.min.css`.
2. **Создать базу MySQL** в панели Beget → «Базы данных» → создать БД и пользователя.
   Импортировать `sql/schema.sql` через phpMyAdmin.
3. **Настроить конфиг**: скопировать `includes/config.example.php` → `includes/config.php`,
   заполнить:
   - `DB_HOST/DB_NAME/DB_USER/DB_PASS` — из шага 2;
   - `TELEGRAM_BOT_TOKEN` — токен бота `@pravkomp_bot` от @BotFather;
   - `TELEGRAM_ADMIN_CHAT_ID` — chat_id Сергея (узнать через `@userinfobot`);
   - `TELEGRAM_WEBHOOK_SECRET` — произвольная случайная строка;
   - `VK_COMMUNITY_TOKEN` — ключ доступа сообщества `prav_kompas` с правами `wall, messages`;
   - `ADMIN_PASSWORD_HASH` — результат
     `php -r "echo password_hash('ваш_пароль', PASSWORD_DEFAULT);"`.
4. **Подключить Telegram-вебхук** (один раз, с любого компьютера с curl):
   ```
   curl "https://api.telegram.org/bot<ТОКЕН>/setWebhook?url=https://zakondemchenko.ru/api/telegram_webhook.php&secret_token=<TELEGRAM_WEBHOOK_SECRET>"
   ```
   Без этого шага бот не сможет узнавать chat_id пользователей, нажавших /start,
   и автоответ в Telegram всегда будет показывать модалку "нажмите Запустить".
5. **Фото**: заменить `assets/img/sergey-demchenko.jpg` на реальное фото (рекомендуется WebP/JPG ~480×600, до 150 КБ).
6. **Проверить `.htaccess`**: убедиться, что на хостинге включён `mod_rewrite` (на Beget включён по умолчанию).

## Про производительность (PageSpeed 90+)

✅ Tailwind уже собран статически через Tailwind CLI v4 (`assets/css/tailwind.min.css`,
~27 КБ минифицированный) — CDN-JIT-компилятор из первой версии убран. После любых правок
классов в HTML пересоберите файл:
```
npm install        # один раз
npm run build:css
```

Что ещё можно улучшить перед финальным запуском:

1. Самостоятельно захостить шрифт Manrope (сейчас грузится с Google Fonts) —
   скачать `.woff2` и подключить через `@font-face` в `style.css`. Не сделано автоматически,
   так как требует скачивания файла шрифта из внешнего источника.
2. Сжать и перевести фото Сергея в WebP.
3. Включить HTTP/2 и Brotli в панели Beget, если доступно (обычно включено по умолчанию).

## Админ-панель новостей

Доступна по адресу `/admin/`. Логин — по паролю из `ADMIN_PASSWORD_HASH`.
Позволяет редактировать блоки «Для бизнеса» / «Для граждан» без доступа к БД —
все изменения сразу пишутся в `data/news.json`, который читает главная страница.

## Ограничение по авто-ответам (важно)

- **Telegram**: бот физически не может написать пользователю первым — только после того,
  как пользователь сам нажал `/start` у `@pravkomp_bot`. Если ник не найден в базе
  подписчиков — сайт покажет всплывающее окно с просьбой запустить бота.
- **VK**: сообщество может писать пользователю, только если тот разрешил сообщения
  от сообщества (написал первым или включил разрешение). Если это не так —
  аналогичное всплывающее окно с просьбой написать сообществу.

В обоих случаях вопрос всё равно сохраняется в БД и админ получает уведомление в Telegram —
теряется только автоматический моментальный ответ пользователю.

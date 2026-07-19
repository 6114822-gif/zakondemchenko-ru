<?php
/**
 * Вход в админ-панель новостей.
 * Пароль сравнивается с хешем ADMIN_PASSWORD_HASH из includes/config.php.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');

    // Небольшая задержка усложняет перебор пароля
    usleep(300000);

    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        header('Location: index.php');
        exit;
    }

    $error = 'Неверный пароль';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — Админ-панель</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#0f172a;color:#f8fafc;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
        form{background:#1e293b;padding:2.5rem;border-radius:16px;width:100%;max-width:340px;box-shadow:0 20px 40px rgba(0,0,0,.3)}
        h1{font-size:1.25rem;margin:0 0 1.5rem}
        input{width:100%;padding:.75rem 1rem;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#f8fafc;box-sizing:border-box;font-size:1rem}
        button{width:100%;margin-top:1rem;padding:.75rem;border:none;border-radius:8px;background:#c9a227;color:#0f172a;font-weight:700;cursor:pointer;font-size:1rem}
        .err{color:#f87171;font-size:.875rem;margin-top:.75rem}
    </style>
</head>
<body>
    <form method="POST" autocomplete="off">
        <h1>Админ-панель новостей</h1>
        <input type="password" name="password" placeholder="Пароль" required autofocus>
        <button type="submit">Войти</button>
        <?php if ($error !== ''): ?><p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    </form>
</body>
</html>

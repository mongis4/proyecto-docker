<?php

declare(strict_types=1);
session_start();

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/functions.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'], $token)) {
            http_response_code(403);
            exit('Solicitud no válida.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title !== '' && mb_strlen($title) <= 255) {
                $stmt = $pdo->prepare('INSERT INTO tasks (title) VALUES (:title)');
                $stmt->execute(['title' => $title]);
            }
        } elseif ($action === 'toggle') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare('UPDATE tasks SET completed = NOT completed WHERE id = :id');
                $stmt->execute(['id' => $id]);
            }
        } elseif ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id');
                $stmt->execute(['id' => $id]);
            }
        }

        redirectHome();
    }

    $tasks = $pdo->query(
        'SELECT id, title, completed, created_at
         FROM tasks ORDER BY created_at DESC, id DESC'
    )->fetchAll();
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    $tasks = [];
    $pageError = 'No se ha podido conectar con el servicio. Revisa los registros.';
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestor de tareas</title>
    <link rel="stylesheet" href="/css/style.css">
</head>

<body>
    <main class="container">
        <header>
            <p class="eyebrow">DOCKER COMPOSE · PHP + MYSQL</p>
            <h1>Mis tareas</h1>
            <p class="subtitle">Organiza tus tareas en un solo lugar.</p>
        </header>

        <?php if (!empty($pageError)): ?>
            <p class="error"><?= e($pageError) ?></p>
        <?php endif; ?>

        <form class="add-form" method="post">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="action" value="add">
            <label class="sr-only" for="title">Nueva tarea</label>
            <input id="title" name="title" maxlength="255" placeholder="¿Qué tienes que hacer?" required>
            <button type="submit">Añadir</button>
        </form>

        <section class="task-list" aria-label="Listado de tareas">
            <?php if (empty($tasks)): ?>
                <p class="empty">Todavía no hay tareas. ¡Añade la primera!</p>
            <?php endif; ?>

            <?php foreach ($tasks as $task): ?>
                <article class="task <?= $task['completed'] ? 'done' : '' ?>">
                    <div>
                        <strong><?= e($task['title']) ?></strong>
                        <small><?= e((string)$task['created_at']) ?></small>
                    </div>
                    <div class="actions">
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                            <button class="secondary" type="submit">
                                <?= $task['completed'] ? 'Reabrir' : 'Completar' ?>
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirm('¿Eliminar esta tarea?')">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
                            <button class="danger" type="submit">Eliminar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>

</html>
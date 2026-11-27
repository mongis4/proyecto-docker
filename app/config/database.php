<?php

declare(strict_types=1);

function getPDO(): PDO
{
    $host = getenv('DB_HOST') ?: 'db';
    $name = getenv('DB_NAME') ?: 'tareas';
    $user = getenv('DB_USER') ?: 'usuario_web';
    $pass = getenv('DB_PASSWORD') ?: '';

    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

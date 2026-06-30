<?php
declare(strict_types=1);

try {
    $host = getenv('SHAREABITE_DB_HOST') ?: '127.0.0.1';
    $database = getenv('SHAREABITE_DB_NAME') ?: 'shareabite_sa';
    $username = getenv('SHAREABITE_DB_USER') ?: 'root';
    $password = getenv('SHAREABITE_DB_PASS') ?: '';
    $port = getenv('SHAREABITE_DB_PORT') ?: '3306';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Check your ShareABite database configuration.');
}

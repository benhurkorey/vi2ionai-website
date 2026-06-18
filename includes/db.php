<?php
/**
 * Vi2ionai Fleet — PDO Database Connection
 * Returns a singleton PDO instance.
 */

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Never expose DB errors in production
            if (APP_ENV === 'development') {
                throw $e;
            }
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database unavailable']);
            exit;
        }
    }
    return $pdo;
}

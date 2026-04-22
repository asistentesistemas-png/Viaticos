<?php
// config/database.php
// Ajusta las credenciales según tu entorno

define('DB_HOST', 'n8n-bc-integration-db.cbnshbrjepdd.us-east-1.rds.amazonaws.com');
define('DB_PORT', '3306');
define('DB_NAME', 'n8n_bc');
define('DB_USER', 'n8n_api_user');
define('DB_PASS', 'bjhuLWJjLWlu*4');
define('DB_CHARSET', 'utf8mb4');


function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log('DB Connection failed: ' . $e->getMessage());
        die(json_encode(['error' => 'Error de conexión a la base de datos.']));
    }
    return $pdo;
}

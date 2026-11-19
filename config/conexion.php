<?php
// Conexión a la base de datos (XAMPP: usuario "root" sin contraseña por defecto).
// Si cambias estos datos, cámbialos solo aquí.
const DB_HOST = '127.0.0.1';
const DB_NOMBRE = 'db_maicol';
const DB_USUARIO = 'root';
const DB_CLAVE = '';

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NOMBRE . ';charset=utf8mb4',
            DB_USUARIO,
            DB_CLAVE,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('GymControl - error de conexión: ' . $e->getMessage());
        http_response_code(500);
        exit('No se pudo conectar a la base de datos. Revisa que MySQL esté encendido en XAMPP y que la base "' . DB_NOMBRE . '" exista.');
    }
    return $pdo;
}

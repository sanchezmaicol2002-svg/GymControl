<?php
// Manejo de sesión, permisos y seguridad básica. Se incluye al inicio de cada página.

require_once __DIR__ . '/../config/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

const ROL_PROPIETARIO = 1;
const ROL_EMPLEADO = 2;

/** Escapa texto antes de mostrarlo en HTML (evita inyección de código). */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/** Devuelve los datos del usuario que inició sesión, o null si no hay sesión. */
function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

/**
 * Obliga a tener sesión iniciada. Si se pasa una lista de roles,
 * solo esos roles pueden ver la página (ej: requerir_login([ROL_PROPIETARIO])).
 */
function requerir_login(array $roles_permitidos = []): void
{
    // Evita que el navegador guarde la página (por ejemplo, al volver atrás después de cerrar sesión).
    header('Cache-Control: no-store');

    $usuario = usuario_actual();
    if ($usuario === null) {
        header('Location: login.php');
        exit;
    }

    // En cada página se revisa que el usuario siga activo y se actualiza su rol:
    // si el propietario lo desactiva o le cambia el rol, se nota de inmediato.
    $consulta = db()->prepare('SELECT NOMBRES, APELLIDOS, ESTADO_FK, ROL_FK FROM usuarios WHERE ID = ?');
    $consulta->execute([$usuario['id']]);
    $fila = $consulta->fetch();
    if (!$fila || (int) $fila['ESTADO_FK'] !== 1 || !in_array((int) $fila['ROL_FK'], [ROL_PROPIETARIO, ROL_EMPLEADO], true)) {
        unset($_SESSION['usuario']);
        $_SESSION['flash'] = ['tipo' => 'error', 'texto' => 'Tu usuario ya no tiene acceso al sistema.'];
        header('Location: login.php');
        exit;
    }
    $_SESSION['usuario']['nombre'] = $fila['NOMBRES'] . ' ' . $fila['APELLIDOS'];
    $_SESSION['usuario']['rol_id'] = (int) $fila['ROL_FK'];
    $_SESSION['usuario']['rol'] = (int) $fila['ROL_FK'] === ROL_PROPIETARIO ? 'Propietario' : 'Empleado';
    $usuario = $_SESSION['usuario'];

    if ($roles_permitidos && !in_array($usuario['rol_id'], $roles_permitidos, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Sin permiso</title></head>'
            . '<body style="font-family:Segoe UI,Arial,sans-serif;text-align:center;margin-top:80px;">'
            . '<h1>No tienes permiso para ver esta página</h1>'
            . '<p><a href="dashboard.php">Volver al dashboard</a></p></body></html>';
        exit;
    }
}

/** Token para proteger los formularios (CSRF). */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

require_once __DIR__ . '/util.php';
require_once __DIR__ . '/negocio.php';

<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/conexion.php';

// Si ya inició sesión, lo mandamos al dashboard.
if (usuario_actual()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$correo = '';

// Mensaje enviado desde otra página (por ejemplo, usuario desactivado).
if (!empty($_SESSION['flash'])) {
    $error = $_SESSION['flash']['texto'];
    unset($_SESSION['flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $clave = $_POST['contrasena'] ?? '';

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $error = 'Tu sesión expiró. Intenta de nuevo.';
    } elseif ($correo === '' || $clave === '') {
        $error = 'Escribe tu correo y tu contraseña.';
    } else {
        $consulta = db()->prepare(
            'SELECT u.ID, u.NOMBRES, u.APELLIDOS, u.CLAVE, u.ESTADO_FK, u.ROL_FK, r.DESCRIPCION AS ROL
             FROM usuarios u
             JOIN roles r ON r.ID = u.ROL_FK
             WHERE u.CORREO = ?'
        );
        $consulta->execute([$correo]);
        $fila = $consulta->fetch();

        // Si el correo no existe se compara contra un hash falso, para que tarde igual.
        $hash = $fila['CLAVE'] ?? '$2y$10$qY7r4WZpTV2nyClprlyf.ekRpg7o9o96L6ujID.LfllBXaV5G6id6';
        $clave_ok = password_verify($clave, $hash) && $fila !== false && $fila['CLAVE'] !== null;

        if (!$clave_ok) {
            $error = 'Correo o contraseña incorrectos.';
        } elseif ((int) $fila['ESTADO_FK'] !== 1) {
            $error = 'Tu usuario está inactivo. Habla con el propietario del gimnasio.';
        } elseif (!in_array((int) $fila['ROL_FK'], [ROL_PROPIETARIO, ROL_EMPLEADO], true)) {
            $error = 'Tu usuario no tiene permiso para entrar al sistema.';
        } else {
            session_regenerate_id(true);
            $_SESSION['usuario'] = [
                'id'     => (int) $fila['ID'],
                'nombre' => $fila['NOMBRES'] . ' ' . $fila['APELLIDOS'],
                'rol_id' => (int) $fila['ROL_FK'],
                'rol'    => ucfirst(strtolower($fila['ROL'])),
            ];
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - Iniciar sesión</title>
  <link rel="stylesheet" href="login.css">
</head>
<body>

  <div class="contenedor-login">
    <form class="tarjeta-login" action="login.php" method="post">

      <div class="logo">
        <span class="logo-icono">🏋️</span>
        <span class="logo-texto">GYMCONTROL</span>
      </div>
      <p class="subtitulo">Sistema de gestión para gimnasios</p>

      <h1>Iniciar sesión</h1>

<?php if ($error !== ''): ?>
      <p class="mensaje-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="campo">
        <label for="correo">Correo electrónico</label>
        <input type="email" id="correo" name="correo" placeholder="admin@gymcontrol.com" value="<?= e($correo) ?>" required autofocus>
      </div>

      <div class="campo">
        <label for="contrasena">Contraseña</label>
        <input type="password" id="contrasena" name="contrasena" placeholder="••••••••" required>
      </div>

      <button type="submit" class="boton-primario">Iniciar sesión</button>
    </form>
  </div>

</body>
</html>

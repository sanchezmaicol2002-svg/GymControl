<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
$pagina_activa = 'configuracion';

$pdo = db();
$yo = usuario_actual()['id'];

$s = $pdo->prepare('SELECT NOMBRES, APELLIDOS, CORREO, TELEFONO, CLAVE FROM usuarios WHERE ID = ?');
$s->execute([$yo]);
$perfil = $s->fetch();
$perfil_form = [
    'nombres' => $perfil['NOMBRES'], 'apellidos' => $perfil['APELLIDOS'],
    'correo' => $perfil['CORREO'] ?? '', 'telefono' => $perfil['TELEFONO'],
];
$errores_perfil = [];
$errores_clave = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('configuracion.php');
    $accion = post_texto('accion');

    if ($accion === 'perfil') {
        $perfil_form = [
            'nombres'   => preg_replace('/\s+/u', ' ', post_texto('nombres')),
            'apellidos' => preg_replace('/\s+/u', ' ', post_texto('apellidos')),
            'correo'    => mb_strtolower(post_texto('correo')),
            'telefono'  => post_texto('telefono'),
        ];
        foreach (['nombres' => 'tus nombres', 'apellidos' => 'tus apellidos'] as $campo => $texto) {
            if ($perfil_form[$campo] === '') {
                $errores_perfil[$campo] = 'Escribe ' . $texto . '.';
            } elseif (mb_strlen($perfil_form[$campo]) > 20) {
                $errores_perfil[$campo] = 'Máximo 20 letras.';
            } elseif (!preg_match("/^[\p{L}' .\-]+$/u", $perfil_form[$campo])) {
                $errores_perfil[$campo] = 'Usa solo letras.';
            }
        }
        $errores_perfil += validar_correo($pdo, $perfil_form['correo'], $yo);
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $perfil_form['telefono'])) {
            $errores_perfil['telefono'] = 'Escribe un teléfono válido (7 a 20 números).';
        }

        if (!$errores_perfil) {
            $pdo->prepare('UPDATE usuarios SET NOMBRES = ?, APELLIDOS = ?, CORREO = ?, TELEFONO = ? WHERE ID = ?')
                ->execute([$perfil_form['nombres'], $perfil_form['apellidos'], $perfil_form['correo'], $perfil_form['telefono'], $yo]);
            flash('exito', 'Perfil actualizado.');
            redirigir('configuracion.php');
        }
    } elseif ($accion === 'clave') {
        $actual = is_string($_POST['contrasenaActual'] ?? null) ? $_POST['contrasenaActual'] : '';
        $nueva = is_string($_POST['contrasenaNueva'] ?? null) ? $_POST['contrasenaNueva'] : '';
        $confirmar = is_string($_POST['contrasenaConfirmar'] ?? null) ? $_POST['contrasenaConfirmar'] : '';

        if ($perfil['CLAVE'] === null || !password_verify($actual, $perfil['CLAVE'])) {
            $errores_clave['actual'] = 'La contraseña actual no es correcta.';
        }
        $mensaje = validar_clave($nueva, $confirmar);
        if ($mensaje !== null) {
            $errores_clave['nueva'] = $mensaje;
        } elseif ($nueva === $actual) {
            $errores_clave['nueva'] = 'La nueva contraseña debe ser diferente a la actual.';
        }

        if (!$errores_clave) {
            $pdo->prepare('UPDATE usuarios SET CLAVE = ? WHERE ID = ?')->execute([password_hash($nueva, PASSWORD_DEFAULT), $yo]);
            session_regenerate_id(true);
            flash('exito', 'Contraseña actualizada.');
            redirigir('configuracion.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - Configuración y perfil</title>
  <link rel="stylesheet" href="configuracion.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
      <div class="pagina">

        <header class="encabezado">
          <h1>Configuración y perfil</h1>
          <p class="subtitulo">Actualiza tus datos personales y tu contraseña</p>
        </header>

<?php mostrar_flash(); ?>

        <section class="tarjeta">
          <h2>Mi perfil</h2>
          <form action="configuracion.php" method="post">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="perfil">
            <div class="fila">
              <div class="campo">
                <label for="nombres">Nombres</label>
                <input type="text" id="nombres" name="nombres" maxlength="20" value="<?= e($perfil_form['nombres']) ?>" required>
                <?php error_campo($errores_perfil, 'nombres'); ?>
              </div>
              <div class="campo">
                <label for="apellidos">Apellidos</label>
                <input type="text" id="apellidos" name="apellidos" maxlength="20" value="<?= e($perfil_form['apellidos']) ?>" required>
                <?php error_campo($errores_perfil, 'apellidos'); ?>
              </div>
            </div>
            <div class="fila">
              <div class="campo">
                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" maxlength="100" value="<?= e($perfil_form['correo']) ?>" required>
                <?php error_campo($errores_perfil, 'correo'); ?>
              </div>
              <div class="campo">
                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" maxlength="20" value="<?= e($perfil_form['telefono']) ?>" required>
                <?php error_campo($errores_perfil, 'telefono'); ?>
              </div>
            </div>
            <div class="fila">
              <div class="campo">
                <label for="rol">Rol</label>
                <input type="text" id="rol" value="<?= e(usuario_actual()['rol']) ?>" disabled>
              </div>
            </div>
            <div class="acciones">
              <button type="submit" class="boton boton-primario">Guardar perfil</button>
            </div>
          </form>
        </section>

        <section class="tarjeta">
          <h2>Cambiar contraseña</h2>
          <form action="configuracion.php" method="post" autocomplete="off">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="clave">
            <div class="fila">
              <div class="campo">
                <label for="contrasenaActual">Contraseña actual</label>
                <input type="password" id="contrasenaActual" name="contrasenaActual" placeholder="••••••••" autocomplete="current-password" required>
                <?php error_campo($errores_clave, 'actual'); ?>
              </div>
            </div>
            <div class="fila">
              <div class="campo">
                <label for="contrasenaNueva">Nueva contraseña</label>
                <input type="password" id="contrasenaNueva" name="contrasenaNueva" placeholder="••••••••" autocomplete="new-password" required>
                <small class="ayuda-campo">Mínimo 8 caracteres, con letras y números.</small>
                <?php error_campo($errores_clave, 'nueva'); ?>
              </div>
              <div class="campo">
                <label for="contrasenaConfirmar">Confirmar contraseña</label>
                <input type="password" id="contrasenaConfirmar" name="contrasenaConfirmar" placeholder="••••••••" autocomplete="new-password" required>
              </div>
            </div>
            <div class="acciones">
              <button type="submit" class="boton boton-primario">Actualizar contraseña</button>
            </div>
          </form>
        </section>

      </div>
    </main>
  </div>

</body>
</html>

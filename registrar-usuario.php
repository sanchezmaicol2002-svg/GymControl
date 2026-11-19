<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login([ROL_PROPIETARIO]);   // solo el propietario crea o edita usuarios
$pagina_activa = 'gestion-usuarios';

$pdo = db();
$yo = usuario_actual()['id'];
$id = (int) ($_GET['id'] ?? 0);
$editando = $id > 0;

$tipos = $pdo->query('SELECT ID, DESCRIPCION FROM tipo_id ORDER BY ID')->fetchAll();
$roles = $pdo->query('SELECT ID, DESCRIPCION FROM roles ORDER BY ID')->fetchAll();
$errores = [];
$datos = [
    'tipo_id' => 1, 'identificacion' => '', 'nombres' => '', 'apellidos' => '', 'telefono' => '',
    'fecha_nacimiento' => '', 'direccion' => '', 'estado' => ESTADO_ACTIVO, 'correo' => '', 'rol' => ROL_EMPLEADO,
];
$actual = null;

if ($editando) {
    $s = $pdo->prepare('SELECT * FROM usuarios WHERE ID = ? AND ROL_FK IN (1, 2, 3)');
    $s->execute([$id]);
    $actual = $s->fetch();
    if (!$actual) {
        flash('error', 'No se encontró ese usuario.');
        redirigir('gestion-usuarios.php');
    }
    $datos = [
        'tipo_id' => (int) $actual['TIPO_ID_FK'], 'identificacion' => $actual['IDENTIFICACION'],
        'nombres' => $actual['NOMBRES'], 'apellidos' => $actual['APELLIDOS'], 'telefono' => $actual['TELEFONO'],
        'fecha_nacimiento' => $actual['FECHA_DE_NACIMIENTO'], 'direccion' => $actual['DIRECCION'],
        'estado' => (int) $actual['ESTADO_FK'], 'correo' => $actual['CORREO'] ?? '', 'rol' => (int) $actual['ROL_FK'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('registrar-usuario.php' . ($editando ? '?id=' . $id : ''));
    [$errores, $datos] = validar_persona($pdo, $_POST, $editando ? $id : null, true);

    // Las contraseñas no se recortan: se toman tal cual las escribió la persona.
    $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';
    $clave2 = is_string($_POST['clave2'] ?? null) ? $_POST['clave2'] : '';
    // Los clientes no inician sesión: para ellos no se guarda correo ni contraseña.
    $es_cliente = $datos['rol'] === ROL_CLIENTE;
    $clave_obligatoria = !$editando || $actual['CLAVE'] === null;
    if (!$es_cliente && ($clave !== '' || $clave_obligatoria)) {
        $mensaje = validar_clave($clave, $clave2);
        if ($mensaje !== null) {
            $errores['clave'] = $mensaje;
        }
    }

    if ($editando) {
        if ($id === $yo && $datos['estado'] !== ESTADO_ACTIVO) {
            $errores['estado'] = 'No puedes desactivar tu propio usuario.';
        }
        if ($id === $yo && $datos['rol'] !== ROL_PROPIETARIO) {
            $errores['rol'] = 'No puedes quitarte a ti mismo el rol de propietario.';
        }
        if (!isset($errores['rol']) && (int) $actual['ROL_FK'] === ROL_PROPIETARIO
            && ($datos['rol'] !== ROL_PROPIETARIO || $datos['estado'] !== ESTADO_ACTIVO)
            && propietarios_activos($pdo, $id) === 0) {
            $errores['rol'] = 'Debe quedar al menos un propietario activo.';
        }
    }

    if (!$errores) {
        $valores = [
            $datos['identificacion'], $datos['tipo_id'], $datos['nombres'], $datos['apellidos'], $es_cliente ? null : $datos['correo'],
            $datos['telefono'], $datos['fecha_nacimiento'], $datos['direccion'], $datos['estado'], $datos['rol'],
        ];
        try {
            if ($editando) {
                $sql = 'UPDATE usuarios SET IDENTIFICACION = ?, TIPO_ID_FK = ?, NOMBRES = ?, APELLIDOS = ?, CORREO = ?,
                        TELEFONO = ?, FECHA_DE_NACIMIENTO = ?, DIRECCION = ?, ESTADO_FK = ?, ROL_FK = ?';
                if ($es_cliente) {
                    $sql .= ', CLAVE = NULL';
                } elseif ($clave !== '') {
                    $sql .= ', CLAVE = ?';
                    $valores[] = password_hash($clave, PASSWORD_DEFAULT);
                }
                $pdo->prepare($sql . ' WHERE ID = ? AND ROL_FK IN (1, 2, 3)')->execute(array_merge($valores, [$id]));
                flash('exito', 'Usuario actualizado: ' . $datos['nombres'] . ' ' . $datos['apellidos'] . '.');
            } else {
                $valores[] = $es_cliente ? null : password_hash($clave, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO usuarios (IDENTIFICACION, TIPO_ID_FK, NOMBRES, APELLIDOS, CORREO, TELEFONO,
                               FECHA_DE_NACIMIENTO, DIRECCION, ESTADO_FK, ROL_FK, CLAVE) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute($valores);
                flash('exito', 'Usuario creado: ' . $datos['nombres'] . ' ' . $datos['apellidos'] . '.');
            }
            redirigir('gestion-usuarios.php');
        } catch (PDOException $ex) {
            error_log('GymControl - error al guardar usuario: ' . $ex->getMessage());
            $errores['general'] = 'No se pudo guardar. Revisa que el documento y el correo no estén repetidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - <?= $editando ? 'Editar usuario' : 'Nuevo usuario' ?></title>
  <link rel="stylesheet" href="registrar-cliente.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
  <div class="contenedor-formulario">
    <form class="tarjeta-formulario" action="registrar-usuario.php<?= $editando ? '?id=' . $id : '' ?>" method="post" autocomplete="off">

      <h1><?= $editando ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
      <p class="subtitulo">Datos de quien puede ingresar al sistema de GymControl</p>

<?php mostrar_flash(); ?>
<?php if (isset($errores['general'])): ?>
      <p class="alerta alerta-error" role="alert"><?= e($errores['general']) ?></p>
<?php endif; ?>
      <?= campo_csrf() ?>

      <div class="fila">
        <div class="campo">
          <label for="nombres">Nombres</label>
          <input type="text" id="nombres" name="nombres" maxlength="20" value="<?= e($datos['nombres']) ?>" required>
          <?php error_campo($errores, 'nombres'); ?>
        </div>
        <div class="campo">
          <label for="apellidos">Apellidos</label>
          <input type="text" id="apellidos" name="apellidos" maxlength="20" value="<?= e($datos['apellidos']) ?>" required>
          <?php error_campo($errores, 'apellidos'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo">
          <label for="tipo_id">Tipo de documento</label>
          <select id="tipo_id" name="tipo_id" required>
<?php foreach ($tipos as $t): ?>
            <option value="<?= (int) $t['ID'] ?>"<?= (int) $datos['tipo_id'] === (int) $t['ID'] ? ' selected' : '' ?>><?= e($t['DESCRIPCION']) ?></option>
<?php endforeach; ?>
          </select>
          <?php error_campo($errores, 'tipo_id'); ?>
        </div>
        <div class="campo">
          <label for="identificacion">Número de documento</label>
          <input type="text" id="identificacion" name="identificacion" maxlength="20" value="<?= e($datos['identificacion']) ?>" required>
          <?php error_campo($errores, 'identificacion'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo">
          <label for="fecha_nacimiento">Fecha de nacimiento</label>
          <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" max="<?= e(hoy()) ?>" value="<?= e($datos['fecha_nacimiento']) ?>" required>
          <?php error_campo($errores, 'fecha_nacimiento'); ?>
        </div>
        <div class="campo">
          <label for="telefono">Teléfono</label>
          <input type="tel" id="telefono" name="telefono" maxlength="20" value="<?= e($datos['telefono']) ?>" required>
          <?php error_campo($errores, 'telefono'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo campo-ancho">
          <label for="direccion">Dirección</label>
          <input type="text" id="direccion" name="direccion" maxlength="100" value="<?= e($datos['direccion']) ?>" required>
          <?php error_campo($errores, 'direccion'); ?>
        </div>
      </div>

      <div class="fila" id="fila-correo">
        <div class="campo campo-ancho">
          <label for="correo">Correo electrónico (para iniciar sesión)</label>
          <input type="email" id="correo" name="correo" maxlength="100" value="<?= e($datos['correo']) ?>" required>
          <?php error_campo($errores, 'correo'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo">
          <label for="rol">Rol</label>
          <select id="rol" name="rol" required>
<?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['ID'] ?>"<?= (int) $datos['rol'] === (int) $r['ID'] ? ' selected' : '' ?>><?= e(ucfirst(strtolower($r['DESCRIPCION']))) ?></option>
<?php endforeach; ?>
          </select>
          <small class="ayuda-campo">Los clientes no inician sesión en el sistema, por eso no llevan correo ni contraseña.</small>
          <?php error_campo($errores, 'rol'); ?>
        </div>
        <div class="campo">
          <label for="estado">Estado</label>
          <select id="estado" name="estado">
            <option value="1"<?= (int) $datos['estado'] === 1 ? ' selected' : '' ?>>Activo</option>
            <option value="2"<?= (int) $datos['estado'] === 2 ? ' selected' : '' ?>>Inactivo</option>
          </select>
          <?php error_campo($errores, 'estado'); ?>
        </div>
      </div>

      <div class="fila" id="fila-clave">
        <div class="campo">
          <label for="clave">Contraseña</label>
          <input type="password" id="clave" name="clave" placeholder="••••••••" autocomplete="new-password" data-obligatoria="<?= !$editando || $actual['CLAVE'] === null ? '1' : '0' ?>">
          <small class="ayuda-campo"><?= $editando && $actual['CLAVE'] !== null ? 'Déjala vacía para no cambiarla. ' : '' ?>Mínimo 8 caracteres, con letras y números.</small>
          <?php error_campo($errores, 'clave'); ?>
        </div>
        <div class="campo">
          <label for="clave2">Confirmar contraseña</label>
          <input type="password" id="clave2" name="clave2" placeholder="••••••••" autocomplete="new-password">
        </div>
      </div>

      <div class="acciones">
        <a href="gestion-usuarios.php" class="boton boton-secundario">Cancelar</a>
        <button type="submit" class="boton boton-primario">Guardar usuario</button>
      </div>

    </form>
  </div>
    </main>
  </div>

  <script>
    // Si el rol es Cliente se ocultan el correo y la contraseña (los clientes no inician sesión).
    (function () {
      var rol = document.getElementById('rol');
      var filaCorreo = document.getElementById('fila-correo');
      var filaClave = document.getElementById('fila-clave');
      var correo = document.getElementById('correo');
      var clave = document.getElementById('clave');
      function ajustar() {
        var cliente = rol.value === '<?= ROL_CLIENTE ?>';
        filaCorreo.hidden = cliente;
        filaClave.hidden = cliente;
        correo.required = !cliente;
        clave.required = !cliente && clave.getAttribute('data-obligatoria') === '1';
      }
      rol.addEventListener('change', ajustar);
      ajustar();
    })();
  </script>

</body>
</html>

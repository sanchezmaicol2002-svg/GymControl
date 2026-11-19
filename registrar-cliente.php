<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
$pagina_activa = 'clientes';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$editando = $id > 0;

$tipos = $pdo->query('SELECT ID, DESCRIPCION FROM tipo_id ORDER BY ID')->fetchAll();
$errores = [];
$datos = [
    'tipo_id' => 1, 'identificacion' => '', 'nombres' => '', 'apellidos' => '', 'telefono' => '',
    'fecha_nacimiento' => '', 'direccion' => '', 'estado' => ESTADO_ACTIVO,
];

if ($editando) {
    $s = $pdo->prepare('SELECT * FROM usuarios WHERE ID = ? AND ROL_FK = 3');
    $s->execute([$id]);
    $cliente = $s->fetch();
    if (!$cliente) {
        flash('error', 'No se encontró ese cliente.');
        redirigir('clientes.php');
    }
    $datos = [
        'tipo_id' => (int) $cliente['TIPO_ID_FK'], 'identificacion' => $cliente['IDENTIFICACION'],
        'nombres' => $cliente['NOMBRES'], 'apellidos' => $cliente['APELLIDOS'], 'telefono' => $cliente['TELEFONO'],
        'fecha_nacimiento' => $cliente['FECHA_DE_NACIMIENTO'], 'direccion' => $cliente['DIRECCION'],
        'estado' => (int) $cliente['ESTADO_FK'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('registrar-cliente.php' . ($editando ? '?id=' . $id : ''));
    [$errores, $datos] = validar_persona($pdo, $_POST, $editando ? $id : null, false);

    if (!$errores) {
        $valores = [
            $datos['identificacion'], $datos['tipo_id'], $datos['nombres'], $datos['apellidos'],
            $datos['telefono'], $datos['fecha_nacimiento'], $datos['direccion'], $datos['estado'],
        ];
        if ($editando) {
            $pdo->prepare('UPDATE usuarios SET IDENTIFICACION = ?, TIPO_ID_FK = ?, NOMBRES = ?, APELLIDOS = ?, TELEFONO = ?,
                           FECHA_DE_NACIMIENTO = ?, DIRECCION = ?, ESTADO_FK = ? WHERE ID = ? AND ROL_FK = 3')
                ->execute(array_merge($valores, [$id]));
            flash('exito', 'Cliente actualizado: ' . $datos['nombres'] . ' ' . $datos['apellidos'] . '.');
            redirigir('ver-cliente.php?id=' . $id);
        }
        $pdo->prepare('INSERT INTO usuarios (IDENTIFICACION, TIPO_ID_FK, NOMBRES, APELLIDOS, TELEFONO,
                       FECHA_DE_NACIMIENTO, DIRECCION, ESTADO_FK, ROL_FK) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 3)')
            ->execute($valores);
        flash('exito', 'Cliente registrado: ' . $datos['nombres'] . ' ' . $datos['apellidos'] . '. Ahora puedes registrar su pago.');
        redirigir('registrar-pago.php?cliente=' . $pdo->lastInsertId());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - <?= $editando ? 'Editar cliente' : 'Registrar cliente' ?></title>
  <link rel="stylesheet" href="registrar-cliente.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
  <div class="contenedor-formulario">
    <form class="tarjeta-formulario" action="registrar-cliente.php<?= $editando ? '?id=' . $id : '' ?>" method="post">

      <h1><?= $editando ? 'Editar cliente' : 'Registrar nuevo cliente' ?></h1>
      <p class="subtitulo"><?= $editando ? 'Actualiza los datos del cliente' : 'Completa los datos para registrar un nuevo cliente del gimnasio' ?></p>

<?php mostrar_flash(); ?>
      <?= campo_csrf() ?>

      <div class="fila">
        <div class="campo">
          <label for="nombres">Nombres</label>
          <input type="text" id="nombres" name="nombres" placeholder="Ej. Juan Carlos" maxlength="20" value="<?= e($datos['nombres']) ?>" required>
          <?php error_campo($errores, 'nombres'); ?>
        </div>
        <div class="campo">
          <label for="apellidos">Apellidos</label>
          <input type="text" id="apellidos" name="apellidos" placeholder="Ej. Pérez Gómez" maxlength="20" value="<?= e($datos['apellidos']) ?>" required>
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
          <input type="text" id="identificacion" name="identificacion" placeholder="Ej. 1023456789" maxlength="20" value="<?= e($datos['identificacion']) ?>" required>
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
          <input type="tel" id="telefono" name="telefono" placeholder="Ej. 301 234 5678" maxlength="20" value="<?= e($datos['telefono']) ?>" required>
          <?php error_campo($errores, 'telefono'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo campo-ancho">
          <label for="direccion">Dirección</label>
          <input type="text" id="direccion" name="direccion" placeholder="Ej. Calle 100 #15-30" maxlength="100" value="<?= e($datos['direccion']) ?>" required>
          <?php error_campo($errores, 'direccion'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo campo-ancho">
          <label for="estado">Estado</label>
          <select id="estado" name="estado">
            <option value="1"<?= (int) $datos['estado'] === 1 ? ' selected' : '' ?>>Activo</option>
            <option value="2"<?= (int) $datos['estado'] === 2 ? ' selected' : '' ?>>Inactivo</option>
          </select>
          <?php error_campo($errores, 'estado'); ?>
        </div>
      </div>

      <div class="acciones">
        <a href="<?= $editando ? 'ver-cliente.php?id=' . $id : 'clientes.php' ?>" class="boton boton-secundario">Cancelar</a>
        <button type="submit" class="boton boton-primario">Guardar cliente</button>
      </div>

    </form>
  </div>
    </main>
  </div>

</body>
</html>

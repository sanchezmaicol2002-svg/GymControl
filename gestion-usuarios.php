<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login([ROL_PROPIETARIO]);   // solo el propietario administra usuarios
$pagina_activa = 'gestion-usuarios';

$pdo = db();
$yo = usuario_actual()['id'];

$filtro = (int) get_texto('rol');
if (!in_array($filtro, [ROL_PROPIETARIO, ROL_EMPLEADO, ROL_CLIENTE], true)) {
    $filtro = 0;
}
$volver = 'gestion-usuarios.php' . ($filtro ? '?rol=' . $filtro : '');

// Activar / desactivar un usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filtro_post = (int) post_texto('filtro');
    $volver = 'gestion-usuarios.php' . ($filtro_post ? '?rol=' . $filtro_post : '');
    exigir_csrf($volver);
    $id = (int) post_texto('id');
    $accion = post_texto('accion');

    $s = $pdo->prepare('SELECT ID, NOMBRES, APELLIDOS, ROL_FK, CORREO, CLAVE FROM usuarios WHERE ID = ? AND ROL_FK IN (1, 2, 3)');
    $s->execute([$id]);
    $u = $s->fetch();

    if (!$u || !in_array($accion, ['activar', 'desactivar'], true)) {
        flash('error', 'No se encontró ese usuario.');
    } elseif ($accion === 'desactivar') {
        if ($id === $yo) {
            flash('error', 'No puedes desactivar tu propio usuario.');
        } elseif ((int) $u['ROL_FK'] === ROL_PROPIETARIO && propietarios_activos($pdo, $id) === 0) {
            flash('error', 'Debe quedar al menos un propietario activo.');
        } else {
            $pdo->prepare('UPDATE usuarios SET ESTADO_FK = 2 WHERE ID = ?')->execute([$id]);
            flash('exito', 'Usuario desactivado: ' . $u['NOMBRES'] . ' ' . $u['APELLIDOS'] . '.');
        }
    } else {
        $es_cliente = (int) $u['ROL_FK'] === ROL_CLIENTE;
        if (!$es_cliente && ($u['CORREO'] === null || $u['CLAVE'] === null)) {
            flash('error', 'Antes de activarlo, asígnale un correo y una contraseña (Editar).');
        } else {
            $pdo->prepare('UPDATE usuarios SET ESTADO_FK = 1 WHERE ID = ?')->execute([$id]);
            flash('exito', 'Usuario activado: ' . $u['NOMBRES'] . ' ' . $u['APELLIDOS'] . '.');
        }
    }
    redirigir($volver);
}

$conteo = [1 => 0, 2 => 0, 3 => 0];
foreach ($pdo->query('SELECT ROL_FK, COUNT(*) AS n FROM usuarios GROUP BY ROL_FK')->fetchAll() as $fila) {
    $conteo[(int) $fila['ROL_FK']] = (int) $fila['n'];
}

$donde = $filtro ? 'WHERE ROL_FK = ' . $filtro : 'WHERE ROL_FK IN (1, 2, 3)';
$total = (int) $pdo->query("SELECT COUNT(*) FROM usuarios $donde")->fetchColumn();
$pag = paginar($total, 15);
$usuarios = $pdo->query("SELECT ID, NOMBRES, APELLIDOS, CORREO, ESTADO_FK, ROL_FK FROM usuarios $donde
                         ORDER BY ROL_FK, NOMBRES, APELLIDOS LIMIT {$pag['limite']} OFFSET {$pag['offset']}")->fetchAll();

$etiquetas_rol = [1 => ['propietario', 'Propietario'], 2 => ['empleado', 'Empleado'], 3 => ['cliente', 'Cliente']];
$pestanas = [0 => 'Todos', 1 => 'Propietarios', 2 => 'Empleados', 3 => 'Clientes'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - Gestión de usuarios</title>
  <link rel="stylesheet" href="gestion-usuarios.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
      <div class="pagina">

        <header class="encabezado">
          <div>
            <h1>Gestión de usuarios</h1>
            <p class="subtitulo">Administra los usuarios del gimnasio, quién puede ingresar al sistema y qué permisos tiene</p>
          </div>
          <div class="acciones-encabezado">
            <a href="registrar-usuario.php" class="boton boton-primario">+ Nuevo usuario</a>
          </div>
        </header>

<?php mostrar_flash(); ?>

        <section class="resumen">
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Propietarios</span>
            <span class="numero-grande"><?= $conteo[1] ?></span>
          </div>
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Empleados</span>
            <span class="numero-grande"><?= $conteo[2] ?></span>
          </div>
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Clientes</span>
            <span class="numero-grande"><?= $conteo[3] ?></span>
          </div>
        </section>

        <section class="tarjeta">
          <h2>Usuarios</h2>
          <nav class="filtros-rapidos" aria-label="Filtrar por rol">
<?php foreach ($pestanas as $valor => $texto): ?>
            <a href="gestion-usuarios.php<?= $valor ? '?rol=' . $valor : '' ?>"<?= $filtro === $valor ? ' class="activo" aria-current="page"' : '' ?>><?= e($texto) ?></a>
<?php endforeach; ?>
          </nav>
          <div class="tabla-contenedor">
            <table class="tabla">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Correo</th>
                  <th>Rol</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
<?php if (!$usuarios): ?>
                <tr><td colspan="5" class="sin-datos">No hay usuarios con este rol.</td></tr>
<?php endif; ?>
<?php foreach ($usuarios as $fila): $activo = (int) $fila['ESTADO_FK'] === 1; [$clase_rol, $texto_rol] = $etiquetas_rol[(int) $fila['ROL_FK']]; ?>
                <tr>
                  <td>
                    <div class="usuario">
                      <span class="avatar <?= e(color_avatar((int) $fila['ID'])) ?>"><?= e(iniciales($fila['NOMBRES'], $fila['APELLIDOS'])) ?></span>
                      <span><?= e($fila['NOMBRES'] . ' ' . $fila['APELLIDOS']) ?></span>
                    </div>
                  </td>
                  <td><?= e($fila['CORREO'] ?? ((int) $fila['ROL_FK'] === ROL_CLIENTE ? '—' : 'Sin correo')) ?></td>
                  <td><span class="etiqueta-rol <?= e($clase_rol) ?>"><?= e($texto_rol) ?></span></td>
                  <td><span class="etiqueta-estado <?= $activo ? 'activo' : 'inactivo' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                  <td>
                    <a href="registrar-usuario.php?id=<?= (int) $fila['ID'] ?>" class="enlace">Editar</a>
<?php if ((int) $fila['ID'] !== $yo): ?>
                    ·
                    <form class="en-linea" action="gestion-usuarios.php" method="post">
                      <?= campo_csrf() ?>
                      <input type="hidden" name="id" value="<?= (int) $fila['ID'] ?>">
                      <input type="hidden" name="accion" value="<?= $activo ? 'desactivar' : 'activar' ?>">
                      <input type="hidden" name="filtro" value="<?= $filtro ?>">
                      <button type="submit" class="boton-enlace<?= $activo ? ' peligro' : '' ?>"><?= $activo ? 'Desactivar' : 'Activar' ?></button>
                    </form>
<?php endif; ?>
                  </td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
<?php mostrar_paginacion($pag); ?>
        </section>

      </div>
    </main>
  </div>

</body>
</html>

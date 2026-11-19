<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
$pagina_activa = 'clientes';

$pdo = db();
$busqueda = get_texto('busqueda');
$estado = get_texto('estado');
if (!in_array($estado, ['activo', 'vencido', 'sin-membresia', 'inactivo'], true)) {
    $estado = 'todos';
}

$condiciones = ['c.ROL_FK = 3'];
$params = [];
if ($busqueda !== '') {
    $condiciones[] = "(CONCAT(c.NOMBRES, ' ', c.APELLIDOS) LIKE ? OR c.IDENTIFICACION LIKE ?)";
    $like = '%' . like_escape($busqueda) . '%';
    array_push($params, $like, $like);
}
if ($estado !== 'todos') {
    $condiciones[] = condicion_estado_cliente($pdo, $estado);
}
$donde = ' WHERE ' . implode(' AND ', $condiciones);
$base = sql_clientes_base();

$cuenta = $pdo->prepare("SELECT COUNT(*) FROM ($base $donde) x");
$cuenta->execute($params);
$pag = paginar((int) $cuenta->fetchColumn(), 15);

$consulta = $pdo->prepare("$base $donde ORDER BY c.NOMBRES, c.APELLIDOS LIMIT {$pag['limite']} OFFSET {$pag['offset']}");
$consulta->execute($params);
$clientes = $consulta->fetchAll();

$opciones_estado = ['todos' => 'Todos', 'activo' => 'Activo', 'vencido' => 'Vencido', 'sin-membresia' => 'Sin membresía', 'inactivo' => 'Inactivo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - Gestión de clientes</title>
  <link rel="stylesheet" href="clientes.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
      <div class="pagina">

        <header class="encabezado">
          <div>
            <h1>Gestión de clientes</h1>
            <p class="subtitulo">Consulta, busca y administra los clientes del gimnasio</p>
          </div>
          <div class="acciones-encabezado">
            <a href="registrar-cliente.php" class="boton boton-primario">+ Nuevo cliente</a>
          </div>
        </header>

<?php mostrar_flash(); ?>

        <section class="tarjeta">
          <form class="fila-filtros" action="clientes.php" method="get">
            <div class="campo campo-buscador">
              <label for="busqueda">Buscar</label>
              <input type="text" id="busqueda" name="busqueda" placeholder="Nombre o número de documento..." value="<?= e($busqueda) ?>">
            </div>
            <div class="campo">
              <label for="estado">Estado</label>
              <select id="estado" name="estado">
<?php foreach ($opciones_estado as $valor => $texto): ?>
                <option value="<?= e($valor) ?>"<?= $estado === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="campo campo-boton">
              <button type="submit" class="boton boton-primario">Buscar</button>
            </div>
          </form>
        </section>

        <section class="tarjeta">
          <div class="tabla-contenedor">
            <table class="tabla">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Documento</th>
                  <th>Teléfono</th>
                  <th>Membresía</th>
                  <th>Vencimiento</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
<?php if (!$clientes): ?>
                <tr><td colspan="7" class="sin-datos">No se encontraron clientes.</td></tr>
<?php endif; ?>
<?php foreach ($clientes as $c): [$clase, $texto] = etiqueta_cliente(estado_cliente($c)); ?>
                <tr>
                  <td><?= e($c['NOMBRES'] . ' ' . $c['APELLIDOS']) ?></td>
                  <td><?= e($c['TIPO_ID'] . ' ' . $c['IDENTIFICACION']) ?></td>
                  <td><?= e($c['TELEFONO']) ?></td>
                  <td><?= e($c['TIPO_DE_PLAN'] ?? '—') ?></td>
                  <td><?= e(fecha_es($c['FECHA_VENCIMIENTO'])) ?></td>
                  <td><span class="etiqueta-estado <?= e($clase) ?>"><?= e($texto) ?></span></td>
                  <td><a href="ver-cliente.php?id=<?= (int) $c['ID'] ?>" class="enlace">Ver</a> · <a href="registrar-cliente.php?id=<?= (int) $c['ID'] ?>" class="enlace">Editar</a></td>
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

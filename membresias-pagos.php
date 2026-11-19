<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
$pagina_activa = 'membresias-pagos';

$pdo = db();
$busqueda = get_texto('busqueda');
$tipo = (int) get_texto('tipoMembresia');
$estado = get_texto('estado');
if (!in_array($estado, ['activo', 'vencido', 'inactivo'], true)) {
    $estado = 'todos';
}

$planes = $pdo->query('SELECT ID, TIPO_DE_PLAN FROM membresias ORDER BY PRECIO')->fetchAll();

$condiciones = ['1 = 1'];
$params = [];
if ($busqueda !== '') {
    $condiciones[] = "(CONCAT(c.NOMBRES, ' ', c.APELLIDOS) LIKE ? OR c.IDENTIFICACION LIKE ?)";
    $like = '%' . like_escape($busqueda) . '%';
    array_push($params, $like, $like);
}
if ($tipo > 0) {
    $condiciones[] = 'p.MEMBRESIA_FK = ?';
    $params[] = $tipo;
}
$hoy = $pdo->quote(hoy());
if ($estado === 'activo') {
    $condiciones[] = "p.ESTADO_FK = 1 AND p.FECHA_VENCIMIENTO >= $hoy";
} elseif ($estado === 'vencido') {
    $condiciones[] = "p.ESTADO_FK = 1 AND p.FECHA_VENCIMIENTO < $hoy";
} elseif ($estado === 'inactivo') {
    $condiciones[] = 'p.ESTADO_FK = 2';
}
$donde = ' WHERE ' . implode(' AND ', $condiciones);
$desde = 'FROM pagos p
          JOIN usuarios c ON c.ID = p.CLIENTE_FK
          JOIN membresias m ON m.ID = p.MEMBRESIA_FK
          JOIN medios_pago mp ON mp.ID = p.MEDIO_PAGO_FK';

$cuenta = $pdo->prepare("SELECT COUNT(*) $desde $donde");
$cuenta->execute($params);
$pag = paginar((int) $cuenta->fetchColumn(), 15);

$consulta = $pdo->prepare("SELECT p.ID, p.FECHA_PAGO, p.FECHA_VENCIMIENTO, p.VALOR, p.ESTADO_FK, c.ID AS CLIENTE_ID,
                                  c.NOMBRES, c.APELLIDOS, m.TIPO_DE_PLAN, mp.DESCRIPCION AS MEDIO
                           $desde $donde ORDER BY p.FECHA_PAGO DESC, p.ID DESC LIMIT {$pag['limite']} OFFSET {$pag['offset']}");
$consulta->execute($params);
$pagos = $consulta->fetchAll();

$membresias_activas = contar_clientes($pdo, 'activo');
$ingresos_mes = ingresos_entre($pdo, date('Y-m-01'), date('Y-m-t'));
$pagos_inactivos = (int) $pdo->query('SELECT COUNT(*) FROM pagos WHERE ESTADO_FK = 2')->fetchColumn();

$opciones_estado = ['todos' => 'Todos', 'activo' => 'Activo', 'vencido' => 'Vencido', 'inactivo' => 'Inactivo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - Membresías y pagos</title>
  <link rel="stylesheet" href="membresias-pagos.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
      <div class="pagina">

        <header class="encabezado">
          <div>
            <h1>Membresías y pagos</h1>
            <p class="subtitulo">Consulta el historial de pagos y el estado de las membresías</p>
          </div>
          <div class="acciones-encabezado">
            <a href="registrar-pago.php" class="boton boton-primario">+ Registrar pago</a>
          </div>
        </header>

<?php mostrar_flash(); ?>

        <section class="resumen">
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Membresías activas</span>
            <span class="numero-grande"><?= $membresias_activas ?></span>
          </div>
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Ingresos del mes</span>
            <span class="numero-grande"><?= e(dinero($ingresos_mes)) ?></span>
          </div>
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Pagos inactivos</span>
            <span class="numero-grande"><?= $pagos_inactivos ?></span>
          </div>
        </section>

        <section class="tarjeta">
          <form class="fila-filtros" action="membresias-pagos.php" method="get">
            <div class="campo campo-buscador">
              <label for="busqueda">Buscar</label>
              <input type="text" id="busqueda" name="busqueda" placeholder="Nombre o documento del cliente..." value="<?= e($busqueda) ?>">
            </div>
            <div class="campo">
              <label for="tipoMembresia">Membresía</label>
              <select id="tipoMembresia" name="tipoMembresia">
                <option value="0">Todas</option>
<?php foreach ($planes as $pl): ?>
                <option value="<?= (int) $pl['ID'] ?>"<?= $tipo === (int) $pl['ID'] ? ' selected' : '' ?>><?= e($pl['TIPO_DE_PLAN']) ?></option>
<?php endforeach; ?>
              </select>
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
              <button type="submit" class="boton boton-primario">Filtrar</button>
            </div>
          </form>
        </section>

        <section class="tarjeta">
          <h2>Historial de pagos</h2>
          <div class="tabla-contenedor">
            <table class="tabla">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Cliente</th>
                  <th>Membresía</th>
                  <th>Vencimiento</th>
                  <th>Valor</th>
                  <th>Método de pago</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
<?php if (!$pagos): ?>
                <tr><td colspan="7" class="sin-datos">No se encontraron pagos.</td></tr>
<?php endif; ?>
<?php foreach ($pagos as $p): [$clase, $texto] = etiqueta_pago($p); ?>
                <tr>
                  <td><?= e(fecha_es($p['FECHA_PAGO'])) ?></td>
                  <td><a href="ver-cliente.php?id=<?= (int) $p['CLIENTE_ID'] ?>" class="enlace"><?= e($p['NOMBRES'] . ' ' . $p['APELLIDOS']) ?></a></td>
                  <td><?= e($p['TIPO_DE_PLAN']) ?></td>
                  <td><?= e(fecha_es($p['FECHA_VENCIMIENTO'])) ?></td>
                  <td><?= e(dinero($p['VALOR'])) ?></td>
                  <td><?= e(ucfirst(strtolower($p['MEDIO']))) ?></td>
                  <td><span class="etiqueta-estado <?= e($clase) ?>"><?= e($texto) ?></span></td>
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

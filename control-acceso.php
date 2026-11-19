<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
$pagina_activa = 'control-acceso';

$pdo = db();

// Botón "Registrar ingreso": se vuelve a revisar todo en el servidor antes de guardar.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buscado = post_texto('busqueda');
    $volver = 'control-acceso.php' . ($buscado !== '' ? '?busqueda=' . urlencode($buscado) : '');
    exigir_csrf($volver);

    $r = registrar_ingreso($pdo, (int) post_texto('cliente_id'));
    if ($r['permitido']) {
        $nombre = $r['cliente']['NOMBRES'] . ' ' . $r['cliente']['APELLIDOS'];
        $extra = $r['tickets'] !== null ? ' · Tickets restantes: ' . $r['tickets_despues'] : '';
        flash('exito', 'Ingreso registrado: ' . $nombre . $extra . '.');
    } else {
        flash('error', 'No se registró el ingreso: ' . $r['motivo'] . '.');
    }
    redirigir($volver);
}

$busqueda = get_texto('busqueda');
$resultados = [];
if ($busqueda !== '') {
    $like = '%' . like_escape($busqueda) . '%';
    $s = $pdo->prepare("SELECT ID FROM usuarios WHERE ROL_FK = 3
                        AND (CONCAT(NOMBRES, ' ', APELLIDOS) LIKE ? OR IDENTIFICACION LIKE ?)
                        ORDER BY NOMBRES, APELLIDOS LIMIT 5");
    $s->execute([$like, $like]);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $cliente_id) {
        $resultados[] = evaluar_acceso($pdo, (int) $cliente_id);
    }
}

$accesos_hoy = accesos_entre($pdo, hoy(), hoy());
$activos = contar_clientes($pdo, 'activo');
$por_vencer = contar_por_vencer($pdo);
$lista_por_vencer = listar_por_vencer($pdo, 5);
$ultimos = $pdo->query('SELECT a.FECHA_REG, a.HORA_REG, a.TICKET, c.NOMBRES, c.APELLIDOS
                        FROM acceso a JOIN usuarios c ON c.ID = a.CLIENTE_FK
                        ORDER BY a.FECHA_REG DESC, a.HORA_REG DESC, a.ID DESC LIMIT 8')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - Control de acceso</title>
  <link rel="stylesheet" href="control-acceso.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
  <div class="pagina">

    <header class="encabezado">
      <h1>Control de acceso</h1>
      <p class="subtitulo">Verifica el estado de la membresía antes de permitir el ingreso al gimnasio</p>
    </header>

<?php mostrar_flash(); ?>

    <section class="tarjeta">
      <h2>Verificar acceso</h2>
      <p class="texto-ayuda">Busca al cliente por nombre o número de documento para validar su membresía</p>
      <form class="fila-busqueda" action="control-acceso.php" method="get">
        <input type="text" name="busqueda" placeholder="Buscar por nombre o número de documento..." value="<?= e($busqueda) ?>" autofocus>
        <button type="submit" class="boton boton-primario">Verificar acceso</button>
      </form>
    </section>

<?php if ($busqueda !== ''): ?>
    <section class="resultados-verificacion">
<?php if (!$resultados): ?>
      <p class="alerta alerta-error">No se encontró ningún cliente con ese nombre o documento.</p>
<?php endif; ?>
<?php foreach ($resultados as $r): $c = $r['cliente']; $ok = $r['permitido']; ?>
      <div class="resultado <?= $ok ? 'resultado-permitido' : 'resultado-denegado' ?>">
        <div class="resultado-cabecera">
          <span class="avatar <?= $ok ? 'avatar-verde' : 'avatar-rojo' ?>"><?= e(iniciales($c['NOMBRES'], $c['APELLIDOS'])) ?></span>
          <div>
            <p class="nombre-cliente"><?= e($c['NOMBRES'] . ' ' . $c['APELLIDOS']) ?></p>
            <p class="documento-cliente"><?= e($c['TIPO_ID'] . ' ' . $c['IDENTIFICACION']) ?></p>
          </div>
        </div>
        <div class="resultado-datos">
          <div>
            <span class="etiqueta">Membresía</span>
            <span class="valor"><?= e($r['pago']['TIPO_DE_PLAN'] ?? '—') ?></span>
          </div>
          <div>
            <span class="etiqueta">Vencimiento</span>
            <span class="valor"><?= e(fecha_es($r['pago']['FECHA_VENCIMIENTO'] ?? null)) ?></span>
          </div>
<?php if ($r['tickets'] !== null): ?>
          <div>
            <span class="etiqueta">Tickets</span>
            <span class="valor"><?= (int) $r['tickets'] ?></span>
          </div>
<?php endif; ?>
        </div>
        <div class="estado-acceso <?= $ok ? 'estado-permitido' : 'estado-denegado' ?>">
          <?= $ok ? '✔ Acceso permitido' : '✖ Acceso denegado' ?>
          <span class="detalle"><?= e($r['motivo']) ?></span>
        </div>
<?php if ($ok): ?>
        <form action="control-acceso.php" method="post">
          <?= campo_csrf() ?>
          <input type="hidden" name="cliente_id" value="<?= (int) $c['ID'] ?>">
          <input type="hidden" name="busqueda" value="<?= e($busqueda) ?>">
          <button type="submit" class="boton boton-primario">Registrar ingreso</button>
        </form>
<?php endif; ?>
      </div>
<?php endforeach; ?>
    </section>
<?php endif; ?>

    <section class="resumen">
      <div class="tarjeta tarjeta-resumen">
        <span class="etiqueta">Accesos de hoy</span>
        <span class="numero-grande"><?= $accesos_hoy ?></span>
      </div>
      <div class="tarjeta tarjeta-resumen">
        <span class="etiqueta">Clientes activos</span>
        <span class="numero-grande"><?= $activos ?></span>
      </div>
      <div class="tarjeta tarjeta-resumen">
        <span class="etiqueta">Membresías por vencer</span>
        <span class="numero-grande"><?= $por_vencer ?></span>
      </div>
    </section>

    <section class="doble-columna">

      <div class="tarjeta">
        <h2>Últimos accesos</h2>
        <table class="tabla">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Tickets restantes</th>
            </tr>
          </thead>
          <tbody>
<?php if (!$ultimos): ?>
            <tr><td colspan="4" class="sin-datos">Todavía no hay accesos registrados.</td></tr>
<?php endif; ?>
<?php foreach ($ultimos as $a): ?>
            <tr>
              <td><?= e($a['NOMBRES'] . ' ' . $a['APELLIDOS']) ?></td>
              <td><?= e(fecha_es($a['FECHA_REG'])) ?></td>
              <td><?= e(hora_corta($a['HORA_REG'])) ?></td>
              <td><?= (int) $a['TICKET'] > 0 ? (int) $a['TICKET'] : '—' ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="tarjeta">
        <h2>Alertas</h2>
        <p class="texto-ayuda">Membresías próximas a vencer</p>
        <ul class="lista-alertas">
<?php if (!$lista_por_vencer): ?>
          <li><span class="documento-cliente">Ninguna vence en los próximos <?= DIAS_POR_VENCER ?> días.</span></li>
<?php endif; ?>
<?php foreach ($lista_por_vencer as $c): ?>
          <li>
            <span class="avatar <?= e(color_avatar((int) $c['ID'])) ?>"><?= e(iniciales($c['NOMBRES'], $c['APELLIDOS'])) ?></span>
            <div class="info-alerta">
              <p class="nombre-cliente"><?= e($c['NOMBRES'] . ' ' . $c['APELLIDOS']) ?></p>
              <p class="documento-cliente">Vence <?= e(fecha_es($c['FECHA_VENCIMIENTO'])) ?></p>
            </div>
            <span class="dias-restantes"><?= e(texto_dias($c['FECHA_VENCIMIENTO'])) ?></span>
          </li>
<?php endforeach; ?>
        </ul>
      </div>

    </section>

  </div>
    </main>
  </div>

</body>
</html>

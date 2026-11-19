<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
$pagina_activa = 'dashboard';

$pdo = db();
$activos = contar_clientes($pdo, 'activo');
$ingresos_mes = ingresos_entre($pdo, date('Y-m-01'), date('Y-m-t'));
$accesos_hoy = accesos_entre($pdo, hoy(), hoy());
$por_vencer = contar_por_vencer($pdo);

$ultimos_pagos = $pdo->query(
    'SELECT p.ID, p.ESTADO_FK, p.FECHA_VENCIMIENTO, p.VALOR, c.NOMBRES, c.APELLIDOS, m.TIPO_DE_PLAN
     FROM pagos p
     JOIN usuarios c ON c.ID = p.CLIENTE_FK
     JOIN membresias m ON m.ID = p.MEMBRESIA_FK
     ORDER BY p.FECHA_PAGO DESC, p.ID DESC LIMIT 5'
)->fetchAll();
$lista_por_vencer = listar_por_vencer($pdo, 5);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - Dashboard</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
      <div class="pagina">

        <header class="encabezado">
          <div>
            <h1>Dashboard</h1>
            <p class="subtitulo">Resumen general del gimnasio</p>
          </div>
          <div class="acciones-encabezado">
            <a href="registrar-cliente.php" class="boton boton-secundario">Registrar cliente</a>
            <a href="registrar-pago.php" class="boton boton-primario">Registrar pago</a>
          </div>
        </header>

<?php mostrar_flash(); ?>

        <section class="resumen">
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Clientes activos</span>
            <span class="numero-grande"><?= $activos ?></span>
          </div>
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Ingresos del mes</span>
            <span class="numero-grande"><?= e(dinero($ingresos_mes)) ?></span>
          </div>
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Accesos de hoy</span>
            <span class="numero-grande"><?= $accesos_hoy ?></span>
          </div>
          <div class="tarjeta tarjeta-resumen">
            <span class="etiqueta">Membresías por vencer</span>
            <span class="numero-grande"><?= $por_vencer ?></span>
          </div>
        </section>

        <section class="doble-columna">

          <div class="tarjeta">
            <div class="cabecera-tarjeta">
              <h2>Últimos pagos</h2>
              <a href="membresias-pagos.php" class="enlace">Ver todos</a>
            </div>
            <table class="tabla">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Membresía</th>
                  <th>Valor</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
<?php if (!$ultimos_pagos): ?>
                <tr><td colspan="4" class="sin-datos">Todavía no hay pagos registrados.</td></tr>
<?php endif; ?>
<?php foreach ($ultimos_pagos as $p): [$clase, $texto] = etiqueta_pago($p); ?>
                <tr>
                  <td><?= e($p['NOMBRES'] . ' ' . $p['APELLIDOS']) ?></td>
                  <td><?= e($p['TIPO_DE_PLAN']) ?></td>
                  <td><?= e(dinero($p['VALOR'])) ?></td>
                  <td><span class="etiqueta-estado <?= e($clase) ?>"><?= e($texto) ?></span></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="tarjeta">
            <div class="cabecera-tarjeta">
              <h2>Por vencer</h2>
              <a href="control-acceso.php" class="enlace">Control de acceso</a>
            </div>
            <ul class="lista-alertas">
<?php if (!$lista_por_vencer): ?>
              <li><span class="documento-cliente">Ninguna membresía vence en los próximos <?= DIAS_POR_VENCER ?> días.</span></li>
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

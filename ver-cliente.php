<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
$pagina_activa = 'clientes';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$s = $pdo->prepare(sql_clientes_base() . ' WHERE c.ID = ? AND c.ROL_FK = 3');
$s->execute([$id]);
$c = $s->fetch();
if (!$c) {
    flash('error', 'No se encontró ese cliente.');
    redirigir('clientes.php');
}

$s = $pdo->prepare('SELECT FECHA_DE_NACIMIENTO, DIRECCION FROM usuarios WHERE ID = ?');
$s->execute([$id]);
$extra = $s->fetch();

$estado = estado_cliente($c);
[$clase_estado, $texto_estado] = etiqueta_cliente($estado);

$tickets = null;
if (!empty($c['PAGO_ID']) && $c['UNIDAD'] === 'TICKET') {
    $tickets = tickets_restantes($pdo, $id, $c);
}

$s = $pdo->prepare('SELECT p.ID, p.FECHA_PAGO, p.FECHA_INICIO, p.FECHA_VENCIMIENTO, p.VALOR, p.ESTADO_FK, m.TIPO_DE_PLAN, mp.DESCRIPCION AS MEDIO
                    FROM pagos p JOIN membresias m ON m.ID = p.MEMBRESIA_FK JOIN medios_pago mp ON mp.ID = p.MEDIO_PAGO_FK
                    WHERE p.CLIENTE_FK = ? ORDER BY p.FECHA_PAGO DESC, p.ID DESC');
$s->execute([$id]);
$pagos = $s->fetchAll();

$s = $pdo->prepare('SELECT FECHA_REG, HORA_REG, TICKET FROM acceso WHERE CLIENTE_FK = ? ORDER BY FECHA_REG DESC, HORA_REG DESC, ID DESC LIMIT 10');
$s->execute([$id]);
$accesos = $s->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - <?= e($c['NOMBRES'] . ' ' . $c['APELLIDOS']) ?></title>
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
            <h1><?= e($c['NOMBRES'] . ' ' . $c['APELLIDOS']) ?></h1>
            <p class="subtitulo"><?= e($c['TIPO_ID'] . ' ' . $c['IDENTIFICACION']) ?> · <span class="etiqueta-estado <?= e($clase_estado) ?>"><?= e($texto_estado) ?></span></p>
          </div>
          <div class="acciones-encabezado">
            <a href="clientes.php" class="boton boton-secundario">Volver</a>
            <a href="registrar-cliente.php?id=<?= $id ?>" class="boton boton-secundario">Editar</a>
            <a href="registrar-pago.php?cliente=<?= $id ?>" class="boton boton-primario">Registrar pago</a>
          </div>
        </header>

<?php mostrar_flash(); ?>

        <section class="tarjeta">
          <h2>Datos del cliente</h2>
          <dl class="ficha">
            <div><dt>Documento</dt><dd><?= e($c['TIPO_ID'] . ' ' . $c['IDENTIFICACION']) ?></dd></div>
            <div><dt>Teléfono</dt><dd><?= e($c['TELEFONO']) ?></dd></div>
            <div><dt>Fecha de nacimiento</dt><dd><?= e(fecha_es($extra['FECHA_DE_NACIMIENTO'])) ?></dd></div>
            <div><dt>Dirección</dt><dd><?= e($extra['DIRECCION']) ?></dd></div>
          </dl>
        </section>

        <section class="tarjeta">
          <h2>Membresía actual</h2>
<?php if (empty($c['PAGO_ID'])): ?>
          <p class="texto-ayuda">Este cliente todavía no tiene una membresía activa.</p>
<?php else: ?>
          <dl class="ficha">
            <div><dt>Plan</dt><dd><?= e($c['TIPO_DE_PLAN']) ?></dd></div>
            <div><dt>Vigencia</dt><dd><?= e(fecha_es($c['FECHA_INICIO'])) ?> a <?= e(fecha_es($c['FECHA_VENCIMIENTO'])) ?></dd></div>
<?php if ($tickets !== null): ?>
            <div><dt>Tickets disponibles</dt><dd><?= $tickets ?> de <?= (int) $c['TICKETS_PAGO'] ?></dd></div>
<?php endif; ?>
          </dl>
<?php endif; ?>
        </section>

        <section class="tarjeta">
          <h2>Historial de pagos</h2>
          <div class="tabla-contenedor">
            <table class="tabla">
              <thead>
                <tr><th>Fecha de pago</th><th>Membresía</th><th>Vigencia</th><th>Valor</th><th>Método de pago</th><th>Estado</th></tr>
              </thead>
              <tbody>
<?php if (!$pagos): ?>
                <tr><td colspan="6" class="sin-datos">Sin pagos registrados.</td></tr>
<?php endif; ?>
<?php foreach ($pagos as $p): [$clase, $texto] = etiqueta_pago($p); ?>
                <tr>
                  <td><?= e(fecha_es($p['FECHA_PAGO'])) ?></td>
                  <td><?= e($p['TIPO_DE_PLAN']) ?></td>
                  <td><?= e(fecha_es($p['FECHA_INICIO'])) ?> a <?= e(fecha_es($p['FECHA_VENCIMIENTO'])) ?></td>
                  <td><?= e(dinero($p['VALOR'])) ?></td>
                  <td><?= e(ucfirst(strtolower($p['MEDIO']))) ?></td>
                  <td><span class="etiqueta-estado <?= e($clase) ?>"><?= e($texto) ?></span></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="tarjeta">
          <h2>Últimos accesos</h2>
          <div class="tabla-contenedor">
            <table class="tabla">
              <thead>
                <tr><th>Fecha</th><th>Hora</th><th>Tickets restantes</th></tr>
              </thead>
              <tbody>
<?php if (!$accesos): ?>
                <tr><td colspan="3" class="sin-datos">Sin accesos registrados.</td></tr>
<?php endif; ?>
<?php foreach ($accesos as $a): ?>
                <tr>
                  <td><?= e(fecha_es($a['FECHA_REG'])) ?></td>
                  <td><?= e(hora_corta($a['HORA_REG'])) ?></td>
                  <td><?= (int) $a['TICKET'] > 0 ? (int) $a['TICKET'] : '—' ?></td>
                </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

      </div>
    </main>
  </div>

</body>
</html>

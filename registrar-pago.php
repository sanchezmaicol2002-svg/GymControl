<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
$pagina_activa = 'membresias-pagos';

$pdo = db();
$clientes = $pdo->query('SELECT ID, NOMBRES, APELLIDOS, IDENTIFICACION FROM usuarios
                         WHERE ROL_FK = 3 AND ESTADO_FK = 1 ORDER BY NOMBRES, APELLIDOS')->fetchAll();
$membresias = $pdo->query('SELECT ID, TIPO_DE_PLAN, DURACION, UNIDAD, PRECIO FROM membresias
                           WHERE ESTADO_FK = 1 ORDER BY PRECIO')->fetchAll();
$medios = $pdo->query('SELECT ID, DESCRIPCION FROM medios_pago ORDER BY ID')->fetchAll();
$iconos_medio = ['EFECTIVO' => '💵', 'TRANSFERENCIA' => '🏦', 'TARJETA' => '💳'];

$errores = [];
$d = [
    'cliente' => (int) ($_GET['cliente'] ?? 0), 'membresia' => 0, 'inicio' => hoy(), 'vencimiento' => '',
    'valor' => '', 'medio' => (int) ($medios[0]['ID'] ?? 1), 'estado' => ESTADO_ACTIVO,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('registrar-pago.php');
    $d = [
        'cliente'     => (int) post_texto('cliente'),
        'membresia'   => (int) post_texto('membresia'),
        'inicio'      => post_texto('fecha_inicio'),
        'vencimiento' => post_texto('fecha_vencimiento'),
        'valor'       => post_texto('valor'),
        'medio'       => (int) post_texto('medio'),
        'estado'      => (int) post_texto('estado'),
    ];

    $s = $pdo->prepare('SELECT ID, NOMBRES, APELLIDOS FROM usuarios WHERE ID = ? AND ROL_FK = 3 AND ESTADO_FK = 1');
    $s->execute([$d['cliente']]);
    $cliente = $s->fetch();
    if (!$cliente) {
        $errores['cliente'] = 'Selecciona un cliente activo.';
    }

    $s = $pdo->prepare('SELECT ID, TIPO_DE_PLAN, DURACION, UNIDAD FROM membresias WHERE ID = ? AND ESTADO_FK = 1');
    $s->execute([$d['membresia']]);
    $membresia = $s->fetch();
    if (!$membresia) {
        $errores['membresia'] = 'Selecciona una membresía.';
    }

    if (!validar_fecha($d['inicio'])) {
        $errores['fecha_inicio'] = 'Escribe una fecha de inicio válida.';
    }
    if ($d['vencimiento'] === '' && $membresia && !isset($errores['fecha_inicio'])) {
        $d['vencimiento'] = calcular_vencimiento($d['inicio'], (int) $membresia['DURACION'], $membresia['UNIDAD']);
    }
    if (!validar_fecha($d['vencimiento'])) {
        $errores['fecha_vencimiento'] = 'Escribe una fecha de vencimiento válida.';
    } elseif (!isset($errores['fecha_inicio']) && $d['vencimiento'] < $d['inicio']) {
        $errores['fecha_vencimiento'] = 'El vencimiento no puede ser antes del inicio.';
    }

    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $d['valor']) || (float) $d['valor'] <= 0) {
        $errores['valor'] = 'Escribe un valor mayor a cero (solo números).';
    }

    if (!in_array($d['medio'], array_map(fn($m) => (int) $m['ID'], $medios), true)) {
        $errores['medio'] = 'Selecciona el método de pago.';
    }
    if (!in_array($d['estado'], [ESTADO_ACTIVO, ESTADO_INACTIVO], true)) {
        $errores['estado'] = 'Selecciona un estado.';
    }

    if (!$errores) {
        $tickets = $membresia['UNIDAD'] === 'TICKET' ? (int) $membresia['DURACION'] : 0;
        $pdo->prepare('INSERT INTO pagos (CLIENTE_FK, MEMBRESIA_FK, FECHA_PAGO, EMPLEADO_FK, FECHA_INICIO, FECHA_VENCIMIENTO,
                       TICKET, VALOR, ESTADO_FK, MEDIO_PAGO_FK) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $d['cliente'], $d['membresia'], hoy(), usuario_actual()['id'], $d['inicio'], $d['vencimiento'],
                $tickets, $d['valor'], $d['estado'], $d['medio'],
            ]);
        flash('exito', 'Pago registrado: ' . $cliente['NOMBRES'] . ' ' . $cliente['APELLIDOS'] . ' · '
            . $membresia['TIPO_DE_PLAN'] . ' · ' . dinero($d['valor']) . '.');
        redirigir('membresias-pagos.php');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - Registrar pago</title>
  <link rel="stylesheet" href="registrar-pago.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
  <div class="contenedor-formulario">
    <form class="tarjeta-formulario" action="registrar-pago.php" method="post">

      <h1>Registrar pago</h1>
      <p class="subtitulo">Completa los datos para registrar el pago de una membresía</p>

<?php mostrar_flash(); ?>
      <?= campo_csrf() ?>

      <div class="fila">
        <div class="campo campo-ancho">
          <label for="cliente">Cliente</label>
          <select id="cliente" name="cliente" required>
            <option value="">Selecciona un cliente...</option>
<?php foreach ($clientes as $c): ?>
            <option value="<?= (int) $c['ID'] ?>"<?= $d['cliente'] === (int) $c['ID'] ? ' selected' : '' ?>><?= e($c['NOMBRES'] . ' ' . $c['APELLIDOS'] . ' · ' . $c['IDENTIFICACION']) ?></option>
<?php endforeach; ?>
          </select>
          <?php error_campo($errores, 'cliente'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo campo-ancho">
          <label for="membresia">Tipo de membresía</label>
          <select id="membresia" name="membresia" required>
            <option value="">Seleccione...</option>
<?php foreach ($membresias as $m): ?>
            <option value="<?= (int) $m['ID'] ?>" data-duracion="<?= (int) $m['DURACION'] ?>" data-unidad="<?= e($m['UNIDAD']) ?>" data-precio="<?= (int) round($m['PRECIO']) ?>"<?= $d['membresia'] === (int) $m['ID'] ? ' selected' : '' ?>><?= e($m['TIPO_DE_PLAN'] . ' · ' . dinero($m['PRECIO'])) ?></option>
<?php endforeach; ?>
          </select>
          <?php error_campo($errores, 'membresia'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo">
          <label for="fecha_inicio">Fecha de inicio</label>
          <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= e($d['inicio']) ?>" required>
          <?php error_campo($errores, 'fecha_inicio'); ?>
        </div>

        <div class="campo">
          <label for="fecha_vencimiento">Fecha de vencimiento</label>
          <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" value="<?= e($d['vencimiento']) ?>" required>
          <?php error_campo($errores, 'fecha_vencimiento'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo campo-ancho">
          <label for="valor">Valor</label>
          <input type="number" id="valor" name="valor" placeholder="Ej. 85900" min="1" step="1" value="<?= e($d['valor']) ?>" required>
          <?php error_campo($errores, 'valor'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo campo-ancho">
          <label>Método de pago</label>
          <div class="grupo-metodo-pago">
<?php foreach ($medios as $m): ?>
            <label class="opcion-metodo">
              <input type="radio" name="medio" value="<?= (int) $m['ID'] ?>"<?= $d['medio'] === (int) $m['ID'] ? ' checked' : '' ?>>
              <span><?= e(($iconos_medio[$m['DESCRIPCION']] ?? '💳') . ' ' . ucfirst(strtolower($m['DESCRIPCION']))) ?></span>
            </label>
<?php endforeach; ?>
          </div>
          <?php error_campo($errores, 'medio'); ?>
        </div>
      </div>

      <div class="fila">
        <div class="campo campo-ancho">
          <label for="estado">Estado del pago</label>
          <select id="estado" name="estado">
            <option value="1"<?= $d['estado'] === 1 ? ' selected' : '' ?>>Activo</option>
            <option value="2"<?= $d['estado'] === 2 ? ' selected' : '' ?>>Inactivo</option>
          </select>
          <small class="ayuda-campo">Un pago inactivo (por ejemplo, incompleto) no cuenta como membresía vigente.</small>
          <?php error_campo($errores, 'estado'); ?>
        </div>
      </div>

      <div class="acciones">
        <a href="membresias-pagos.php" class="boton boton-secundario">Cancelar</a>
        <button type="submit" class="boton boton-primario">Registrar pago</button>
      </div>

    </form>
  </div>
    </main>
  </div>

  <script>
    // Al elegir la membresía se llenan solos el valor y el vencimiento (se pueden cambiar).
    (function () {
      var membresia = document.getElementById('membresia');
      var inicio = document.getElementById('fecha_inicio');
      var vence = document.getElementById('fecha_vencimiento');
      var valor = document.getElementById('valor');
      var DIAS_TIQUETERA = <?= DIAS_TIQUETERA ?>;
      var venceManual = vence.value !== '';

      vence.addEventListener('input', function () { venceManual = true; });

      function sumarDias(texto, dias) {
        var p = texto.split('-');
        var f = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]) + dias * 86400000);
        return f.toISOString().slice(0, 10);
      }

      function calcular() {
        var op = membresia.options[membresia.selectedIndex];
        if (!op || !op.value || !inicio.value || venceManual) { return; }
        var duracion = parseInt(op.getAttribute('data-duracion'), 10);
        var dias = op.getAttribute('data-unidad') === 'TICKET' ? DIAS_TIQUETERA : (duracion <= 1 ? 0 : duracion);
        vence.value = sumarDias(inicio.value, dias);
      }

      membresia.addEventListener('change', function () {
        var op = membresia.options[membresia.selectedIndex];
        venceManual = false;
        if (op && op.value) { valor.value = op.getAttribute('data-precio'); }
        calcular();
      });
      inicio.addEventListener('change', calcular);
    })();
  </script>

</body>
</html>

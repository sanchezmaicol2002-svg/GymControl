<?php
require_once __DIR__ . '/includes/auth.php';
requerir_login();
$pagina_activa = 'reportes';

$pdo = db();

// ---------------------------------------------------------------- Filtros
$inicio = get_texto('fechaInicial');
$fin = get_texto('fechaFinal');
if (!validar_fecha($inicio)) {
    $inicio = date('Y-m-01');
}
if (!validar_fecha($fin)) {
    $fin = hoy();
}
if ($inicio > $fin) {
    [$inicio, $fin] = [$fin, $inicio];
}
$tipo = get_texto('tipoReporte');
if (!in_array($tipo, ['ingresos', 'clientes', 'membresias', 'accesos'], true)) {
    $tipo = 'ingresos';
}
$estado = get_texto('estado');
if (!in_array($estado, ['activo', 'vencido', 'sin-membresia', 'inactivo'], true)) {
    $estado = 'todos';
}
$filtros = ['fechaInicial' => $inicio, 'fechaFinal' => $fin, 'tipoReporte' => $tipo, 'estado' => $estado];

// ---------------------------------------------------------------- Datos del reporte
/**
 * Arma la tabla del reporte elegido. Cada celda es un texto o un arreglo
 * ['t' => texto, 'clase' => clase CSS de la etiqueta de estado].
 */
function datos_reporte(PDO $pdo, string $tipo, string $inicio, string $fin, string $estado, int $limite): array
{
    $hoy = $pdo->quote(hoy());
    $r = ['titulo' => '', 'columnas' => [], 'filas' => [], 'pie' => ''];

    if ($tipo === 'ingresos') {
        $r['titulo'] = 'Resumen de pagos';
        $r['columnas'] = ['Fecha', 'Cliente', 'Membresía', 'Valor', 'Método de pago', 'Estado'];
        $extra = '';
        if ($estado === 'activo') {
            $extra = " AND p.ESTADO_FK = 1 AND p.FECHA_VENCIMIENTO >= $hoy";
        } elseif ($estado === 'vencido') {
            $extra = " AND p.ESTADO_FK = 1 AND p.FECHA_VENCIMIENTO < $hoy";
        } elseif ($estado === 'inactivo') {
            $extra = ' AND p.ESTADO_FK = 2';
        }
        $s = $pdo->prepare("SELECT p.FECHA_PAGO, p.FECHA_VENCIMIENTO, p.VALOR, p.ESTADO_FK, c.NOMBRES, c.APELLIDOS,
                                   m.TIPO_DE_PLAN, mp.DESCRIPCION AS MEDIO
                            FROM pagos p JOIN usuarios c ON c.ID = p.CLIENTE_FK
                            JOIN membresias m ON m.ID = p.MEMBRESIA_FK JOIN medios_pago mp ON mp.ID = p.MEDIO_PAGO_FK
                            WHERE p.FECHA_PAGO BETWEEN ? AND ? $extra
                            ORDER BY p.FECHA_PAGO DESC, p.ID DESC LIMIT $limite");
        $s->execute([$inicio, $fin]);
        $total = 0;
        foreach ($s->fetchAll() as $p) {
            [$clase, $texto] = etiqueta_pago($p);
            if ((int) $p['ESTADO_FK'] === ESTADO_ACTIVO) {
                $total += (float) $p['VALOR'];
            }
            $r['filas'][] = [fecha_es($p['FECHA_PAGO']), $p['NOMBRES'] . ' ' . $p['APELLIDOS'], $p['TIPO_DE_PLAN'],
                dinero($p['VALOR']), ucfirst(strtolower($p['MEDIO'])), ['t' => $texto, 'clase' => $clase]];
        }
        $r['pie'] = 'Total de pagos activos: ' . dinero($total);
    } elseif ($tipo === 'clientes') {
        $r['titulo'] = 'Estado de los clientes';
        $r['columnas'] = ['Cliente', 'Documento', 'Teléfono', 'Membresía', 'Vencimiento', 'Estado'];
        $donde = 'c.ROL_FK = 3' . ($estado !== 'todos' ? ' AND ' . condicion_estado_cliente($pdo, $estado) : '');
        foreach ($pdo->query(sql_clientes_base() . " WHERE $donde ORDER BY c.NOMBRES, c.APELLIDOS LIMIT $limite")->fetchAll() as $c) {
            [$clase, $texto] = etiqueta_cliente(estado_cliente($c));
            $r['filas'][] = [$c['NOMBRES'] . ' ' . $c['APELLIDOS'], $c['TIPO_ID'] . ' ' . $c['IDENTIFICACION'], $c['TELEFONO'],
                $c['TIPO_DE_PLAN'] ?? '—', fecha_es($c['FECHA_VENCIMIENTO']), ['t' => $texto, 'clase' => $clase]];
        }
        $r['pie'] = 'Total de clientes en la lista: ' . count($r['filas']);
    } elseif ($tipo === 'membresias') {
        $r['titulo'] = 'Membresías vendidas';
        $r['columnas'] = ['Membresía', 'Cantidad', 'Ingresos'];
        $s = $pdo->prepare('SELECT m.TIPO_DE_PLAN, COUNT(*) AS CANTIDAD, SUM(p.VALOR) AS TOTAL
                            FROM pagos p JOIN membresias m ON m.ID = p.MEMBRESIA_FK
                            WHERE p.ESTADO_FK = 1 AND p.FECHA_PAGO BETWEEN ? AND ?
                            GROUP BY m.ID, m.TIPO_DE_PLAN ORDER BY CANTIDAD DESC, m.TIPO_DE_PLAN');
        $s->execute([$inicio, $fin]);
        $cantidad = 0;
        $suma = 0;
        foreach ($s->fetchAll() as $m) {
            $cantidad += (int) $m['CANTIDAD'];
            $suma += (float) $m['TOTAL'];
            $r['filas'][] = [$m['TIPO_DE_PLAN'], (string) $m['CANTIDAD'], dinero($m['TOTAL'])];
        }
        $r['pie'] = 'Total: ' . $cantidad . ' membresías · ' . dinero($suma);
    } else {
        $r['titulo'] = 'Accesos registrados';
        $r['columnas'] = ['Fecha', 'Hora', 'Cliente', 'Documento', 'Tickets restantes'];
        $s = $pdo->prepare("SELECT a.FECHA_REG, a.HORA_REG, a.TICKET, c.NOMBRES, c.APELLIDOS, c.IDENTIFICACION, t.DESCRIPCION AS TIPO_ID
                            FROM acceso a JOIN usuarios c ON c.ID = a.CLIENTE_FK JOIN tipo_id t ON t.ID = c.TIPO_ID_FK
                            WHERE a.FECHA_REG BETWEEN ? AND ?
                            ORDER BY a.FECHA_REG DESC, a.HORA_REG DESC, a.ID DESC LIMIT $limite");
        $s->execute([$inicio, $fin]);
        foreach ($s->fetchAll() as $a) {
            $r['filas'][] = [fecha_es($a['FECHA_REG']), hora_corta($a['HORA_REG']), $a['NOMBRES'] . ' ' . $a['APELLIDOS'],
                $a['TIPO_ID'] . ' ' . $a['IDENTIFICACION'], (int) $a['TICKET'] > 0 ? (string) $a['TICKET'] : '—'];
        }
        $r['pie'] = 'Total de accesos: ' . count($r['filas']);
    }
    return $r;
}

// ---------------------------------------------------------------- Exportar a Excel (CSV)
if (get_texto('exportar') === 'csv') {
    $reporte = datos_reporte($pdo, $tipo, $inicio, $fin, $estado, 50000);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte-' . $tipo . '-' . hoy() . '.csv"');
    header('Cache-Control: no-store');
    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF");   // para que Excel respete las tildes
    fputcsv($salida, [$reporte['titulo'] . ' (' . $inicio . ' a ' . $fin . ')'], ';');
    fputcsv($salida, $reporte['columnas'], ';');
    foreach ($reporte['filas'] as $fila) {
        fputcsv($salida, array_map(fn($celda) => is_array($celda) ? $celda['t'] : $celda, $fila), ';');
    }
    fputcsv($salida, [$reporte['pie']], ';');
    fclose($salida);
    exit;
}

$reporte = datos_reporte($pdo, $tipo, $inicio, $fin, $estado, 300);

// ---------------------------------------------------------------- Tarjetas de resumen
$dias = (int) round((strtotime($fin) - strtotime($inicio)) / 86400) + 1;
$anterior_fin = date('Y-m-d', strtotime($inicio . ' -1 day'));
$anterior_inicio = date('Y-m-d', strtotime($anterior_fin . ' -' . ($dias - 1) . ' days'));

$clientes_registrados = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE ROL_FK = 3')->fetchColumn();
$membresias_activas = contar_clientes($pdo, 'activo');
$ingresos = ingresos_entre($pdo, $inicio, $fin);
$ingresos_antes = ingresos_entre($pdo, $anterior_inicio, $anterior_fin);
$accesos = accesos_entre($pdo, $inicio, $fin);
$accesos_antes = accesos_entre($pdo, $anterior_inicio, $anterior_fin);

function variacion(float $actual, float $anterior): string
{
    if ($anterior <= 0) {
        return '';
    }
    $pct = (int) round(($actual - $anterior) / $anterior * 100);
    return '<span class="variacion ' . ($pct >= 0 ? 'positiva' : 'negativa') . '">'
        . ($pct >= 0 ? '↗ +' : '↘ ') . $pct . '% vs período anterior</span>';
}

// ---------------------------------------------------------------- Gráfico: ingresos de los últimos 6 meses
$meses = [];
for ($i = 5; $i >= 0; $i--) {
    $t = strtotime("first day of -$i month");
    $meses[date('Y-m', $t)] = ['mes' => mes_corto((int) date('n', $t)), 'total' => 0.0];
}
$s = $pdo->prepare("SELECT DATE_FORMAT(FECHA_PAGO, '%Y-%m') AS ym, SUM(VALOR) AS total FROM pagos
                    WHERE ESTADO_FK = 1 AND FECHA_PAGO >= ? GROUP BY ym");
$s->execute([date('Y-m-01', strtotime('first day of -5 month'))]);
foreach ($s->fetchAll() as $fila) {
    if (isset($meses[$fila['ym']])) {
        $meses[$fila['ym']]['total'] = (float) $fila['total'];
    }
}
$maximo = max(array_column($meses, 'total'));

// ---------------------------------------------------------------- Membresías por tipo (en el período)
$s = $pdo->prepare('SELECT m.TIPO_DE_PLAN, COUNT(*) AS n FROM pagos p JOIN membresias m ON m.ID = p.MEMBRESIA_FK
                    WHERE p.ESTADO_FK = 1 AND p.FECHA_PAGO BETWEEN ? AND ?
                    GROUP BY m.ID, m.TIPO_DE_PLAN ORDER BY n DESC, m.TIPO_DE_PLAN');
$s->execute([$inicio, $fin]);
$por_tipo = $s->fetchAll();
$total_tipo = array_sum(array_column($por_tipo, 'n'));
$colores_plan = ['mensual' => 'azul', 'trimestral' => 'verde', 'semestral' => 'morado', 'bimestral' => 'celeste',
    'tiquetera' => 'rosa', 'día' => 'verdeclaro', 'dia' => 'verdeclaro'];
$extras = ['azul', 'verde', 'morado', 'celeste'];

$opciones_estado = ['todos' => 'Todos', 'activo' => 'Activo', 'vencido' => 'Vencido', 'sin-membresia' => 'Sin membresía', 'inactivo' => 'Inactivo'];
$opciones_tipo = ['ingresos' => 'Ingresos', 'clientes' => 'Clientes', 'membresias' => 'Membresías', 'accesos' => 'Accesos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GymControl - Reportes</title>
  <link rel="stylesheet" href="reportes.css">
  <link rel="stylesheet" href="navegacion.css">
</head>
<body>

  <div class="layout">
<?php include __DIR__ . '/includes/menu.php'; ?>
    <main class="contenido-principal">
  <div class="pagina">

    <header class="encabezado">
      <div>
        <h1>Reportes</h1>
        <p class="subtitulo">Consulta y analiza la información del gimnasio · <?= e(fecha_es($inicio)) ?> a <?= e(fecha_es($fin)) ?></p>
      </div>
      <div class="acciones-encabezado no-imprimir">
        <button type="button" class="boton boton-secundario" onclick="window.print()">Exportar PDF</button>
        <a class="boton boton-primario" href="reportes.php?<?= e(http_build_query($filtros + ['exportar' => 'csv'])) ?>">Exportar Excel</a>
      </div>
    </header>

    <section class="tarjeta no-imprimir">
      <form class="fila-filtros" action="reportes.php" method="get">
        <div class="campo">
          <label for="fechaInicial">Fecha inicial</label>
          <input type="date" id="fechaInicial" name="fechaInicial" value="<?= e($inicio) ?>">
        </div>
        <div class="campo">
          <label for="fechaFinal">Fecha final</label>
          <input type="date" id="fechaFinal" name="fechaFinal" value="<?= e($fin) ?>">
        </div>
        <div class="campo">
          <label for="tipoReporte">Tipo de reporte</label>
          <select id="tipoReporte" name="tipoReporte">
<?php foreach ($opciones_tipo as $valor => $texto): ?>
            <option value="<?= e($valor) ?>"<?= $tipo === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="campo">
          <label for="estado">Estado</label>
          <select id="estado" name="estado" title="Se aplica a los reportes de Ingresos y Clientes">
<?php foreach ($opciones_estado as $valor => $texto): ?>
            <option value="<?= e($valor) ?>"<?= $estado === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="campo campo-botones">
          <a href="reportes.php" class="boton boton-secundario">Limpiar</a>
          <button type="submit" class="boton boton-primario">Aplicar filtros</button>
        </div>
      </form>
    </section>

    <section class="resumen">
      <div class="tarjeta tarjeta-resumen">
        <span class="etiqueta">Clientes registrados</span>
        <span class="numero-grande"><?= $clientes_registrados ?></span>
      </div>
      <div class="tarjeta tarjeta-resumen">
        <span class="etiqueta">Membresías activas</span>
        <span class="numero-grande"><?= $membresias_activas ?></span>
      </div>
      <div class="tarjeta tarjeta-resumen">
        <span class="etiqueta">Ingresos del período</span>
        <span class="numero-grande"><?= e(dinero($ingresos)) ?></span>
        <?= variacion($ingresos, $ingresos_antes) ?>
      </div>
      <div class="tarjeta tarjeta-resumen">
        <span class="etiqueta">Accesos registrados</span>
        <span class="numero-grande"><?= number_format($accesos, 0, ',', '.') ?></span>
        <?= variacion($accesos, $accesos_antes) ?>
      </div>
    </section>

    <section class="doble-columna">

      <div class="tarjeta">
        <h2>Ingresos mensuales</h2>
        <p class="texto-ayuda">Últimos 6 meses</p>
        <div class="grafico-barras">
<?php foreach ($meses as $m): $alto = $maximo > 0 ? max($m['total'] > 0 ? 2 : 0, (int) round($m['total'] / $maximo * 85)) : 0; ?>
          <div class="barra"><span class="valor-barra"><?= e(dinero_corto($m['total'])) ?></span><div class="barra-relleno" style="height: <?= $alto ?>%;"></div><span class="mes"><?= e($m['mes']) ?></span></div>
<?php endforeach; ?>
        </div>
      </div>

      <div class="tarjeta">
        <h2>Membresías por tipo</h2>
        <p class="texto-ayuda">Pagos activos del período</p>
        <ul class="lista-membresias">
<?php if (!$por_tipo): ?>
          <li>Sin pagos en este período.</li>
<?php endif; ?>
<?php foreach ($por_tipo as $i => $t): $color = $colores_plan[mb_strtolower($t['TIPO_DE_PLAN'])] ?? $extras[$i % 4]; ?>
          <li><span class="punto punto-<?= e($color) ?>"></span> <?= e($t['TIPO_DE_PLAN']) ?> <span class="porcentaje"><?= (int) round($t['n'] / $total_tipo * 100) ?>%</span></li>
<?php endforeach; ?>
        </ul>
      </div>

    </section>

    <section class="tarjeta">
      <div class="cabecera-tarjeta">
        <h2><?= e($reporte['titulo']) ?></h2>
<?php if ($tipo === 'ingresos'): ?>
        <a href="membresias-pagos.php" class="enlace no-imprimir">Ver todos</a>
<?php endif; ?>
      </div>
<?php if ($tipo === 'clientes'): ?>
      <p class="texto-ayuda">Estado actual de los clientes (no depende de las fechas).</p>
<?php endif; ?>
      <div class="tabla-contenedor">
        <table class="tabla">
          <thead>
            <tr>
<?php foreach ($reporte['columnas'] as $columna): ?>
              <th><?= e($columna) ?></th>
<?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
<?php if (!$reporte['filas']): ?>
            <tr><td colspan="<?= count($reporte['columnas']) ?>" class="sin-datos">No hay datos para estos filtros.</td></tr>
<?php endif; ?>
<?php foreach ($reporte['filas'] as $fila): ?>
            <tr>
<?php foreach ($fila as $celda): ?>
<?php if (is_array($celda)): ?>
              <td><span class="etiqueta-estado <?= e($celda['clase']) ?>"><?= e($celda['t']) ?></span></td>
<?php else: ?>
              <td><?= e($celda) ?></td>
<?php endif; ?>
<?php endforeach; ?>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="fila-total"><strong><?= e($reporte['pie']) ?></strong></p>
<?php if (count($reporte['filas']) >= 300): ?>
      <p class="texto-ayuda no-imprimir">Se muestran los primeros 300 registros. "Exportar Excel" incluye todos.</p>
<?php endif; ?>
    </section>

  </div>
    </main>
  </div>

</body>
</html>

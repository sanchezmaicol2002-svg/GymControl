<?php
// Funciones pequeñas que se usan en varias pantallas.

date_default_timezone_set('America/Bogota');

/** Lee un texto de un arreglo ($_GET, $_POST...). Si no existe o no es texto, devuelve ''. */
function texto(array $datos, string $clave): string
{
    $valor = $datos[$clave] ?? '';
    return is_string($valor) ? trim($valor) : '';
}
function get_texto(string $clave): string  { return texto($_GET, $clave); }
function post_texto(string $clave): string { return texto($_POST, $clave); }

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** $85900 -> "$85.900" */
function dinero($valor): string
{
    return '$' . number_format((float) $valor, 0, ',', '.');
}

/** $4850000 -> "$4,85M", $850000 -> "$850K" (para el gráfico) */
function dinero_corto($valor): string
{
    $v = (float) $valor;
    if ($v >= 1000000) {
        return '$' . rtrim(rtrim(number_format($v / 1000000, 2, ',', ''), '0'), ',') . 'M';
    }
    if ($v >= 1000) {
        return '$' . round($v / 1000) . 'K';
    }
    return '$' . round($v);
}

/** "2026-08-20" -> "20 Ago 2026" */
function fecha_es(?string $fecha): string
{
    if (!$fecha) {
        return '—';
    }
    $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $t = strtotime($fecha);
    return date('d', $t) . ' ' . $meses[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

function mes_corto(int $numero_mes): string
{
    $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return $meses[$numero_mes - 1];
}

/** "08:15:00" -> "08:15" */
function hora_corta(?string $hora): string
{
    return $hora ? substr($hora, 0, 5) : '—';
}

function iniciales(string $nombres, string $apellidos): string
{
    return mb_strtoupper(mb_substr($nombres, 0, 1) . mb_substr($apellidos, 0, 1));
}

function color_avatar(int $id): string
{
    $colores = ['azul', 'verde', 'morado', 'naranja'];
    return 'avatar-' . $colores[$id % 4];
}

/** Escapa % y _ para usar el texto dentro de un LIKE. */
function like_escape(string $texto): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);
}

/** "2026-11-25" -> "Hoy" / "1 día" / "6 días" */
function texto_dias(string $fecha): string
{
    $dias = (int) round((strtotime($fecha) - strtotime(date('Y-m-d'))) / 86400);
    if ($dias <= 0) {
        return 'Hoy';
    }
    return $dias === 1 ? '1 día' : $dias . ' días';
}

// ---- Mensajes que se muestran una sola vez (después de guardar, por ejemplo) ----

function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function mostrar_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo '<p class="alerta alerta-' . e($f['tipo']) . '" role="alert">' . e($f['texto']) . '</p>';
}

/** Mensaje de error debajo de un campo del formulario. */
function error_campo(array $errores, string $campo): void
{
    if (isset($errores[$campo])) {
        echo '<small class="error-campo">' . e($errores[$campo]) . '</small>';
    }
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** En formularios POST: si el token no es válido, avisa y vuelve a la página indicada. */
function exigir_csrf(string $volver): void
{
    if (!csrf_valido($_POST['csrf'] ?? null)) {
        flash('error', 'Tu sesión expiró. Intenta de nuevo.');
        redirigir($volver);
    }
}

// ---- Paginación ----

function paginar(int $total, int $por_pagina = 15): array
{
    $paginas = max(1, (int) ceil($total / $por_pagina));
    $pedida = $_GET['pagina'] ?? 1;
    $actual = min(max(1, is_string($pedida) ? (int) $pedida : 1), $paginas);
    return [
        'actual' => $actual,
        'paginas' => $paginas,
        'limite' => $por_pagina,
        'offset' => ($actual - 1) * $por_pagina,
        'total' => $total,
    ];
}

function mostrar_paginacion(array $p): void
{
    if ($p['paginas'] <= 1) {
        return;
    }
    $enlace = function (int $n): string {
        $q = $_GET;
        $q['pagina'] = $n;
        return '?' . http_build_query($q);
    };
    echo '<nav class="paginacion" aria-label="Paginación">';
    if ($p['actual'] > 1) {
        echo '<a class="enlace" href="' . e($enlace($p['actual'] - 1)) . '">← Anterior</a>';
    }
    echo '<span>Página ' . $p['actual'] . ' de ' . $p['paginas'] . ' · ' . $p['total'] . ' registros</span>';
    if ($p['actual'] < $p['paginas']) {
        echo '<a class="enlace" href="' . e($enlace($p['actual'] + 1)) . '">Siguiente →</a>';
    }
    echo '</nav>';
}

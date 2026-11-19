<?php
// Reglas del negocio: cuándo una membresía está vigente, tickets, control de acceso y validaciones.
//
// Cómo se interpretan los datos de la base de datos:
//  - Un cliente es un usuario con rol CLIENTE (3).
//  - Un pago con estado ACTIVO (1) cuenta como membresía. Un pago INACTIVO (2) no cuenta
//    (por ejemplo, un pago incompleto o anulado).
//  - La membresía "vigente" es la del pago activo con FECHA_VENCIMIENTO más lejana.
//  - Tiquetera: pagos.TICKET guarda los tickets comprados y acceso.TICKET guarda
//    cuántos le quedaban después de ese ingreso.

const ESTADO_ACTIVO = 1;
const ESTADO_INACTIVO = 2;
const ROL_CLIENTE = 3;
const DIAS_TIQUETERA = 60;     // una tiquetera se puede usar durante 60 días
const DIAS_POR_VENCER = 7;     // "por vencer" = vence en los próximos 7 días

function hoy(): string
{
    return date('Y-m-d');
}

// ---------------------------------------------------------------- Clientes

/** Cada cliente con su membresía vigente (o la última que tuvo). */
function sql_clientes_base(): string
{
    return "SELECT c.ID, c.IDENTIFICACION, t.DESCRIPCION AS TIPO_ID, c.NOMBRES, c.APELLIDOS, c.TELEFONO, c.ESTADO_FK,
                   p.ID AS PAGO_ID, p.FECHA_INICIO, p.FECHA_VENCIMIENTO, p.TICKET AS TICKETS_PAGO,
                   m.TIPO_DE_PLAN, m.UNIDAD
            FROM usuarios c
            JOIN tipo_id t ON t.ID = c.TIPO_ID_FK
            LEFT JOIN pagos p ON p.ID = (
                SELECT p2.ID FROM pagos p2
                WHERE p2.CLIENTE_FK = c.ID AND p2.ESTADO_FK = 1
                ORDER BY p2.FECHA_VENCIMIENTO DESC, p2.ID DESC LIMIT 1)
            LEFT JOIN membresias m ON m.ID = p.MEMBRESIA_FK";
}

/** Condición SQL para filtrar clientes por estado (se usa con sql_clientes_base). */
function condicion_estado_cliente(PDO $pdo, string $estado): string
{
    $hoy = $pdo->quote(hoy());
    switch ($estado) {
        case 'activo':
            return "c.ESTADO_FK = 1 AND p.ID IS NOT NULL AND p.FECHA_VENCIMIENTO >= $hoy";
        case 'vencido':
            return "c.ESTADO_FK = 1 AND p.ID IS NOT NULL AND p.FECHA_VENCIMIENTO < $hoy";
        case 'sin-membresia':
            return "c.ESTADO_FK = 1 AND p.ID IS NULL";
        case 'inactivo':
            return "c.ESTADO_FK = 2";
    }
    return '1 = 1';
}

function estado_cliente(array $fila): string
{
    if ((int) $fila['ESTADO_FK'] !== ESTADO_ACTIVO) {
        return 'inactivo';
    }
    if (empty($fila['PAGO_ID'])) {
        return 'sin-membresia';
    }
    return $fila['FECHA_VENCIMIENTO'] < hoy() ? 'vencido' : 'activo';
}

/** [clase CSS, texto] de la etiqueta de estado de un cliente. */
function etiqueta_cliente(string $estado): array
{
    $mapa = [
        'activo'        => ['activo', 'Activo'],
        'vencido'       => ['vencido', 'Vencido'],
        'sin-membresia' => ['inactivo', 'Sin membresía'],
        'inactivo'      => ['inactivo', 'Inactivo'],
    ];
    return $mapa[$estado] ?? ['inactivo', $estado];
}

function contar_clientes(PDO $pdo, string $estado): int
{
    $sql = 'SELECT COUNT(*) FROM (' . sql_clientes_base() . ' WHERE c.ROL_FK = 3 AND '
        . condicion_estado_cliente($pdo, $estado) . ') x';
    return (int) $pdo->query($sql)->fetchColumn();
}

/** Clientes cuya membresía vence en los próximos días (cantidad o lista). */
function sql_por_vencer(PDO $pdo, int $dias): string
{
    $hoy = $pdo->quote(hoy());
    $hasta = $pdo->quote(date('Y-m-d', strtotime("+$dias days")));
    return sql_clientes_base() . " WHERE c.ROL_FK = 3 AND c.ESTADO_FK = 1 AND p.ID IS NOT NULL
            AND p.FECHA_VENCIMIENTO BETWEEN $hoy AND $hasta";
}

function contar_por_vencer(PDO $pdo, int $dias = DIAS_POR_VENCER): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM (' . sql_por_vencer($pdo, $dias) . ') x')->fetchColumn();
}

function listar_por_vencer(PDO $pdo, int $limite, int $dias = DIAS_POR_VENCER): array
{
    return $pdo->query(sql_por_vencer($pdo, $dias) . ' ORDER BY p.FECHA_VENCIMIENTO, c.NOMBRES LIMIT ' . (int) $limite)->fetchAll();
}

// ---------------------------------------------------------------- Pagos

function ingresos_entre(PDO $pdo, string $desde, string $hasta): float
{
    $s = $pdo->prepare('SELECT COALESCE(SUM(VALOR), 0) FROM pagos WHERE ESTADO_FK = 1 AND FECHA_PAGO BETWEEN ? AND ?');
    $s->execute([$desde, $hasta]);
    return (float) $s->fetchColumn();
}

function accesos_entre(PDO $pdo, string $desde, string $hasta): int
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM acceso WHERE FECHA_REG BETWEEN ? AND ?');
    $s->execute([$desde, $hasta]);
    return (int) $s->fetchColumn();
}

/** [clase CSS, texto] de la etiqueta de estado de un pago. */
function etiqueta_pago(array $pago): array
{
    if ((int) $pago['ESTADO_FK'] !== ESTADO_ACTIVO) {
        return ['inactivo', 'Inactivo'];
    }
    return $pago['FECHA_VENCIMIENTO'] < hoy() ? ['vencido', 'Vencido'] : ['activo', 'Activo'];
}

/**
 * Fecha de vencimiento sugerida: "Día" vence el mismo día, los planes por días
 * suman su duración y la tiquetera dura DIAS_TIQUETERA días.
 * (El formulario de pagos hace el mismo cálculo en JavaScript.)
 */
function calcular_vencimiento(string $inicio, int $duracion, string $unidad): string
{
    $dias = $unidad === 'TICKET' ? DIAS_TIQUETERA : ($duracion <= 1 ? 0 : $duracion);
    return date('Y-m-d', strtotime($inicio . " +$dias days"));
}

// ---------------------------------------------------------------- Control de acceso

function tickets_restantes(PDO $pdo, int $cliente_id, array $pago): int
{
    $s = $pdo->prepare('SELECT TICKET FROM acceso WHERE CLIENTE_FK = ? AND FECHA_REG >= ?
                        ORDER BY FECHA_REG DESC, HORA_REG DESC, ID DESC LIMIT 1');
    $s->execute([$cliente_id, $pago['FECHA_INICIO']]);
    $ultimo = $s->fetchColumn();
    return $ultimo === false ? (int) $pago['TICKETS_PAGO'] : (int) $ultimo;
}

function ingreso_hoy(PDO $pdo, int $cliente_id): bool
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM acceso WHERE CLIENTE_FK = ? AND FECHA_REG = ?');
    $s->execute([$cliente_id, hoy()]);
    return (int) $s->fetchColumn() > 0;
}

/**
 * Decide si un cliente puede entrar hoy.
 * Devuelve: cliente, permitido (bool), motivo (texto), pago (membresía usada),
 * tickets (disponibles, solo si entra con tiquetera), consume_ticket (si este ingreso gasta uno)
 * y reserva (tickets que le quedan en una tiquetera que no usó en este ingreso).
 *
 * Si tiene un plan por días vigente y una tiquetera, entra con el plan por días
 * y la tiquetera no se toca.
 */
function evaluar_acceso(PDO $pdo, int $cliente_id): array
{
    $r = ['cliente' => null, 'permitido' => false, 'motivo' => '', 'pago' => null, 'tickets' => null,
          'consume_ticket' => false, 'reserva' => 0];

    $s = $pdo->prepare('SELECT u.ID, t.DESCRIPCION AS TIPO_ID, u.IDENTIFICACION, u.NOMBRES, u.APELLIDOS, u.ESTADO_FK
                        FROM usuarios u JOIN tipo_id t ON t.ID = u.TIPO_ID_FK WHERE u.ID = ? AND u.ROL_FK = 3');
    $s->execute([$cliente_id]);
    $cliente = $s->fetch();
    if (!$cliente) {
        $r['motivo'] = 'No se encontró el cliente';
        return $r;
    }
    $r['cliente'] = $cliente;

    if ((int) $cliente['ESTADO_FK'] !== ESTADO_ACTIVO) {
        $r['motivo'] = 'El cliente está inactivo';
        return $r;
    }

    $q = $pdo->prepare('SELECT p.ID, p.FECHA_INICIO, p.FECHA_VENCIMIENTO, p.TICKET AS TICKETS_PAGO, m.TIPO_DE_PLAN, m.UNIDAD
                        FROM pagos p JOIN membresias m ON m.ID = p.MEMBRESIA_FK
                        WHERE p.CLIENTE_FK = ? AND p.ESTADO_FK = 1
                        ORDER BY p.FECHA_VENCIMIENTO DESC, p.ID DESC');
    $q->execute([$cliente_id]);
    $pagos = $q->fetchAll();
    if (!$pagos) {
        $r['motivo'] = 'No tiene ninguna membresía pagada';
        return $r;
    }

    // Membresías vigentes hoy: una por días y/o una tiquetera.
    $hoy = hoy();
    $por_dias = null;
    $tiquetera = null;
    foreach ($pagos as $p) {
        if ($p['FECHA_INICIO'] > $hoy || $p['FECHA_VENCIMIENTO'] < $hoy) {
            continue;
        }
        if ($p['UNIDAD'] === 'TICKET') {
            $tiquetera = $tiquetera ?? $p;
        } else {
            $por_dias = $por_dias ?? $p;
        }
    }
    $restantes = $tiquetera ? tickets_restantes($pdo, $cliente_id, $tiquetera) : 0;

    if ($por_dias) {
        $r['permitido'] = true;
        $r['pago'] = $por_dias;
        $r['reserva'] = $restantes;
        $r['motivo'] = 'Cliente con membresía activa';
        return $r;
    }

    if ($tiquetera) {
        $ya_entro = ingreso_hoy($pdo, $cliente_id);   // si ya entró hoy, volver a entrar no gasta otro ticket
        $r['pago'] = $tiquetera;
        $r['tickets'] = $restantes;
        if ($restantes <= 0 && !$ya_entro) {
            $r['motivo'] = 'Se acabaron los tickets de la tiquetera';
            return $r;
        }
        $r['permitido'] = true;
        $r['consume_ticket'] = !$ya_entro;
        $r['motivo'] = $ya_entro ? 'Ya registró ingreso hoy (no gasta otro ticket)' : 'Tiquetera con tickets disponibles';
        return $r;
    }

    $reciente = $pagos[0];
    $r['pago'] = $reciente;
    $r['motivo'] = $reciente['FECHA_INICIO'] > $hoy
        ? 'Su membresía empieza el ' . fecha_es($reciente['FECHA_INICIO'])
        : 'Su membresía está vencida (venció el ' . fecha_es($reciente['FECHA_VENCIMIENTO']) . ')';
    return $r;
}

/** Revisa de nuevo el acceso y, si está permitido, guarda el ingreso. */
function registrar_ingreso(PDO $pdo, int $cliente_id): array
{
    $r = evaluar_acceso($pdo, $cliente_id);
    if (!$r['permitido']) {
        return $r;
    }
    // acceso.TICKET guarda cuántos tickets le quedan (0 si no tiene tiquetera).
    if ($r['tickets'] !== null) {
        $quedan = $r['consume_ticket'] ? $r['tickets'] - 1 : $r['tickets'];
    } else {
        $quedan = $r['reserva'];
    }
    $s = $pdo->prepare('INSERT INTO acceso (FECHA_REG, HORA_REG, CLIENTE_FK, TICKET) VALUES (?, ?, ?, ?)');
    $s->execute([hoy(), date('H:i:s'), $cliente_id, $quedan]);
    $r['tickets_despues'] = $quedan;
    return $r;
}

// ---------------------------------------------------------------- Validaciones

function validar_fecha(string $texto): bool
{
    $f = DateTime::createFromFormat('Y-m-d', $texto);
    return $f !== false && $f->format('Y-m-d') === $texto;
}

/**
 * Valida los datos de una persona (cliente o usuario del sistema).
 * Los largos máximos son los de la base de datos.
 * Devuelve [errores por campo, datos limpios].
 */
function validar_persona(PDO $pdo, array $in, ?int $id, bool $es_usuario_sistema): array
{
    $e = [];
    $limpiar = fn(string $t): string => preg_replace('/\s+/u', ' ', trim($t));
    $d = [
        'tipo_id'          => (int) texto($in, 'tipo_id'),
        'identificacion'   => texto($in, 'identificacion'),
        'nombres'          => $limpiar(texto($in, 'nombres')),
        'apellidos'        => $limpiar(texto($in, 'apellidos')),
        'telefono'         => texto($in, 'telefono'),
        'fecha_nacimiento' => texto($in, 'fecha_nacimiento'),
        'direccion'        => $limpiar(texto($in, 'direccion')),
        'estado'           => (int) texto($in, 'estado'),
        'correo'           => mb_strtolower(texto($in, 'correo')),
        'rol'              => (int) texto($in, 'rol'),
    ];

    $tipos = $pdo->query('SELECT ID FROM tipo_id')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($d['tipo_id'], array_map('intval', $tipos), true)) {
        $e['tipo_id'] = 'Selecciona el tipo de documento.';
    }

    if (!preg_match('/^[A-Za-z0-9\-]{4,20}$/', $d['identificacion'])) {
        $e['identificacion'] = 'Escribe un documento válido (4 a 20 letras o números).';
    } else {
        $s = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE IDENTIFICACION = ? AND ID <> ?');
        $s->execute([$d['identificacion'], $id ?? 0]);
        if ((int) $s->fetchColumn() > 0) {
            $e['identificacion'] = 'Ya existe una persona con este número de documento.';
        }
    }

    foreach (['nombres' => 'los nombres', 'apellidos' => 'los apellidos'] as $campo => $texto) {
        if ($d[$campo] === '') {
            $e[$campo] = 'Escribe ' . $texto . '.';
        } elseif (mb_strlen($d[$campo]) > 20) {
            $e[$campo] = 'Máximo 20 letras.';
        } elseif (!preg_match("/^[\p{L}' .\-]+$/u", $d[$campo])) {
            $e[$campo] = 'Usa solo letras.';
        }
    }

    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $d['telefono'])) {
        $e['telefono'] = 'Escribe un teléfono válido (7 a 20 números).';
    }

    if (!validar_fecha($d['fecha_nacimiento'])) {
        $e['fecha_nacimiento'] = 'Escribe una fecha de nacimiento válida.';
    } elseif ($d['fecha_nacimiento'] > hoy() || $d['fecha_nacimiento'] < '1900-01-01') {
        $e['fecha_nacimiento'] = 'La fecha de nacimiento no es posible.';
    }

    if ($d['direccion'] === '') {
        $e['direccion'] = 'Escribe la dirección.';
    } elseif (mb_strlen($d['direccion']) > 100) {
        $e['direccion'] = 'Máximo 100 caracteres.';
    }

    if (!in_array($d['estado'], [ESTADO_ACTIVO, ESTADO_INACTIVO], true)) {
        $e['estado'] = 'Selecciona un estado.';
    }

    if ($es_usuario_sistema) {
        if (!in_array($d['rol'], [ROL_PROPIETARIO, ROL_EMPLEADO, ROL_CLIENTE], true)) {
            $e['rol'] = 'Selecciona un rol.';
        } elseif ($d['rol'] !== ROL_CLIENTE) {
            // Los clientes no inician sesión, así que solo propietarios y empleados llevan correo.
            $e = $e + validar_correo($pdo, $d['correo'], $id);
        }
    }
    return [$e, $d];
}

/** Valida un correo (formato y que no lo use otra persona). */
function validar_correo(PDO $pdo, string $correo, ?int $id): array
{
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 100) {
        return ['correo' => 'Escribe un correo válido.'];
    }
    $s = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE CORREO = ? AND ID <> ?');
    $s->execute([$correo, $id ?? 0]);
    if ((int) $s->fetchColumn() > 0) {
        return ['correo' => 'Ese correo ya lo usa otro usuario.'];
    }
    return [];
}

/** Reglas de la contraseña. Devuelve el mensaje de error o null si está bien. */
function validar_clave(string $clave, string $confirmar): ?string
{
    if (mb_strlen($clave) < 8) {
        return 'La contraseña debe tener mínimo 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $clave) || !preg_match('/[0-9]/', $clave)) {
        return 'La contraseña debe tener letras y números.';
    }
    if ($clave !== $confirmar) {
        return 'Las contraseñas no coinciden.';
    }
    return null;
}

/** Cuántos propietarios activos hay (siempre debe quedar al menos uno). */
function propietarios_activos(PDO $pdo, int $excepto_id = 0): int
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE ROL_FK = 1 AND ESTADO_FK = 1 AND CLAVE IS NOT NULL AND ID <> ?');
    $s->execute([$excepto_id]);
    return (int) $s->fetchColumn();
}

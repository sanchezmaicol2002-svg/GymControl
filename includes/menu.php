<?php
// Menú lateral compartido. Cada página define $pagina_activa antes de incluirlo.
// Está dentro de una función para que sus variables no se mezclen con las de la página.
function dibujar_menu(array $usuario, string $activa): void
{
    $opciones = [
        'dashboard'        => ['📊 Dashboard', 'dashboard.php', []],
        'clientes'         => ['👥 Clientes', 'clientes.php', []],
        'membresias-pagos' => ['💳 Membresías y pagos', 'membresias-pagos.php', []],
        'control-acceso'   => ['🚪 Control de acceso', 'control-acceso.php', []],
        'reportes'         => ['📈 Reportes', 'reportes.php', []],
        'gestion-usuarios' => ['🔐 Usuarios', 'gestion-usuarios.php', [ROL_PROPIETARIO]],
        'configuracion'    => ['⚙️ Configuración', 'configuracion.php', []],
    ];
?>
    <aside class="menu-lateral">
      <div class="menu-logo">🏋️ GYMCONTROL</div>
      <ul class="menu-lista">
<?php foreach ($opciones as $clave => [$texto, $enlace, $roles_permitidos]): ?>
<?php if ($roles_permitidos && !in_array($usuario['rol_id'], $roles_permitidos, true)) continue; ?>
        <li><a href="<?= e($enlace) ?>" class="menu-enlace<?= $clave === $activa ? ' activo' : '' ?>"<?= $clave === $activa ? ' aria-current="page"' : '' ?>><?= $texto ?></a></li>
<?php endforeach; ?>
      </ul>
      <div class="menu-salir">
        <p class="menu-usuario"><?= e($usuario['nombre']) ?><br><span><?= e($usuario['rol']) ?></span></p>
        <form action="logout.php" method="post">
          <button type="submit" class="menu-enlace">↩ Cerrar sesión</button>
        </form>
      </div>
    </aside>
<?php
}

dibujar_menu(usuario_actual(), $pagina_activa ?? '');

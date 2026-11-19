# GymControl

Sistema web para la gestión de un gimnasio: clientes, membresías, pagos, control de acceso,
reportes y usuarios. Hecho con **PHP + MariaDB (XAMPP)**.

## Cómo ponerlo a funcionar

1. Instala y abre **XAMPP**. Enciende **Apache** y **MySQL**.
2. Copia esta carpeta a `C:\xampp\htdocs\gymcontrol`.
3. Abre `http://localhost/phpmyadmin`, entra a la pestaña **Importar** y carga, en este orden:
   1. `database/db_maicol.sql`
   2. `database/01_login.sql`
4. Abre `http://localhost/gymcontrol/` en el navegador.

Si vuelves a importar `db_maicol.sql`, ejecuta de nuevo `01_login.sql`.

## Usuarios de prueba (contraseña: `Gym2026*`)

| Correo                 | Rol         | Puede ver                 |
|------------------------|-------------|---------------------------|
| admin@gymcontrol.com   | Propietario | Todo, incluido "Usuarios" |
| margara@gymcontrol.com | Empleado    | Todo menos "Usuarios"     |

Cambia la contraseña en **Configuración** después de entrar.

## Qué hace cada pantalla

- **Dashboard:** resumen del gimnasio (clientes activos, ingresos del mes, accesos de hoy, membresías por vencer).
- **Clientes:** lista con búsqueda y filtro por estado, ficha del cliente, registrar y editar.
- **Membresías y pagos:** historial con filtros y registro de pagos (el vencimiento y el valor se llenan solos).
- **Control de acceso:** verifica si el cliente puede entrar y registra el ingreso (descuenta tickets de la tiquetera).
- **Reportes:** ingresos, clientes, membresías y accesos por fechas. Exporta a Excel (CSV) y a PDF (imprimir).
- **Usuarios (solo propietario):** crear, editar, activar y desactivar usuarios con rol Propietario, Empleado o Cliente. Los clientes no inician sesión, por eso no llevan correo ni contraseña.
- **Configuración:** editar el perfil y cambiar la contraseña.

## Reglas que usa el sistema

- Un **cliente** es un usuario con rol CLIENTE. Un pago con estado ACTIVO cuenta como membresía; uno INACTIVO no.
- La membresía vigente es la del pago activo con el vencimiento más lejano.
- Vencimiento sugerido: **Día** = el mismo día; planes por días = inicio + su duración; **Tiquetera** = 60 días.
- Tiquetera: `pagos.TICKET` guarda los tickets comprados y `acceso.TICKET` los que quedan después de cada ingreso.
  Si el cliente vuelve a entrar el mismo día, no gasta otro ticket.
- "Por vencer" = vence en los próximos 7 días.

## Estructura

- `config/conexion.php`: datos de conexión a la base de datos.
- `includes/auth.php`: sesión, permisos por rol y seguridad de formularios.
- `includes/negocio.php`: reglas del negocio (membresías, acceso, validaciones).
- `includes/util.php` y `includes/menu.php`: funciones comunes y menú lateral.
- `database/`: scripts SQL.

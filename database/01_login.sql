-- GymControl · Parte 1: usuario y contraseña para iniciar sesión
-- Ejecutar DESPUÉS de importar db_maicol.sql (en phpMyAdmin > pestaña SQL).
-- Si vuelves a importar db_maicol.sql, hay que ejecutar este archivo otra vez.

USE `db_maicol`;

-- 1) El rol 1 estaba cortado ("PROPIETARI") porque la columna era de 10 letras.
ALTER TABLE `roles` MODIFY `DESCRIPCION` varchar(15) NOT NULL;
UPDATE `roles` SET `DESCRIPCION` = 'PROPIETARIO' WHERE `ID` = 1;

-- 2) Correo y contraseña (encriptada) para quien inicia sesión.
--    Quedan vacíos (NULL) para los clientes, que no entran al sistema.
ALTER TABLE `usuarios`
  ADD COLUMN `CORREO` varchar(100) NULL AFTER `APELLIDOS`,
  ADD COLUMN `CLAVE` varchar(255) NULL AFTER `CORREO`,
  ADD UNIQUE KEY `CORREO` (`CORREO`);

-- 3) Usuarios de prueba. Contraseña inicial de ambos: Gym2026*
--    (cámbiala cuando esté listo el módulo de configuración)
UPDATE `usuarios` SET `CORREO` = 'admin@gymcontrol.com',   `CLAVE` = '$2y$10$ZagRXdl.hc/.AHg7WaOr..M78sKtgUB3OBdA5E1mul61vebEPYqHO' WHERE `ID` = 1; -- Victor Rueda (propietario)
UPDATE `usuarios` SET `CORREO` = 'margara@gymcontrol.com', `CLAVE` = '$2y$10$ZagRXdl.hc/.AHg7WaOr..M78sKtgUB3OBdA5E1mul61vebEPYqHO' WHERE `ID` = 4; -- Margara Valencia (empleada)

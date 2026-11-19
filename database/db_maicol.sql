-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-09-2026 a las 02:37:47
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `db_maicol`
--
CREATE DATABASE IF NOT EXISTS `db_maicol` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_maicol`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acceso`
--

DROP TABLE IF EXISTS `acceso`;
CREATE TABLE `acceso` (
  `ID` int(11) NOT NULL,
  `FECHA_REG` date NOT NULL,
  `HORA_REG` time NOT NULL,
  `CLIENTE_FK` int(11) NOT NULL,
  `TICKET` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `acceso`
--

INSERT INTO `acceso` (`ID`, `FECHA_REG`, `HORA_REG`, `CLIENTE_FK`, `TICKET`) VALUES
(1, '2026-09-24', '06:27:00', 1, 13),
(2, '2026-09-24', '08:20:00', 2, 0),
(3, '2026-09-24', '10:05:00', 3, 0),
(4, '2026-09-24', '11:44:00', 4, 0),
(5, '2026-09-24', '17:44:00', 5, 0),
(6, '2026-09-24', '20:58:00', 6, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados`
--

DROP TABLE IF EXISTS `estados`;
CREATE TABLE `estados` (
  `ID` int(11) NOT NULL,
  `DESCRIPCION` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados`
--

INSERT INTO `estados` (`ID`, `DESCRIPCION`) VALUES
(1, 'ACTIVO'),
(2, 'INACTIVO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medios_pago`
--

DROP TABLE IF EXISTS `medios_pago`;
CREATE TABLE `medios_pago` (
  `ID` int(11) NOT NULL,
  `DESCRIPCION` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `medios_pago`
--

INSERT INTO `medios_pago` (`ID`, `DESCRIPCION`) VALUES
(1, 'EFECTIVO'),
(2, 'TRANSFERENCIA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `membresias`
--

DROP TABLE IF EXISTS `membresias`;
CREATE TABLE `membresias` (
  `ID` int(11) NOT NULL,
  `TIPO_DE_PLAN` varchar(20) NOT NULL,
  `DURACION` int(11) NOT NULL,
  `UNIDAD` varchar(10) NOT NULL,
  `PRECIO` decimal(10,2) NOT NULL,
  `ESTADO_FK` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `membresias`
--

INSERT INTO `membresias` (`ID`, `TIPO_DE_PLAN`, `DURACION`, `UNIDAD`, `PRECIO`, `ESTADO_FK`) VALUES
(1, 'Día', 1, 'DIA', 9900.00, 1),
(2, 'Tiquetera', 15, 'TICKET', 73900.00, 1),
(3, 'Mensual', 30, 'DIA', 85900.00, 1),
(4, 'Bimestral', 60, 'DIA', 149900.00, 1),
(5, 'Trimestral', 90, 'DIA', 220000.00, 1),
(6, 'Semestral', 180, 'DIA', 420000.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

DROP TABLE IF EXISTS `pagos`;
CREATE TABLE `pagos` (
  `ID` int(11) NOT NULL,
  `CLIENTE_FK` int(11) NOT NULL,
  `MEMBRESIA_FK` int(11) NOT NULL,
  `FECHA_PAGO` date NOT NULL,
  `EMPLEADO_FK` int(11) NOT NULL,
  `FECHA_INICIO` date NOT NULL,
  `FECHA_VENCIMIENTO` date NOT NULL,
  `TICKET` int(11) NOT NULL,
  `VALOR` decimal(10,2) NOT NULL,
  `ESTADO_FK` int(11) NOT NULL,
  `MEDIO_PAGO_FK` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`ID`, `CLIENTE_FK`, `MEMBRESIA_FK`, `FECHA_PAGO`, `EMPLEADO_FK`, `FECHA_INICIO`, `FECHA_VENCIMIENTO`, `TICKET`, `VALOR`, `ESTADO_FK`, `MEDIO_PAGO_FK`) VALUES
(1, 1, 2, '2026-09-23', 2, '2026-09-22', '2026-11-22', 15, 73900.00, 1, 1),
(2, 2, 3, '2026-09-23', 2, '2026-09-22', '2026-10-23', 0, 85900.00, 1, 1),
(3, 3, 6, '2026-09-23', 4, '2026-09-22', '2027-03-22', 0, 240000.00, 2, 1),
(4, 4, 5, '2026-09-23', 2, '2026-09-22', '2026-12-22', 0, 220000.00, 1, 1),
(5, 5, 3, '2026-09-23', 4, '2026-09-22', '2026-10-23', 0, 85900.00, 1, 1),
(6, 6, 3, '2026-09-23', 4, '2026-09-22', '2026-10-23', 0, 85900.00, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `ID` int(11) NOT NULL,
  `DESCRIPCION` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`ID`, `DESCRIPCION`) VALUES
(1, 'PROPIETARI'),
(2, 'EMPLEADO'),
(3, 'CLIENTE');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_id`
--

DROP TABLE IF EXISTS `tipo_id`;
CREATE TABLE `tipo_id` (
  `ID` int(11) NOT NULL,
  `DESCRIPCION` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipo_id`
--

INSERT INTO `tipo_id` (`ID`, `DESCRIPCION`) VALUES
(1, 'CC'),
(2, 'CE'),
(3, 'TI'),
(4, 'PPT'),
(5, 'PP'),
(6, 'VS'),
(7, 'NUID'),
(8, 'RC');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `ID` int(11) NOT NULL,
  `IDENTIFICACION` varchar(20) NOT NULL,
  `TIPO_ID_FK` int(11) NOT NULL,
  `NOMBRES` varchar(20) NOT NULL,
  `APELLIDOS` varchar(20) NOT NULL,
  `TELEFONO` varchar(20) NOT NULL,
  `FECHA_DE_NACIMIENTO` date NOT NULL,
  `DIRECCION` varchar(100) NOT NULL,
  `ESTADO_FK` int(11) NOT NULL,
  `ROL_FK` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`ID`, `IDENTIFICACION`, `TIPO_ID_FK`, `NOMBRES`, `APELLIDOS`, `TELEFONO`, `FECHA_DE_NACIMIENTO`, `DIRECCION`, `ESTADO_FK`, `ROL_FK`) VALUES
(1, '70323988', 1, 'Victor', 'Rueda', '3148597490', '1968-09-12', 'Calle 63 #62B-3', 1, 1),
(2, '1152187886', 2, 'Camilo', 'Ruiz', '3207812713', '2012-02-13', 'Calle 100G #84-69', 2, 2),
(3, '26696271', 1, 'Andrilainys', 'Marin', '3117969634', '1987-11-23', 'Calle 20E #44-36', 1, 3),
(4, '43825154', 1, 'Margara', 'Valencia', '3056753478', '1971-01-29', 'Calle 101 #34-05', 1, 2),
(5, '1000291842', 3, 'Maicol', 'Sanchez', '3145678964', '2011-02-12', 'Calle 100G #84-69', 2, 3),
(6, '26786987', 4, 'Lili', 'Duque', '3112246789', '1999-05-29', 'Calle 98B #56-09', 2, 3);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `acceso`
--
ALTER TABLE `acceso`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ID` (`ID`),
  ADD KEY `ACCESO_fk3` (`CLIENTE_FK`);

--
-- Indices de la tabla `estados`
--
ALTER TABLE `estados`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ID` (`ID`);

--
-- Indices de la tabla `medios_pago`
--
ALTER TABLE `medios_pago`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ID` (`ID`);

--
-- Indices de la tabla `membresias`
--
ALTER TABLE `membresias`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ID` (`ID`),
  ADD UNIQUE KEY `TIPO_DE_PLAN` (`TIPO_DE_PLAN`),
  ADD KEY `MEMBRESIAS_fk5` (`ESTADO_FK`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ID` (`ID`),
  ADD KEY `PAGOS_fk1` (`CLIENTE_FK`),
  ADD KEY `PAGOS_fk2` (`MEMBRESIA_FK`),
  ADD KEY `PAGOS_fk4` (`EMPLEADO_FK`),
  ADD KEY `PAGOS_fk9` (`ESTADO_FK`),
  ADD KEY `PAGOS_fk10` (`MEDIO_PAGO_FK`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ID` (`ID`);

--
-- Indices de la tabla `tipo_id`
--
ALTER TABLE `tipo_id`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ID` (`ID`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `ID` (`ID`),
  ADD UNIQUE KEY `IDENTIFICACION` (`IDENTIFICACION`),
  ADD KEY `USUARIOS_fk2` (`TIPO_ID_FK`),
  ADD KEY `USUARIOS_fk8` (`ESTADO_FK`),
  ADD KEY `USUARIOS_fk9` (`ROL_FK`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `acceso`
--
ALTER TABLE `acceso`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `estados`
--
ALTER TABLE `estados`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `medios_pago`
--
ALTER TABLE `medios_pago`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `membresias`
--
ALTER TABLE `membresias`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tipo_id`
--
ALTER TABLE `tipo_id`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `acceso`
--
ALTER TABLE `acceso`
  ADD CONSTRAINT `ACCESO_fk3` FOREIGN KEY (`CLIENTE_FK`) REFERENCES `usuarios` (`ID`);

--
-- Filtros para la tabla `membresias`
--
ALTER TABLE `membresias`
  ADD CONSTRAINT `MEMBRESIAS_fk5` FOREIGN KEY (`ESTADO_FK`) REFERENCES `estados` (`ID`);

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `PAGOS_fk1` FOREIGN KEY (`CLIENTE_FK`) REFERENCES `usuarios` (`ID`),
  ADD CONSTRAINT `PAGOS_fk10` FOREIGN KEY (`MEDIO_PAGO_FK`) REFERENCES `medios_pago` (`ID`),
  ADD CONSTRAINT `PAGOS_fk2` FOREIGN KEY (`MEMBRESIA_FK`) REFERENCES `membresias` (`ID`),
  ADD CONSTRAINT `PAGOS_fk4` FOREIGN KEY (`EMPLEADO_FK`) REFERENCES `usuarios` (`ID`),
  ADD CONSTRAINT `PAGOS_fk9` FOREIGN KEY (`ESTADO_FK`) REFERENCES `estados` (`ID`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `USUARIOS_fk2` FOREIGN KEY (`TIPO_ID_FK`) REFERENCES `tipo_id` (`ID`),
  ADD CONSTRAINT `USUARIOS_fk8` FOREIGN KEY (`ESTADO_FK`) REFERENCES `estados` (`ID`),
  ADD CONSTRAINT `USUARIOS_fk9` FOREIGN KEY (`ROL_FK`) REFERENCES `roles` (`ID`);
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

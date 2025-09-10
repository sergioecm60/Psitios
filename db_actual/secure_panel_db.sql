-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 10, 2025 at 06:05 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-03:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `secure_panel_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `service_id` int DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int NOT NULL,
  `company_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `name`, `created_at`) VALUES
(1, 'Argentina', '2025-09-04 17:15:52'),
(2, 'Uruguay', '2025-09-04 17:15:52'),
(3, 'Paraguay', '2025-09-04 17:15:52'),
(4, 'Bolivia', '2025-09-04 18:15:36'),
(5, 'Brasil', '2025-09-04 18:15:36'),
(6, 'Chile', '2025-09-04 18:15:36'),
(7, 'Colombia', '2025-09-04 18:15:36'),
(8, 'Ecuador', '2025-09-04 18:15:36'),
(9, 'Guyana', '2025-09-04 18:15:36'),
(10, 'Perú', '2025-09-04 18:15:36'),
(11, 'Surinam', '2025-09-04 18:15:36'),
(12, 'Venezuela', '2025-09-04 18:15:36'),
(13, 'Afganistán', '2025-09-04 18:21:11'),
(14, 'Albania', '2025-09-04 18:21:11'),
(15, 'Alemania', '2025-09-04 18:21:11'),
(16, 'Andorra', '2025-09-04 18:21:11'),
(17, 'Angola', '2025-09-04 18:21:11'),
(18, 'Antigua y Barbuda', '2025-09-04 18:21:11'),
(19, 'Arabia Saudita', '2025-09-04 18:21:11'),
(20, 'Argelia', '2025-09-04 18:21:11'),
(21, 'Armenia', '2025-09-04 18:21:11'),
(22, 'Australia', '2025-09-04 18:21:11'),
(23, 'Austria', '2025-09-04 18:21:11'),
(24, 'Azerbaiyán', '2025-09-04 18:21:11'),
(25, 'Zimbabue', '2025-09-04 18:21:11'),
(26, 'Bahamas', '2025-09-04 18:27:03'),
(27, 'Bangladés', '2025-09-04 18:27:03'),
(28, 'Barbados', '2025-09-04 18:27:03'),
(29, 'Baréin', '2025-09-04 18:27:03'),
(30, 'Bélgica', '2025-09-04 18:27:03'),
(31, 'Belice', '2025-09-04 18:27:03'),
(32, 'Benín', '2025-09-04 18:27:03'),
(33, 'Bielorrusia', '2025-09-04 18:27:03'),
(34, 'Birmania', '2025-09-04 18:27:03'),
(35, 'Bosnia y Herzegovina', '2025-09-04 18:27:03'),
(36, 'Botsuana', '2025-09-04 18:27:03'),
(37, 'Brunéi', '2025-09-04 18:27:03'),
(38, 'Bulgaria', '2025-09-04 18:27:03'),
(39, 'Burkina Faso', '2025-09-04 18:27:03'),
(40, 'Burundi', '2025-09-04 18:27:03'),
(41, 'Bután', '2025-09-04 18:27:03'),
(42, 'Cabo Verde', '2025-09-04 18:27:03'),
(43, 'Camboya', '2025-09-04 18:27:03'),
(44, 'Camerún', '2025-09-04 18:27:03'),
(45, 'Canadá', '2025-09-04 18:27:03'),
(46, 'Catar', '2025-09-04 18:27:03'),
(47, 'Chad', '2025-09-04 18:27:03'),
(48, 'China', '2025-09-04 18:27:03'),
(49, 'Chipre', '2025-09-04 18:27:03'),
(50, 'Comoras', '2025-09-04 18:27:03'),
(51, 'Corea del Norte', '2025-09-04 18:27:03'),
(52, 'Corea del Sur', '2025-09-04 18:27:03'),
(53, 'Costa de Marfil', '2025-09-04 18:27:03'),
(54, 'Costa Rica', '2025-09-04 18:27:03'),
(55, 'Croacia', '2025-09-04 18:27:03'),
(56, 'Cuba', '2025-09-04 18:27:03'),
(57, 'Dinamarca', '2025-09-04 18:27:03'),
(58, 'Dominica', '2025-09-04 18:27:03'),
(59, 'Egipto', '2025-09-04 18:27:03'),
(60, 'El Salvador', '2025-09-04 18:27:03'),
(61, 'Emiratos Árabes Unidos', '2025-09-04 18:27:03'),
(62, 'Eritrea', '2025-09-04 18:27:03'),
(63, 'Eslovaquia', '2025-09-04 18:27:03'),
(64, 'Eslovenia', '2025-09-04 18:27:03'),
(65, 'España', '2025-09-04 18:27:03'),
(66, 'Estados Unidos', '2025-09-04 18:27:03'),
(67, 'Estonia', '2025-09-04 18:27:03'),
(68, 'Esuatini', '2025-09-04 18:27:03'),
(69, 'Etiopía', '2025-09-04 18:27:03'),
(70, 'Filipinas', '2025-09-04 18:27:03'),
(71, 'Finlandia', '2025-09-04 18:27:03'),
(72, 'Fiyi', '2025-09-04 18:27:03'),
(73, 'Francia', '2025-09-04 18:27:03'),
(92, 'Irak', '2025-09-04 18:32:07'),
(93, 'Irán', '2025-09-04 18:32:07'),
(94, 'Irlanda', '2025-09-04 18:32:07'),
(95, 'Islandia', '2025-09-04 18:32:07'),
(96, 'Islas Marshall', '2025-09-04 18:32:07'),
(97, 'Islas Salomón', '2025-09-04 18:32:07'),
(98, 'Israel', '2025-09-04 18:32:07'),
(99, 'Italia', '2025-09-04 18:32:07'),
(100, 'Jamaica', '2025-09-04 18:32:07'),
(101, 'Japón', '2025-09-04 18:32:07'),
(102, 'Jordania', '2025-09-04 18:32:07'),
(103, 'Kazajistán', '2025-09-04 18:32:07'),
(104, 'Kenia', '2025-09-04 18:32:07'),
(105, 'Kirguistán', '2025-09-04 18:32:07'),
(106, 'Kiribati', '2025-09-04 18:32:07'),
(107, 'Kuwait', '2025-09-04 18:32:07'),
(108, 'Laos', '2025-09-04 18:32:07'),
(109, 'Lesoto', '2025-09-04 18:32:07'),
(110, 'Letonia', '2025-09-04 18:32:07'),
(111, 'Líbano', '2025-09-04 18:32:07'),
(112, 'Liberia', '2025-09-04 18:32:07'),
(113, 'Libia', '2025-09-04 18:32:07'),
(114, 'Liechtenstein', '2025-09-04 18:32:07'),
(115, 'Lituania', '2025-09-04 18:32:07'),
(116, 'Luxemburgo', '2025-09-04 18:32:07'),
(117, 'Madagascar', '2025-09-04 18:32:07'),
(118, 'Malasia', '2025-09-04 18:32:07'),
(119, 'Malaui', '2025-09-04 18:32:07'),
(120, 'Maldivas', '2025-09-04 18:32:07'),
(121, 'Malí', '2025-09-04 18:32:07'),
(122, 'Malta', '2025-09-04 18:32:07'),
(123, 'Marruecos', '2025-09-04 18:32:07'),
(124, 'Mauricio', '2025-09-04 18:32:07'),
(125, 'Mauritania', '2025-09-04 18:32:07'),
(126, 'México', '2025-09-04 18:32:07'),
(127, 'Micronesia', '2025-09-04 18:32:07'),
(128, 'Moldavia', '2025-09-04 18:32:07'),
(129, 'Mónaco', '2025-09-04 18:32:07'),
(130, 'Mongolia', '2025-09-04 18:32:07'),
(131, 'Montenegro', '2025-09-04 18:32:07'),
(132, 'Mozambique', '2025-09-04 18:32:07'),
(133, 'Namibia', '2025-09-04 18:32:07'),
(134, 'Nauru', '2025-09-04 18:32:07'),
(135, 'Nepal', '2025-09-04 18:32:07'),
(136, 'Nicaragua', '2025-09-04 18:32:07'),
(137, 'Níger', '2025-09-04 18:32:07'),
(138, 'Nigeria', '2025-09-04 18:32:07'),
(139, 'Noruega', '2025-09-04 18:32:07'),
(140, 'Nueva Zelanda', '2025-09-04 18:32:07'),
(141, 'Omán', '2025-09-04 18:32:07'),
(142, 'Países Bajos', '2025-09-04 18:32:07'),
(143, 'Pakistán', '2025-09-04 18:32:07'),
(144, 'Palaos', '2025-09-04 18:32:07'),
(145, 'Palestina', '2025-09-04 18:32:07'),
(146, 'Panamá', '2025-09-04 18:32:07'),
(147, 'Papúa Nueva Guinea', '2025-09-04 18:32:07'),
(148, 'Polonia', '2025-09-04 18:32:07'),
(149, 'Portugal', '2025-09-04 18:32:07'),
(150, 'Reino Unido', '2025-09-04 18:32:07'),
(151, 'República Centroafricana', '2025-09-04 18:32:07'),
(152, 'República Checa', '2025-09-04 18:32:07'),
(153, 'República del Congo', '2025-09-04 18:32:07'),
(154, 'República Democrática del Congo', '2025-09-04 18:32:07'),
(155, 'República Dominicana', '2025-09-04 18:32:07'),
(156, 'Ruanda', '2025-09-04 18:32:07'),
(157, 'Rumania', '2025-09-04 18:32:07'),
(158, 'Rusia', '2025-09-04 18:32:07'),
(159, 'Samoa', '2025-09-04 18:32:07'),
(163, 'Gabón', '2025-09-04 18:38:27'),
(164, 'Gambia', '2025-09-04 18:38:27'),
(165, 'Georgia', '2025-09-04 18:38:27'),
(166, 'Ghana', '2025-09-04 18:38:27'),
(167, 'Granada', '2025-09-04 18:38:27'),
(168, 'Grecia', '2025-09-04 18:38:27'),
(169, 'Guatemala', '2025-09-04 18:38:27');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `company_id` int DEFAULT NULL,
  `branch_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `sender_id` int DEFAULT NULL,
  `receiver_id` int DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `scope` enum('direct','admin_only') COLLATE utf8mb4_unicode_ci DEFAULT 'direct'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `site_id` int NOT NULL,
  `message` text COLLATE utf8mb4_general_ci,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by_admin_id` int DEFAULT NULL,
  `target_admin_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`id`, `name`, `country_id`, `created_at`) VALUES
(1, 'Buenos Aires', 1, '2025-09-04 17:15:52'),
(2, 'Córdoba', 1, '2025-09-04 17:15:52'),
(3, 'Santa Fe', 1, '2025-09-04 17:15:52'),
(4, 'Mendoza', 1, '2025-09-04 17:15:52'),
(5, 'Tucumán', 1, '2025-09-04 17:15:52'),
(6, 'Salta', 1, '2025-09-04 17:15:52'),
(7, 'Entre Ríos', 1, '2025-09-04 17:15:52'),
(8, 'Misiones', 1, '2025-09-04 17:15:52'),
(9, 'Chaco', 1, '2025-09-04 17:15:52'),
(10, 'Formosa', 1, '2025-09-04 17:15:52'),
(11, 'San Juan', 1, '2025-09-04 17:15:52'),
(12, 'La Rioja', 1, '2025-09-04 17:15:52'),
(13, 'Catamarca', 1, '2025-09-04 17:15:52'),
(14, 'Jujuy', 1, '2025-09-04 17:15:52'),
(15, 'Río Negro', 1, '2025-09-04 17:15:52'),
(16, 'Neuquén', 1, '2025-09-04 17:15:52'),
(17, 'Chubut', 1, '2025-09-04 17:15:52'),
(18, 'Santa Cruz', 1, '2025-09-04 17:15:52'),
(19, 'Tierra del Fuego', 1, '2025-09-04 17:15:52'),
(20, 'Santiago del Estero', 1, '2025-09-04 17:15:52'),
(21, 'San Luis', 1, '2025-09-04 17:15:52'),
(22, 'Corrientes', 1, '2025-09-04 17:15:52'),
(23, 'La Pampa', 1, '2025-09-04 17:15:52'),
(24, 'Montevideo', 2, '2025-09-04 17:15:52'),
(25, 'Canelones', 2, '2025-09-04 17:15:52'),
(26, 'Maldonado', 2, '2025-09-04 17:15:52'),
(27, 'Salto', 2, '2025-09-04 17:15:52'),
(28, 'Paysandú', 2, '2025-09-04 17:15:52'),
(29, 'Río Negro', 2, '2025-09-04 17:15:52'),
(30, 'Durazno', 2, '2025-09-04 17:15:52'),
(31, 'Flores', 2, '2025-09-04 17:15:52'),
(32, 'Florida', 2, '2025-09-04 17:15:52'),
(33, 'San José', 2, '2025-09-04 17:15:52'),
(34, 'Colonia', 2, '2025-09-04 17:15:52'),
(35, 'Soriano', 2, '2025-09-04 17:15:52'),
(36, 'Tacuarembó', 2, '2025-09-04 17:15:52'),
(37, 'Rivera', 2, '2025-09-04 17:15:52'),
(38, 'Artigas', 2, '2025-09-04 17:15:52'),
(39, 'Cerro Largo', 2, '2025-09-04 17:15:52'),
(40, 'Lavalleja', 2, '2025-09-04 17:15:52'),
(41, 'Treinta y Tres', 2, '2025-09-04 17:15:52'),
(42, 'Asunción', 3, '2025-09-04 17:15:52'),
(43, 'Central', 3, '2025-09-04 17:15:52'),
(44, 'Alto Paraná', 3, '2025-09-04 17:15:52'),
(45, 'Cordillera', 3, '2025-09-04 17:15:52'),
(46, 'Guairá', 3, '2025-09-04 17:15:52'),
(47, 'Caaguazú', 3, '2025-09-04 17:15:52'),
(48, 'Caazapá', 3, '2025-09-04 17:15:52'),
(49, 'Itapúa', 3, '2025-09-04 17:15:52'),
(50, 'Misiones', 3, '2025-09-04 17:15:52'),
(51, 'Paraguarí', 3, '2025-09-04 17:15:52'),
(52, 'Amambay', 3, '2025-09-04 17:15:52'),
(53, 'Canindeyú', 3, '2025-09-04 17:15:52'),
(54, 'Presidente Hayes', 3, '2025-09-04 17:15:52'),
(55, 'Alto Paraguay', 3, '2025-09-04 17:15:52'),
(56, 'Boquerón', 3, '2025-09-04 17:15:52'),
(75, 'San Andrés y Providencia', 7, '2025-09-04 18:34:04'),
(76, 'Santander', 7, '2025-09-04 18:34:04'),
(77, 'Sucre', 7, '2025-09-04 18:34:04'),
(78, 'Tolima', 7, '2025-09-04 18:34:04'),
(79, 'Valle del Cauca', 7, '2025-09-04 18:34:04'),
(80, 'Vaupés', 7, '2025-09-04 18:34:04'),
(81, 'Vichada', 7, '2025-09-04 18:34:04'),
(82, 'Azuay', 8, '2025-09-04 18:34:04'),
(83, 'Bolívar', 8, '2025-09-04 18:34:04'),
(84, 'Cañar', 8, '2025-09-04 18:34:04'),
(85, 'Carchi', 8, '2025-09-04 18:34:04'),
(86, 'Chimborazo', 8, '2025-09-04 18:34:04'),
(87, 'Cotopaxi', 8, '2025-09-04 18:34:04'),
(88, 'El Oro', 8, '2025-09-04 18:34:04'),
(89, 'Esmeraldas', 8, '2025-09-04 18:34:04'),
(90, 'Galápagos', 8, '2025-09-04 18:34:04'),
(91, 'Guayas', 8, '2025-09-04 18:34:04'),
(92, 'Imbabura', 8, '2025-09-04 18:34:04'),
(93, 'Loja', 8, '2025-09-04 18:34:04'),
(94, 'Los Ríos', 8, '2025-09-04 18:34:04'),
(95, 'Manabí', 8, '2025-09-04 18:34:04'),
(96, 'Morona Santiago', 8, '2025-09-04 18:34:04'),
(97, 'Napo', 8, '2025-09-04 18:34:04'),
(98, 'Orellana', 8, '2025-09-04 18:34:04'),
(99, 'Pastaza', 8, '2025-09-04 18:34:04'),
(100, 'Pichincha', 8, '2025-09-04 18:34:04'),
(101, 'Santa Elena', 8, '2025-09-04 18:34:04'),
(102, 'Santo Domingo de los Tsáchilas', 8, '2025-09-04 18:34:04'),
(103, 'Sucumbíos', 8, '2025-09-04 18:34:04'),
(104, 'Tungurahua', 8, '2025-09-04 18:34:04'),
(105, 'Zamora-Chinchipe', 8, '2025-09-04 18:34:04'),
(106, 'Barima-Waini', 9, '2025-09-04 18:34:04'),
(107, 'Cuyuni-Mazaruni', 9, '2025-09-04 18:34:04'),
(108, 'Demerara-Mahaica', 9, '2025-09-04 18:34:04'),
(109, 'East Berbice-Corentyne', 9, '2025-09-04 18:34:04'),
(110, 'Essequibo Islands-West Demerara', 9, '2025-09-04 18:34:04'),
(111, 'Mahaica-Berbice', 9, '2025-09-04 18:34:04'),
(112, 'Pomeroon-Supenaam', 9, '2025-09-04 18:34:04'),
(113, 'Potaro-Siparuni', 9, '2025-09-04 18:34:04'),
(114, 'Upper Demerara-Berbice', 9, '2025-09-04 18:34:04'),
(115, 'Upper Takutu-Upper Essequibo', 9, '2025-09-04 18:34:04'),
(116, 'Acre', 5, '2025-09-04 18:35:31'),
(117, 'Alagoas', 5, '2025-09-04 18:35:31'),
(118, 'Amapá', 5, '2025-09-04 18:35:31'),
(119, 'Amazonas', 5, '2025-09-04 18:35:31'),
(120, 'Bahia', 5, '2025-09-04 18:35:31'),
(121, 'Ceará', 5, '2025-09-04 18:35:31'),
(122, 'Distrito Federal', 5, '2025-09-04 18:35:31'),
(123, 'Espírito Santo', 5, '2025-09-04 18:35:31'),
(124, 'Goiás', 5, '2025-09-04 18:35:31'),
(125, 'Maranhão', 5, '2025-09-04 18:35:31'),
(126, 'Mato Grosso', 5, '2025-09-04 18:35:31'),
(127, 'Mato Grosso do Sul', 5, '2025-09-04 18:35:31'),
(128, 'Minas Gerais', 5, '2025-09-04 18:35:31'),
(129, 'Pará', 5, '2025-09-04 18:35:31'),
(130, 'Paraíba', 5, '2025-09-04 18:35:31'),
(131, 'Paraná', 5, '2025-09-04 18:35:31'),
(132, 'Pernambuco', 5, '2025-09-04 18:35:31'),
(133, 'Piauí', 5, '2025-09-04 18:35:31'),
(134, 'Rio de Janeiro', 5, '2025-09-04 18:35:31'),
(135, 'Rio Grande do Norte', 5, '2025-09-04 18:35:31'),
(136, 'Rio Grande do Sul', 5, '2025-09-04 18:35:31'),
(137, 'Rondônia', 5, '2025-09-04 18:35:31'),
(138, 'Roraima', 5, '2025-09-04 18:35:31'),
(139, 'Santa Catarina', 5, '2025-09-04 18:35:31'),
(140, 'São Paulo', 5, '2025-09-04 18:35:31'),
(141, 'Sergipe', 5, '2025-09-04 18:35:31'),
(142, 'Tocantins', 5, '2025-09-04 18:35:31'),
(143, 'Arica y Parinacota', 6, '2025-09-04 18:35:49'),
(144, 'Tarapacá', 6, '2025-09-04 18:35:49'),
(145, 'Antofagasta', 6, '2025-09-04 18:35:49'),
(146, 'Atacama', 6, '2025-09-04 18:35:49'),
(147, 'Coquimbo', 6, '2025-09-04 18:35:49'),
(148, 'Valparaíso', 6, '2025-09-04 18:35:49'),
(149, 'Metropolitana de Santiago', 6, '2025-09-04 18:35:49'),
(150, 'O’Higgins', 6, '2025-09-04 18:35:49'),
(151, 'Maule', 6, '2025-09-04 18:35:49'),
(152, 'Ñuble', 6, '2025-09-04 18:35:49'),
(153, 'Biobío', 6, '2025-09-04 18:35:49'),
(154, 'La Araucanía', 6, '2025-09-04 18:35:49'),
(155, 'Los Ríos', 6, '2025-09-04 18:35:49'),
(156, 'Los Lagos', 6, '2025-09-04 18:35:49'),
(157, 'Aysén', 6, '2025-09-04 18:35:49'),
(158, 'Magallanes y la Antártica Chilena', 6, '2025-09-04 18:35:49'),
(159, 'Amazonas', 7, '2025-09-04 18:36:08'),
(160, 'Antioquia', 7, '2025-09-04 18:36:08'),
(161, 'Arauca', 7, '2025-09-04 18:36:08'),
(162, 'Atlántico', 7, '2025-09-04 18:36:08'),
(163, 'Bolívar', 7, '2025-09-04 18:36:08'),
(164, 'Boyacá', 7, '2025-09-04 18:36:08'),
(165, 'Caldas', 7, '2025-09-04 18:36:08'),
(166, 'Caquetá', 7, '2025-09-04 18:36:08'),
(167, 'Casanare', 7, '2025-09-04 18:36:08'),
(168, 'Cauca', 7, '2025-09-04 18:36:08'),
(169, 'Cesar', 7, '2025-09-04 18:36:08'),
(170, 'Chocó', 7, '2025-09-04 18:36:08'),
(172, 'Cundinamarca', 7, '2025-09-04 18:36:08'),
(173, 'Guainía', 7, '2025-09-04 18:36:08'),
(174, 'Guaviare', 7, '2025-09-04 18:36:08'),
(175, 'Huila', 7, '2025-09-04 18:36:08'),
(176, 'La Guajira', 7, '2025-09-04 18:36:08'),
(177, 'Magdalena', 7, '2025-09-04 18:36:08'),
(178, 'Meta', 7, '2025-09-04 18:36:08'),
(179, 'Nariño', 7, '2025-09-04 18:36:08'),
(180, 'Norte de Santander', 7, '2025-09-04 18:36:08'),
(181, 'Putumayo', 7, '2025-09-04 18:36:08'),
(182, 'Quindío', 7, '2025-09-04 18:36:08'),
(183, 'Risaralda', 7, '2025-09-04 18:36:08');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int NOT NULL,
  `service_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_password_encrypted` blob,
  `iv` blob,
  `password_needs_update` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `user_id` int NOT NULL,
  `site_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shared_sites_assignments`
--

CREATE TABLE `shared_sites_assignments` (
  `id` int NOT NULL,
  `site_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `assigned_by` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Usuario por defecto para este sitio',
  `password_encrypted` blob,
  `iv` blob,
  `password_needs_update` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Flag para notificar si la pass expiró',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL DEFAULT '1',
  `department_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('superadmin','admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `company_id` int DEFAULT NULL,
  `branch_id` int DEFAULT NULL,
  `assigned_admin_id` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `theme` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'light'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `company_id`, `branch_id`, `assigned_admin_id`, `is_active`, `created_at`, `created_by`, `department_id`, `theme`) VALUES
(1, 'admin', '$2y$10$NlguZUw9cO1.weM9Sw2sL.1B61BTQNhv/5do/Z66SLZOR8Zo7OdIy', 'superadmin', NULL, NULL, NULL, 1, '2025-08-27 19:37:58', NULL, NULL, 'light');

-- --------------------------------------------------------

--
-- Table structure for table `user_reminders`
--

CREATE TABLE `user_reminders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `type` enum('credential','note') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_encrypted` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `reminder_datetime` datetime DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sites`
--

CREATE TABLE `user_sites` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_encrypted` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_branch` (`company_id`,`name`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_branch_department` (`branch_id`,`name`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `idx_conversation` (`sender_id`,`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `target_admin_id` (`target_admin_id`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_province_per_country` (`name`,`country_id`),
  ADD KEY `country_id` (`country_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`user_id`),
  ADD KEY `fk_services_site` (`site_id`);

--
-- Indexes for table `shared_sites_assignments`
--
ALTER TABLE `shared_sites_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `site_id` (`site_id`,`admin_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `fk_assigned_admin` (`assigned_admin_id`),
  ADD KEY `fk_users_company` (`company_id`),
  ADD KEY `fk_users_branch` (`branch_id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `user_reminders`
--
ALTER TABLE `user_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_sites`
--
ALTER TABLE `user_sites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shared_sites_assignments`
--
ALTER TABLE `shared_sites_assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_reminders`
--
ALTER TABLE `user_reminders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sites`
--
ALTER TABLE `user_sites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `branches_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `departments_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_3` FOREIGN KEY (`target_admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `provinces`
--
ALTER TABLE `provinces`
  ADD CONSTRAINT `provinces_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_services_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `shared_sites_assignments`
--
ALTER TABLE `shared_sites_assignments`
  ADD CONSTRAINT `shared_sites_assignments_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `shared_sites_assignments_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `shared_sites_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sites`
--
ALTER TABLE `sites`
  ADD CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_assigned_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `fk_users_createdby` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `user_reminders`
--
ALTER TABLE `user_reminders`
  ADD CONSTRAINT `user_reminders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sites`
--
ALTER TABLE `user_sites`
  ADD CONSTRAINT `user_sites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

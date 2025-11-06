-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 06. Nov 2025 um 14:47
-- Server-Version: 8.0.43-0ubuntu0.24.04.1
-- PHP-Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `Kasse`
--

DELIMITER $$
--
-- Prozeduren
--
CREATE DEFINER=`Admin`@`localhost` PROCEDURE `v2_ensure_member_exists` (IN `in_name` VARCHAR(100), IN `in_flag` VARCHAR(10))   BEGIN
  DECLARE mid INT UNSIGNED$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `admins`
--

CREATE TABLE `admins` (
  `pin` varchar(6) NOT NULL,
  `member_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `admins_v2`
--

CREATE TABLE `admins_v2` (
  `member_id` int UNSIGNED NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `granted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `admins_v2`
--

INSERT INTO `admins_v2` (`member_id`, `is_admin`, `granted_at`) VALUES
(1, 1, '2025-11-06 14:40:05');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `admin_board`
--

CREATE TABLE `admin_board` (
  `id` int UNSIGNED NOT NULL,
  `type` enum('event','announcement') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `scheduled_for` datetime DEFAULT NULL,
  `created_by` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `admin_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `discord_notifications`
--

CREATE TABLE `discord_notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `event_id` bigint UNSIGNED DEFAULT NULL,
  `payload` json NOT NULL,
  `status` enum('queued','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `discord_status_cache`
--

CREATE TABLE `discord_status_cache` (
  `member_id` int UNSIGNED NOT NULL,
  `status` enum('online','away','busy','offline') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'offline',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `events`
--

CREATE TABLE `events` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `start` datetime NOT NULL,
  `end` datetime DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `paid_by` enum('pool','private') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'private',
  `created_by` int UNSIGNED DEFAULT NULL,
  `status` enum('active','canceled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `event_participants`
--

CREATE TABLE `event_participants` (
  `event_id` bigint UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `state` enum('yes','no','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `availability` enum('free','vacation','shift','sick') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `feedback_entries`
--

CREATE TABLE `feedback_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('new','read') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `holidays_cache`
--

CREATE TABLE `holidays_cache` (
  `id` int UNSIGNED NOT NULL,
  `holiday_date` date NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NRW'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `members`
--

CREATE TABLE `members` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `flag` varchar(32) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `pic` varchar(255) DEFAULT NULL,
  `pin` varchar(6) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `members_v2`
--

CREATE TABLE `members_v2` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discord_tag` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roles` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Europe/Berlin',
  `flag` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `joined_at` date NOT NULL DEFAULT (curdate()),
  `left_at` date DEFAULT NULL,
  `status` enum('active','inactive','banned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `pin_plain` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `members_v2`
--

INSERT INTO `members_v2` (`id`, `name`, `email`, `discord_tag`, `avatar_url`, `roles`, `timezone`, `flag`, `joined_at`, `left_at`, `status`, `is_locked`, `pin_plain`, `created_at`, `updated_at`) VALUES
(1, 'AdminDemo', NULL, NULL, NULL, 'member', 'Europe/Berlin', '🏁', '2025-11-06', NULL, 'active', 0, '1234', '2025-11-06 14:40:05', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `payment_requests`
--

CREATE TABLE `payment_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `token` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_reference` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `reservations_v2`
--

CREATE TABLE `reservations_v2` (
  `id` bigint UNSIGNED NOT NULL,
  `event_id` bigint UNSIGNED DEFAULT NULL,
  `member_id` int UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('active','released','consumed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `settings_v2`
--

CREATE TABLE `settings_v2` (
  `key_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `settings_v2`
--

INSERT INTO `settings_v2` (`key_name`, `value`, `updated_at`) VALUES
('delay_no_gutschrift', '1', '2025-11-06 14:40:05'),
('max_prepay_months', '6', '2025-11-06 14:40:05'),
('monthly_fee', '10.00', '2025-11-06 14:40:05'),
('start_fee_may_2025', '5.00', '2025-11-06 14:40:05'),
('transfer_on_leave', '1', '2025-11-06 14:40:05');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `shifts`
--

CREATE TABLE `shifts` (
  `id` bigint UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `member_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shift_date` date NOT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `shift_type` enum('early','late','night','day','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sickdays`
--

CREATE TABLE `sickdays` (
  `id` bigint UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `transactions`
--

CREATE TABLE `transactions` (
  `uid` int UNSIGNED NOT NULL,
  `id` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` date NOT NULL,
  `type` enum('Einzahlung','Auszahlung','Gutschrift','Schaden','Gruppenaktion') NOT NULL,
  `reason` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `transactions_v2`
--

CREATE TABLE `transactions_v2` (
  `id` bigint UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `event_id` bigint UNSIGNED DEFAULT NULL,
  `payment_request_id` bigint UNSIGNED DEFAULT NULL,
  `reservation_id` bigint UNSIGNED DEFAULT NULL,
  `type` enum('Einzahlung','Auszahlung','Gutschrift','Schaden','Gruppenaktion','Gruppenaktion_anteilig','Reservierung','Ausgleich','Korrektur','Umbuchung') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('gebucht','gesperrt','storniert') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gebucht',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_settings`
--

CREATE TABLE `user_settings` (
  `member_id` int UNSIGNED NOT NULL,
  `theme` enum('light','dark') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dark',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v2_kassenstand_real`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v2_kassenstand_real` (
`kassenstand` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v2_member_gross_flow`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v2_member_gross_flow` (
`member_id` int unsigned
,`name` varchar(100)
,`gross_flow` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v2_member_real_balance`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v2_member_real_balance` (
`member_id` int unsigned
,`name` varchar(100)
,`real_balance` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vacations`
--

CREATE TABLE `vacations` (
  `id` bigint UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur des Views `v2_kassenstand_real`
--
DROP TABLE IF EXISTS `v2_kassenstand_real`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Admin`@`localhost` SQL SECURITY DEFINER VIEW `v2_kassenstand_real`  AS SELECT round(coalesce(sum((case when (`transactions_v2`.`type` = 'Einzahlung') then `transactions_v2`.`amount` when (`transactions_v2`.`type` = 'Gutschrift') then 0 when (`transactions_v2`.`type` in ('Auszahlung','Schaden','Gruppenaktion')) then -(`transactions_v2`.`amount`) else 0 end)),0),2) AS `kassenstand` FROM `transactions_v2` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v2_member_gross_flow`
--
DROP TABLE IF EXISTS `v2_member_gross_flow`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Admin`@`localhost` SQL SECURITY DEFINER VIEW `v2_member_gross_flow`  AS SELECT `m`.`id` AS `member_id`, `m`.`name` AS `name`, round(coalesce(sum((case when (`t`.`type` = 'Einzahlung') then `t`.`amount` when (`t`.`type` = 'Gutschrift') then 0 when (`t`.`type` in ('Auszahlung','Schaden','Gruppenaktion')) then -(`t`.`amount`) else 0 end)),0),2) AS `gross_flow` FROM (`members_v2` `m` left join `transactions_v2` `t` on((`t`.`member_id` = `m`.`id`))) GROUP BY `m`.`id`, `m`.`name` ;

-- --------------------------------------------------------

--
-- Struktur des Views `v2_member_real_balance`
--
DROP TABLE IF EXISTS `v2_member_real_balance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`Admin`@`localhost` SQL SECURITY DEFINER VIEW `v2_member_real_balance`  AS SELECT `m`.`id` AS `member_id`, `m`.`name` AS `name`, round(coalesce(sum((case when (`t`.`type` = 'Einzahlung') then `t`.`amount` when (`t`.`type` in ('Auszahlung','Schaden','Gruppenaktion')) then -(`t`.`amount`) else 0 end)),0),2) AS `real_balance` FROM (`members_v2` `m` left join `transactions_v2` `t` on((`t`.`member_id` = `m`.`id`))) GROUP BY `m`.`id`, `m`.`name` ;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`pin`),
  ADD KEY `idx_member_name` (`member_name`);

--
-- Indizes für die Tabelle `admins_v2`
--
ALTER TABLE `admins_v2`
  ADD PRIMARY KEY (`member_id`);

--
-- Indizes für die Tabelle `admin_board`
--
ALTER TABLE `admin_board`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_board_schedule` (`scheduled_for`),
  ADD KEY `idx_admin_board_type` (`type`);

--
-- Indizes für die Tabelle `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_logs_action` (`action`),
  ADD KEY `idx_admin_logs_entity` (`entity_type`,`entity_id`),
  ADD KEY `fk_admin_logs_member` (`admin_id`);

--
-- Indizes für die Tabelle `discord_notifications`
--
ALTER TABLE `discord_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_status` (`status`),
  ADD KEY `fk_notifications_event` (`event_id`);

--
-- Indizes für die Tabelle `discord_status_cache`
--
ALTER TABLE `discord_status_cache`
  ADD PRIMARY KEY (`member_id`);

--
-- Indizes für die Tabelle `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_start` (`start`),
  ADD KEY `idx_events_status` (`status`),
  ADD KEY `fk_events_member` (`created_by`);

--
-- Indizes für die Tabelle `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`event_id`,`member_id`),
  ADD KEY `idx_event_participants_state` (`state`),
  ADD KEY `fk_ep_member` (`member_id`);

--
-- Indizes für die Tabelle `feedback_entries`
--
ALTER TABLE `feedback_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feedback_status` (`status`);

--
-- Indizes für die Tabelle `holidays_cache`
--
ALTER TABLE `holidays_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_holiday_region` (`holiday_date`,`region`);

--
-- Indizes für die Tabelle `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_members_name` (`name`);

--
-- Indizes für die Tabelle `members_v2`
--
ALTER TABLE `members_v2`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_members_v2_name` (`name`),
  ADD KEY `idx_members_v2_status` (`status`),
  ADD KEY `idx_members_v2_joined` (`joined_at`);

--
-- Indizes für die Tabelle `payment_requests`
--
ALTER TABLE `payment_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payment_token` (`token`),
  ADD KEY `idx_payment_member` (`member_id`);

--
-- Indizes für die Tabelle `reservations_v2`
--
ALTER TABLE `reservations_v2`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservations_status` (`status`),
  ADD KEY `idx_reservations_event` (`event_id`),
  ADD KEY `fk_reservations_member` (`member_id`);

--
-- Indizes für die Tabelle `settings_v2`
--
ALTER TABLE `settings_v2`
  ADD PRIMARY KEY (`key_name`);

--
-- Indizes für die Tabelle `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shifts_member` (`member_id`),
  ADD KEY `idx_shifts_date` (`shift_date`);

--
-- Indizes für die Tabelle `sickdays`
--
ALTER TABLE `sickdays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sickdays_member` (`member_id`);

--
-- Indizes für die Tabelle `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`uid`),
  ADD UNIQUE KEY `uq_transactions_id` (`id`),
  ADD KEY `idx_transactions_name` (`name`),
  ADD KEY `idx_transactions_date` (`date`),
  ADD KEY `idx_transactions_type` (`type`);

--
-- Indizes für die Tabelle `transactions_v2`
--
ALTER TABLE `transactions_v2`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tx_v2_member` (`member_id`),
  ADD KEY `idx_tx_v2_created` (`created_at`),
  ADD KEY `idx_tx_v2_type` (`type`),
  ADD KEY `idx_tx_v2_status` (`status`),
  ADD KEY `idx_tx_v2_event` (`event_id`),
  ADD KEY `idx_tx_v2_payment` (`payment_request_id`);

--
-- Indizes für die Tabelle `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`member_id`);

--
-- Indizes für die Tabelle `vacations`
--
ALTER TABLE `vacations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vacations_member` (`member_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `admin_board`
--
ALTER TABLE `admin_board`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `discord_notifications`
--
ALTER TABLE `discord_notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `events`
--
ALTER TABLE `events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `feedback_entries`
--
ALTER TABLE `feedback_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `holidays_cache`
--
ALTER TABLE `holidays_cache`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `members`
--
ALTER TABLE `members`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `members_v2`
--
ALTER TABLE `members_v2`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT für Tabelle `payment_requests`
--
ALTER TABLE `payment_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `reservations_v2`
--
ALTER TABLE `reservations_v2`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `sickdays`
--
ALTER TABLE `sickdays`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `transactions`
--
ALTER TABLE `transactions`
  MODIFY `uid` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `transactions_v2`
--
ALTER TABLE `transactions_v2`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vacations`
--
ALTER TABLE `vacations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `admins_v2`
--
ALTER TABLE `admins_v2`
  ADD CONSTRAINT `fk_admins_v2_member` FOREIGN KEY (`member_id`) REFERENCES `members_v2` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `fk_admin_logs_member` FOREIGN KEY (`admin_id`) REFERENCES `members_v2` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `discord_notifications`
--
ALTER TABLE `discord_notifications`
  ADD CONSTRAINT `fk_notifications_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `discord_status_cache`
--
ALTER TABLE `discord_status_cache`
  ADD CONSTRAINT `fk_discord_status_member` FOREIGN KEY (`member_id`) REFERENCES `members_v2` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_member` FOREIGN KEY (`created_by`) REFERENCES `members_v2` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `fk_ep_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ep_member` FOREIGN KEY (`member_id`) REFERENCES `members_v2` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `payment_requests`
--
ALTER TABLE `payment_requests`
  ADD CONSTRAINT `fk_payment_member` FOREIGN KEY (`member_id`) REFERENCES `members_v2` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `reservations_v2`
--
ALTER TABLE `reservations_v2`
  ADD CONSTRAINT `fk_reservations_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservations_member` FOREIGN KEY (`member_id`) REFERENCES `members_v2` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `sickdays`
--
ALTER TABLE `sickdays`
  ADD CONSTRAINT `fk_sickdays_member` FOREIGN KEY (`member_id`) REFERENCES `members_v2` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `transactions_v2`
--
ALTER TABLE `transactions_v2`
  ADD CONSTRAINT `fk_tx_v2_member` FOREIGN KEY (`member_id`) REFERENCES `members_v2` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints der Tabelle `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_user_settings_member` FOREIGN KEY (`member_id`) REFERENCES `members_v2` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `vacations`
--
ALTER TABLE `vacations`
  ADD CONSTRAINT `fk_vacations_member` FOREIGN KEY (`member_id`) REFERENCES `members_v2` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

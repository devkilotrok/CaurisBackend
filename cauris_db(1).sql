-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : dim. 01 fév. 2026 à 18:21
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `cauris_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `log_id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `admin_messages`
--

CREATE TABLE `admin_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID de l''admin qui envoie',
  `recipient_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID du super admin (peut être null pour messages généraux)',
  `subject` varchar(255) NOT NULL COMMENT 'Sujet du message',
  `message` text NOT NULL COMMENT 'Contenu du message',
  `media_type` enum('text','audio','image') NOT NULL DEFAULT 'text',
  `media_url` varchar(255) DEFAULT NULL,
  `media_data_temp` longtext DEFAULT NULL COMMENT 'Données base64 temporaires, supprimées après récupération par le destinataire',
  `status` enum('unread','read','replied') NOT NULL DEFAULT 'unread',
  `message_status` enum('sending','sent','delivered','read','failed') NOT NULL DEFAULT 'sent',
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `edited_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `reactions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`reactions`)),
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'ID du message parent (pour les réponses)',
  `reply_to_message_id` bigint(20) UNSIGNED DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `announcement_value` int(11) NOT NULL CHECK (`announcement_value` between 0 and 13),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_conversations`
--

CREATE TABLE `chat_conversations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('active','waiting_manager','with_manager','closed') NOT NULL DEFAULT 'active',
  `assigned_manager_id` int(11) DEFAULT NULL,
  `assistant_type` enum('ai','manager') NOT NULL DEFAULT 'ai',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `conversation_id` bigint(20) UNSIGNED NOT NULL,
  `sender_type` enum('user','ai','manager') NOT NULL,
  `sender_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('unread','read','processed') NOT NULL DEFAULT 'unread',
  `read_by` bigint(20) UNSIGNED DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `processed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `email_verification_codes`
--

CREATE TABLE `email_verification_codes` (
  `code_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `code` varchar(6) NOT NULL COMMENT 'Code à 6 chiffres',
  `type` enum('verification','reset') NOT NULL COMMENT 'Type de code',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `friendships`
--

CREATE TABLE `friendships` (
  `friendship_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `status` enum('pending','accepted','blocked') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `friend_requests`
--

CREATE TABLE `friend_requests` (
  `request_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `games`
--

CREATE TABLE `games` (
  `game_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `deck_id` varchar(100) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `final_scores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`final_scores`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2025_10_30_000002_update_games_table_finalize_fields', 1),
(2, '2025_10_30_000001_update_rounds_table_for_game_data', 2),
(3, '2025_10_30_100000_normalize_schema_option_a', 3),
(4, '2025_10_30_100001_update_room_players_timestamps', 4),
(5, '2025_10_30_100002_add_deck_hash_to_rounds', 5),
(7, '2025_11_01_024722_create_player_replacements_tables', 6),
(8, '2025_11_01_032634_add_is_bot_to_users_table', 7),
(9, '2025_01_20_000000_create_contact_messages_table', 8),
(10, '2025_01_20_120000_add_role_to_users_table', 9),
(12, '2025_01_21_000000_create_chat_tables', 10),
(13, '2025_01_20_130000_remove_is_admin_from_users_table', 11),
(15, '2025_01_21_000000_create_admin_messages_table', 12),
(16, '2025_11_15_000000_create_room_chat_messages_table', 13),
(17, '2014_10_12_000000_create_users_table', 14),
(18, '2014_10_12_100000_create_password_reset_tokens_table', 14),
(19, '2019_08_19_000000_create_failed_jobs_table', 14),
(20, '2019_12_14_000001_create_personal_access_tokens_table', 14),
(21, '2025_01_21_100000_add_message_features_to_admin_messages', 14),
(22, '2025_10_26_094848_create_friend_requests_table', 15),
(23, '2025_10_26_094848_create_friendships_table', 16),
(24, '2025_11_16_054845_add_media_to_admin_messages_table', 17),
(25, '2025_11_16_060440_add_media_data_temp_to_admin_messages_table', 18),
(26, '2025_11_16_061637_add_presence_to_users_table', 19),
(27, '2025_11_16_070247_add_reactions_to_admin_messages_table', 20),
(28, '2025_11_27_135416_create_jobs_table', 21),
(29, '2025_11_27_175344_add_distributed_cards_to_rounds_table', 22),
(30, '2025_12_01_075113_add_updated_at_to_announcements_table', 23),
(31, '2025_12_02_002935_add_status_and_announcement_end_at_to_rounds_table', 24),
(32, '2025_12_02_010626_modify_status_column_in_rounds_table', 25);

-- --------------------------------------------------------

--
-- Structure de la table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `played_cards`
--

CREATE TABLE `played_cards` (
  `card_id` int(11) NOT NULL,
  `trick_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `card_code` varchar(3) NOT NULL,
  `card_value` varchar(10) NOT NULL,
  `card_suit` varchar(10) NOT NULL,
  `played_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `player_disconnections`
--

CREATE TABLE `player_disconnections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_id` int(11) NOT NULL,
  `player_name` varchar(50) NOT NULL,
  `disconnected_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reconnected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `player_replacements`
--

CREATE TABLE `player_replacements` (
  `replacement_id` bigint(20) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `player_name` varchar(50) NOT NULL,
  `bot_name` varchar(50) NOT NULL,
  `is_permanent` tinyint(1) NOT NULL DEFAULT 0,
  `disconnected_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `restored_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `player_stats`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `player_stats` (
`user_id` int(11)
,`pseudo` varchar(50)
,`email` varchar(100)
,`total_games` bigint(21)
,`games_won` bigint(21)
,`games_lost` bigint(21)
,`avg_score` decimal(14,4)
,`best_score` int(11)
,`is_admin` tinyint(1)
,`is_active` tinyint(1)
,`last_login` timestamp
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure de la table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `room_code` varchar(6) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `minimum_bet` int(11) DEFAULT 50,
  `status` enum('waiting','playing','finished','cancelled') DEFAULT 'waiting',
  `max_players` int(11) DEFAULT 4,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `room_chat_messages`
--

CREATE TABLE `room_chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message_type` enum('text','preset','emoji') NOT NULL DEFAULT 'text',
  `preset_code` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `room_invitations`
--

CREATE TABLE `room_invitations` (
  `invitation_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled') DEFAULT 'pending',
  `message` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `room_players`
--

CREATE TABLE `room_players` (
  `player_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `position` int(11) NOT NULL CHECK (`position` between 1 and 4),
  `is_creator` tinyint(1) DEFAULT 0,
  `status` enum('waiting','ready','playing','left') DEFAULT 'waiting',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_replacement_bot` tinyint(1) NOT NULL DEFAULT 0,
  `replaced_player_name` varchar(50) DEFAULT NULL,
  `is_excluded` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `room_stats`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `room_stats` (
`room_id` int(11)
,`room_name` varchar(100)
,`room_code` varchar(6)
,`creator_id` int(11)
,`creator_pseudo` varchar(50)
,`minimum_bet` int(11)
,`status` enum('waiting','playing','finished','cancelled')
,`current_players` bigint(21)
,`max_players` int(11)
,`total_games` bigint(21)
,`created_at` timestamp
,`started_at` timestamp
,`finished_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure de la table `rounds`
--

CREATE TABLE `rounds` (
  `round_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `deck_hash` varchar(128) DEFAULT NULL,
  `announcements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`announcements`)),
  `results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`results`)),
  `trick_winner_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL COMMENT 'Statut du round: ANNOUNCEMENT_PHASE, PLAYING, FINISHED',
  `announcement_end_at` timestamp NULL DEFAULT NULL COMMENT 'Date/heure de fin de la phase d''annonces (timeout)',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  `room_id` varchar(255) NOT NULL,
  `obtained_tricks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`obtained_tricks`)),
  `distributed_cards` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`distributed_cards`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `scores`
--

CREATE TABLE `scores` (
  `score_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `round_id` int(11) DEFAULT NULL,
  `player_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `announcement` int(11) DEFAULT 0,
  `tricks_won` int(11) DEFAULT 0,
  `round_score` int(11) DEFAULT 0,
  `cumulative_score` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('depot','retrait') NOT NULL,
  `cauris_amount` int(11) NOT NULL,
  `fcfa_amount` int(11) NOT NULL,
  `beneficiaire_name` varchar(255) DEFAULT NULL COMMENT 'Nom du bénéficiaire pour les retraits',
  `phone_number` varchar(20) DEFAULT NULL COMMENT 'Numéro de téléphone pour les retraits',
  `image_path` varchar(500) DEFAULT NULL COMMENT 'Chemin de la preuve de paiement pour les dépôts',
  `status` enum('en_attente','valide','rejete') DEFAULT 'en_attente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `validated_at` timestamp NULL DEFAULT NULL,
  `validated_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `fedapay_transaction_id` varchar(255) DEFAULT NULL COMMENT 'ID de la transaction FedaPay',
  `fedapay_status` varchar(50) DEFAULT NULL COMMENT 'Statut de la transaction FedaPay (pending, approved, canceled)',
  `payment_method` varchar(50) DEFAULT 'manual' COMMENT 'Méthode de paiement (fedapay, manual)',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tricks`
--

CREATE TABLE `tricks` (
  `trick_id` int(11) NOT NULL,
  `round_id` int(11) NOT NULL,
  `trick_number` int(11) NOT NULL,
  `lead_player_id` int(11) NOT NULL,
  `winner_player_id` int(11) DEFAULT NULL,
  `cards_played` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`cards_played`)),
  `status` enum('in_progress','completed') DEFAULT 'in_progress',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `pseudo` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT 'default_avatar.png',
  `theme_preference` varchar(20) DEFAULT 'light',
  `cauris_balance` int(11) DEFAULT 0 COMMENT 'Solde en Cauris du joueur',
  `company_balance` int(11) DEFAULT 0 COMMENT 'Solde de l''entreprise',
  `role` enum('superadmin','admin','manager','user') NOT NULL DEFAULT 'user',
  `is_bot` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_seen_at` timestamp NULL DEFAULT NULL COMMENT 'Dernière activité de l''utilisateur',
  `first_name` varchar(50) DEFAULT NULL COMMENT 'Prénom de l''utilisateur',
  `last_name` varchar(50) DEFAULT NULL COMMENT 'Nom de l''utilisateur',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Numéro de téléphone',
  `address` text DEFAULT NULL COMMENT 'Adresse de l''utilisateur'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`user_id`, `pseudo`, `email`, `password_hash`, `avatar`, `theme_preference`, `cauris_balance`, `company_balance`, `role`, `is_bot`, `is_active`, `last_login`, `created_at`, `updated_at`, `last_seen_at`, `first_name`, `last_name`, `phone`, `address`) VALUES
(1, 'superAdmin', 'superadmin@cauris.com', '$2y$12$cgrrMgOSK5U15Rqnzy1creIPifrk/uUBDstk27GEzSc51hFELL.Vy', '👑', 'light', 0, 3975, 'superadmin', 0, 1, '2025-11-16 08:54:38', '2025-10-26 18:29:35', '2025-12-12 21:47:50', NULL, NULL, NULL, NULL, NULL),
(2, 'managerAdmin', 'manager@cauris.com', '$2y$12$cgrrMgOSK5U15Rqnzy1creIPifrk/uUBDstk27GEzSc51hFELL.Vy', '🔧', 'light', 0, 0, 'manager', 0, 1, '2025-11-16 08:53:56', '2025-10-26 18:29:35', '2025-11-16 08:53:56', NULL, NULL, NULL, NULL, NULL),
(3, 'admin', 'admin@cauris.com', '$2y$12$cgrrMgOSK5U15Rqnzy1creIPifrk/uUBDstk27GEzSc51hFELL.Vy', '🛡️', 'light', 0, 0, 'admin', 0, 1, '2025-11-16 08:52:46', '2025-10-26 18:29:35', '2025-11-16 08:52:46', NULL, NULL, NULL, NULL, NULL),
(28, 'Alpha', 'adolpheakotan@gmail.com', '$2y$12$cgrrMgOSK5U15Rqnzy1creIPifrk/uUBDstk27GEzSc51hFELL.Vy', '👤', 'light', 3035, 0, 'user', 0, 1, '2026-01-14 05:07:53', '2025-10-27 00:38:36', '2026-01-14 05:07:53', NULL, 'Adolphe', 'AKOTAN', NULL, NULL),
(29, 'Elias', 'eliasakotan@gmail.com', '$2y$12$6vbB0ov7.2e2uDtCWYbfmOlmm7ntPCwdD6Y5FRO6l634wPyzjSfEa', '👤', 'light', 3250, 0, 'user', 0, 1, '2025-12-12 21:47:15', '2025-10-29 11:37:32', '2025-12-12 21:47:50', NULL, 'Elias', 'AKOTAN', NULL, NULL),
(30, 'Lewis_Bot', 'bot_lewis@example.com', '$2y$12$cgrrMgOSK5U15Rqnzy1creIPifrk/uUBDstk27GEzSc51hFELL.Vy', '🤖', 'light', 0, 0, 'user', 0, 1, NULL, '2025-10-30 14:13:06', '2025-10-30 14:35:18', NULL, 'Lewis', 'Bot', NULL, NULL),
(31, 'Bil_Bot', 'bot_bil@example.com', '$2y$12$cgrrMgOSK5U15Rqnzy1creIPifrk/uUBDstk27GEzSc51hFELL.Vy', '🤖', 'light', 0, 0, 'user', 0, 1, NULL, '2025-10-30 14:13:06', '2025-10-30 14:35:15', NULL, 'Bil', 'Bot', NULL, NULL),
(32, 'Jonh_Bot', 'bot_jonh@example.com', '$2y$12$cgrrMgOSK5U15Rqnzy1creIPifrk/uUBDstk27GEzSc51hFELL.Vy', '🤖', 'light', 0, 0, 'user', 0, 1, NULL, '2025-10-30 14:13:06', '2025-10-30 14:35:09', NULL, 'Jonh', 'Bot', NULL, NULL),
(33, 'Bot1', 'bot1@cauris.com', 'N/A', '��', 'light', 0, 0, 'user', 1, 1, NULL, '2025-11-01 04:11:53', '2025-11-01 04:11:53', NULL, 'Bot', 'One', NULL, NULL),
(34, 'Bot2', 'bot2@cauris.com', 'N/A', '🤖', 'light', 0, 0, 'user', 1, 1, NULL, '2025-11-01 04:11:53', '2025-11-01 04:11:53', NULL, 'Bot', 'Two', NULL, NULL),
(35, 'Bot3', 'bot3@cauris.com', 'N/A', '🤖', 'light', 0, 0, 'user', 1, 1, NULL, '2025-11-01 04:11:53', '2025-11-01 04:11:53', NULL, 'Bot', 'Three', NULL, NULL),
(36, 'pine', 'billtossou6@gmail.com', '$2y$12$RhAgSybS.SrVpxR1YdeXdOih1ggNvci7BgK5wd8/Cs3rLLeLEFYym', '👤', 'light', 920, 0, 'user', 0, 1, '2025-11-16 14:41:18', '2025-11-01 11:45:23', '2025-11-16 14:41:32', NULL, 'bill', 'jonh', NULL, NULL),
(37, 'visiteur', 'enocknouveaux@gmail.com', '$2y$12$58ktMe40922gsLBgo2jHZuMtOP8ZfpuRuU6MTZkQPkqb0bh4WOMvS', '👤', 'light', 980, 0, 'user', 0, 1, '2025-11-01 19:18:42', '2025-11-01 12:58:04', '2025-11-01 19:36:32', NULL, 'Enock', 'Dotou', '66150249', NULL),
(43, 'Elie', 'smekpon@gmail.com', '$2y$12$a0RMOaWtAxYBXzUmHA90s.RHH8zcuk5H.Ut.gU4BBi.G8WF2iEomW', '👤', 'light', 60, 0, 'user', 0, 1, '2025-11-16 16:51:20', '2025-11-13 09:40:41', '2025-11-16 16:56:00', NULL, 'Sèdami Elie', 'MEKPON', NULL, NULL),
(44, 'Junior', 'kpohloclaude@gmail.com', '$2y$12$I2LLsSvgTzNEn597dCwQ0u0yoKAHVn95jgad/byX6MZItXnLRepW2', '👤', 'light', 80, 0, 'user', 0, 1, '2025-11-13 10:27:50', '2025-11-13 09:47:38', '2025-11-13 10:32:15', NULL, 'Claude', 'KPOHLO', NULL, NULL),
(45, 'Électro', 'arnauddeguenongue@gmail.com', '$2y$12$DBwuUFHTB4yMRNuNnaQrBuMIhYmDwBhk6EDiQTsmcQnqkCOq5mtUy', '👤', 'light', 90, 0, 'user', 0, 1, '2025-11-14 11:18:28', '2025-11-14 11:12:13', '2025-11-14 11:19:55', NULL, 'Arnaud', 'Deguenongue', NULL, NULL),
(46, 'big', 'bassqfoot@gmail.com', '$2y$12$nprdMORBxxtc/f2oROz.ye5XZDDoCFVA8bRcbe1PKDqJ0oh4sVJ.O', '👤', 'light', 30, 0, 'user', 0, 1, '2025-11-16 23:23:28', '2025-11-16 09:47:05', '2025-11-16 23:23:28', NULL, 'bass', 'big', NULL, NULL),
(47, 'YVON CHOCO', 'allaountayvon@gmail.com', '$2y$12$fc6n5TWF9TzR4AnEN669EuuK3maCEt2OygP93Grt9bn7CSDUdAEju', '👤', 'light', 90, 0, 'user', 0, 1, '2025-11-16 16:56:25', '2025-11-16 16:48:37', '2025-11-16 16:57:07', NULL, 'YVON', 'ALLAOUNTA', '0165090376', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `user_settings`
--

CREATE TABLE `user_settings` (
  `setting_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `language` varchar(10) DEFAULT 'fr',
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `sound_enabled` tinyint(1) DEFAULT 1,
  `vibration_enabled` tinyint(1) DEFAULT 1,
  `theme_mode` varchar(20) DEFAULT 'light',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_settings`
--

INSERT INTO `user_settings` (`setting_id`, `user_id`, `language`, `notifications_enabled`, `sound_enabled`, `vibration_enabled`, `theme_mode`, `created_at`, `updated_at`) VALUES
(1, 1, 'fr', 1, 1, 1, 'dark', '2025-10-26 18:29:35', '2025-10-26 18:29:35'),
(2, 2, 'fr', 1, 1, 1, 'dark', '2025-10-26 18:29:35', '2025-10-26 18:29:35'),
(3, 3, 'fr', 1, 1, 1, 'dark', '2025-10-26 18:29:35', '2025-10-26 18:29:35'),
(16, 28, 'fr', 1, 1, 1, 'light', '2025-10-27 00:38:41', '2025-10-27 00:38:41'),
(17, 29, 'fr', 1, 1, 1, 'light', '2025-10-29 11:37:42', '2025-10-29 11:37:42'),
(18, 36, 'fr', 1, 1, 1, 'light', '2025-11-01 11:45:27', '2025-11-01 11:45:27'),
(19, 37, 'fr', 1, 1, 1, 'light', '2025-11-01 12:58:08', '2025-11-01 12:58:08'),
(25, 43, 'fr', 1, 1, 1, 'light', '2025-11-13 09:40:50', '2025-11-13 09:40:50'),
(26, 44, 'fr', 1, 1, 1, 'light', '2025-11-13 09:47:42', '2025-11-13 09:47:42'),
(27, 45, 'fr', 1, 1, 1, 'light', '2025-11-14 11:12:17', '2025-11-14 11:12:17'),
(28, 46, 'fr', 1, 1, 1, 'light', '2025-11-16 09:47:10', '2025-11-16 09:47:10'),
(29, 47, 'fr', 1, 1, 1, 'light', '2025-11-16 16:48:41', '2025-11-16 16:48:41');

-- --------------------------------------------------------

--
-- Structure de la vue `player_stats`
--
DROP TABLE IF EXISTS `player_stats`;

CREATE ALGORITHM=UNDEFINED VIEW `player_stats`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`pseudo` AS `pseudo`, `u`.`email` AS `email`, count(distinct `g`.`game_id`) AS `total_games`, count(distinct case when `g`.`winner_id` = `u`.`user_id` then `g`.`game_id` end) AS `games_won`, count(distinct case when `g`.`winner_id` <> `u`.`user_id` and `g`.`finished_at` is not null then `g`.`game_id` end) AS `games_lost`, avg(`s`.`cumulative_score`) AS `avg_score`, max(`s`.`cumulative_score`) AS `best_score`, (CASE WHEN `u`.`role` IN ('admin', 'superadmin') THEN 1 ELSE 0 END) AS `is_admin`, `u`.`is_active` AS `is_active`, `u`.`last_login` AS `last_login`, `u`.`created_at` AS `created_at` FROM ((`users` `u` left join `scores` `s` on(`u`.`user_id` = `s`.`user_id`)) left join `games` `g` on(`s`.`game_id` = `g`.`game_id`)) GROUP BY `u`.`user_id`, `u`.`pseudo`, `u`.`email`, `u`.`role`, `u`.`is_active`, `u`.`last_login`, `u`.`created_at` ;

-- --------------------------------------------------------

--
-- Structure de la vue `room_stats`
--
DROP TABLE IF EXISTS `room_stats`;

CREATE ALGORITHM=UNDEFINED VIEW `room_stats`  AS SELECT `r`.`room_id` AS `room_id`, `r`.`room_name` AS `room_name`, `r`.`room_code` AS `room_code`, `r`.`creator_id` AS `creator_id`, `u`.`pseudo` AS `creator_pseudo`, `r`.`minimum_bet` AS `minimum_bet`, `r`.`status` AS `status`, count(`rp`.`player_id`) AS `current_players`, `r`.`max_players` AS `max_players`, count(distinct `g`.`game_id`) AS `total_games`, `r`.`created_at` AS `created_at`, `r`.`started_at` AS `started_at`, `r`.`finished_at` AS `finished_at` FROM (((`rooms` `r` left join `room_players` `rp` on(`r`.`room_id` = `rp`.`room_id`)) left join `users` `u` on(`r`.`creator_id` = `u`.`user_id`)) left join `games` `g` on(`r`.`room_id` = `g`.`room_id`)) GROUP BY `r`.`room_id` ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_admin_user` (`admin_user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_messages_sender_id_index` (`sender_id`),
  ADD KEY `admin_messages_recipient_id_index` (`recipient_id`),
  ADD KEY `admin_messages_status_index` (`status`),
  ADD KEY `admin_messages_parent_id_index` (`parent_id`),
  ADD KEY `admin_messages_created_at_index` (`created_at`),
  ADD KEY `admin_messages_reply_to_message_id_foreign` (`reply_to_message_id`);

--
-- Index pour la table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_game_round` (`game_id`,`round_number`),
  ADD KEY `idx_player_id` (`player_id`);

--
-- Index pour la table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_conversations_user_id_index` (`user_id`),
  ADD KEY `chat_conversations_status_index` (`status`),
  ADD KEY `chat_conversations_assigned_manager_id_index` (`assigned_manager_id`);

--
-- Index pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_messages_conversation_id_index` (`conversation_id`),
  ADD KEY `chat_messages_created_at_index` (`created_at`);

--
-- Index pour la table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contact_messages_status_index` (`status`),
  ADD KEY `contact_messages_email_index` (`email`),
  ADD KEY `contact_messages_created_at_index` (`created_at`);

--
-- Index pour la table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  ADD PRIMARY KEY (`code_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_email_type` (`email`,`type`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Index pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Index pour la table `friendships`
--
ALTER TABLE `friendships`
  ADD PRIMARY KEY (`friendship_id`),
  ADD UNIQUE KEY `unique_friendship` (`user_id`,`friend_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_friend_id` (`friend_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`game_id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_winner` (`winner_id`);

--
-- Index pour la table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Index pour la table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Index pour la table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_tokenable` (`tokenable_type`,`tokenable_id`);

--
-- Index pour la table `played_cards`
--
ALTER TABLE `played_cards`
  ADD PRIMARY KEY (`card_id`),
  ADD KEY `idx_trick_id` (`trick_id`),
  ADD KEY `idx_player_id` (`player_id`);

--
-- Index pour la table `player_disconnections`
--
ALTER TABLE `player_disconnections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_disconnections_room_id_player_name_index` (`room_id`,`player_name`),
  ADD KEY `player_disconnections_disconnected_at_index` (`disconnected_at`),
  ADD KEY `player_disconnections_reconnected_at_index` (`reconnected_at`);

--
-- Index pour la table `player_replacements`
--
ALTER TABLE `player_replacements`
  ADD PRIMARY KEY (`replacement_id`);

--
-- Index pour la table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `room_code` (`room_code`),
  ADD KEY `idx_room_code` (`room_code`),
  ADD KEY `idx_creator` (`creator_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `room_chat_messages`
--
ALTER TABLE `room_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_chat_messages_user_id_foreign` (`user_id`),
  ADD KEY `room_chat_messages_room_id_id_index` (`room_id`,`id`),
  ADD KEY `room_chat_messages_room_id_created_at_index` (`room_id`,`created_at`);

--
-- Index pour la table `room_invitations`
--
ALTER TABLE `room_invitations`
  ADD PRIMARY KEY (`invitation_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `room_players`
--
ALTER TABLE `room_players`
  ADD PRIMARY KEY (`player_id`),
  ADD UNIQUE KEY `unique_room_position` (`room_id`,`position`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Index pour la table `rounds`
--
ALTER TABLE `rounds`
  ADD PRIMARY KEY (`round_id`),
  ADD UNIQUE KEY `rounds_room_id_round_number_unique` (`room_id`,`round_number`),
  ADD KEY `trick_winner_id` (`trick_winner_id`),
  ADD KEY `idx_game_id` (`game_id`),
  ADD KEY `idx_round_number` (`round_number`);

--
-- Index pour la table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`score_id`),
  ADD KEY `round_id` (`round_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_game_id` (`game_id`),
  ADD KEY `idx_player_id` (`player_id`);

--
-- Index pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `validated_by` (`validated_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_fedapay_transaction_id` (`fedapay_transaction_id`),
  ADD KEY `idx_payment_method` (`payment_method`);

--
-- Index pour la table `tricks`
--
ALTER TABLE `tricks`
  ADD PRIMARY KEY (`trick_id`),
  ADD KEY `lead_player_id` (`lead_player_id`),
  ADD KEY `winner_player_id` (`winner_player_id`),
  ADD KEY `idx_round_trick` (`round_id`,`trick_number`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `pseudo` (`pseudo`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_pseudo` (`pseudo`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Index pour la table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `admin_messages`
--
ALTER TABLE `admin_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `email_verification_codes`
--
ALTER TABLE `email_verification_codes`
  MODIFY `code_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `friendships`
--
ALTER TABLE `friendships`
  MODIFY `friendship_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `games`
--
ALTER TABLE `games`
  MODIFY `game_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT pour la table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `played_cards`
--
ALTER TABLE `played_cards`
  MODIFY `card_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `player_disconnections`
--
ALTER TABLE `player_disconnections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `player_replacements`
--
ALTER TABLE `player_replacements`
  MODIFY `replacement_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `room_chat_messages`
--
ALTER TABLE `room_chat_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `room_invitations`
--
ALTER TABLE `room_invitations`
  MODIFY `invitation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `room_players`
--
ALTER TABLE `room_players`
  MODIFY `player_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rounds`
--
ALTER TABLE `rounds`
  MODIFY `round_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `scores`
--
ALTER TABLE `scores`
  MODIFY `score_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tricks`
--
ALTER TABLE `tricks`
  MODIFY `trick_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT pour la table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `admin_messages`
--
ALTER TABLE `admin_messages`
  ADD CONSTRAINT `admin_messages_reply_to_message_id_foreign` FOREIGN KEY (`reply_to_message_id`) REFERENCES `admin_messages` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `room_players` (`player_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD CONSTRAINT `chat_conversations_assigned_manager_id_foreign` FOREIGN KEY (`assigned_manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_conversations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `friendships`
--
ALTER TABLE `friendships`
  ADD CONSTRAINT `friendships_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friendships_ibfk_2` FOREIGN KEY (`friend_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD CONSTRAINT `friend_requests_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friend_requests_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `games_ibfk_2` FOREIGN KEY (`winner_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `played_cards`
--
ALTER TABLE `played_cards`
  ADD CONSTRAINT `played_cards_ibfk_1` FOREIGN KEY (`trick_id`) REFERENCES `tricks` (`trick_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `played_cards_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `room_players` (`player_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `player_disconnections`
--
ALTER TABLE `player_disconnections`
  ADD CONSTRAINT `player_disconnections_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `room_chat_messages`
--
ALTER TABLE `room_chat_messages`
  ADD CONSTRAINT `room_chat_messages_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_chat_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `room_invitations`
--
ALTER TABLE `room_invitations`
  ADD CONSTRAINT `room_invitations_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_invitations_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_invitations_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `room_players`
--
ALTER TABLE `room_players`
  ADD CONSTRAINT `room_players_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_players_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `rounds`
--
ALTER TABLE `rounds`
  ADD CONSTRAINT `rounds_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rounds_ibfk_2` FOREIGN KEY (`trick_winner_id`) REFERENCES `room_players` (`player_id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_ibfk_2` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`round_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `scores_ibfk_3` FOREIGN KEY (`player_id`) REFERENCES `room_players` (`player_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`validated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `tricks`
--
ALTER TABLE `tricks`
  ADD CONSTRAINT `tricks_ibfk_1` FOREIGN KEY (`round_id`) REFERENCES `rounds` (`round_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tricks_ibfk_2` FOREIGN KEY (`lead_player_id`) REFERENCES `room_players` (`player_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tricks_ibfk_3` FOREIGN KEY (`winner_player_id`) REFERENCES `room_players` (`player_id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

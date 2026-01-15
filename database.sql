SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- --------------------------------------------------------

--
-- 1. Table structure for table `admin`
--
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `admin`
-- Password is: admin123 (Generated using BCrypt)
--
INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$Be0.g.g.g.g.g.g.g.g.g.u.r.p.a.s.s.w.o.r.d.h.a.s.h'); 
-- NOTE: Please use admin/generate_pass.php to generate your own hash for login.

-- --------------------------------------------------------

--
-- 2. Table structure for table `settings`
-- (Added Swap Limits, Deposit Limits, and Referral Trade Condition)
--
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `p2p_buy_rate_margin` decimal(10,2) DEFAULT 2.00,
  `p2p_sell_rate_margin` decimal(10,2) DEFAULT 2.00,
  `referral_bonus_amount` decimal(10,2) DEFAULT 0.50,
  `referral_min_trade` decimal(10,2) DEFAULT 50.00,
  `min_deposit_limit` decimal(10,2) DEFAULT 1.00,
  `min_withdraw_limit` decimal(10,2) DEFAULT 10.00,
  `min_swap_limit` decimal(10,2) DEFAULT 5.00,
  `bot_url` varchar(255) DEFAULT 'https://t.me/YourBotName_bot',
  `support_url` varchar(255) DEFAULT 'https://t.me/YourSupport',
  `admin_upi` varchar(100) DEFAULT 'admin@upi',
  `admin_wallets_json` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Default Settings Data
--
INSERT INTO `settings` (`id`, `referral_bonus_amount`, `referral_min_trade`, `min_deposit_limit`, `min_withdraw_limit`, `min_swap_limit`, `admin_wallets_json`) VALUES
(1, 0.50, 50.00, 1.00, 10.00, 5.00, '{"USDT_TRC20":"T_Your_TRC20_Addr", "USDT_BEP20":"0x_Your_BEP20_Addr", "TON":"Your_TON_Addr", "BTC":"Your_BTC_Addr"}');

-- --------------------------------------------------------

--
-- 3. Table structure for table `assets`
-- (Added icon_url and network for Wallet Page)
--
CREATE TABLE `assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `network` varchar(20) DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Default Assets (Matching Wallet Page Logic)
--
INSERT INTO `assets` (`symbol`, `name`, `network`, `icon_url`, `is_active`) VALUES
('USDT', 'Tether', 'TRC20', 'https://cryptologos.cc/logos/tether-usdt-logo.png', 1),
('TON', 'Toncoin', 'TON', 'https://cryptologos.cc/logos/toncoin-ton-logo.png', 1),
('BTC', 'Bitcoin', 'BTC', 'https://cryptologos.cc/logos/bitcoin-btc-logo.png', 1),
('BNB', 'BNB', 'BEP20', 'https://cryptologos.cc/logos/bnb-bnb-logo.png', 1),
('TRX', 'Tron', 'TRC20', 'https://cryptologos.cc/logos/tron-trx-logo.png', 1);

-- --------------------------------------------------------

--
-- 4. Table structure for table `users`
-- (Added photo_url and last_name for Invite Page)
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint(20) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `photo_url` text DEFAULT NULL,
  `referred_by` bigint(20) DEFAULT NULL,
  `is_blocked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 5. Table structure for table `user_wallets`
--
CREATE TABLE `user_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `asset_symbol` varchar(10) NOT NULL,
  `balance` decimal(20,8) DEFAULT 0.00000000,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 6. Table structure for table `transactions`
-- (Added Bank Details, Wallet Address, Description, Admin Adjust Type)
--
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `type` enum('buy','sell','deposit','withdraw','swap','referral_bonus','admin_adjust') NOT NULL,
  `asset_symbol` varchar(10) NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `status` enum('pending','approved','rejected','completed','failed') DEFAULT 'pending',
  
  -- Proof & Address Columns
  `tx_hash` varchar(255) DEFAULT NULL,
  `wallet_address` varchar(255) DEFAULT NULL,
  `network` varchar(50) DEFAULT NULL,
  
  -- Bank / UPI Columns (For Sell Requests)
  `payment_method` varchar(20) DEFAULT NULL, 
  `upi_id` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `account_holder` varchar(100) DEFAULT NULL,
  
  -- Old JSON Column (Optional, keeping for backup)
  `user_payment_details_json` text DEFAULT NULL,

  -- Extra Info
  `description` text DEFAULT NULL,
  `reject_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

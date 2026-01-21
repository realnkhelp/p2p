SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- --------------------------------------------------------

-- 
-- 1. Table structure for table `admin`
-- (एडमिन लॉगिन डेटा)
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
-- (Default User: admin, Pass: admin123 - Hash change kar lena baad me)
--
INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$YourHashedPasswordHere');

-- --------------------------------------------------------

-- 
-- 2. Table structure for table `settings`
-- (पूरी वेबसाइट की सेटिंग्स, रेट्स, और मेंटेनेंस)
--
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  
  -- P2P Rates (Direct Price)
  `p2p_buy_rate` decimal(10,2) DEFAULT '92.00',
  `p2p_sell_rate` decimal(10,2) DEFAULT '88.00',
  
  -- Margins (Optional logic ke liye)
  `p2p_buy_rate_margin` decimal(5,2) DEFAULT '2.00',
  `p2p_sell_rate_margin` decimal(5,2) DEFAULT '2.00',
  
  -- Referral Settings
  `referral_bonus_amount` decimal(10,2) DEFAULT '0.50',
  `referral_min_trade` decimal(10,2) DEFAULT '50.00',
  
  -- Limits
  `min_deposit_limit` decimal(10,2) DEFAULT '1.00',
  `min_withdraw_limit` decimal(10,2) DEFAULT '10.00',
  `min_swap_limit` decimal(10,2) DEFAULT '5.00',
  
  -- URLs & Contacts
  `bot_url` varchar(255) DEFAULT 'https://t.me/YourBotName_bot',
  `support_url` varchar(255) DEFAULT 'https://t.me/YourSupportUsername',
  `admin_upi` varchar(100) DEFAULT 'admin@ybl',
  `admin_wallets_json` text DEFAULT NULL COMMENT 'JSON format {"USDT_TRC20":"addr..."}',
  
  -- Maintenance Mode System
  `maintenance_mode` tinyint(1) DEFAULT 0 COMMENT '0=Off, 1=On',
  `maintenance_message` text DEFAULT NULL,
  `maintenance_end_date` date DEFAULT NULL,
  `maintenance_end_time` time DEFAULT NULL,

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Default Settings Data
--
INSERT INTO `settings` (`id`, `p2p_buy_rate`, `p2p_sell_rate`, `maintenance_mode`, `maintenance_message`, `admin_wallets_json`) VALUES
(1, 92.00, 88.00, 0, 'We are upgrading our servers. Please wait.', '{"TRC20":"T_YOUR_TRC20_ADDRESS", "BEP20":"0x_YOUR_BEP20_ADDRESS", "TON":"YOUR_TON_ADDRESS"}');

-- --------------------------------------------------------

-- 
-- 3. Table structure for table `assets`
-- (कोइन्स की लिस्ट)
--
CREATE TABLE `assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Default Assets
--
INSERT INTO `assets` (`symbol`, `name`, `is_active`) VALUES
('USDT', 'Tether', 1),
('TON', 'Toncoin', 1),
('BTC', 'Bitcoin', 1);

-- --------------------------------------------------------

-- 
-- 4. Table structure for table `users`
-- (यूज़र्स का डेटा - फोटो और ब्लॉक स्टेटस के साथ)
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint(20) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `photo_url` text DEFAULT NULL,
  `referred_by` bigint(20) DEFAULT NULL,
  `status` enum('active','blocked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 
-- 5. Table structure for table `user_wallets`
-- (यूज़र का बैलेंस)
--
CREATE TABLE `user_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT 'Telegram ID Store karega',
  `asset_symbol` varchar(10) NOT NULL,
  `balance` decimal(20,8) DEFAULT '0.00000000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 
-- 6. Table structure for table `transactions`
-- (लेन-देन का पूरा रिकॉर्ड - UPI और Bank details के साथ)
--
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `type` enum('buy','sell','deposit','withdraw','swap','referral_bonus') NOT NULL,
  `asset_symbol` varchar(10) NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `network` varchar(50) DEFAULT NULL,
  
  -- Payment Details (Columns separated for easy PHP access)
  `payment_method` varchar(50) DEFAULT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `account_holder` varchar(100) DEFAULT NULL,
  `ifsc_code` varchar(50) DEFAULT NULL,
  `wallet_address` varchar(255) DEFAULT NULL COMMENT 'For Withdrawals',
  
  `description` text DEFAULT NULL COMMENT 'For Swap or extra info',
  `status` enum('pending','approved','rejected','completed','failed') DEFAULT 'pending',
  `tx_hash` varchar(255) DEFAULT NULL,
  `reject_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

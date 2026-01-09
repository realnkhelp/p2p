SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- --------------------------------------------------------

-- 1. Admin Table (एडमिन लॉगिन के लिए)
CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Admin (Username: admin, Password: admin123)
INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$YourHashedPasswordHere'); 

-- --------------------------------------------------------

-- 2. Settings Table (फीस, वॉलेट एड्रेस और लिंक्स के लिए)
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `p2p_buy_rate_margin` decimal(5,2) DEFAULT '2.00',
  `p2p_sell_rate_margin` decimal(5,2) DEFAULT '2.00',
  `referral_bonus_amount` decimal(10,2) DEFAULT '0.50',
  `bot_url` varchar(255) DEFAULT 'https://t.me/YourBotName_bot',
  `min_withdraw_limit` decimal(10,2) DEFAULT '10.00',
  `admin_wallets_json` text DEFAULT NULL, 
  `admin_upi` varchar(100) DEFAULT 'example@upi',
  `support_url` varchar(255) DEFAULT 'https://t.me/YourSupport',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Settings Data (ताकि एरर न आये)
INSERT INTO `settings` (`id`, `referral_bonus_amount`, `bot_url`, `admin_upi`, `admin_wallets_json`) VALUES
(1, 0.50, 'https://t.me/HoneyBux_bot', 'admin@ybl', '{"USDT":"T_YOUR_TRC20_ADDRESS_HERE", "TON":"YOUR_TON_ADDRESS_HERE"}');

-- --------------------------------------------------------

-- 3. Assets Table (कोइन्स की लिस्ट)
CREATE TABLE `assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `symbol` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Assets
INSERT INTO `assets` (`symbol`, `name`, `is_active`) VALUES
('USDT', 'Tether', 1),
('TON', 'Toncoin', 1),
('BTC', 'Bitcoin', 1);

-- --------------------------------------------------------

-- 4. Users Table (यूज़र्स का डेटा)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint(20) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `referred_by` bigint(20) DEFAULT NULL,
  `is_blocked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 5. User Wallets (यूज़र का बैलेंस)
CREATE TABLE `user_wallets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `asset_symbol` varchar(10) NOT NULL,
  `balance` decimal(20,8) DEFAULT '0.00000000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

-- 6. Transactions Table (लेन-देन का रिकॉर्ड)
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `type` enum('buy','sell','deposit','withdraw','swap','referral_bonus') NOT NULL,
  `asset_symbol` varchar(10) NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `network` varchar(50) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `user_payment_details_json` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `tx_hash` varchar(255) DEFAULT NULL,
  `reject_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

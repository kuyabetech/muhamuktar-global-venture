-- Muhamuktar Global Venture Database Backup
-- Generated: 2026-02-28 13:23:45
-- Tables: activity_logs, blog_categories, blog_comments, blog_posts, brands, carts, categories, coupons, deal_products, deals, media, order_items, orders, pages, payment_methods, payments, product_attributes, product_images, products, settings, shipping_methods, shipping_rates, shipping_tracking, shipping_zones, stock_movements, testimonials, transactions, users

SET FOREIGN_KEY_CHECKS=0;


-- Table structure for table `activity_logs`
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(191) NOT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table structure for table `blog_categories`
DROP TABLE IF EXISTS `blog_categories`;
CREATE TABLE `blog_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table structure for table `blog_comments`
DROP TABLE IF EXISTS `blog_comments`;
CREATE TABLE `blog_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('approved','pending','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post` (`post_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `blog_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blog_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table structure for table `blog_posts`
DROP TABLE IF EXISTS `blog_posts`;
CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `excerpt` text,
  `content` text,
  `featured_image` varchar(255) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `view_count` int(11) DEFAULT '0',
  `views` int(11) DEFAULT '0',
  `status` enum('published','draft') DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_blog_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table structure for table `brands`
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `display_order` int(11) DEFAULT '0',
  `featured` tinyint(1) DEFAULT '0',
  `meta_title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `brands`
INSERT INTO `brands` (`id`, `name`, `slug`, `logo`, `website`, `description`, `display_order`, `featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('1', 'E-store', 'e-store', NULL, 'https://e-shop.com.ng', 'There is a critical preliminary meeting that is', '0', '1', 'There are a', 'There are pests of us to have the', 'active', '2026-02-28 12:18:15', '2026-02-28 12:19:44');


-- Table structure for table `carts`
DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `carts`
INSERT INTO `carts` (`id`, `user_id`, `session_id`, `product_id`, `quantity`, `created_at`, `deleted_at`, `price`, `updated_at`) VALUES ('1', '1', '8ef423dd9b1fe77ef635222a31155a64', '3', '5', '2026-02-10 21:40:47', '2026-02-10 22:33:56', '200000.00', '2026-02-10 22:33:56');
INSERT INTO `carts` (`id`, `user_id`, `session_id`, `product_id`, `quantity`, `created_at`, `deleted_at`, `price`, `updated_at`) VALUES ('2', '1', '8ef423dd9b1fe77ef635222a31155a64', '2', '1', '2026-02-10 21:55:30', '2026-02-10 23:10:59', '20000.00', '2026-02-10 23:10:59');
INSERT INTO `carts` (`id`, `user_id`, `session_id`, `product_id`, `quantity`, `created_at`, `deleted_at`, `price`, `updated_at`) VALUES ('3', '1', '8ef423dd9b1fe77ef635222a31155a64', '1', '4', '2026-02-10 21:55:51', '2026-02-10 23:10:59', '2000.00', '2026-02-10 23:10:59');
INSERT INTO `carts` (`id`, `user_id`, `session_id`, `product_id`, `quantity`, `created_at`, `deleted_at`, `price`, `updated_at`) VALUES ('4', NULL, '0e47669c35fbda4e99d102a811831e48', '2', '1', '2026-02-10 23:15:57', NULL, '20000.00', '2026-02-10 23:15:57');
INSERT INTO `carts` (`id`, `user_id`, `session_id`, `product_id`, `quantity`, `created_at`, `deleted_at`, `price`, `updated_at`) VALUES ('5', '3', '0e47669c35fbda4e99d102a811831e48', '2', '1', '2026-02-10 23:16:43', '2026-02-10 23:18:19', '20000.00', '2026-02-10 23:18:19');
INSERT INTO `carts` (`id`, `user_id`, `session_id`, `product_id`, `quantity`, `created_at`, `deleted_at`, `price`, `updated_at`) VALUES ('6', NULL, '15a02206b33c47eb7b8883bdcb2928be', '1', '1', '2026-02-11 09:15:54', NULL, '2000.00', '2026-02-11 09:15:54');
INSERT INTO `carts` (`id`, `user_id`, `session_id`, `product_id`, `quantity`, `created_at`, `deleted_at`, `price`, `updated_at`) VALUES ('7', '3', '15a02206b33c47eb7b8883bdcb2928be', '1', '1', '2026-02-11 09:17:40', '2026-02-11 09:18:34', '2000.00', '2026-02-11 09:18:34');


-- Table structure for table `categories`
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT '0',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `fk_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `categories`
INSERT INTO `categories` (`id`, `name`, `slug`, `created_at`, `description`, `updated_at`, `parent_id`, `display_order`, `image`, `meta_title`, `meta_description`, `meta_keywords`, `status`) VALUES ('1', 'Phone', 'hone', '2026-02-09 19:04:40', '', NULL, NULL, '0', NULL, NULL, NULL, NULL, 'active');
INSERT INTO `categories` (`id`, `name`, `slug`, `created_at`, `description`, `updated_at`, `parent_id`, `display_order`, `image`, `meta_title`, `meta_description`, `meta_keywords`, `status`) VALUES ('2', 'Clothes', 'clothes', '2026-02-10 07:50:34', '', NULL, NULL, '0', NULL, NULL, NULL, NULL, 'active');
INSERT INTO `categories` (`id`, `name`, `slug`, `created_at`, `description`, `updated_at`, `parent_id`, `display_order`, `image`, `meta_title`, `meta_description`, `meta_keywords`, `status`) VALUES ('3', 'Kitchen Wares', 'kitchen-wares', '2026-02-11 09:22:07', '', NULL, NULL, '0', NULL, NULL, NULL, NULL, 'active');


-- Table structure for table `coupons`
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `min_order` decimal(10,2) DEFAULT NULL,
  `max_uses` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `deal_products`
DROP TABLE IF EXISTS `deal_products`;
CREATE TABLE `deal_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deal_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_deal_product` (`deal_id`,`product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `deals`
DROP TABLE IF EXISTS `deals`;
CREATE TABLE `deals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `discount_type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_featured` tinyint(1) DEFAULT '0',
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT '0',
  `min_order_amount` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `media`
DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dimensions` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `uploaded_by` int(11) DEFAULT NULL,
  `is_image` tinyint(1) DEFAULT '0',
  `width` int(11) DEFAULT '0',
  `height` int(11) DEFAULT '0',
  `download_count` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`file_type`),
  KEY `idx_is_image` (`is_image`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `media`
INSERT INTO `media` (`id`, `filename`, `original_name`, `file_path`, `file_size`, `file_type`, `mime_type`, `dimensions`, `alt_text`, `caption`, `description`, `uploaded_by`, `is_image`, `width`, `height`, `download_count`, `created_at`) VALUES ('1', '69a2dd4054077_1772281152.jpg', 'IMG-20260228-WA0012.jpg', '../uploads/media/69a2dd4054077_1772281152.jpg', '135087', 'image', 'image/jpeg', '902x1280', NULL, NULL, NULL, '1', '1', '902', '1280', '0', '2026-02-28 13:19:12');


-- Table structure for table `order_items`
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `price_at_time` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `order_status` enum('processing','paid','confirmed','shipped','delivered','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `order_items`
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`, `price_at_time`, `created_at`, `updated_at`, `order_status`) VALUES ('1', '5', '1', 'SPARK 50', '4', '2000.00', '8000.00', '0.00', '2026-02-10 23:10:59', '2026-02-11 00:19:47', 'processing');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`, `price_at_time`, `created_at`, `updated_at`, `order_status`) VALUES ('2', '5', '2', 'T-Shirt', '1', '20000.00', '20000.00', '0.00', '2026-02-10 23:10:59', '2026-02-11 00:19:47', 'processing');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`, `price_at_time`, `created_at`, `updated_at`, `order_status`) VALUES ('3', '5', '2', 'T-Shirt', '1', '20000.00', '20000.00', '0.00', '2026-02-10 23:10:59', '2026-02-11 00:19:47', 'processing');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`, `price_at_time`, `created_at`, `updated_at`, `order_status`) VALUES ('4', '5', '2', 'T-Shirt', '1', '20000.00', '20000.00', '0.00', '2026-02-10 23:10:59', '2026-02-11 00:19:47', 'processing');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`, `price_at_time`, `created_at`, `updated_at`, `order_status`) VALUES ('5', '6', '2', 'T-Shirt', '1', '20000.00', '20000.00', '0.00', '2026-02-10 23:18:19', '2026-02-11 00:19:47', 'processing');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`, `price_at_time`, `created_at`, `updated_at`, `order_status`) VALUES ('6', '6', '2', 'T-Shirt', '1', '20000.00', '20000.00', '0.00', '2026-02-10 23:18:19', '2026-02-11 00:19:47', 'processing');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`, `price_at_time`, `created_at`, `updated_at`, `order_status`) VALUES ('7', '6', '2', 'T-Shirt', '1', '20000.00', '20000.00', '0.00', '2026-02-10 23:18:19', '2026-02-11 00:19:47', 'processing');
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `total_price`, `price_at_time`, `created_at`, `updated_at`, `order_status`) VALUES ('8', '7', '1', 'SPARK 50', '1', '2000.00', '2000.00', '0.00', '2026-02-11 09:18:34', '2026-02-11 09:18:34', 'processing');


-- Table structure for table `orders`
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `status` enum('pending','paid','processing','shipped','delivered','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `order_status` enum('pending','paid','processing','confirmed','shipped','delivered','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `shipping_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_state` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_notes` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(10,2) NOT NULL,
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `carrier_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  KEY `idx_reference` (`reference`),
  KEY `idx_order_number` (`order_number`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `orders`
INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `reference`, `user_id`, `total_amount`, `status`, `order_status`, `payment_method`, `payment_status`, `payment_reference`, `payment_date`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `tracking_number`, `order_notes`, `subtotal`, `shipping_fee`, `created_at`, `updated_at`, `carrier_name`, `carrier_url`, `tracking_status`) VALUES ('1', 'ORD-20260210-77EFC6ED', 'Usnan Adamu', 'adamuusnan87@gmail.com', '09034095385', NULL, '1', '68000.00', 'processing', 'paid', NULL, 'pending', NULL, NULL, 'The Technology is a and it is used to', 'Minna', 'Lagos', '1334678', '123566', '', '68000.00', '0.00', '2026-02-10 23:07:05', '2026-02-11 00:28:15', NULL, NULL, 'pending');
INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `reference`, `user_id`, `total_amount`, `status`, `order_status`, `payment_method`, `payment_status`, `payment_reference`, `payment_date`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `tracking_number`, `order_notes`, `subtotal`, `shipping_fee`, `created_at`, `updated_at`, `carrier_name`, `carrier_url`, `tracking_status`) VALUES ('2', 'ORD-20260210-490AED1B', 'Usnan Adamu', 'adamuusnan87@gmail.com', '09034095385', NULL, '1', '68000.00', 'completed', 'completed', NULL, 'pending', NULL, NULL, 'The Technology is a and it is used to', 'Minna', 'Lagos', '1334678', 'Trackno-123', '', '68000.00', '0.00', '2026-02-10 23:08:24', '2026-02-28 11:40:20', NULL, NULL, 'pending');
INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `reference`, `user_id`, `total_amount`, `status`, `order_status`, `payment_method`, `payment_status`, `payment_reference`, `payment_date`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `tracking_number`, `order_notes`, `subtotal`, `shipping_fee`, `created_at`, `updated_at`, `carrier_name`, `carrier_url`, `tracking_status`) VALUES ('3', 'ORD-20260210-00FD075B', 'Usnan Adamu', 'adamuusnan87@gmail.com', '09034095385', NULL, '1', '68000.00', 'pending', 'processing', NULL, 'pending', NULL, NULL, 'The Technology is a and it is used to', 'Minna', 'Lagos', '1334678', NULL, '', '68000.00', '0.00', '2026-02-10 23:09:17', '2026-02-11 00:18:39', NULL, NULL, 'pending');
INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `reference`, `user_id`, `total_amount`, `status`, `order_status`, `payment_method`, `payment_status`, `payment_reference`, `payment_date`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `tracking_number`, `order_notes`, `subtotal`, `shipping_fee`, `created_at`, `updated_at`, `carrier_name`, `carrier_url`, `tracking_status`) VALUES ('4', 'ORD-20260210-B41E7FFB', 'Usnan Adamu', 'adamuusnan87@gmail.com', '09034095385', NULL, '1', '68000.00', 'pending', 'processing', NULL, 'pending', NULL, NULL, 'The Technology is a and it is used to', 'Minna', 'Lagos', '1334678', NULL, '', '68000.00', '0.00', '2026-02-10 23:10:03', '2026-02-11 00:18:39', NULL, NULL, 'pending');
INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `reference`, `user_id`, `total_amount`, `status`, `order_status`, `payment_method`, `payment_status`, `payment_reference`, `payment_date`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `tracking_number`, `order_notes`, `subtotal`, `shipping_fee`, `created_at`, `updated_at`, `carrier_name`, `carrier_url`, `tracking_status`) VALUES ('5', 'ORD-20260210-98D3ECD4', 'Usnan Adamu', 'adamuusnan87@gmail.com', '09034095385', NULL, '1', '68000.00', 'pending', 'processing', NULL, 'pending', NULL, NULL, 'The Technology is a and it is used to', 'Minna', 'Lagos', '1334678', NULL, '', '68000.00', '0.00', '2026-02-10 23:10:59', '2026-02-11 00:18:39', NULL, NULL, 'pending');
INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `reference`, `user_id`, `total_amount`, `status`, `order_status`, `payment_method`, `payment_status`, `payment_reference`, `payment_date`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `tracking_number`, `order_notes`, `subtotal`, `shipping_fee`, `created_at`, `updated_at`, `carrier_name`, `carrier_url`, `tracking_status`) VALUES ('6', 'ORD-20260210-51A57DBA', 'Abdullah Adamu', 'kuyabe3232@gmail.com', '09034095385', NULL, '3', '60000.00', 'shipped', 'shipped', NULL, 'pending', NULL, NULL, 'The Technology is a and it is used to', 'Minna', 'Lagos', '1334678', '1235664', '', '60000.00', '0.00', '2026-02-10 23:18:19', '2026-02-11 00:45:30', NULL, NULL, 'pending');
INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `reference`, `user_id`, `total_amount`, `status`, `order_status`, `payment_method`, `payment_status`, `payment_reference`, `payment_date`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `tracking_number`, `order_notes`, `subtotal`, `shipping_fee`, `created_at`, `updated_at`, `carrier_name`, `carrier_url`, `tracking_status`) VALUES ('7', 'ORD-20260211-1D5F243B', 'Usnan Adamu', 'kuyabe3232@gmail.com', '09034095385', NULL, '3', '3500.00', 'pending', 'processing', NULL, 'pending', NULL, NULL, 'The Technology is a and it is used to', 'Minna', 'Lagos', '1334678', NULL, '', '2000.00', '1500.00', '2026-02-11 09:18:34', '2026-02-11 09:18:34', NULL, NULL, 'pending');


-- Table structure for table `pages`
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext,
  `excerpt` text,
  `featured_image` varchar(255) DEFAULT NULL,
  `template` varchar(100) DEFAULT 'default',
  `status` enum('published','draft','private') DEFAULT 'draft',
  `parent_id` int(11) DEFAULT NULL,
  `menu_order` int(11) DEFAULT '0',
  `show_in_menu` tinyint(1) DEFAULT '1',
  `allow_comments` tinyint(1) DEFAULT '0',
  `view_count` int(11) DEFAULT '0',
  `author_id` int(11) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text,
  `meta_keywords` text,
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_slug` (`slug`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `pages_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table structure for table `payment_methods`
DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('card','bank','wallet','cash','crypto','other') COLLATE utf8mb4_unicode_ci DEFAULT 'card',
  `description` text COLLATE utf8mb4_unicode_ci,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `test_mode` tinyint(1) DEFAULT '1',
  `test_public_key` text COLLATE utf8mb4_unicode_ci,
  `test_secret_key` text COLLATE utf8mb4_unicode_ci,
  `live_public_key` text COLLATE utf8mb4_unicode_ci,
  `live_secret_key` text COLLATE utf8mb4_unicode_ci,
  `webhook_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `callback_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supported_currencies` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'NGN',
  `min_amount` decimal(10,2) DEFAULT NULL,
  `max_amount` decimal(10,2) DEFAULT NULL,
  `processing_fee` decimal(5,2) DEFAULT NULL,
  `fee_type` enum('fixed','percentage') COLLATE utf8mb4_unicode_ci DEFAULT 'percentage',
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_type` (`type`),
  KEY `idx_active` (`is_active`),
  KEY `idx_default` (`is_default`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `payment_methods`
INSERT INTO `payment_methods` (`id`, `name`, `code`, `type`, `description`, `instructions`, `logo`, `is_default`, `is_active`, `test_mode`, `test_public_key`, `test_secret_key`, `live_public_key`, `live_secret_key`, `webhook_url`, `callback_url`, `supported_currencies`, `min_amount`, `max_amount`, `processing_fee`, `fee_type`, `sort_order`, `created_at`, `updated_at`) VALUES ('1', 'Paystack', 'paystack', 'card', 'Pay with cards, bank transfers, or USSD via Paystack', 'You will be redirected to Paystack to complete your payment securely.', NULL, '0', '1', '1', NULL, NULL, NULL, NULL, NULL, NULL, 'NGN,GHS,ZAR,USD', NULL, NULL, NULL, 'percentage', '1', '2026-02-28 13:10:12', NULL);
INSERT INTO `payment_methods` (`id`, `name`, `code`, `type`, `description`, `instructions`, `logo`, `is_default`, `is_active`, `test_mode`, `test_public_key`, `test_secret_key`, `live_public_key`, `live_secret_key`, `webhook_url`, `callback_url`, `supported_currencies`, `min_amount`, `max_amount`, `processing_fee`, `fee_type`, `sort_order`, `created_at`, `updated_at`) VALUES ('2', 'Cash on Delivery', 'cod', 'cash', 'Pay with cash when your order is delivered', 'Please have the exact amount ready upon delivery.', NULL, '0', '1', '0', NULL, NULL, NULL, NULL, NULL, NULL, 'NGN', NULL, NULL, NULL, 'percentage', '2', '2026-02-28 13:10:12', NULL);
INSERT INTO `payment_methods` (`id`, `name`, `code`, `type`, `description`, `instructions`, `logo`, `is_default`, `is_active`, `test_mode`, `test_public_key`, `test_secret_key`, `live_public_key`, `live_secret_key`, `webhook_url`, `callback_url`, `supported_currencies`, `min_amount`, `max_amount`, `processing_fee`, `fee_type`, `sort_order`, `created_at`, `updated_at`) VALUES ('3', 'Bank Transfer', 'bank_transfer', 'bank', 'Make a direct bank transfer to our account', 'Transfer the total amount to our bank account and upload payment proof.', NULL, '0', '1', '0', NULL, NULL, NULL, NULL, NULL, NULL, 'NGN', NULL, NULL, NULL, 'percentage', '3', '2026-02-28 13:10:12', NULL);
INSERT INTO `payment_methods` (`id`, `name`, `code`, `type`, `description`, `instructions`, `logo`, `is_default`, `is_active`, `test_mode`, `test_public_key`, `test_secret_key`, `live_public_key`, `live_secret_key`, `webhook_url`, `callback_url`, `supported_currencies`, `min_amount`, `max_amount`, `processing_fee`, `fee_type`, `sort_order`, `created_at`, `updated_at`) VALUES ('4', 'PayPal', 'paypal', 'card', 'Pay with your PayPal account or credit/debit card', 'You will be redirected to PayPal to complete your payment.', NULL, '0', '0', '1', NULL, NULL, NULL, NULL, NULL, NULL, 'USD,EUR,GBP', NULL, NULL, NULL, 'percentage', '4', '2026-02-28 13:10:12', '2026-02-28 13:10:41');


-- Table structure for table `payments`
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `gateway` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','successful','failed','refunded') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `product_attributes`
DROP TABLE IF EXISTS `product_attributes`;
CREATE TABLE `product_attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `attribute_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `product_images`
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_main` tinyint(1) DEFAULT '0',
  `display_order` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `product_images`
INSERT INTO `product_images` (`id`, `product_id`, `filename`, `is_main`, `display_order`) VALUES ('1', '1', '1_698acebb182ad.jpeg', '1', '1');
INSERT INTO `product_images` (`id`, `product_id`, `filename`, `is_main`, `display_order`) VALUES ('2', '2', '2_698ad79be5d0b.jpg', '1', '1');
INSERT INTO `product_images` (`id`, `product_id`, `filename`, `is_main`, `display_order`) VALUES ('3', '2', '2_698ad7fa6169a.jpg', '1', '1');
INSERT INTO `product_images` (`id`, `product_id`, `filename`, `is_main`, `display_order`) VALUES ('4', '2', '2_698ae002e5f83.jpg', '1', '1');
INSERT INTO `product_images` (`id`, `product_id`, `filename`, `is_main`, `display_order`) VALUES ('5', '3', '3_698ae0b43bedd.jpg', '1', '1');
INSERT INTO `product_images` (`id`, `product_id`, `filename`, `is_main`, `display_order`) VALUES ('6', '3', '3_698b8ba94515b.jpeg', '1', '1');


-- Table structure for table `products`
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upc` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ean` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `stock` int(11) DEFAULT '0',
  `status` enum('draft','active','inactive','out_of_stock') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_virtual` tinyint(1) DEFAULT '0',
  `downloadable` tinyint(1) DEFAULT '0',
  `taxable` tinyint(1) DEFAULT '1',
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `products`
INSERT INTO `products` (`id`, `name`, `slug`, `description`, `price`, `discount_price`, `cost_price`, `category_id`, `brand`, `sku`, `upc`, `ean`, `model`, `weight`, `dimensions`, `short_description`, `stock`, `status`, `featured`, `created_at`, `is_virtual`, `downloadable`, `taxable`, `meta_title`, `meta_keywords`, `meta_description`, `updated_at`, `deleted_at`) VALUES ('1', 'SPARK 50', 'spark-50', 'The Technology is a sequence where the Technology is used for heating milk and the common difference and ', '2000.00', '500.00', '2000.00', '1', 'Tecno', 'SKU001', '', '', '', NULL, '', '', '9', 'active', '0', '2026-02-10 07:21:29', '0', '0', '1', '', '', '', '2026-02-11 09:18:34', NULL);
INSERT INTO `products` (`id`, `name`, `slug`, `description`, `price`, `discount_price`, `cost_price`, `category_id`, `brand`, `sku`, `upc`, `ean`, `model`, `weight`, `dimensions`, `short_description`, `stock`, `status`, `featured`, `created_at`, `is_virtual`, `downloadable`, `taxable`, `meta_title`, `meta_keywords`, `meta_description`, `updated_at`, `deleted_at`) VALUES ('2', 'T-Shirt', 't-shirt', 'The Technology Incubation complex is a and most important thing that has a ', '20000.00', '15000.00', '20000.00', '2', '', '', '', '', '', NULL, '', '', '11', 'active', '1', '2026-02-10 08:00:43', '0', '0', '1', '', '', '', '2026-02-10 23:18:19', NULL);
INSERT INTO `products` (`id`, `name`, `slug`, `description`, `price`, `discount_price`, `cost_price`, `category_id`, `brand`, `sku`, `upc`, `ean`, `model`, `weight`, `dimensions`, `short_description`, `stock`, `status`, `featured`, `created_at`, `is_virtual`, `downloadable`, `taxable`, `meta_title`, `meta_keywords`, `meta_description`, `updated_at`, `deleted_at`) VALUES ('3', 'Infinix Hot 40', 'infinix-hot-40', 'The Technology is not a big problem at the moment but I am good to the point that this is a and no problem for us states ', '200000.00', '190000.00', '200000.00', '1', 'Infinix', 'SKU002', '13dfg', 'Ggvv', '12d545', NULL, '', 'The Technology Incubation complex is a ', '9', 'active', '1', '2026-02-10 08:39:32', '0', '0', '1', '', '', '', '2026-02-10 21:43:53', NULL);


-- Table structure for table `settings`
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `settings`
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('1', 'site_name', 'MUHAMUKTAR GLOBAL VENTURE', '2026-02-11 09:24:55');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('2', 'site_slogan', 'Quality Products • Fast Delivery', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('3', 'contact_email', 'support@muhamuktar.com', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('4', 'contact_phone', '+234 9034095385', '2026-02-10 16:32:42');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('5', 'contact_address', 'El-wazir Estate Bosso Minna, Niger State', '2026-02-10 16:32:42');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('6', 'free_shipping_threshold', '50000', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('7', 'shipping_fee', '1500', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('8', 'currency', '$ USD', '2026-02-11 09:24:55');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('9', 'paystack_test_secret', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('10', 'paystack_live_secret', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('11', 'paystack_test_public', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('12', 'paystack_live_public', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('13', 'paystack_mode', 'test', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('14', 'maintenance_mode', '1', '2026-02-10 16:33:15');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('15', 'enable_registration', '1', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('16', 'enable_reviews', '1', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('17', 'default_country', 'NG', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('18', 'tax_rate', '7.5', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('19', 'store_logo', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('20', 'favicon', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('21', 'meta_description', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('22', 'meta_keywords', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('23', 'social_facebook', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('24', 'social_twitter', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('25', 'social_instagram', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('26', 'social_whatsapp', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('27', 'google_analytics', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('28', 'recaptcha_site_key', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('29', 'recaptcha_secret_key', '', '2026-02-10 16:31:27');
INSERT INTO `settings` (`id`, `key`, `value`, `updated_at`) VALUES ('30', 'enable_recaptcha', '1', '2026-02-10 16:33:15');


-- Table structure for table `shipping_methods`
DROP TABLE IF EXISTS `shipping_methods`;
CREATE TABLE `shipping_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zone_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('flat','free','percentage','weight_based','price_based','pickup') COLLATE utf8mb4_unicode_ci DEFAULT 'flat',
  `cost` decimal(10,2) DEFAULT '0.00',
  `min_order` decimal(10,2) DEFAULT NULL,
  `max_order` decimal(10,2) DEFAULT NULL,
  `min_weight` decimal(10,2) DEFAULT NULL,
  `max_weight` decimal(10,2) DEFAULT NULL,
  `free_shipping_threshold` decimal(10,2) DEFAULT NULL,
  `estimated_days_min` int(11) DEFAULT NULL,
  `estimated_days_max` int(11) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `zone_id` (`zone_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `shipping_rates`
DROP TABLE IF EXISTS `shipping_rates`;
CREATE TABLE `shipping_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `method_id` int(11) NOT NULL,
  `min_value` decimal(10,2) NOT NULL,
  `max_value` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `additional_item_cost` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `method_id` (`method_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `shipping_tracking`
DROP TABLE IF EXISTS `shipping_tracking`;
CREATE TABLE `shipping_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carrier_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carrier_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estimated_delivery` date DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_checked` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tracking_data` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_tracking_number` (`tracking_number`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `shipping_tracking`
INSERT INTO `shipping_tracking` (`id`, `order_id`, `tracking_number`, `carrier_name`, `carrier_url`, `status`, `tracking_date`, `estimated_delivery`, `last_updated`, `created_at`, `last_checked`, `tracking_data`) VALUES ('1', '1', '123566', 'Shipping Carrier', '#', 'In Transit', '2026-02-11 07:28:27', '2026-02-14', '2026-02-11 07:28:27', '2026-02-11 07:28:27', '2026-02-11 07:28:27', '{\"tracking_history\":[{\"date\":\"2026-02-09 07:28:27\",\"location\":\"Origin Facility\",\"description\":\"Shipment picked up\"},{\"date\":\"2026-02-10 07:28:27\",\"location\":\"Transit Hub\",\"description\":\"In transit\"},{\"date\":\"2026-02-11 07:28:27\",\"location\":\"Local Delivery Center\",\"description\":\"Arrived at delivery center\"}],\"carrier_response\":{\"success\":true,\"carrier\":\"Shipping Carrier\",\"tracking_number\":\"123566\",\"status\":\"In Transit\",\"location\":\"Processing Facility\",\"estimated_delivery\":\"2026-02-14\",\"last_update\":\"2026-02-11 07:28:27\",\"tracking_history\":[{\"date\":\"2026-02-09 07:28:27\",\"location\":\"Origin Facility\",\"description\":\"Shipment picked up\"},{\"date\":\"2026-02-10 07:28:27\",\"location\":\"Transit Hub\",\"description\":\"In transit\"},{\"date\":\"2026-02-11 07:28:27\",\"location\":\"Local Delivery Center\",\"description\":\"Arrived at delivery center\"}]}}');
INSERT INTO `shipping_tracking` (`id`, `order_id`, `tracking_number`, `carrier_name`, `carrier_url`, `status`, `tracking_date`, `estimated_delivery`, `last_updated`, `created_at`, `last_checked`, `tracking_data`) VALUES ('2', '6', '1235664', 'Shipping Carrier', '#', 'In Transit', '2026-02-11 09:19:12', '2026-02-14', '2026-02-11 09:19:12', '2026-02-11 09:19:12', '2026-02-11 09:19:12', '{\"tracking_history\":[{\"date\":\"2026-02-09 09:19:12\",\"location\":\"Origin Facility\",\"description\":\"Shipment picked up\"},{\"date\":\"2026-02-10 09:19:12\",\"location\":\"Transit Hub\",\"description\":\"In transit\"},{\"date\":\"2026-02-11 09:19:12\",\"location\":\"Local Delivery Center\",\"description\":\"Arrived at delivery center\"}],\"carrier_response\":{\"success\":true,\"carrier\":\"Shipping Carrier\",\"tracking_number\":\"1235664\",\"status\":\"In Transit\",\"location\":\"Processing Facility\",\"estimated_delivery\":\"2026-02-14\",\"last_update\":\"2026-02-11 09:19:12\",\"tracking_history\":[{\"date\":\"2026-02-09 09:19:12\",\"location\":\"Origin Facility\",\"description\":\"Shipment picked up\"},{\"date\":\"2026-02-10 09:19:12\",\"location\":\"Transit Hub\",\"description\":\"In transit\"},{\"date\":\"2026-02-11 09:19:12\",\"location\":\"Local Delivery Center\",\"description\":\"Arrived at delivery center\"}]}}');
INSERT INTO `shipping_tracking` (`id`, `order_id`, `tracking_number`, `carrier_name`, `carrier_url`, `status`, `tracking_date`, `estimated_delivery`, `last_updated`, `created_at`, `last_checked`, `tracking_data`) VALUES ('3', '6', '1235664', 'Shipping Carrier', '#', 'In Transit', '2026-02-11 17:50:21', '2026-02-14', '2026-02-11 17:50:21', '2026-02-11 17:50:21', '2026-02-11 17:50:21', '{\"tracking_history\":[{\"date\":\"2026-02-09 17:50:21\",\"location\":\"Origin Facility\",\"description\":\"Shipment picked up\"},{\"date\":\"2026-02-10 17:50:21\",\"location\":\"Transit Hub\",\"description\":\"In transit\"},{\"date\":\"2026-02-11 17:50:21\",\"location\":\"Local Delivery Center\",\"description\":\"Arrived at delivery center\"}],\"carrier_response\":{\"success\":true,\"carrier\":\"Shipping Carrier\",\"tracking_number\":\"1235664\",\"status\":\"In Transit\",\"location\":\"Processing Facility\",\"estimated_delivery\":\"2026-02-14\",\"last_update\":\"2026-02-11 17:50:21\",\"tracking_history\":[{\"date\":\"2026-02-09 17:50:21\",\"location\":\"Origin Facility\",\"description\":\"Shipment picked up\"},{\"date\":\"2026-02-10 17:50:21\",\"location\":\"Transit Hub\",\"description\":\"In transit\"},{\"date\":\"2026-02-11 17:50:21\",\"location\":\"Local Delivery Center\",\"description\":\"Arrived at delivery center\"}]}}');


-- Table structure for table `shipping_zones`
DROP TABLE IF EXISTS `shipping_zones`;
CREATE TABLE `shipping_zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `countries` text,
  `rates` json DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `priority` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table structure for table `stock_movements`
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference` varchar(191) DEFAULT NULL,
  `note` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table structure for table `testimonials`
DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(191) NOT NULL,
  `customer_email` varchar(191) DEFAULT NULL,
  `content` text NOT NULL,
  `rating` int(11) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT '0',
  `product_id` int(11) DEFAULT NULL,
  `status` enum('approved','pending','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_testimonials_approved_by` (`approved_by`),
  CONSTRAINT `fk_testimonials_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Table structure for table `transactions`
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `transaction_ref` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'NGN',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_response` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transaction_ref` (`transaction_ref`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('customer','admin') COLLATE utf8mb4_unicode_ci DEFAULT 'customer',
  `status` enum('active','inactive','banned') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `phone`, `role`, `status`, `created_at`) VALUES ('1', 'adamuusnan87@gmail.com', '$2y$10$R1KPsDJUivGGg.VKMpu7oOpFxSxrhvyRB5haNAlsG3/nUfcbI/wkm', 'Usnan Adamu', '09130685889', 'admin', 'active', '2026-02-09 09:23:04');
INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `phone`, `role`, `status`, `created_at`) VALUES ('2', 'admin@muhamuktar.com', '$2y$10$YourGeneratedSaltHere123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabc', 'Admin User', NULL, 'admin', 'active', '2026-02-09 09:40:32');
INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `phone`, `role`, `status`, `created_at`) VALUES ('3', 'kuyabe3232@gmail.com', '$2y$10$7gPVuPr/gdHR2bqD6/C/nu1suWqreShfhS.YwIKEHnMTrwi2mssk.', 'ISAH ABDULLAHI', '09034095385', 'customer', 'active', '2026-02-10 08:46:57');

SET FOREIGN_KEY_CHECKS=1;

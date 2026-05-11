-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql
-- Generation Time: Apr 25, 2026 at 01:17 AM
-- Server version: 8.0.44
-- PHP Version: 8.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pc_shop`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `district` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ward` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `badge` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `title`, `description`, `badge`, `metadata`, `image`, `link`, `position`, `sort_order`, `is_active`, `starts_at`, `ends_at`, `created_at`, `updated_at`) VALUES
(4, 'Flash Sale - Giảm đến 30%', 'Ưu đãi hấp dẫn cho linh kiện máy tính', 'Sale', NULL, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=800&h=400&fit=crop', '/products?sort=sale', 'sidebar', 1, 1, NULL, NULL, '2026-03-05 01:50:20', '2026-03-05 03:08:23'),
(5, 'Phụ kiện Gaming chính hãng', 'Bàn phím, chuột, tai nghe gaming hàng đầu', 'Gaming', NULL, 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=800&h=400&fit=crop', '/categories/phu-kien', 'sidebar', 2, 1, NULL, NULL, '2026-03-05 01:50:20', '2026-03-05 03:08:23'),
(6, 'Xây dựng PC<br>trong mơ của bạn', 'Công cụ build cấu hình thông minh — kiểm tra tương thích tự động, xem TDP & giá ngay lập tức.', 'Hot Deal', '{\"glow_a\": \"bg-white\", \"glow_b\": \"bg-yellow-300\", \"cta_link\": \"/configurator\", \"gradient\": \"from-indigo-600 via-purple-600 to-pink-500\", \"cta2_link\": \"/products\", \"cta_label\": \"Build PC ngay\", \"cta2_label\": \"Xem sản phẩm\"}', 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=1920&h=600&fit=crop', '/configurator', 'hero', 1, 1, NULL, NULL, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(7, 'PC Gaming<br>hiệu năng khủng', 'Cấu hình mạnh mẽ với RTX 50 Series, Intel Gen 15 & AMD Ryzen 9000. Sẵn sàng chiến mọi tựa game.', 'PC Gaming', '{\"glow_a\": \"bg-orange-300\", \"glow_b\": \"bg-red-400\", \"cta_link\": \"/categories/pc-gaming\", \"gradient\": \"from-red-600 via-orange-600 to-amber-500\", \"cta2_link\": \"/configurator\", \"cta_label\": \"Xem PC Gaming\", \"cta2_label\": \"Build cấu hình\"}', 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=1920&h=600&fit=crop', '/categories/pc-gaming', 'hero', 2, 1, NULL, NULL, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(8, 'Laptop chính hãng<br>giá tốt nhất', 'Laptop Gaming, Đồ họa, Văn phòng — đa dạng thương hiệu, bảo hành toàn quốc.', 'Laptop', '{\"glow_a\": \"bg-cyan-300\", \"glow_b\": \"bg-emerald-300\", \"cta_link\": \"/categories/laptop\", \"gradient\": \"from-emerald-600 via-teal-600 to-cyan-500\", \"cta2_link\": \"/products\", \"cta_label\": \"Xem Laptop\", \"cta2_label\": \"So sánh giá\"}', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=1920&h=600&fit=crop', '/categories/laptop', 'hero', 3, 1, NULL, NULL, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(9, 'Giảm đến 40%<br>linh kiện PC', 'RAM, SSD, VGA, PSU — hàng chính hãng giá sốc. Số lượng có hạn, mua ngay!', 'Flash Sale', '{\"glow_a\": \"bg-pink-300\", \"glow_b\": \"bg-rose-400\", \"cta_link\": \"/categories/linh-kien-pc\", \"gradient\": \"from-pink-600 via-rose-600 to-red-500\", \"cta2_link\": \"/products\", \"cta_label\": \"Mua ngay\", \"cta2_label\": \"Xem tất cả\"}', 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=1920&h=600&fit=crop', '/categories/linh-kien-pc', 'hero', 4, 1, NULL, NULL, '2026-03-05 03:08:23', '2026-03-05 03:08:23');

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `slug`, `logo`, `website`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Intel', 'intel', 'https://logo.clearbit.com/intel.com', NULL, 1, '2026-02-20 01:54:27', '2026-03-05 03:08:17'),
(2, 'AMD', 'amd', 'https://logo.clearbit.com/amd.com', NULL, 1, '2026-02-20 01:54:27', '2026-03-05 03:08:17'),
(3, 'NVIDIA', 'nvidia', 'https://logo.clearbit.com/nvidia.com', NULL, 1, '2026-02-20 01:54:27', '2026-03-05 03:08:17'),
(4, 'ASUS', 'asus', 'https://logo.clearbit.com/asus.com', NULL, 1, '2026-02-20 01:54:27', '2026-03-05 03:08:17'),
(5, 'MSI', 'msi', 'https://logo.clearbit.com/msi.com', NULL, 1, '2026-02-20 01:54:27', '2026-03-05 03:08:17'),
(6, 'Gigabyte', 'gigabyte', 'https://logo.clearbit.com/gigabyte.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(7, 'EVGA', 'evga', 'https://logo.clearbit.com/evga.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(8, 'Zotac', 'zotac', 'https://logo.clearbit.com/zotac.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(9, 'Colorful', 'colorful', 'https://logo.clearbit.com/colorful.cn', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(10, 'Galax', 'galax', 'https://logo.clearbit.com/galax.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(11, 'ASRock', 'asrock', 'https://logo.clearbit.com/asrock.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(12, 'G.Skill', 'gskill', 'https://logo.clearbit.com/gskill.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(13, 'Kingston', 'kingston', 'https://logo.clearbit.com/kingston.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(14, 'Corsair', 'corsair', 'https://logo.clearbit.com/corsair.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(15, 'TeamGroup', 'teamgroup', 'https://logo.clearbit.com/teamgroupinc.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(16, 'Crucial', 'crucial', 'https://logo.clearbit.com/crucial.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(17, 'Samsung', 'samsung', 'https://logo.clearbit.com/samsung.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(18, 'Western Digital', 'western-digital', 'https://logo.clearbit.com/westerndigital.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(19, 'Seagate', 'seagate', 'https://logo.clearbit.com/seagate.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(20, 'SK Hynix', 'sk-hynix', 'https://logo.clearbit.com/skhynix.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(21, 'Lexar', 'lexar', 'https://logo.clearbit.com/lexar.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(22, 'Seasonic', 'seasonic', 'https://logo.clearbit.com/seasonic.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(23, 'NZXT', 'nzxt', 'https://logo.clearbit.com/nzxt.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(24, 'Cooler Master', 'cooler-master', 'https://logo.clearbit.com/coolermaster.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(25, 'Thermaltake', 'thermaltake', 'https://logo.clearbit.com/thermaltake.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(26, 'Be Quiet!', 'be-quiet', 'https://logo.clearbit.com/bequiet.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(27, 'Super Flower', 'super-flower', 'https://logo.clearbit.com/superflower.com.tw', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(28, 'Lian Li', 'lian-li', 'https://logo.clearbit.com/lian-li.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(29, 'Phanteks', 'phanteks', 'https://logo.clearbit.com/phanteks.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(30, 'Fractal Design', 'fractal-design', 'https://logo.clearbit.com/fractal-design.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(31, 'Noctua', 'noctua', 'https://logo.clearbit.com/noctua.at', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(32, 'Arctic', 'arctic', 'https://logo.clearbit.com/arctic.de', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(33, 'DeepCool', 'deepcool', 'https://logo.clearbit.com/deepcool.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(34, 'ID-Cooling', 'id-cooling', 'https://logo.clearbit.com/idcooling.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:17'),
(35, 'Logitech', 'logitech', 'https://logo.clearbit.com/logitech.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(36, 'Razer', 'razer', 'https://logo.clearbit.com/razer.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(37, 'SteelSeries', 'steelseries', 'https://logo.clearbit.com/steelseries.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(38, 'HyperX', 'hyperx', 'https://logo.clearbit.com/hyperx.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(39, 'Ducky', 'ducky', 'https://logo.clearbit.com/duckychannel.com.tw', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(40, 'Keychron', 'keychron', 'https://logo.clearbit.com/keychron.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(41, 'LG', 'lg', 'https://logo.clearbit.com/lg.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(42, 'Dell', 'dell', 'https://logo.clearbit.com/dell.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(43, 'BenQ', 'benq', 'https://logo.clearbit.com/benq.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(44, 'AOC', 'aoc', 'https://logo.clearbit.com/aoc.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(45, 'ViewSonic', 'viewsonic', 'https://logo.clearbit.com/viewsonic.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(46, 'Lenovo', 'lenovo', 'https://logo.clearbit.com/lenovo.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(47, 'HP', 'hp', 'https://logo.clearbit.com/hp.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(48, 'Acer', 'acer', 'https://logo.clearbit.com/acer.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(49, 'Apple', 'apple', 'https://logo.clearbit.com/apple.com', NULL, 1, '2026-02-20 01:54:28', '2026-03-05 03:08:18');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `session_id`, `created_at`, `updated_at`) VALUES
(1, NULL, 'NWa6yrymRzpJCqTthmzhU17OssXhb433ge9GmFue', '2026-02-20 04:26:46', '2026-02-20 04:26:46'),
(2, NULL, 'JwM6p1S3IFGKGLH3CWhir2UI9EfKQtca7WY447uz', '2026-02-20 04:27:07', '2026-02-20 04:27:07'),
(3, NULL, 'XVtsUow6WF9HnDsYR6uNDcyFNhhp6R8qOKk7g0Ym', '2026-02-20 04:27:11', '2026-02-20 04:27:11'),
(4, NULL, 'bcfyUAfvvxAUoIt2kYkZ6DUOYuPKdyZLbfd6MNZL', '2026-02-20 04:27:37', '2026-02-20 04:27:37'),
(5, NULL, '9KMlOTjRTCyqzT3PoqQm3QZ9QV4z7n64wXxGF4ez', '2026-02-20 04:31:08', '2026-02-20 04:31:08'),
(6, NULL, 'RxT4MfHEvJ5iE4XOVi8sJGuX1VqwHYpkwpMVbdUv', '2026-02-20 04:32:34', '2026-02-20 04:32:34'),
(7, NULL, '2PthxF4z29CXC3z4HCy9MIIRQHCo01EmvtZLk3wX', '2026-02-20 04:32:51', '2026-02-20 04:32:51'),
(8, NULL, 'w27B2qSqfWmJLA4sO3kzcm0VbvrpQ1d1TGJtNr5c', '2026-02-20 04:38:00', '2026-02-20 04:38:00'),
(9, NULL, '6LLK4y7XJ7wDGxmyRw68MGoqlXxLgItvLNfw5jUJ', '2026-02-20 04:41:46', '2026-02-20 04:41:46'),
(10, NULL, 'IZqUtmALmXFlJjuLyY1qqO3iHkfSTAuokWEskpGr', '2026-02-20 04:59:29', '2026-02-20 04:59:29'),
(11, NULL, 'IM4jfDdfxUifTAthXAGzMEXkdG2eQu5xdxRbvH4U', '2026-02-20 05:24:07', '2026-02-20 05:24:07'),
(12, NULL, 'QcKTDHzkDqOviHSTT9VfIa3SAdJIQmR88iUFq5p2', '2026-02-20 09:25:13', '2026-02-20 09:25:13'),
(13, NULL, '1XX5SliGh1NAmVg4PIsGBv8JKdboGdeHVCqbiN39', '2026-02-20 09:25:13', '2026-02-20 09:25:13'),
(14, NULL, '8LsE2rTb6uVnHLZYYT7jOKZvnvXft962ewYI9wJr', '2026-02-20 09:28:39', '2026-02-20 09:28:39'),
(15, NULL, '0ovJrzGp11Rh9d32ZJ9DtT1qMptpQPNuAfZ1lW1D', '2026-02-20 09:30:48', '2026-02-20 09:30:48'),
(16, NULL, 'Z1NlaHitOAmzLCZ6HBLhUshOFF97PBxaEeJHKiLW', '2026-02-20 09:32:14', '2026-02-20 09:32:14'),
(17, NULL, 'OBjW6dte2QvhDAiOachrz4Nech4H8H9MK9i7dw2C', '2026-02-20 09:32:42', '2026-02-20 09:32:42'),
(18, NULL, 'kHre3ASEzEAHgVLeYt92p8bJNgRudPTL0oTLA3Xd', '2026-02-20 09:32:46', '2026-02-20 09:32:46'),
(19, NULL, 'AHKk65tWP278Rs8ueTWmqBwVYwVyg5oxMlrQgd9r', '2026-02-20 09:33:03', '2026-02-20 09:33:03'),
(20, NULL, 'pFYeCSeskw79gbPp62kr18iPRnij21DtOUVej4Dl', '2026-02-20 09:33:30', '2026-02-20 09:33:30'),
(21, NULL, 'Z6Llfb11MOGzmjNTwxEIG8d8oMi1LtEjpS5b3V8f', '2026-02-20 09:37:49', '2026-02-20 09:37:49'),
(22, NULL, 'QLrtEV0ce2pNRtzal6nFCLLHgb1D2mhmyx8nlGo5', '2026-02-20 11:35:20', '2026-02-20 11:35:20'),
(23, NULL, 'RbYliTstSlwq0w3ALWamHMwsEWo1D6I3XljjkJ4q', '2026-02-20 12:01:09', '2026-02-20 12:01:09'),
(24, NULL, 'nEP5MzgWgOOqqwj1IJ7mVJaaizGtY7ift0Z4yJNL', '2026-02-20 15:30:25', '2026-02-20 15:30:25'),
(25, NULL, 'vDCiBpBFjt4a3fsKH6HkNwwQ9y2SwOEMZOy8TJZ1', '2026-02-20 15:39:17', '2026-02-20 15:39:17'),
(26, NULL, 'ckaEmeE0V55FNjqpwMr5Eayx2rYipWBgKT7IwNtO', '2026-02-20 15:39:17', '2026-02-20 15:39:17'),
(27, NULL, 'QTFkVDanGqEBEFbOHQfeOoBiBdnXl7eBMZWkoufX', '2026-02-20 15:42:06', '2026-02-20 15:42:06'),
(28, NULL, 'lvFRGzuz9yoTx40zKm3j2It0cPf1KpES51nLQheM', '2026-02-20 15:48:47', '2026-02-20 15:48:47'),
(29, NULL, 'BWExRzcxr9v9ppksQcqmIUPZeKnrWeHKOu8HMNvg', '2026-02-20 15:50:33', '2026-02-20 15:50:33'),
(30, NULL, 'FUv2XSjQcAxSSGoRvTjtKqGvpHfTmsrVhaVr0YSV', '2026-02-20 16:04:25', '2026-02-20 16:04:25'),
(31, NULL, 'DlsI0FAUNtM02N2MyOhikS7yXzeeveVcARoUCUrQ', '2026-02-20 16:05:53', '2026-02-20 16:05:53'),
(32, NULL, 'go8UJn3gRD69FVT4Hm3fLoZI7H1UU9M2Ct2P5K0u', '2026-02-21 00:21:24', '2026-02-21 00:21:24'),
(33, NULL, '8rZGSaeK4d9N6fksvO6krUXL2SKpr5RmMfzgIvjG', '2026-02-21 00:36:00', '2026-02-21 00:36:00'),
(34, NULL, 'Vhq5yFMeaUJjW3CnZU66mVenNyMbXAWOtv8YGfAq', '2026-02-21 05:49:30', '2026-02-21 05:49:30'),
(35, NULL, '69taBqBicAUNSYonDaoFErSjtK0SnyidjmRsQu9P', '2026-02-21 16:41:37', '2026-02-21 16:41:37'),
(36, NULL, 'VYAtfilgrWtB7as5RVE9rNcSjgxdKe5ML65wwuKU', '2026-02-22 00:30:32', '2026-02-22 00:30:32'),
(37, NULL, 'F2v4bsSQYbbz57dIrYP99AgMzUgAXv66fyDgTkYz', '2026-02-22 00:33:26', '2026-02-22 00:33:26'),
(38, NULL, 'oz3ilMnJQB74AaLThRX9YJE6WLH6X0oGv02edIqC', '2026-02-22 00:34:08', '2026-02-22 00:34:08'),
(39, NULL, 'IRyNLDro49Ln7iCcmXiaL0i4Cthufn981GdUBubU', '2026-02-22 01:13:17', '2026-02-22 01:13:17'),
(40, NULL, 'h3QLlDpTRh7yxPJxaubJ4HNIgzLIJ1jLuJIv2dw3', '2026-02-23 02:33:53', '2026-02-23 02:33:53'),
(41, NULL, 'ViC9VDKnuycepgS0qxLwnF8FLbWYIaChUvXWafzf', '2026-02-23 15:55:26', '2026-02-23 15:55:26'),
(42, NULL, 'LfyPMkbXDgDnsR85VguaZwCF8SBzCIwxfm3TXRUA', '2026-02-23 16:29:48', '2026-02-23 16:29:48'),
(43, NULL, 'zLxPKBZOUIkM3tq2klrhvg8STm1aNFHGdlCr1jGP', '2026-02-23 16:30:11', '2026-02-23 16:30:11'),
(44, NULL, 'QU3srB6qln5zD45mGKOTrnrrpHgIjJKk433f0n4C', '2026-02-23 16:38:21', '2026-02-23 16:38:21'),
(45, NULL, 'GndNNQy48tK7iL7abtB9VD2avFfWkX40jll8nrxp', '2026-02-23 16:48:58', '2026-02-23 16:48:58'),
(46, NULL, 'nkcQWjOjTTj8razWCvuwEmCjxBpitCz5Wcq0nmZA', '2026-02-23 16:49:02', '2026-02-23 16:49:02'),
(47, NULL, 'rqoIyROj36SdL3wkzTrYzHMAUVwVjY3jgNALRTcZ', '2026-02-23 16:49:33', '2026-02-23 16:49:33'),
(48, NULL, 'iYUKvIgT2fEwwug3HLTprpc0gOs8POeTmLrkXpxP', '2026-02-23 16:49:37', '2026-02-23 16:49:37'),
(49, NULL, '5wiSsy7Y6bvokoMm8VwxMfI8M3cbM2QGgPzMRio3', '2026-02-23 16:50:25', '2026-02-23 16:50:25'),
(50, NULL, 'pAgmuXCiSw5oz3QAanL9x5kILe0xufMe5zdNshiU', '2026-02-25 16:04:53', '2026-02-25 16:04:53'),
(51, NULL, 'SahhXXXheAEIVuJ3E3zHyD6gbZFGUa1oT8mB7nZz', '2026-02-25 16:05:30', '2026-02-25 16:05:30'),
(52, NULL, 'L7bKOx6sI25UJGEULIj1c0LdjoFKGJbAnPypnxXb', '2026-02-25 16:12:32', '2026-02-25 16:12:32'),
(53, NULL, 'RfGRxcWgKBoC5KSdwK2dlLOTEpRm8T6dOGkR8K6k', '2026-02-25 16:25:30', '2026-02-25 16:25:30'),
(54, NULL, 'CYwiz2NMZOK9hd5IO4jvjGq5RLYDx5WG2JDGRHEl', '2026-02-25 16:37:00', '2026-02-25 16:37:00'),
(55, NULL, 'eEkkwlHMaRAlLdQEvUemJcaLDgbbGt6GmmBYEzJV', '2026-02-25 16:37:55', '2026-02-25 16:37:55'),
(56, NULL, 'tA3MVS8onwgF2N4zkkkwBG2oUFT7EeicYI383XGe', '2026-02-25 16:46:22', '2026-02-25 16:46:22'),
(57, NULL, 'xRkpfafSiIOhktFpfZ7v7jjRbLrU0aSK9ze8K97q', '2026-02-25 16:46:36', '2026-02-25 16:46:36'),
(58, NULL, 'E19rtrgtRcy2OciAOYeCtlobF302pVzzkpmtNDh1', '2026-02-25 16:54:15', '2026-02-25 16:54:15'),
(59, NULL, 'FbsPImYLkWHwDtGc11T4M7cARN82RzvpqTE7ckzP', '2026-02-26 07:18:18', '2026-02-26 07:18:18'),
(60, NULL, 'kgEZSxB7439NDnN8xHEmSAZY56euPglK6iesWMg1', '2026-02-26 07:21:03', '2026-02-26 07:21:03'),
(61, NULL, 'JTjY1uaXL7xg1MOFjRdLAuJWvU5ZaDE9EU3DbTwZ', '2026-02-26 07:27:08', '2026-02-26 07:27:08'),
(62, NULL, 'aP9wz3jqQYlQxCSQNZS6fSLFL7LlM0yEEhEMf654', '2026-02-26 07:56:17', '2026-02-26 07:56:17'),
(63, NULL, 'T2RJxDRm0FCd61LGH44OzxBAVMjL7gVFa9Mkd2Hw', '2026-02-26 07:56:17', '2026-02-26 07:56:17'),
(64, NULL, 'fqh0s9BCRldsdHGeg7hMKz0Bg2P6W5TwVuA4sX9h', '2026-02-26 07:58:30', '2026-02-26 07:58:30'),
(65, NULL, 'vJHmJvrQPDtRgCIKu6U7dGNOSJjxWUWYDi8SL7SC', '2026-02-26 09:16:31', '2026-02-26 09:16:31'),
(66, NULL, 'hAliYCYZi6pXs94QgWcM2kHILP0A1WO4tSU1nr4A', '2026-02-26 09:20:57', '2026-02-26 09:20:57'),
(67, NULL, '0JrjB4GfYYJU96Z96sg5YKleAz4u9g7JtUlDQPeW', '2026-02-26 09:20:57', '2026-02-26 09:20:57'),
(68, NULL, 'IiWh0B78YYyEjsK2wTulKpzCFDmF8YqdOZYi9R9Q', '2026-02-26 09:29:38', '2026-02-26 09:29:38'),
(69, NULL, 'bVgJak7XDBasWE1Q6ekgFpGJZGNVcuSNBmGYTMn2', '2026-02-26 09:29:43', '2026-02-26 09:29:43'),
(70, NULL, 'UBHfdl4VrEqkuwITPLuQtiO5Cs3Q2UYkfFsIfaMs', '2026-02-26 09:34:47', '2026-02-26 09:34:47'),
(71, NULL, 'Ig3jYwLt3AeYY9IoE9AHrEnevFzhFruchUECy7LT', '2026-02-26 09:35:01', '2026-02-26 09:35:01'),
(72, NULL, 'iNatik4m9njSbBUY79xiuBVei90Q6RFtEPNAUcFA', '2026-02-26 16:38:30', '2026-02-26 16:38:30'),
(73, NULL, 'lMwz1r13eTissw9jvlr6vEpSRxOt3MU1Wa5Zp09v', '2026-02-26 16:51:01', '2026-02-26 16:51:01'),
(74, NULL, '2dUszSsQBspQC00LGRDQcf3SMuGuPyx1gqCxKIpp', '2026-03-03 14:23:49', '2026-03-03 14:23:49'),
(75, NULL, '0ema94NVtXlwFgvcmNZaqu1cDHadSh7w51ectqz6', '2026-03-03 14:33:12', '2026-03-03 14:33:12'),
(76, NULL, 'lEoAtodtDab9LFoFLgfYqxHP0m6rQZ28uaAXI2xb', '2026-03-03 14:34:29', '2026-03-03 14:34:29'),
(77, NULL, 'JqjyG06brX6tRvIPlIZZHaKRLtOIbLlfE1ztAOcw', '2026-03-03 14:35:47', '2026-03-03 14:35:47'),
(78, NULL, 'bTvhUfejcBd2j6tAe8Esu3DlbBzxwJXFG21pN76g', '2026-03-03 14:36:08', '2026-03-03 14:36:08'),
(79, NULL, '0gus7lpRz1K1CxJTAVUNo7kC7DGIcPnlXpvVwTOO', '2026-03-03 14:37:27', '2026-03-03 14:37:27'),
(80, NULL, 'f9G1U48L7dIBSuB4iJ8A59h7S5nvrZteSDf67uwY', '2026-03-03 14:37:30', '2026-03-03 14:37:30'),
(81, NULL, 'jFFdt9f5o1B7SJObLOwvEjht1WnWKnkLl2oWUo6v', '2026-03-03 14:58:13', '2026-03-03 14:58:13'),
(82, NULL, 'SkZGiWhssxC5azHZfaG4s74PB3RZpuCFuK78IGg6', '2026-03-03 15:40:34', '2026-03-03 15:40:34'),
(83, NULL, 'UY51gIQXcSTDMBqYLjCjx0chKPX4AOwO3ZLRb3gv', '2026-03-03 16:12:37', '2026-03-03 16:12:37'),
(84, NULL, 'OVnM6ntwhI3MIAg3bicrjBMnsqLgYbaOwRH8rGc8', '2026-03-03 16:28:37', '2026-03-03 16:28:37'),
(85, NULL, 'DHtM0dDyHWHI5EJAjxzgZckfGjmiv3yb0ZRhlpsA', '2026-03-05 01:54:32', '2026-03-05 01:54:32'),
(86, NULL, 'YbHJOfn90PfeBdWtD8W4F43XNp3EzeOsVijGJfWS', '2026-03-05 02:47:01', '2026-03-05 02:47:01'),
(87, NULL, 'hWPSmYegnnuu1QZuXLnwxDvUktX6gPgxNeul0ACQ', '2026-03-05 03:05:38', '2026-03-05 03:05:38'),
(88, NULL, 'BBtVvI3TRLzyY6YZcAVk1MAp98t77pCQQ62P5Upt', '2026-03-05 04:17:44', '2026-03-05 04:17:44'),
(89, NULL, 'Yk2ZPiM5ZjOsnG4wfknRpJxaxJ8oFvYwPFXL5UjX', '2026-03-05 07:30:00', '2026-03-05 07:30:00'),
(90, NULL, 'iowTh2YJOEjp9GcNKSyjDrnWzft6oAcF9WTe3N8R', '2026-03-05 07:32:31', '2026-03-05 07:32:31'),
(91, NULL, '8UtqFPaeukLH6IhLrLzep0sG6gMxkIkIrSiMItNS', '2026-03-05 11:52:27', '2026-03-05 11:52:27'),
(92, NULL, 'jGMOC41QnTXjA76Jr0inwbPy4aaVnBdiAxoHXFlG', '2026-03-05 11:53:07', '2026-03-05 11:53:07'),
(93, NULL, 'VDmQ22bQTHHh6f6ShMJEOoA9OQzppSJJVIGXt22U', '2026-03-05 11:55:04', '2026-03-05 11:55:04'),
(94, NULL, 'LpPq9iQ4EwRVqFKIPxD4v62y4bupr5OOVaAuKhM9', '2026-03-05 11:55:06', '2026-03-05 11:55:06'),
(95, NULL, 'XIXr4zbrl70bBhkxT93S57oKbu9AdBZDdguPzn7X', '2026-03-05 11:59:56', '2026-03-05 11:59:56'),
(96, NULL, 'EMwN9F3Bm1z6oQ0jenxgVEf8qEXIxyZBZv5l6HNt', '2026-03-05 12:10:27', '2026-03-05 12:10:27'),
(97, NULL, 'ae61aaa5-0a6b-4beb-8c59-03f14f6a4285', '2026-03-05 15:10:21', '2026-03-05 15:10:21');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint UNSIGNED NOT NULL,
  `cart_id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(15,0) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `quantity`, `price`, `created_at`, `updated_at`) VALUES
(1, 93, 6, 1, 5290000, '2026-03-05 11:55:04', '2026-03-05 11:55:04'),
(2, 97, 39, 1, 6990000, '2026-03-05 15:11:37', '2026-03-05 15:11:37');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint UNSIGNED NOT NULL,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `component_type_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `component_type_id`, `name`, `slug`, `description`, `image`, `icon`, `sort_order`, `is_active`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(1, NULL, NULL, 'PC - Máy tính để bàn', 'pc-may-tinh-de-ban', 'Máy tính để bàn các loại', NULL, 'http://localhost:8901/storage/icons/pc-may-tinh-de-ban.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(2, NULL, NULL, 'Laptop', 'laptop', 'Laptop các loại', NULL, 'http://localhost:8901/storage/icons/laptop.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(3, NULL, NULL, 'Linh kiện PC', 'linh-kien-pc', 'Linh kiện máy tính để bàn', NULL, 'http://localhost:8901/storage/icons/linh-kien-pc.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(4, NULL, NULL, 'Phụ kiện', 'phu-kien', 'Phụ kiện máy tính', NULL, 'http://localhost:8901/storage/icons/phu-kien.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(5, 1, NULL, 'PC Gaming', 'pc-gaming', NULL, NULL, 'http://localhost:8901/storage/icons/pc-gaming.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(6, 1, NULL, 'PC Văn phòng', 'pc-van-phong', NULL, NULL, 'http://localhost:8901/storage/icons/pc-van-phong.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(7, 1, NULL, 'PC Đồ họa - Render', 'pc-do-hoa-render', NULL, NULL, 'http://localhost:8901/storage/icons/pc-do-hoa-render.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(8, 2, NULL, 'Laptop Gaming', 'laptop-gaming', NULL, NULL, 'http://localhost:8901/storage/icons/laptop-gaming.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(9, 2, NULL, 'Laptop Văn phòng', 'laptop-van-phong', NULL, NULL, 'http://localhost:8901/storage/icons/laptop-van-phong.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(10, 2, NULL, 'Laptop Đồ họa', 'laptop-do-hoa', NULL, NULL, 'http://localhost:8901/storage/icons/laptop-do-hoa.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(11, 3, 1, 'CPU', 'cpu', NULL, NULL, 'http://localhost:8901/storage/icons/cpu.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(12, 3, 2, 'Mainboard', 'mainboard', NULL, NULL, 'http://localhost:8901/storage/icons/mainboard.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(13, 3, 3, 'RAM', 'ram', NULL, NULL, 'http://localhost:8901/storage/icons/ram.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(14, 3, 4, 'VGA (Card đồ họa)', 'vga', NULL, NULL, 'http://localhost:8901/storage/icons/vga.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(15, 3, 5, 'SSD', 'ssd', NULL, NULL, 'http://localhost:8901/storage/icons/ssd.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(16, 3, 6, 'HDD', 'hdd', NULL, NULL, 'http://localhost:8901/storage/icons/hdd.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(17, 3, 7, 'Nguồn (PSU)', 'psu', NULL, NULL, 'http://localhost:8901/storage/icons/psu.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(18, 3, 8, 'Vỏ case', 'case', NULL, NULL, 'http://localhost:8901/storage/icons/case.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:18'),
(19, 3, 9, 'Tản nhiệt CPU', 'cooler', NULL, NULL, 'http://localhost:8901/storage/icons/cooler.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(20, 3, 10, 'Quạt case', 'fan', NULL, NULL, 'http://localhost:8901/storage/icons/fan.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(21, 4, NULL, 'Màn hình', 'man-hinh', NULL, NULL, 'http://localhost:8901/storage/icons/man-hinh.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(22, 4, NULL, 'Bàn phím', 'ban-phim', NULL, NULL, 'http://localhost:8901/storage/icons/ban-phim.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(23, 4, NULL, 'Chuột', 'chuot', NULL, NULL, 'http://localhost:8901/storage/icons/chuot.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(24, 4, NULL, 'Tai nghe', 'tai-nghe', NULL, NULL, 'http://localhost:8901/storage/icons/tai-nghe.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(25, 4, NULL, 'Loa', 'loa', NULL, NULL, 'http://localhost:8901/storage/icons/loa.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(26, 4, NULL, 'Webcam', 'webcam', NULL, NULL, 'http://localhost:8901/storage/icons/webcam.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(27, 4, NULL, 'Bàn Gaming', 'ban-gaming', NULL, NULL, 'http://localhost:8901/storage/icons/ban-gaming.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19'),
(28, 4, NULL, 'Ghế Gaming', 'ghe-gaming', NULL, NULL, 'http://localhost:8901/storage/icons/ghe-gaming.svg', 0, 1, NULL, NULL, '2026-02-20 01:54:28', '2026-03-05 03:08:19');

-- --------------------------------------------------------

--
-- Table structure for table `compatibility_rules`
--

CREATE TABLE `compatibility_rules` (
  `id` bigint UNSIGNED NOT NULL,
  `source_type_id` bigint UNSIGNED NOT NULL,
  `target_type_id` bigint UNSIGNED NOT NULL,
  `source_spec_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_spec_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule_type` enum('must_match','must_fit','must_fit_dimension','must_contain','power_check') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'must_match',
  `allowed_values` json DEFAULT NULL,
  `power_headroom` int DEFAULT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compatibility_rules`
--

INSERT INTO `compatibility_rules` (`id`, `source_type_id`, `target_type_id`, `source_spec_key`, `target_spec_key`, `rule_type`, `allowed_values`, `power_headroom`, `message`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'socket', 'socket', 'must_match', NULL, NULL, 'Socket CPU phải khớp với socket mainboard', 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(2, 1, 2, 'memory_type', 'memory_type', 'must_match', NULL, NULL, 'Loại RAM hỗ trợ của CPU phải khớp với mainboard', 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(3, 2, 3, 'memory_type', 'memory_type', 'must_match', NULL, NULL, 'Loại RAM phải tương thích với mainboard', 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(4, 2, 8, 'form_factor', 'form_factor', 'must_fit', '{\"ATX\": [\"ATX\", \"E-ATX\"], \"ITX\": [\"ITX\", \"Micro-ATX\", \"ATX\", \"E-ATX\"], \"E-ATX\": [\"E-ATX\"], \"Micro-ATX\": [\"Micro-ATX\", \"ATX\", \"E-ATX\"]}', NULL, 'Kích thước mainboard phải vừa với case', 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(5, 4, 8, 'length', 'max_gpu_length', 'must_fit_dimension', NULL, NULL, 'Chiều dài VGA phải nhỏ hơn chiều dài tối đa của case', 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(6, 9, 8, 'height', 'max_cooler_height', 'must_fit_dimension', NULL, NULL, 'Chiều cao tản nhiệt phải nhỏ hơn chiều cao tối đa của case', 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(7, 9, 1, 'socket_support', 'socket', 'must_contain', NULL, NULL, 'Tản nhiệt phải hỗ trợ socket CPU', 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(8, 4, 7, 'tdp', 'wattage', 'power_check', NULL, 150, 'Tổng TDP linh kiện phải nhỏ hơn công suất PSU', 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27');

-- --------------------------------------------------------

--
-- Table structure for table `component_supported_values`
--

CREATE TABLE `component_supported_values` (
  `id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `specification_key_id` bigint UNSIGNED NOT NULL,
  `supported_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `component_types`
--

CREATE TABLE `component_types` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `component_types`
--

INSERT INTO `component_types` (`id`, `name`, `slug`, `display_order`, `is_required`, `created_at`, `updated_at`) VALUES
(1, 'CPU', 'cpu', 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(2, 'Mainboard', 'mainboard', 2, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(3, 'RAM', 'ram', 3, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(4, 'VGA (Card đồ họa)', 'vga', 4, 0, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(5, 'SSD', 'ssd', 5, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(6, 'HDD', 'hdd', 6, 0, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(7, 'Nguồn (PSU)', 'psu', 7, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(8, 'Vỏ case', 'case', 8, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(9, 'Tản nhiệt CPU', 'cooler', 9, 0, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(10, 'Quạt case', 'fan', 10, 0, '2026-02-20 01:54:27', '2026-02-20 01:54:27');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `value` decimal(15,2) NOT NULL,
  `min_order_amount` decimal(15,0) DEFAULT NULL,
  `max_uses` int DEFAULT NULL,
  `used_count` int NOT NULL DEFAULT '0',
  `starts_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE `media` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint UNSIGNED NOT NULL,
  `width` int UNSIGNED DEFAULT NULL,
  `height` int UNSIGNED DEFAULT NULL,
  `alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/',
  `uploaded_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `media`
--

INSERT INTO `media` (`id`, `name`, `file_name`, `path`, `disk`, `mime_type`, `size`, `width`, `height`, `alt`, `folder`, `uploaded_by`, `created_at`, `updated_at`) VALUES
(1, 'shark', 'shark.jpg', 'media/shark-RaTikH.jpg', 'public', 'image/jpeg', 190679, 900, 900, 'shark', '/', NULL, '2026-02-21 17:12:02', '2026-02-21 17:12:02'),
(2, 'cuong1', 'cuong1.jpg', 'media/cuong1-45fZPC.jpg', 'public', 'image/jpeg', 302587, 1024, 1024, 'cuong1', '/', NULL, '2026-02-21 17:12:18', '2026-02-21 17:12:18'),
(3, 'shark', 'shark.jpg', 'media/shark-hRIOsA.jpg', 'public', 'image/jpeg', 190679, 900, 900, 'shark', '/', NULL, '2026-02-21 17:13:46', '2026-02-21 17:13:46'),
(4, 'Dell_logo.svg', 'Dell_logo.svg.png', 'media/dell-logosvg-69ONCo.png', 'public', 'image/png', 10013, 1280, 407, 'Dell_logo.svg', '/', NULL, '2026-02-26 07:57:29', '2026-02-26 07:57:29'),
(5, '2782b0b66efa5815b12c9c637322aff3-desktop-computer-icon-computer', '2782b0b66efa5815b12c9c637322aff3-desktop-computer-icon-computer.webp', 'media/2782b0b66efa5815b12c9c637322aff3-desktop-computer-icon-computer-hrIvNk.webp', 'public', 'image/webp', 16336, 512, 512, '2782b0b66efa5815b12c9c637322aff3-desktop-computer-icon-computer', '/', NULL, '2026-02-26 09:34:36', '2026-02-26 09:34:36');

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `name`, `slug`, `location`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Menu Chính', 'main-menu', 'header', 'Menu hiển thị trên header trang web', 1, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(2, 'Footer Menu', 'footer-menu', 'footer', 'Menu hiển thị ở chân trang', 1, '2026-02-20 15:36:42', '2026-02-20 15:36:42');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` bigint UNSIGNED NOT NULL,
  `menu_id` bigint UNSIGNED NOT NULL,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_color` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `css_class` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '_self',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_mega` tinyint(1) NOT NULL DEFAULT '0',
  `mega_columns` tinyint UNSIGNED NOT NULL DEFAULT '4',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `menu_id`, `parent_id`, `title`, `url`, `type`, `category_id`, `icon`, `badge_text`, `badge_color`, `css_class`, `target`, `sort_order`, `is_active`, `is_mega`, `mega_columns`, `description`, `image`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Trang chủ', '/', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(2, 1, NULL, 'PC', '/categories/pc-may-tinh-de-ban', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 1, 3, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(3, 1, 2, 'PC Gaming', '/categories/pc-gaming', 'custom', NULL, NULL, 'HOT', 'red', NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(4, 1, 3, 'PC Gaming Starter', '/products?category=pc-gaming&max_price=20000000', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, 'Từ 16 triệu', NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(5, 1, 3, 'PC Gaming Pro', '/products?category=pc-gaming&min_price=20000000&max_price=50000000', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, 'Từ 20 triệu', NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(6, 1, 3, 'PC Gaming Ultimate', '/products?category=pc-gaming&min_price=50000000', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, 'Từ 50 triệu', NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(7, 1, 2, 'PC Văn phòng', '/categories/pc-van-phong', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(8, 1, 7, 'PC Văn phòng Basic', '/products?category=pc-van-phong&max_price=10000000', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(9, 1, 7, 'PC Văn phòng Pro', '/products?category=pc-van-phong&min_price=10000000', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(10, 1, 2, 'PC Đồ họa - Render', '/categories/pc-do-hoa-render', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(11, 1, NULL, 'Laptop', '/categories/laptop', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 1, 3, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(12, 1, 11, 'Laptop Gaming', '/categories/laptop-gaming', 'custom', NULL, NULL, 'HOT', 'red', NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(13, 1, 12, 'ASUS ROG', '/products?category=laptop-gaming&brand=asus', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(14, 1, 12, 'MSI Gaming', '/products?category=laptop-gaming&brand=msi', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(15, 1, 12, 'Lenovo Legion', '/products?category=laptop-gaming&brand=lenovo', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(16, 1, 12, 'Acer Predator', '/products?category=laptop-gaming&brand=acer', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(17, 1, 11, 'Laptop Văn phòng', '/categories/laptop-van-phong', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(18, 1, 17, 'Lenovo ThinkPad', '/products?category=laptop-van-phong&brand=lenovo', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(19, 1, 17, 'HP EliteBook', '/products?category=laptop-van-phong&brand=hp', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(20, 1, 17, 'Dell Latitude', '/products?category=laptop-van-phong&brand=dell', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(21, 1, 11, 'Laptop Đồ họa', '/categories/laptop-do-hoa', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(22, 1, NULL, 'Linh kiện', '/categories/linh-kien-pc', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 1, 5, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(23, 1, 22, 'CPU - Bộ xử lý', '/categories/cpu', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(24, 1, 23, 'Intel Core i9', '/products?category=cpu&brand=intel&search=i9', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(25, 1, 23, 'Intel Core i7', '/products?category=cpu&brand=intel&search=i7', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(26, 1, 23, 'Intel Core i5', '/products?category=cpu&brand=intel&search=i5', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(27, 1, 23, 'AMD Ryzen 9', '/products?category=cpu&brand=amd&search=ryzen+9', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(28, 1, 23, 'AMD Ryzen 7', '/products?category=cpu&brand=amd&search=ryzen+7', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 4, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(29, 1, 23, 'AMD Ryzen 5', '/products?category=cpu&brand=amd&search=ryzen+5', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 5, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(30, 1, 22, 'VGA - Card đồ họa', '/categories/vga', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(31, 1, 30, 'NVIDIA RTX 4090', '/products?category=vga&search=4090', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(32, 1, 30, 'NVIDIA RTX 4080', '/products?category=vga&search=4080', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(33, 1, 30, 'NVIDIA RTX 4070', '/products?category=vga&search=4070', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(34, 1, 30, 'NVIDIA RTX 4060', '/products?category=vga&search=4060', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(35, 1, 30, 'AMD Radeon RX 7900', '/products?category=vga&search=7900', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 4, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(36, 1, 22, 'Mainboard', '/categories/mainboard', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(37, 1, 36, 'Intel LGA 1700', '/products?category=mainboard&search=LGA+1700', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(38, 1, 36, 'AMD AM5', '/products?category=mainboard&search=AM5', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(39, 1, 36, 'ASUS ROG / TUF', '/products?category=mainboard&brand=asus', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(40, 1, 36, 'MSI MAG / MEG', '/products?category=mainboard&brand=msi', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(41, 1, 36, 'Gigabyte AORUS', '/products?category=mainboard&brand=gigabyte', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 4, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(42, 1, 22, 'Lưu trữ', '/categories/ram', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(43, 1, 42, 'RAM DDR5', '/categories/ram', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(44, 1, 42, 'SSD NVMe M.2', '/categories/ssd', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(45, 1, 42, 'HDD', '/categories/hdd', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(46, 1, 42, 'Samsung', '/products?category=ssd&brand=samsung', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(47, 1, 42, 'Kingston', '/products?category=ram&brand=kingston', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 4, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(48, 1, 22, 'Nguồn & Tản nhiệt', '/categories/psu', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 4, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(49, 1, 48, 'Nguồn (PSU)', '/categories/psu', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(50, 1, 48, 'Vỏ case', '/categories/case', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(51, 1, 48, 'Tản nhiệt CPU', '/categories/cooler', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(52, 1, 48, 'Quạt case', '/categories/fan', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(53, 1, NULL, 'Phụ kiện', '/categories/phu-kien', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 4, 1, 1, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(54, 1, 53, 'Màn hình', '/categories/man-hinh', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(55, 1, 54, 'Màn hình Gaming', '/products?category=man-hinh&search=gaming', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:41', '2026-02-20 15:36:41'),
(56, 1, 54, 'Màn hình 4K', '/products?category=man-hinh&search=4k', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(57, 1, 54, 'LG', '/products?category=man-hinh&brand=lg', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(58, 1, 54, 'Samsung', '/products?category=man-hinh&brand=samsung', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(59, 1, 54, 'Dell', '/products?category=man-hinh&brand=dell', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 4, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(60, 1, 53, 'Bàn phím', '/categories/ban-phim', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(61, 1, 60, 'Cơ / Mechanical', '/products?category=ban-phim&search=mechanical', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(62, 1, 60, 'Logitech', '/products?category=ban-phim&brand=logitech', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(63, 1, 60, 'Razer', '/products?category=ban-phim&brand=razer', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(64, 1, 53, 'Chuột', '/categories/chuot', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(65, 1, 64, 'Chuột Wireless', '/products?category=chuot&search=wireless', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(66, 1, 64, 'Logitech', '/products?category=chuot&brand=logitech', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(67, 1, 64, 'Razer', '/products?category=chuot&brand=razer', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(68, 1, 53, 'Tai nghe & Loa', '/categories/tai-nghe', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(69, 1, 68, 'Tai nghe Gaming', '/products?category=tai-nghe&search=gaming', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(70, 1, 68, 'Loa', '/categories/loa', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(71, 1, 68, 'SteelSeries', '/products?category=tai-nghe&brand=steelseries', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(72, 1, 68, 'HyperX', '/products?category=tai-nghe&brand=hyperx', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(73, 1, NULL, 'Build PC', '/configurator', 'custom', NULL, NULL, 'New', 'blue', NULL, '_self', 5, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(74, 1, NULL, 'Tin tức', '/blog', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 6, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(75, 2, NULL, 'Giới thiệu', '/about', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 0, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(76, 2, NULL, 'Liên hệ', '/contact', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 1, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(77, 2, NULL, 'Bảo hành', '/warranty', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 2, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(78, 2, NULL, 'Vận chuyển', '/shipping', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 3, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42'),
(79, 2, NULL, 'Chính sách đổi trả', '/returns', 'custom', NULL, NULL, NULL, NULL, NULL, '_self', 4, 1, 0, 4, NULL, NULL, '2026-02-20 15:36:42', '2026-02-20 15:36:42');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_02_20_010815_create_categories_table', 1),
(5, '2026_02_20_010828_create_brands_table', 1),
(6, '2026_02_20_010833_create_component_types_table', 1),
(7, '2026_02_20_010837_create_products_table', 1),
(8, '2026_02_20_010842_create_product_images_table', 1),
(9, '2026_02_20_010846_create_specification_keys_table', 1),
(10, '2026_02_20_010850_create_product_specifications_table', 1),
(11, '2026_02_20_010855_create_compatibility_rules_table', 1),
(12, '2026_02_20_010859_create_component_supported_values_table', 1),
(13, '2026_02_20_010904_create_power_requirements_table', 1),
(14, '2026_02_20_010917_create_addresses_table', 1),
(15, '2026_02_20_010922_create_carts_table', 1),
(16, '2026_02_20_010926_create_cart_items_table', 1),
(17, '2026_02_20_010931_create_orders_table', 1),
(18, '2026_02_20_010936_create_order_items_table', 1),
(19, '2026_02_20_010940_create_transactions_table', 1),
(20, '2026_02_20_010945_create_coupons_table', 1),
(21, '2026_02_20_010949_create_reviews_table', 1),
(22, '2026_02_20_010954_create_posts_table', 1),
(23, '2026_02_20_010959_create_banners_table', 1),
(24, '2026_02_20_011003_create_pages_table', 1),
(25, '2026_02_20_011008_create_saved_builds_table', 1),
(26, '2026_02_20_011244_add_phone_and_role_to_users_table', 1),
(27, '2026_02_20_012624_create_permission_tables', 1),
(28, '2026_02_20_012640_create_media_table', 1),
(29, '2026_02_20_030644_create_personal_access_tokens_table', 2),
(30, '2026_02_20_051904_create_menus_table', 2),
(31, '2026_02_20_051910_create_menu_items_table', 2),
(32, '2026_02_21_004022_recreate_media_table_simple', 3),
(33, '2026_02_21_010000_add_specifications_text_to_products_table', 4),
(34, '2026_02_26_000001_add_payment_fields_to_orders_table', 5),
(35, '2026_02_26_074349_create_post_categories_table', 5),
(36, '2026_02_26_074401_update_posts_table_for_blog_system', 5),
(37, '2026_02_26_100000_add_icon_to_categories_table', 6),
(38, '2026_02_21_000001_create_settings_table', 7),
(39, '2026_02_21_000002_add_metadata_to_banners_table', 7);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtotal` decimal(15,0) NOT NULL,
  `discount` decimal(15,0) NOT NULL DEFAULT '0',
  `shipping_fee` decimal(15,0) NOT NULL DEFAULT '0',
  `total` decimal(15,0) NOT NULL,
  `payment_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `order_status` enum('pending','confirmed','processing','shipping','delivered','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `shipping_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_district` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_ward` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sepay',
  `paid_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED DEFAULT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `price` decimal(15,0) NOT NULL,
  `total` decimal(15,0) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `post_category_id` bigint UNSIGNED DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `view_count` bigint UNSIGNED NOT NULL DEFAULT '0',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `featured_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `post_category_id`, `title`, `slug`, `excerpt`, `body`, `status`, `view_count`, `is_featured`, `featured_image`, `published_at`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'Top 5 cấu hình PC Gaming tầm trung 2026', 'top-5-cau-hinh-pc-gaming-tam-trung-2026', 'Khám phá 5 cấu hình PC Gaming tầm trung giá từ 15-25 triệu đồng, đáp ứng mọi tựa game hot hiện nay.', '<h2>Giới thiệu</h2><p>Trong năm 2026, nhu cầu về PC Gaming ngày càng cao. Dưới đây là 5 cấu hình tối ưu cho game thủ tầm trung...</p><h3>Cấu hình 1: Intel Core i5 + RTX 4060</h3><p>Cấu hình này phù hợp cho các tựa game esports và AAA ở mức 1080p...</p>', 'published', 2738, 1, 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=1200&h=600&fit=crop', '2026-02-10 07:49:00', NULL, NULL, '2026-02-26 07:49:31', '2026-03-05 03:08:23'),
(2, 1, 3, 'RTX 5080 chính thức ra mắt - Đánh giá hiệu năng', 'rtx-5080-chinh-thuc-ra-mat-danh-gia-hieu-nang', 'NVIDIA vừa chính thức công bố RTX 5080 với hiệu năng vượt trội. Cùng xem đánh giá chi tiết.', '<h2>Thông số kỹ thuật</h2><p>RTX 5080 sở hữu 10240 CUDA Cores, 16GB GDDR6X...</p>', 'published', 3834, 1, 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=1200&h=600&fit=crop', '2026-02-07 07:49:31', NULL, NULL, '2026-02-26 07:49:31', '2026-03-05 03:08:23'),
(3, 1, 5, 'Hướng dẫn tối ưu hiệu năng Windows 11 cho Gaming', 'huong-dan-toi-uu-hieu-nang-windows-11-cho-gaming', 'Những thủ thuật đơn giản giúp tăng FPS và giảm độ trễ khi chơi game trên Windows 11.', '<h2>Tắt các hiệu ứng không cần thiết</h2><p>Windows 11 có nhiều hiệu ứng đồ họa tốn tài nguyên...</p>', 'published', 2730, 0, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=1200&h=600&fit=crop', '2026-02-08 07:49:31', NULL, NULL, '2026-02-26 07:49:31', '2026-03-05 03:08:23'),
(4, 1, 3, 'AMD Ryzen 9 7950X3D vs Intel Core i9-14900K', 'amd-ryzen-9-7950x3d-vs-intel-core-i9-14900k', 'So sánh chi tiết hai con chip hàng đầu từ AMD và Intel trong năm 2026.', '<h2>Hiệu năng Gaming</h2><p>Ryzen 9 7950X3D với công nghệ 3D V-Cache cho hiệu năng gaming vượt trội...</p>', 'published', 3219, 1, 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=1200&h=600&fit=crop', '2026-02-17 07:49:31', NULL, NULL, '2026-02-26 07:49:31', '2026-03-05 03:08:23'),
(5, 1, 1, 'Tin tức: DDR5 giảm giá mạnh trong Q1/2026', 'tin-tuc-ddr5-giam-gia-manh-trong-q12026', 'Giá RAM DDR5 đã giảm gần 40% so với đầu năm, đây là thời điểm tốt để nâng cấp PC.', '<h2>Xu hướng giá</h2><p>Theo báo cáo từ các nhà sản xuất, giá RAM DDR5 đang có xu hướng giảm...</p>', 'published', 3645, 0, 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=1200&h=600&fit=crop', '2026-02-03 07:49:31', NULL, NULL, '2026-02-26 07:49:31', '2026-03-05 03:08:23'),
(6, 1, 4, '10 Game AAA đáng chơi nhất năm 2026', '10-game-aaa-dang-choi-nhat-nam-2026', 'Danh sách các tựa game AAA được đánh giá cao nhất trong năm nay.', '<h2>Top 1: Cyberpunk 2078</h2><p>Phần tiếp theo của Cyberpunk 2077 với đồ họa tuyệt đẹp...</p>', 'published', 484, 1, 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=1200&h=600&fit=crop', '2026-02-19 07:49:31', NULL, NULL, '2026-02-26 07:49:31', '2026-03-05 03:08:23'),
(7, 1, 2, 'Cách chọn nguồn máy tính phù hợp', 'cach-chon-nguon-may-tinh-phu-hop', 'Hướng dẫn chi tiết cách tính công suất và chọn nguồn máy tính cho PC.', '<h2>Tính toán công suất</h2><p>Để chọn nguồn phù hợp, bạn cần tính tổng TDP của CPU + GPU...</p>', 'published', 289, 0, 'https://images.unsplash.com/photo-1600348712270-5af9e3590f67?w=1200&h=600&fit=crop', '2026-02-13 07:49:31', NULL, NULL, '2026-02-26 07:49:31', '2026-03-05 03:08:23'),
(8, 1, 3, 'SSD NVMe Gen 5 - Có đáng để nâng cấp?', 'ssd-nvme-gen-5-co-dang-de-nang-cap', 'Phân tích hiệu năng thực tế của SSD NVMe Gen 5 so với Gen 4.', '<h2>Tốc độ đọc/ghi</h2><p>Gen 5 có tốc độ lý thuyết lên đến 14000 MB/s nhưng trong thực tế...</p>', 'published', 4552, 0, 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?w=1200&h=600&fit=crop', '2026-02-10 07:49:31', NULL, NULL, '2026-02-26 07:49:31', '2026-03-05 03:08:23');

-- --------------------------------------------------------

--
-- Table structure for table `post_categories`
--

CREATE TABLE `post_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `post_categories`
--

INSERT INTO `post_categories` (`id`, `name`, `slug`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Tin tức công nghệ', 'tin-tuc-cong-nghe', 'Tin tức mới nhất về công nghệ, phần cứng máy tính', 1, '2026-02-26 07:49:22', '2026-02-26 07:49:22'),
(2, 'Hướng dẫn Build PC', 'huong-dan-build-pc', 'Hướng dẫn xây dựng cấu hình máy tính', 2, '2026-02-26 07:49:22', '2026-02-26 07:49:22'),
(3, 'Review sản phẩm', 'review-san-pham', 'Đánh giá chi tiết các sản phẩm phần cứng', 3, '2026-02-26 07:49:22', '2026-02-26 07:49:22'),
(4, 'Gaming', 'gaming', 'Tin tức và review về PC Gaming', 4, '2026-02-26 07:49:22', '2026-02-26 07:49:22'),
(5, 'Tips & Tricks', 'tips-tricks', 'Mẹo vặt và kinh nghiệm sử dụng PC', 5, '2026-02-26 07:49:22', '2026-02-26 07:49:22');

-- --------------------------------------------------------

--
-- Table structure for table `power_requirements`
--

CREATE TABLE `power_requirements` (
  `product_id` bigint UNSIGNED NOT NULL,
  `typical_tdp` int DEFAULT NULL COMMENT 'watts',
  `peak_tdp` int DEFAULT NULL COMMENT 'watts',
  `requires_pcie_power` tinyint(1) NOT NULL DEFAULT '0',
  `pcie_connectors_needed` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint UNSIGNED NOT NULL,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `brand_id` bigint UNSIGNED DEFAULT NULL,
  `component_type_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `specifications_text` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(15,0) NOT NULL,
  `sale_price` decimal(15,0) DEFAULT NULL,
  `cost_price` decimal(15,0) DEFAULT NULL,
  `stock_quantity` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `weight` int DEFAULT NULL COMMENT 'grams',
  `warranty_months` int NOT NULL DEFAULT '12',
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `views_count` int UNSIGNED NOT NULL DEFAULT '0',
  `sold_count` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `brand_id`, `component_type_id`, `name`, `slug`, `sku`, `short_description`, `description`, `specifications_text`, `price`, `sale_price`, `cost_price`, `stock_quantity`, `is_active`, `is_featured`, `weight`, `warranty_months`, `meta_title`, `meta_description`, `views_count`, `sold_count`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 11, 1, 1, 'Intel Core i9-14900K', 'intel-core-i9-14900k', 'SP00001', '24 nhân 32 luồng, 6.0GHz Turbo, 36MB Cache, LGA 1700', '<p>24 nhân 32 luồng, 6.0GHz Turbo, 36MB Cache, LGA 1700</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 15990000, 14490000, 11992500, 15, 1, 1, NULL, 36, NULL, NULL, 1331, 86, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(2, 11, 1, 1, 'Intel Core i7-14700K', 'intel-core-i7-14700k', 'SP00002', '20 nhân 28 luồng, 5.6GHz Turbo, 33MB Cache, LGA 1700', '<p>20 nhân 28 luồng, 5.6GHz Turbo, 33MB Cache, LGA 1700</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 10990000, 9990000, 8242500, 20, 1, 1, NULL, 36, NULL, NULL, 917, 77, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(3, 11, 1, 1, 'Intel Core i5-14600K', 'intel-core-i5-14600k', 'SP00003', '14 nhân 20 luồng, 5.3GHz Turbo, 24MB Cache, LGA 1700', '<p>14 nhân 20 luồng, 5.3GHz Turbo, 24MB Cache, LGA 1700</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 7490000, 6890000, 5617500, 30, 1, 0, NULL, 36, NULL, NULL, 176, 18, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(4, 11, 2, 1, 'AMD Ryzen 9 7950X3D', 'amd-ryzen-9-7950x3d', 'SP00004', '16 nhân 32 luồng, 5.7GHz Boost, 128MB 3D V-Cache, AM5', '<p>16 nhân 32 luồng, 5.7GHz Boost, 128MB 3D V-Cache, AM5</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 16990000, NULL, 12742500, 8, 1, 1, NULL, 36, NULL, NULL, 266, 94, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(5, 11, 2, 1, 'AMD Ryzen 7 7800X3D', 'amd-ryzen-7-7800x3d', 'SP00005', '8 nhân 16 luồng, 5.0GHz Boost, 96MB 3D V-Cache, AM5', '<p>8 nhân 16 luồng, 5.0GHz Boost, 96MB 3D V-Cache, AM5</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 10490000, 9490000, 7867500, 25, 1, 1, NULL, 36, NULL, NULL, 1412, 26, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(6, 11, 2, 1, 'AMD Ryzen 5 7600X', 'amd-ryzen-5-7600x', 'SP00006', '6 nhân 12 luồng, 5.3GHz Boost, 32MB Cache, AM5', '<p>6 nhân 12 luồng, 5.3GHz Boost, 32MB Cache, AM5</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 5990000, 5290000, 4492500, 35, 1, 0, NULL, 36, NULL, NULL, 828, 34, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(7, 14, 4, 4, 'ASUS ROG Strix RTX 4090 OC 24GB', 'asus-rog-strix-rtx-4090-oc-24gb', 'SP00007', 'GDDR6X 24GB, 384-bit, 2640MHz Boost, 3.5 slot', '<p>GDDR6X 24GB, 384-bit, 2640MHz Boost, 3.5 slot</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 52990000, 49990000, 39742500, 5, 1, 1, NULL, 36, NULL, NULL, 1044, 68, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(8, 14, 5, 4, 'MSI GeForce RTX 4080 SUPER Gaming X Trio 16GB', 'msi-geforce-rtx-4080-super-gaming-x-trio-16gb', 'SP00008', 'GDDR6X 16GB, 256-bit, 2610MHz Boost', '<p>GDDR6X 16GB, 256-bit, 2610MHz Boost</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 32990000, 30990000, 24742500, 10, 1, 1, NULL, 36, NULL, NULL, 712, 93, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(9, 14, 6, 4, 'Gigabyte RTX 4070 Ti SUPER Eagle OC 16GB', 'gigabyte-rtx-4070-ti-super-eagle-oc-16gb', 'SP00009', 'GDDR6X 16GB, 256-bit, 2640MHz Boost', '<p>GDDR6X 16GB, 256-bit, 2640MHz Boost</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 22990000, 21490000, 17242500, 12, 1, 1, NULL, 36, NULL, NULL, 1540, 156, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(10, 14, 5, 4, 'MSI GeForce RTX 4070 SUPER Ventus 2X 12GB', 'msi-geforce-rtx-4070-super-ventus-2x-12gb', 'SP00010', 'GDDR6X 12GB, 192-bit, 2510MHz Boost', '<p>GDDR6X 12GB, 192-bit, 2510MHz Boost</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 16990000, 15490000, 12742500, 18, 1, 0, NULL, 36, NULL, NULL, 1892, 141, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(11, 14, 4, 4, 'ASUS Dual RTX 4060 Ti OC 8GB', 'asus-dual-rtx-4060-ti-oc-8gb', 'SP00011', 'GDDR6 8GB, 128-bit, 2580MHz Boost', '<p>GDDR6 8GB, 128-bit, 2580MHz Boost</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 11990000, 10990000, 8992500, 22, 1, 0, NULL, 36, NULL, NULL, 1822, 149, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(12, 14, 6, 4, 'Gigabyte RTX 4060 Eagle OC 8GB', 'gigabyte-rtx-4060-eagle-oc-8gb', 'SP00012', 'GDDR6 8GB, 128-bit, 2475MHz Boost', '<p>GDDR6 8GB, 128-bit, 2475MHz Boost</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 8490000, 7990000, 6367500, 30, 1, 0, NULL, 36, NULL, NULL, 770, 187, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(13, 14, 2, 4, 'AMD Radeon RX 7900 XTX 24GB', 'amd-radeon-rx-7900-xtx-24gb', 'SP00013', 'GDDR6 24GB, 384-bit, 2500MHz Boost', '<p>GDDR6 24GB, 384-bit, 2500MHz Boost</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 28990000, 26990000, 21742500, 7, 1, 1, NULL, 36, NULL, NULL, 1845, 29, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(14, 12, 4, 2, 'ASUS ROG Maximus Z790 Hero', 'asus-rog-maximus-z790-hero', 'SP00014', 'LGA 1700, DDR5, Wi-Fi 6E, Thunderbolt 4, E-ATX', '<p>LGA 1700, DDR5, Wi-Fi 6E, Thunderbolt 4, E-ATX</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 16990000, NULL, 12742500, 8, 1, 1, NULL, 36, NULL, NULL, 470, 46, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(15, 12, 5, 2, 'MSI MAG B760 Tomahawk WiFi', 'msi-mag-b760-tomahawk-wifi', 'SP00015', 'LGA 1700, DDR5, Wi-Fi 6E, 2.5G LAN, ATX', '<p>LGA 1700, DDR5, Wi-Fi 6E, 2.5G LAN, ATX</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 5490000, 4990000, 4117500, 25, 1, 0, NULL, 36, NULL, NULL, 936, 142, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(16, 12, 6, 2, 'Gigabyte B650 AORUS Elite AX V2', 'gigabyte-b650-aorus-elite-ax-v2', 'SP00016', 'AM5, DDR5, Wi-Fi 6E, 2.5G LAN, ATX', '<p>AM5, DDR5, Wi-Fi 6E, 2.5G LAN, ATX</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 5990000, 5490000, 4492500, 20, 1, 0, NULL, 36, NULL, NULL, 1236, 46, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(17, 12, 4, 2, 'ASUS ROG Strix X670E-E Gaming WiFi', 'asus-rog-strix-x670e-e-gaming-wifi', 'SP00017', 'AM5, DDR5, Wi-Fi 6E, PCIe 5.0, ATX', '<p>AM5, DDR5, Wi-Fi 6E, PCIe 5.0, ATX</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 12990000, 11990000, 9742500, 10, 1, 1, NULL, 36, NULL, NULL, 856, 189, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(18, 13, 12, 3, 'G.Skill Trident Z5 RGB 32GB (2x16GB) DDR5-6000', 'gskill-trident-z5-rgb-32gb-2x16gb-ddr5-6000', 'SP00018', 'DDR5, 6000MHz, CL36, Dual Channel, RGB', '<p>DDR5, 6000MHz, CL36, Dual Channel, RGB</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 4590000, 3990000, 3442500, 30, 1, 1, NULL, 60, NULL, NULL, 551, 39, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(19, 13, 14, 3, 'Corsair Vengeance 32GB (2x16GB) DDR5-5600', 'corsair-vengeance-32gb-2x16gb-ddr5-5600', 'SP00019', 'DDR5, 5600MHz, CL36, Dual Channel', '<p>DDR5, 5600MHz, CL36, Dual Channel</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 3290000, 2990000, 2467500, 40, 1, 0, NULL, 60, NULL, NULL, 1990, 103, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(20, 13, 13, 3, 'Kingston Fury Beast 32GB (2x16GB) DDR5-5200', 'kingston-fury-beast-32gb-2x16gb-ddr5-5200', 'SP00020', 'DDR5, 5200MHz, CL40, Dual Channel', '<p>DDR5, 5200MHz, CL40, Dual Channel</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 2790000, NULL, 2092500, 50, 1, 0, NULL, 60, NULL, NULL, 1600, 167, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(21, 13, 12, 3, 'G.Skill Trident Z5 Neo 64GB (2x32GB) DDR5-6000', 'gskill-trident-z5-neo-64gb-2x32gb-ddr5-6000', 'SP00021', 'DDR5, 6000MHz, CL30, Tối ưu AMD EXPO', '<p>DDR5, 6000MHz, CL30, Tối ưu AMD EXPO</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 7990000, 7290000, 5992500, 15, 1, 1, NULL, 60, NULL, NULL, 95, 11, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(22, 15, 17, 5, 'Samsung 990 Pro 2TB NVMe M.2', 'samsung-990-pro-2tb-nvme-m2', 'SP00022', 'PCIe Gen 4, 7450/6900 MB/s, TLC NAND', '<p>PCIe Gen 4, 7450/6900 MB/s, TLC NAND</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 5490000, 4990000, 4117500, 25, 1, 1, NULL, 60, NULL, NULL, 650, 95, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(23, 15, 17, 5, 'Samsung 990 Pro 1TB NVMe M.2', 'samsung-990-pro-1tb-nvme-m2', 'SP00023', 'PCIe Gen 4, 7450/6900 MB/s, TLC NAND', '<p>PCIe Gen 4, 7450/6900 MB/s, TLC NAND</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 3290000, 2990000, 2467500, 40, 1, 0, NULL, 60, NULL, NULL, 1117, 113, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(24, 15, 18, 5, 'WD Black SN850X 2TB NVMe M.2', 'wd-black-sn850x-2tb-nvme-m2', 'SP00024', 'PCIe Gen 4, 7300/6600 MB/s, TLC NAND', '<p>PCIe Gen 4, 7300/6600 MB/s, TLC NAND</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 4990000, 4490000, 3742500, 20, 1, 0, NULL, 60, NULL, NULL, 1734, 54, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(25, 15, 16, 5, 'Crucial T700 2TB NVMe M.2', 'crucial-t700-2tb-nvme-m2', 'SP00025', 'PCIe Gen 5, 12400/11800 MB/s, TLC NAND', '<p>PCIe Gen 5, 12400/11800 MB/s, TLC NAND</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 7990000, NULL, 5992500, 10, 1, 1, NULL, 60, NULL, NULL, 1484, 99, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(26, 17, 22, 7, 'Seasonic Focus GX-850 850W 80+ Gold', 'seasonic-focus-gx-850-850w-80-gold', 'SP00026', 'Full Modular, 80+ Gold, 10 năm bảo hành', '<p>Full Modular, 80+ Gold, 10 năm bảo hành</p><p>Sản phẩm chính hãng, bảo hành 120 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 3290000, 2990000, 2467500, 20, 1, 0, NULL, 120, NULL, NULL, 1886, 148, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(27, 17, 14, 7, 'Corsair RM1000x 1000W 80+ Gold', 'corsair-rm1000x-1000w-80-gold', 'SP00027', 'Full Modular, 80+ Gold, ATX 3.0, PCIe 5.0', '<p>Full Modular, 80+ Gold, ATX 3.0, PCIe 5.0</p><p>Sản phẩm chính hãng, bảo hành 120 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 4890000, 4490000, 3667500, 15, 1, 1, NULL, 120, NULL, NULL, 1012, 85, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(28, 17, 22, 7, 'Seasonic Vertex GX-1200 1200W 80+ Gold', 'seasonic-vertex-gx-1200-1200w-80-gold', 'SP00028', 'Full Modular, 80+ Gold, ATX 3.0, PCIe 5.0', '<p>Full Modular, 80+ Gold, ATX 3.0, PCIe 5.0</p><p>Sản phẩm chính hãng, bảo hành 120 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 6490000, NULL, 4867500, 8, 1, 1, NULL, 120, NULL, NULL, 1196, 161, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(29, 18, 28, 8, 'Lian Li O11 Dynamic EVO', 'lian-li-o11-dynamic-evo', 'SP00029', 'Mid Tower, Tempered Glass, hỗ trợ E-ATX, USB-C', '<p>Mid Tower, Tempered Glass, hỗ trợ E-ATX, USB-C</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 4290000, 3990000, 3217500, 12, 1, 1, NULL, 24, NULL, NULL, 1590, 30, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(30, 18, 23, 8, 'NZXT H7 Flow', 'nzxt-h7-flow', 'SP00030', 'Mid Tower, Airflow tối ưu, TG, USB-C', '<p>Mid Tower, Airflow tối ưu, TG, USB-C</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 3290000, NULL, 2467500, 18, 1, 0, NULL, 24, NULL, NULL, 1276, 194, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(31, 18, 29, 8, 'Phanteks NV7 TG', 'phanteks-nv7-tg', 'SP00031', 'Full Tower, Dual Chamber, Tempered Glass', '<p>Full Tower, Dual Chamber, Tempered Glass</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 4990000, 4490000, 3742500, 10, 1, 0, NULL, 24, NULL, NULL, 855, 29, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(32, 19, 31, 9, 'Noctua NH-D15 chromax.black', 'noctua-nh-d15-chromaxblack', 'SP00032', 'Dual Tower, 2x 140mm fan, 250W TDP', '<p>Dual Tower, 2x 140mm fan, 250W TDP</p><p>Sản phẩm chính hãng, bảo hành 72 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 2890000, NULL, 2167500, 15, 1, 1, NULL, 72, NULL, NULL, 1396, 152, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(33, 19, 33, 9, 'DeepCool LT720 AIO 360mm', 'deepcool-lt720-aio-360mm', 'SP00033', 'AIO 360mm, Infinity Mirror, ARGB', '<p>AIO 360mm, Infinity Mirror, ARGB</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 3490000, 2990000, 2617500, 20, 1, 1, NULL, 60, NULL, NULL, 1677, 163, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(34, 19, 14, 9, 'Corsair iCUE H150i Elite LCD XT 360mm', 'corsair-icue-h150i-elite-lcd-xt-360mm', 'SP00034', 'AIO 360mm, LCD Display, iCUE RGB', '<p>AIO 360mm, LCD Display, iCUE RGB</p><p>Sản phẩm chính hãng, bảo hành 60 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 7990000, 7490000, 5992500, 8, 1, 0, NULL, 60, NULL, NULL, 569, 110, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(35, 5, NULL, NULL, 'PC Gaming Pro - RTX 4070 Super', 'pc-gaming-pro-rtx-4070-super', 'SP00035', 'i7-14700K / RTX 4070 Super / 32GB DDR5 / 1TB NVMe', '<p>i7-14700K / RTX 4070 Super / 32GB DDR5 / 1TB NVMe</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 32990000, 29990000, 24742500, 5, 1, 1, NULL, 36, NULL, NULL, 939, 141, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(36, 5, NULL, NULL, 'PC Gaming Elite - RTX 4080 Super', 'pc-gaming-elite-rtx-4080-super', 'SP00036', 'i9-14900K / RTX 4080 Super / 64GB DDR5 / 2TB NVMe', '<p>i9-14900K / RTX 4080 Super / 64GB DDR5 / 2TB NVMe</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 52990000, 48990000, 39742500, 3, 1, 1, NULL, 36, NULL, NULL, 1462, 92, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(37, 5, NULL, NULL, 'PC Gaming Ultimate - RTX 4090', 'pc-gaming-ultimate-rtx-4090', 'SP00037', 'i9-14900K / RTX 4090 / 64GB DDR5 / 4TB NVMe / AIO 360', '<p>i9-14900K / RTX 4090 / 64GB DDR5 / 4TB NVMe / AIO 360</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 79990000, 74990000, 59992500, 2, 1, 1, NULL, 36, NULL, NULL, 1565, 179, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(38, 5, NULL, NULL, 'PC Gaming Starter - RTX 4060', 'pc-gaming-starter-rtx-4060', 'SP00038', 'i5-14600K / RTX 4060 / 16GB DDR5 / 512GB NVMe', '<p>i5-14600K / RTX 4060 / 16GB DDR5 / 512GB NVMe</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 18990000, 16990000, 14242500, 10, 1, 0, NULL, 36, NULL, NULL, 1221, 165, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(39, 6, NULL, NULL, 'PC Văn phòng Basic', 'pc-van-phong-basic', 'SP00039', 'i3-14100 / Intel UHD 730 / 8GB DDR5 / 256GB NVMe', '<img src=\"http://localhost:8901/storage/media/shark-hRIOsA.jpg\" alt=\"shark\"><p>i3-14100 / Intel UHD 730 / 8GB DDR5 / 256GB NVMe</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', 'CPU: Intel Core i7-12700K\nRAM: 16GB DDR5\nSSD: 512GB NVMe\nCard đồ họa: RTX 4060 8GB\nMàn hình: 15.6 inch FHD IPS', 7990000, 6990000, 5992500, 20, 1, 0, NULL, 24, NULL, NULL, 1093, 60, '2026-02-20 04:37:29', '2026-02-26 09:29:31', NULL),
(40, 6, NULL, NULL, 'PC Văn phòng Pro', 'pc-van-phong-pro', 'SP00040', 'i5-14400 / Intel UHD 730 / 16GB DDR5 / 512GB NVMe', '<p>i5-14400 / Intel UHD 730 / 16GB DDR5 / 512GB NVMe</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 12990000, 11490000, 9742500, 15, 1, 0, NULL, 24, NULL, NULL, 1580, 150, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(41, 8, 4, NULL, 'ASUS ROG Strix G16 G614JV', 'asus-rog-strix-g16-g614jv', 'SP00041', 'i9-13980HX / RTX 4060 / 16GB DDR5 / 1TB / 16\" QHD 240Hz', '<p>i9-13980HX / RTX 4060 / 16GB DDR5 / 1TB / 16\" QHD 240Hz</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 39990000, 36990000, 29992500, 8, 1, 1, NULL, 24, NULL, NULL, 753, 90, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(42, 8, 5, NULL, 'MSI Raider GE78 HX 13VH', 'msi-raider-ge78-hx-13vh', 'SP00042', 'i9-13950HX / RTX 4080 / 32GB DDR5 / 2TB / 17\" QHD 240Hz', '<p>i9-13950HX / RTX 4080 / 32GB DDR5 / 2TB / 17\" QHD 240Hz</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 69990000, 64990000, 52492500, 3, 1, 1, NULL, 24, NULL, NULL, 1351, 74, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(43, 8, 46, NULL, 'Lenovo Legion Pro 5 16IRX9', 'lenovo-legion-pro-5-16irx9', 'SP00043', 'i7-14700HX / RTX 4070 / 32GB DDR5 / 1TB / 16\" WQXGA 240Hz', '<p>i7-14700HX / RTX 4070 / 32GB DDR5 / 1TB / 16\" WQXGA 240Hz</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 44990000, 41990000, 33742500, 6, 1, 1, NULL, 24, NULL, NULL, 1405, 88, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(44, 8, 48, NULL, 'Acer Predator Helios Neo 16 PHN16-72', 'acer-predator-helios-neo-16-phn16-72', 'SP00044', 'i7-14700HX / RTX 4060 / 16GB DDR5 / 1TB / 16\" WQXGA 165Hz', '<p>i7-14700HX / RTX 4060 / 16GB DDR5 / 1TB / 16\" WQXGA 165Hz</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 34990000, 31990000, 26242500, 10, 1, 0, NULL, 24, NULL, NULL, 1755, 102, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(45, 9, 46, NULL, 'Lenovo ThinkPad X1 Carbon Gen 11', 'lenovo-thinkpad-x1-carbon-gen-11', 'SP00045', 'i7-1365U / 16GB / 512GB / 14\" 2.8K OLED', '<p>i7-1365U / 16GB / 512GB / 14\" 2.8K OLED</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 39990000, 36990000, 29992500, 5, 1, 0, NULL, 24, NULL, NULL, 1329, 175, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(46, 9, 47, NULL, 'HP EliteBook 840 G10', 'hp-elitebook-840-g10', 'SP00046', 'i7-1365U / 16GB / 512GB / 14\" FHD IPS', '<p>i7-1365U / 16GB / 512GB / 14\" FHD IPS</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 29990000, 27990000, 22492500, 8, 1, 0, NULL, 24, NULL, NULL, 137, 105, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(47, 21, 41, NULL, 'LG 27GP850-B UltraGear 27\" QHD IPS', 'lg-27gp850-b-ultragear-27-qhd-ips', 'SP00047', '27\" QHD, IPS, 165Hz, 1ms, HDR400, NVIDIA G-Sync', '<p>27\" QHD, IPS, 165Hz, 1ms, HDR400, NVIDIA G-Sync</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 9990000, 8990000, 7492500, 12, 1, 1, NULL, 36, NULL, NULL, 105, 39, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(48, 21, 42, NULL, 'Dell S2722DGM 27\" QHD Curved', 'dell-s2722dgm-27-qhd-curved', 'SP00048', '27\" QHD, VA Curved, 165Hz, 1ms, AMD FreeSync', '<p>27\" QHD, VA Curved, 165Hz, 1ms, AMD FreeSync</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 7990000, 6990000, 5992500, 15, 1, 0, NULL, 36, NULL, NULL, 836, 61, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(49, 21, 17, NULL, 'Samsung Odyssey G7 32\" 4K', 'samsung-odyssey-g7-32-4k', 'SP00049', '32\" 4K, IPS, 144Hz, 1ms, Smart TV, HDMI 2.1', '<p>32\" 4K, IPS, 144Hz, 1ms, Smart TV, HDMI 2.1</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 18990000, 16990000, 14242500, 7, 1, 1, NULL, 36, NULL, NULL, 1655, 38, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(50, 22, 35, NULL, 'Logitech G Pro X TKL', 'logitech-g-pro-x-tkl', 'SP00050', 'Mechanical, Hot-swap, GX Switch, LIGHTSYNC RGB', '<p>Mechanical, Hot-swap, GX Switch, LIGHTSYNC RGB</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 2990000, NULL, 2242500, 25, 1, 0, NULL, 24, NULL, NULL, 287, 92, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(51, 22, 36, NULL, 'Razer BlackWidow V4 Pro', 'razer-blackwidow-v4-pro', 'SP00051', 'Mechanical, Green Switch, Chroma RGB, Cmd Dial', '<p>Mechanical, Green Switch, Chroma RGB, Cmd Dial</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 5990000, 5490000, 4492500, 10, 1, 1, NULL, 24, NULL, NULL, 1155, 177, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(52, 23, 35, NULL, 'Logitech G Pro X Superlight 2', 'logitech-g-pro-x-superlight-2', 'SP00052', 'Wireless, 60g, HERO 2 Sensor 32K DPI, 95h Pin', '<p>Wireless, 60g, HERO 2 Sensor 32K DPI, 95h Pin</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 3590000, 3290000, 2692500, 20, 1, 1, NULL, 24, NULL, NULL, 130, 197, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(53, 23, 36, NULL, 'Razer DeathAdder V3 Pro', 'razer-deathadder-v3-pro', 'SP00053', 'Wireless, 63g, Focus Pro 30K DPI, 90h Pin', '<p>Wireless, 63g, Focus Pro 30K DPI, 90h Pin</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 3790000, NULL, 2842500, 15, 1, 0, NULL, 24, NULL, NULL, 1999, 23, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(54, 24, 37, NULL, 'SteelSeries Arctis Nova Pro Wireless', 'steelseries-arctis-nova-pro-wireless', 'SP00054', 'Wireless, ANC, Hi-Res, Hot-Swap Pin, Multi-Source', '<p>Wireless, ANC, Hi-Res, Hot-Swap Pin, Multi-Source</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 8990000, 7990000, 6742500, 8, 1, 1, NULL, 24, NULL, NULL, 602, 195, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(55, 24, 38, NULL, 'HyperX Cloud III Wireless', 'hyperx-cloud-iii-wireless', 'SP00055', 'Wireless, DTS:X, 53mm Drivers, 120h Pin', '<p>Wireless, DTS:X, 53mm Drivers, 120h Pin</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 3990000, 3490000, 2992500, 18, 1, 0, NULL, 24, NULL, NULL, 712, 142, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(56, 28, NULL, NULL, 'Ghế Gaming DXRacer Air Pro', 'ghe-gaming-dxracer-air-pro', 'SP00056', 'Lưới mesh cao cấp, 4D Armrest, Lumbar Support', '<p>Lưới mesh cao cấp, 4D Armrest, Lumbar Support</p><p>Sản phẩm chính hãng, bảo hành 24 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 12990000, 11490000, 9742500, 5, 1, 0, NULL, 24, NULL, NULL, 1013, 81, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL),
(57, 7, NULL, NULL, 'PC Đồ họa - Render Chuyên nghiệp', 'pc-do-hoa-render-chuyen-nghiep', 'SP00057', 'i9-14900K / RTX 4080 Super / 128GB DDR5 / 4TB NVMe RAID', '<p>i9-14900K / RTX 4080 Super / 128GB DDR5 / 4TB NVMe RAID</p><p>Sản phẩm chính hãng, bảo hành 36 tháng. Miễn phí giao hàng toàn quốc.</p>', NULL, 65990000, 59990000, 49492500, 3, 1, 1, NULL, 36, NULL, NULL, 445, 191, '2026-02-20 04:37:29', '2026-02-20 04:37:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `url`, `alt_text`, `sort_order`, `is_primary`, `created_at`, `updated_at`) VALUES
(1, 1, 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=600&h=600&fit=crop', 'Intel Core i9-14900K', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(2, 2, 'https://images.unsplash.com/photo-1563203369-26f2e4a5ccf7?w=600&h=600&fit=crop', 'Intel Core i7-14700K', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(3, 3, 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=600&h=600&fit=crop', 'Intel Core i5-14600K', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(4, 4, 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=600&h=600&fit=crop', 'AMD Ryzen 9 7950X3D', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(5, 5, 'https://images.unsplash.com/photo-1563203369-26f2e4a5ccf7?w=600&h=600&fit=crop', 'AMD Ryzen 7 7800X3D', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(6, 6, 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=600&h=600&fit=crop', 'AMD Ryzen 5 7600X', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(7, 7, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'ASUS ROG Strix RTX 4090 OC 24GB', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(8, 8, 'https://images.unsplash.com/photo-1623820919239-0d0ff10797a1?w=600&h=600&fit=crop', 'MSI GeForce RTX 4080 SUPER Gaming X Trio 16GB', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(9, 9, 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=600&h=600&fit=crop', 'Gigabyte RTX 4070 Ti SUPER Eagle OC 16GB', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(10, 10, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'MSI GeForce RTX 4070 SUPER Ventus 2X 12GB', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(11, 11, 'https://images.unsplash.com/photo-1623820919239-0d0ff10797a1?w=600&h=600&fit=crop', 'ASUS Dual RTX 4060 Ti OC 8GB', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(12, 12, 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=600&h=600&fit=crop', 'Gigabyte RTX 4060 Eagle OC 8GB', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(13, 13, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'AMD Radeon RX 7900 XTX 24GB', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(14, 14, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'ASUS ROG Maximus Z790 Hero', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(15, 15, 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop', 'MSI MAG B760 Tomahawk WiFi', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(16, 16, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Gigabyte B650 AORUS Elite AX V2', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(17, 17, 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop', 'ASUS ROG Strix X670E-E Gaming WiFi', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(18, 18, 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop', 'G.Skill Trident Z5 RGB 32GB (2x16GB) DDR5-6000', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(19, 19, 'https://images.unsplash.com/photo-1541029071515-84cc54f84dc5?w=600&h=600&fit=crop', 'Corsair Vengeance 32GB (2x16GB) DDR5-5600', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(20, 20, 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop', 'Kingston Fury Beast 32GB (2x16GB) DDR5-5200', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(21, 21, 'https://images.unsplash.com/photo-1541029071515-84cc54f84dc5?w=600&h=600&fit=crop', 'G.Skill Trident Z5 Neo 64GB (2x32GB) DDR5-6000', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(22, 22, 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?w=600&h=600&fit=crop', 'Samsung 990 Pro 2TB NVMe M.2', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(23, 23, 'https://images.unsplash.com/photo-1531492746076-161ca9bcad09?w=600&h=600&fit=crop', 'Samsung 990 Pro 1TB NVMe M.2', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(24, 24, 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?w=600&h=600&fit=crop', 'WD Black SN850X 2TB NVMe M.2', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(25, 25, 'https://images.unsplash.com/photo-1531492746076-161ca9bcad09?w=600&h=600&fit=crop', 'Crucial T700 2TB NVMe M.2', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(26, 26, 'https://images.unsplash.com/photo-1600348712270-5af9e3590f67?w=600&h=600&fit=crop', 'Seasonic Focus GX-850 850W 80+ Gold', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(27, 27, 'https://images.unsplash.com/photo-1600348712270-5af9e3590f67?w=600&h=600&fit=crop', 'Corsair RM1000x 1000W 80+ Gold', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(28, 28, 'https://images.unsplash.com/photo-1600348712270-5af9e3590f67?w=600&h=600&fit=crop', 'Seasonic Vertex GX-1200 1200W 80+ Gold', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(29, 29, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'Lian Li O11 Dynamic EVO', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(30, 30, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'NZXT H7 Flow', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(31, 31, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'Phanteks NV7 TG', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(32, 32, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'Noctua NH-D15 chromax.black', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(33, 33, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'DeepCool LT720 AIO 360mm', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(34, 34, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'Corsair iCUE H150i Elite LCD XT 360mm', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(35, 35, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'PC Gaming Pro - RTX 4070 Super', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(36, 36, 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=600&h=600&fit=crop', 'PC Gaming Elite - RTX 4080 Super', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(37, 37, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'PC Gaming Ultimate - RTX 4090', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(38, 38, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'PC Gaming Starter - RTX 4060', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(40, 40, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'PC Văn phòng Pro', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(41, 41, 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&h=600&fit=crop', 'ASUS ROG Strix G16 G614JV', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(42, 42, 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop', 'MSI Raider GE78 HX 13VH', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(43, 43, 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=600&h=600&fit=crop', 'Lenovo Legion Pro 5 16IRX9', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(44, 44, 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&h=600&fit=crop', 'Acer Predator Helios Neo 16 PHN16-72', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(45, 45, 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop', 'Lenovo ThinkPad X1 Carbon Gen 11', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(46, 46, 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=600&h=600&fit=crop', 'HP EliteBook 840 G10', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(47, 47, 'https://images.unsplash.com/photo-1585792180666-f7347c490ee2?w=600&h=600&fit=crop', 'LG 27GP850-B UltraGear 27\" QHD IPS', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(48, 48, 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&h=600&fit=crop', 'Dell S2722DGM 27\" QHD Curved', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(49, 49, 'https://images.unsplash.com/photo-1585792180666-f7347c490ee2?w=600&h=600&fit=crop', 'Samsung Odyssey G7 32\" 4K', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(50, 50, 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=600&h=600&fit=crop', 'Logitech G Pro X TKL', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(51, 51, 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=600&h=600&fit=crop', 'Razer BlackWidow V4 Pro', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(52, 52, 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=600&h=600&fit=crop', 'Logitech G Pro X Superlight 2', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(53, 53, 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?w=600&h=600&fit=crop', 'Razer DeathAdder V3 Pro', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(54, 54, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&h=600&fit=crop', 'SteelSeries Arctis Nova Pro Wireless', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(55, 55, 'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?w=600&h=600&fit=crop', 'HyperX Cloud III Wireless', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(56, 56, 'https://images.unsplash.com/photo-1589384267710-7a170981ca78?w=600&h=600&fit=crop', 'Ghế Gaming DXRacer Air Pro', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(57, 57, 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=600&h=600&fit=crop', 'PC Đồ họa - Render Chuyên nghiệp', 0, 1, '2026-02-20 04:37:29', '2026-03-05 01:50:20'),
(59, 39, 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=600&h=600&fit=crop', NULL, 0, 1, '2026-02-26 09:29:31', '2026-03-05 01:50:20'),
(687, 1, 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=600&h=600&fit=crop', 'Intel Core i9-14900K - Hình 2', 1, 0, '2026-03-05 03:08:19', '2026-03-05 03:08:19'),
(688, 1, 'https://images.unsplash.com/photo-1563203369-26f2e4a5ccf7?w=600&h=600&fit=crop', 'Intel Core i9-14900K - Hình 3', 2, 0, '2026-03-05 03:08:19', '2026-03-05 03:08:19'),
(689, 2, 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=600&h=600&fit=crop', 'Intel Core i7-14700K - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(690, 2, 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=600&h=600&fit=crop', 'Intel Core i7-14700K - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(691, 3, 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=600&h=600&fit=crop', 'Intel Core i5-14600K - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(692, 3, 'https://images.unsplash.com/photo-1563203369-26f2e4a5ccf7?w=600&h=600&fit=crop', 'Intel Core i5-14600K - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(693, 4, 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=600&h=600&fit=crop', 'AMD Ryzen 9 7950X3D - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(694, 4, 'https://images.unsplash.com/photo-1563203369-26f2e4a5ccf7?w=600&h=600&fit=crop', 'AMD Ryzen 9 7950X3D - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(695, 5, 'https://images.unsplash.com/photo-1555617981-dac3880eac6e?w=600&h=600&fit=crop', 'AMD Ryzen 7 7800X3D - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(696, 5, 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=600&h=600&fit=crop', 'AMD Ryzen 7 7800X3D - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(697, 6, 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?w=600&h=600&fit=crop', 'AMD Ryzen 5 7600X - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(698, 6, 'https://images.unsplash.com/photo-1563203369-26f2e4a5ccf7?w=600&h=600&fit=crop', 'AMD Ryzen 5 7600X - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(699, 7, 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=600&h=600&fit=crop', 'ASUS ROG Strix RTX 4090 OC 24GB - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(700, 7, 'https://images.unsplash.com/photo-1623820919239-0d0ff10797a1?w=600&h=600&fit=crop', 'ASUS ROG Strix RTX 4090 OC 24GB - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(701, 8, 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=600&h=600&fit=crop', 'MSI GeForce RTX 4080 SUPER Gaming X Trio 16GB - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(702, 8, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'MSI GeForce RTX 4080 SUPER Gaming X Trio 16GB - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(703, 9, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'Gigabyte RTX 4070 Ti SUPER Eagle OC 16GB - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(704, 9, 'https://images.unsplash.com/photo-1623820919239-0d0ff10797a1?w=600&h=600&fit=crop', 'Gigabyte RTX 4070 Ti SUPER Eagle OC 16GB - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(705, 10, 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=600&h=600&fit=crop', 'MSI GeForce RTX 4070 SUPER Ventus 2X 12GB - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(706, 10, 'https://images.unsplash.com/photo-1623820919239-0d0ff10797a1?w=600&h=600&fit=crop', 'MSI GeForce RTX 4070 SUPER Ventus 2X 12GB - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(707, 11, 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=600&h=600&fit=crop', 'ASUS Dual RTX 4060 Ti OC 8GB - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(708, 11, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'ASUS Dual RTX 4060 Ti OC 8GB - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(709, 12, 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7?w=600&h=600&fit=crop', 'Gigabyte RTX 4060 Eagle OC 8GB - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(710, 12, 'https://images.unsplash.com/photo-1623820919239-0d0ff10797a1?w=600&h=600&fit=crop', 'Gigabyte RTX 4060 Eagle OC 8GB - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(711, 13, 'https://images.unsplash.com/photo-1591488320449-011701bb6704?w=600&h=600&fit=crop', 'AMD Radeon RX 7900 XTX 24GB - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(712, 13, 'https://images.unsplash.com/photo-1623820919239-0d0ff10797a1?w=600&h=600&fit=crop', 'AMD Radeon RX 7900 XTX 24GB - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(713, 14, 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop', 'ASUS ROG Maximus Z790 Hero - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(714, 14, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'ASUS ROG Maximus Z790 Hero - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(715, 15, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'MSI MAG B760 Tomahawk WiFi - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(716, 15, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'MSI MAG B760 Tomahawk WiFi - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(717, 16, 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop', 'Gigabyte B650 AORUS Elite AX V2 - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(718, 16, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'Gigabyte B650 AORUS Elite AX V2 - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(719, 17, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'ASUS ROG Strix X670E-E Gaming WiFi - Hình 2', 1, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(720, 17, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'ASUS ROG Strix X670E-E Gaming WiFi - Hình 3', 2, 0, '2026-03-05 03:08:20', '2026-03-05 03:08:20'),
(721, 18, 'https://images.unsplash.com/photo-1541029071515-84cc54f84dc5?w=600&h=600&fit=crop', 'G.Skill Trident Z5 RGB 32GB (2x16GB) DDR5-6000 - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(722, 18, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'G.Skill Trident Z5 RGB 32GB (2x16GB) DDR5-6000 - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(723, 19, 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop', 'Corsair Vengeance 32GB (2x16GB) DDR5-5600 - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(724, 19, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Corsair Vengeance 32GB (2x16GB) DDR5-5600 - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(725, 20, 'https://images.unsplash.com/photo-1541029071515-84cc54f84dc5?w=600&h=600&fit=crop', 'Kingston Fury Beast 32GB (2x16GB) DDR5-5200 - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(726, 20, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Kingston Fury Beast 32GB (2x16GB) DDR5-5200 - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(727, 21, 'https://images.unsplash.com/photo-1562976540-1502c2145186?w=600&h=600&fit=crop', 'G.Skill Trident Z5 Neo 64GB (2x32GB) DDR5-6000 - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(728, 21, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'G.Skill Trident Z5 Neo 64GB (2x32GB) DDR5-6000 - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(729, 22, 'https://images.unsplash.com/photo-1531492746076-161ca9bcad09?w=600&h=600&fit=crop', 'Samsung 990 Pro 2TB NVMe M.2 - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(730, 22, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Samsung 990 Pro 2TB NVMe M.2 - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(731, 23, 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?w=600&h=600&fit=crop', 'Samsung 990 Pro 1TB NVMe M.2 - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(732, 23, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Samsung 990 Pro 1TB NVMe M.2 - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(733, 24, 'https://images.unsplash.com/photo-1531492746076-161ca9bcad09?w=600&h=600&fit=crop', 'WD Black SN850X 2TB NVMe M.2 - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(734, 24, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'WD Black SN850X 2TB NVMe M.2 - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(735, 25, 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?w=600&h=600&fit=crop', 'Crucial T700 2TB NVMe M.2 - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(736, 25, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Crucial T700 2TB NVMe M.2 - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(737, 26, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Seasonic Focus GX-850 850W 80+ Gold - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(738, 26, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'Seasonic Focus GX-850 850W 80+ Gold - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(739, 27, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Corsair RM1000x 1000W 80+ Gold - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(740, 27, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'Corsair RM1000x 1000W 80+ Gold - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(741, 28, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Seasonic Vertex GX-1200 1200W 80+ Gold - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(742, 28, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'Seasonic Vertex GX-1200 1200W 80+ Gold - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(743, 29, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'Lian Li O11 Dynamic EVO - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(744, 29, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Lian Li O11 Dynamic EVO - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(745, 30, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'NZXT H7 Flow - Hình 2', 1, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(746, 30, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'NZXT H7 Flow - Hình 3', 2, 0, '2026-03-05 03:08:21', '2026-03-05 03:08:21'),
(747, 31, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'Phanteks NV7 TG - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(748, 31, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Phanteks NV7 TG - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(749, 32, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Noctua NH-D15 chromax.black - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(750, 32, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'Noctua NH-D15 chromax.black - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(751, 33, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'DeepCool LT720 AIO 360mm - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(752, 33, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'DeepCool LT720 AIO 360mm - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(753, 34, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Corsair iCUE H150i Elite LCD XT 360mm - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(754, 34, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'Corsair iCUE H150i Elite LCD XT 360mm - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(755, 35, 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=600&h=600&fit=crop', 'PC Gaming Pro - RTX 4070 Super - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(756, 35, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'PC Gaming Pro - RTX 4070 Super - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(757, 36, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'PC Gaming Elite - RTX 4080 Super - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(758, 36, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'PC Gaming Elite - RTX 4080 Super - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(759, 37, 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=600&h=600&fit=crop', 'PC Gaming Ultimate - RTX 4090 - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(760, 37, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'PC Gaming Ultimate - RTX 4090 - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(761, 38, 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=600&h=600&fit=crop', 'PC Gaming Starter - RTX 4060 - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(762, 38, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'PC Gaming Starter - RTX 4060 - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(763, 39, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'PC Văn phòng Basic - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(764, 39, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'PC Văn phòng Basic - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(765, 40, 'https://images.unsplash.com/photo-1593640408182-31c70c8268f5?w=600&h=600&fit=crop', 'PC Văn phòng Pro - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(766, 40, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'PC Văn phòng Pro - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(767, 41, 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop', 'ASUS ROG Strix G16 G614JV - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(768, 41, 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=600&h=600&fit=crop', 'ASUS ROG Strix G16 G614JV - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(769, 42, 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=600&h=600&fit=crop', 'MSI Raider GE78 HX 13VH - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(770, 42, 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&h=600&fit=crop', 'MSI Raider GE78 HX 13VH - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(771, 43, 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop', 'Lenovo Legion Pro 5 16IRX9 - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(772, 43, 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&h=600&fit=crop', 'Lenovo Legion Pro 5 16IRX9 - Hình 3', 2, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(773, 44, 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop', 'Acer Predator Helios Neo 16 PHN16-72 - Hình 2', 1, 0, '2026-03-05 03:08:22', '2026-03-05 03:08:22'),
(774, 44, 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=600&h=600&fit=crop', 'Acer Predator Helios Neo 16 PHN16-72 - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(775, 45, 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=600&h=600&fit=crop', 'Lenovo ThinkPad X1 Carbon Gen 11 - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(776, 45, 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&h=600&fit=crop', 'Lenovo ThinkPad X1 Carbon Gen 11 - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(777, 46, 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop', 'HP EliteBook 840 G10 - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(778, 46, 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&h=600&fit=crop', 'HP EliteBook 840 G10 - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(779, 47, 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&h=600&fit=crop', 'LG 27GP850-B UltraGear 27\" QHD IPS - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(780, 47, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'LG 27GP850-B UltraGear 27\" QHD IPS - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(781, 48, 'https://images.unsplash.com/photo-1585792180666-f7347c490ee2?w=600&h=600&fit=crop', 'Dell S2722DGM 27\" QHD Curved - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(782, 48, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Dell S2722DGM 27\" QHD Curved - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(783, 49, 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&h=600&fit=crop', 'Samsung Odyssey G7 32\" 4K - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(784, 49, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Samsung Odyssey G7 32\" 4K - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(785, 50, 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=600&h=600&fit=crop', 'Logitech G Pro X TKL - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(786, 50, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Logitech G Pro X TKL - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(787, 51, 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=600&h=600&fit=crop', 'Razer BlackWidow V4 Pro - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(788, 51, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Razer BlackWidow V4 Pro - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(789, 52, 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?w=600&h=600&fit=crop', 'Logitech G Pro X Superlight 2 - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(790, 52, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Logitech G Pro X Superlight 2 - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(791, 53, 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=600&h=600&fit=crop', 'Razer DeathAdder V3 Pro - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(792, 53, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Razer DeathAdder V3 Pro - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(793, 54, 'https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?w=600&h=600&fit=crop', 'SteelSeries Arctis Nova Pro Wireless - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(794, 54, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'SteelSeries Arctis Nova Pro Wireless - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(795, 55, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&h=600&fit=crop', 'HyperX Cloud III Wireless - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(796, 55, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'HyperX Cloud III Wireless - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(797, 56, 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&h=600&fit=crop', 'Ghế Gaming DXRacer Air Pro - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(798, 56, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&h=600&fit=crop', 'Ghế Gaming DXRacer Air Pro - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(799, 57, 'https://images.unsplash.com/photo-1587202372634-32705e3bf49c?w=600&h=600&fit=crop', 'PC Đồ họa - Render Chuyên nghiệp - Hình 2', 1, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23'),
(800, 57, 'https://images.unsplash.com/photo-1624705002806-5d72df19c3ad?w=600&h=600&fit=crop', 'PC Đồ họa - Render Chuyên nghiệp - Hình 3', 2, 0, '2026-03-05 03:08:23', '2026-03-05 03:08:23');

-- --------------------------------------------------------

--
-- Table structure for table `product_specifications`
--

CREATE TABLE `product_specifications` (
  `id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `specification_key_id` bigint UNSIGNED NOT NULL,
  `value_string` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_numeric` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED DEFAULT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `admin_reply` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_builds`
--

CREATE TABLE `saved_builds` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `products` json NOT NULL,
  `total_price` decimal(15,0) NOT NULL DEFAULT '0',
  `total_tdp` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint UNSIGNED NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `group` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` json DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `group`, `type`, `label`, `options`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'PC Shop', 'general', 'text', 'Tên website', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(2, 'site_tagline', 'Bán PC, Laptop & Linh kiện máy tính', 'general', 'text', 'Slogan', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(3, 'site_description', 'Chuyên cung cấp PC Gaming, Laptop, linh kiện máy tính chính hãng. Xây dựng cấu hình PC thông minh với công cụ kiểm tra tương thích.', 'general', 'textarea', 'Mô tả website', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(4, 'site_logo', '', 'general', 'image', 'Logo website', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(5, 'site_favicon', '', 'general', 'image', 'Favicon', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(6, 'currency', 'VND', 'general', 'text', 'Đơn vị tiền tệ', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(7, 'timezone', 'Asia/Ho_Chi_Minh', 'general', 'text', 'Múi giờ', NULL, 0, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(8, 'contact_phone', '1900 1234', 'contact', 'text', 'Số điện thoại', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(9, 'contact_hotline', '0909 123 456', 'contact', 'text', 'Hotline', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(10, 'contact_email', 'contact@pcshop.vn', 'contact', 'text', 'Email', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(11, 'contact_address', '123 Nguyễn Văn Linh, Quận 7, TP.HCM', 'contact', 'textarea', 'Địa chỉ', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(12, 'business_hours', 'T2 - CN: 8:00 - 21:00', 'contact', 'text', 'Giờ làm việc', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(13, 'contact_map', '', 'contact', 'textarea', 'Google Maps embed URL', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(14, 'social_facebook', 'https://facebook.com/pcshop', 'social', 'text', 'Facebook', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(15, 'social_youtube', 'https://youtube.com/@pcshop', 'social', 'text', 'YouTube', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(16, 'social_tiktok', '', 'social', 'text', 'TikTok', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(17, 'social_zalo', '', 'social', 'text', 'Zalo OA', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(18, 'social_instagram', '', 'social', 'text', 'Instagram', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(19, 'seo_title', 'PC Shop - Bán PC, Laptop & Linh kiện máy tính chính hãng', 'seo', 'text', 'SEO Title mặc định', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(20, 'seo_description', 'PC Shop - Chuyên cung cấp PC Gaming, Laptop, linh kiện máy tính chính hãng giá tốt. Build PC online, kiểm tra tương thích tự động.', 'seo', 'textarea', 'SEO Description mặc định', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(21, 'seo_keywords', 'pc gaming, laptop, linh kiện máy tính, build pc, mua pc, pc shop', 'seo', 'text', 'SEO Keywords mặc định', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(22, 'seo_og_image', '', 'seo', 'image', 'OG Image mặc định', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(23, 'google_analytics_id', '', 'seo', 'text', 'Google Analytics ID (UA/G-)', NULL, 0, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(24, 'google_tag_manager_id', '', 'seo', 'text', 'Google Tag Manager ID', NULL, 0, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(25, 'facebook_pixel_id', '', 'seo', 'text', 'Facebook Pixel ID', NULL, 0, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(26, 'homepage_hero_autoplay', '1', 'homepage', 'boolean', 'Auto-play banner hero', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(27, 'homepage_hero_interval', '5000', 'homepage', 'number', 'Thời gian chuyển slide (ms)', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(28, 'homepage_products_per_section', '8', 'homepage', 'number', 'Số sản phẩm mỗi mục', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(29, 'homepage_show_brands', '1', 'homepage', 'boolean', 'Hiện carousel thương hiệu', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(30, 'homepage_show_posts', '1', 'homepage', 'boolean', 'Hiện bài viết nổi bật', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(31, 'payment_bank_name', 'MB Bank', 'payment', 'text', 'Tên ngân hàng', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(32, 'payment_bank_account', '0123456789', 'payment', 'text', 'Số tài khoản', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(33, 'payment_bank_holder', 'CONG TY PC SHOP', 'payment', 'text', 'Tên chủ tài khoản', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(34, 'payment_cod_enabled', '1', 'payment', 'boolean', 'Cho phép COD', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(35, 'shipping_free_threshold', '2000000', 'shipping', 'number', 'Miễn phí ship từ (VNĐ)', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(36, 'shipping_default_fee', '30000', 'shipping', 'number', 'Phí ship mặc định (VNĐ)', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02'),
(37, 'shipping_express_fee', '50000', 'shipping', 'number', 'Phí ship nhanh (VNĐ)', NULL, 1, '2026-03-05 03:08:02', '2026-03-05 03:08:02');

-- --------------------------------------------------------

--
-- Table structure for table `specification_keys`
--

CREATE TABLE `specification_keys` (
  `id` bigint UNSIGNED NOT NULL,
  `component_type_id` bigint UNSIGNED DEFAULT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_type` enum('string','integer','decimal','boolean') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_filterable` tinyint(1) NOT NULL DEFAULT '0',
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `specification_keys`
--

INSERT INTO `specification_keys` (`id`, `component_type_id`, `key`, `label`, `data_type`, `unit`, `is_filterable`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'socket', 'Socket', 'string', NULL, 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(2, 1, 'cores', 'Số nhân', 'integer', NULL, 1, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(3, 1, 'threads', 'Số luồng', 'integer', NULL, 1, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(4, 1, 'base_clock', 'Xung cơ bản', 'decimal', 'GHz', 1, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(5, 1, 'boost_clock', 'Xung tối đa', 'decimal', 'GHz', 1, 5, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(6, 1, 'tdp', 'TDP', 'integer', 'W', 1, 6, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(7, 1, 'integrated_gpu', 'GPU tích hợp', 'boolean', NULL, 1, 7, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(8, 1, 'memory_type', 'Loại RAM hỗ trợ', 'string', NULL, 1, 8, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(9, 2, 'socket', 'Socket', 'string', NULL, 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(10, 2, 'chipset', 'Chipset', 'string', NULL, 1, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(11, 2, 'form_factor', 'Kích thước', 'string', NULL, 1, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(12, 2, 'memory_type', 'Loại RAM', 'string', NULL, 1, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(13, 2, 'memory_slots', 'Số khe RAM', 'integer', NULL, 1, 5, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(14, 2, 'max_memory', 'RAM tối đa', 'integer', 'GB', 1, 6, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(15, 2, 'm2_slots', 'Số khe M.2', 'integer', NULL, 1, 7, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(16, 2, 'sata_ports', 'Số cổng SATA', 'integer', NULL, 0, 8, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(17, 2, 'pcie_x16_slots', 'Số khe PCIe x16', 'integer', NULL, 0, 9, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(18, 3, 'memory_type', 'Loại RAM', 'string', NULL, 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(19, 3, 'capacity', 'Dung lượng', 'integer', 'GB', 1, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(20, 3, 'speed', 'Tốc độ', 'integer', 'MHz', 1, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(21, 3, 'kit_type', 'Số thanh', 'string', NULL, 1, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(22, 3, 'latency', 'CAS Latency', 'string', NULL, 0, 5, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(23, 3, 'rgb', 'LED RGB', 'boolean', NULL, 1, 6, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(24, 4, 'gpu_chip', 'Chip GPU', 'string', NULL, 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(25, 4, 'vram', 'VRAM', 'integer', 'GB', 1, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(26, 4, 'vram_type', 'Loại VRAM', 'string', NULL, 1, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(27, 4, 'core_clock', 'Xung core', 'integer', 'MHz', 0, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(28, 4, 'boost_clock', 'Xung boost', 'integer', 'MHz', 0, 5, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(29, 4, 'tdp', 'TDP', 'integer', 'W', 1, 6, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(30, 4, 'length', 'Chiều dài', 'integer', 'mm', 0, 7, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(31, 4, 'power_connectors', 'Nguồn phụ', 'string', NULL, 0, 8, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(32, 5, 'capacity', 'Dung lượng', 'integer', 'GB', 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(33, 5, 'interface', 'Chuẩn kết nối', 'string', NULL, 1, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(34, 5, 'form_factor', 'Kích thước', 'string', NULL, 1, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(35, 5, 'read_speed', 'Tốc độ đọc', 'integer', 'MB/s', 0, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(36, 5, 'write_speed', 'Tốc độ ghi', 'integer', 'MB/s', 0, 5, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(37, 6, 'capacity', 'Dung lượng', 'integer', 'TB', 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(38, 6, 'rpm', 'Tốc độ quay', 'integer', 'RPM', 1, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(39, 6, 'cache', 'Bộ nhớ đệm', 'integer', 'MB', 0, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(40, 6, 'form_factor', 'Kích thước', 'string', NULL, 1, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(41, 7, 'wattage', 'Công suất', 'integer', 'W', 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(42, 7, 'efficiency', 'Chứng nhận 80+', 'string', NULL, 1, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(43, 7, 'modular', 'Modular', 'string', NULL, 1, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(44, 7, 'form_factor', 'Kích thước', 'string', NULL, 1, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(45, 8, 'form_factor', 'Kích thước mainboard', 'string', NULL, 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(46, 8, 'max_gpu_length', 'Chiều dài VGA tối đa', 'integer', 'mm', 0, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(47, 8, 'max_cooler_height', 'Chiều cao tản nhiệt tối đa', 'integer', 'mm', 0, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(48, 8, 'drive_bays_25', 'Khay ổ 2.5\"', 'integer', NULL, 0, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(49, 8, 'drive_bays_35', 'Khay ổ 3.5\"', 'integer', NULL, 0, 5, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(50, 8, 'tempered_glass', 'Kính cường lực', 'boolean', NULL, 1, 6, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(51, 9, 'type', 'Loại tản nhiệt', 'string', NULL, 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(52, 9, 'socket_support', 'Socket hỗ trợ', 'string', NULL, 1, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(53, 9, 'tdp_rating', 'TDP hỗ trợ', 'integer', 'W', 1, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(54, 9, 'height', 'Chiều cao', 'integer', 'mm', 0, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(55, 9, 'radiator_size', 'Kích thước rad', 'integer', 'mm', 1, 5, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(56, 9, 'rgb', 'LED RGB', 'boolean', NULL, 1, 6, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(57, 10, 'size', 'Kích thước', 'integer', 'mm', 1, 1, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(58, 10, 'rpm', 'Tốc độ', 'string', 'RPM', 0, 2, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(59, 10, 'airflow', 'Lưu lượng gió', 'decimal', 'CFM', 0, 3, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(60, 10, 'noise_level', 'Độ ồn', 'decimal', 'dBA', 0, 4, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(61, 10, 'rgb', 'LED RGB', 'boolean', NULL, 1, 5, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(62, 10, 'pack_quantity', 'Số lượng trong bộ', 'integer', NULL, 0, 6, '2026-02-20 01:54:27', '2026-02-20 01:54:27');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `sepay_transaction_id` bigint UNSIGNED NOT NULL,
  `gateway` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,0) NOT NULL,
  `reference_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `transaction_date` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','staff','customer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `role`, `avatar`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin@pcshop.vn', NULL, 'admin', NULL, '2026-02-20 01:54:27', '$2y$12$ouB5pyRBmQmnv1JfnR3Fde5uU8kotvKyef/FMLF41eORn6gyxcEZG', NULL, '2026-02-20 01:54:27', '2026-02-20 01:54:27'),
(2, 'Staff', 'staff@pcshop.vn', NULL, 'staff', NULL, '2026-02-20 01:54:27', '$2y$12$C2BkjPC2QtQs5wKvNL1.T.F1T1XCyexAJq3yjuBkdt1u82/UZJJaK', NULL, '2026-02-20 01:54:27', '2026-02-20 01:54:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `addresses_user_id_foreign` (`user_id`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `brands_slug_unique` (`slug`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `carts_user_id_foreign` (`user_id`),
  ADD KEY `carts_session_id_index` (`session_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cart_items_cart_id_product_id_unique` (`cart_id`,`product_id`),
  ADD KEY `cart_items_product_id_foreign` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`),
  ADD KEY `categories_parent_id_foreign` (`parent_id`);

--
-- Indexes for table `compatibility_rules`
--
ALTER TABLE `compatibility_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `compatibility_rules_source_type_id_foreign` (`source_type_id`),
  ADD KEY `compatibility_rules_target_type_id_foreign` (`target_type_id`);

--
-- Indexes for table `component_supported_values`
--
ALTER TABLE `component_supported_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `component_supported_values_specification_key_id_foreign` (`specification_key_id`),
  ADD KEY `component_supported_values_product_id_specification_key_id_index` (`product_id`,`specification_key_id`);

--
-- Indexes for table `component_types`
--
ALTER TABLE `component_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `component_types_slug_unique` (`slug`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupons_code_unique` (`code`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `media`
--
ALTER TABLE `media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `media_uploaded_by_foreign` (`uploaded_by`),
  ADD KEY `media_folder_index` (`folder`),
  ADD KEY `media_mime_type_index` (`mime_type`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `menus_slug_unique` (`slug`),
  ADD KEY `menus_location_index` (`location`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menu_items_parent_id_foreign` (`parent_id`),
  ADD KEY `menu_items_category_id_foreign` (`category_id`),
  ADD KEY `menu_items_menu_id_parent_id_sort_order_index` (`menu_id`,`parent_id`,`sort_order`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_order_number_unique` (`order_number`),
  ADD KEY `orders_user_id_foreign` (`user_id`),
  ADD KEY `orders_payment_status_order_status_index` (`order_status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_order_id_foreign` (`order_id`),
  ADD KEY `order_items_product_id_foreign` (`product_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pages_slug_unique` (`slug`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `posts_slug_unique` (`slug`),
  ADD KEY `posts_user_id_foreign` (`user_id`),
  ADD KEY `posts_is_published_published_at_index` (`published_at`),
  ADD KEY `posts_post_category_id_foreign` (`post_category_id`),
  ADD KEY `posts_status_index` (`status`),
  ADD KEY `posts_is_featured_index` (`is_featured`);

--
-- Indexes for table `post_categories`
--
ALTER TABLE `post_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `post_categories_slug_unique` (`slug`);

--
-- Indexes for table `power_requirements`
--
ALTER TABLE `power_requirements`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_slug_unique` (`slug`),
  ADD UNIQUE KEY `products_sku_unique` (`sku`),
  ADD KEY `products_category_id_foreign` (`category_id`),
  ADD KEY `products_brand_id_foreign` (`brand_id`),
  ADD KEY `products_component_type_id_foreign` (`component_type_id`),
  ADD KEY `products_is_active_is_featured_index` (`is_active`,`is_featured`),
  ADD KEY `products_price_index` (`price`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_images_product_id_foreign` (`product_id`);

--
-- Indexes for table `product_specifications`
--
ALTER TABLE `product_specifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_specifications_product_id_specification_key_id_unique` (`product_id`,`specification_key_id`),
  ADD KEY `product_specifications_specification_key_id_value_string_index` (`specification_key_id`,`value_string`),
  ADD KEY `product_specifications_specification_key_id_value_numeric_index` (`specification_key_id`,`value_numeric`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviews_user_id_foreign` (`user_id`),
  ADD KEY `reviews_order_id_foreign` (`order_id`),
  ADD KEY `reviews_product_id_is_approved_index` (`product_id`,`is_approved`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `saved_builds`
--
ALTER TABLE `saved_builds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `saved_builds_user_id_foreign` (`user_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `settings_key_unique` (`key`),
  ADD KEY `settings_group_index` (`group`);

--
-- Indexes for table `specification_keys`
--
ALTER TABLE `specification_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `specification_keys_component_type_id_key_unique` (`component_type_id`,`key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transactions_sepay_transaction_id_unique` (`sepay_transaction_id`),
  ADD KEY `transactions_order_id_foreign` (`order_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `compatibility_rules`
--
ALTER TABLE `compatibility_rules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `component_supported_values`
--
ALTER TABLE `component_supported_values`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `component_types`
--
ALTER TABLE `component_types`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media`
--
ALTER TABLE `media`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `post_categories`
--
ALTER TABLE `post_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=801;

--
-- AUTO_INCREMENT for table `product_specifications`
--
ALTER TABLE `product_specifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `saved_builds`
--
ALTER TABLE `saved_builds`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `specification_keys`
--
ALTER TABLE `specification_keys`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_cart_id_foreign` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `compatibility_rules`
--
ALTER TABLE `compatibility_rules`
  ADD CONSTRAINT `compatibility_rules_source_type_id_foreign` FOREIGN KEY (`source_type_id`) REFERENCES `component_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `compatibility_rules_target_type_id_foreign` FOREIGN KEY (`target_type_id`) REFERENCES `component_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `component_supported_values`
--
ALTER TABLE `component_supported_values`
  ADD CONSTRAINT `component_supported_values_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `component_supported_values_specification_key_id_foreign` FOREIGN KEY (`specification_key_id`) REFERENCES `specification_keys` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `media`
--
ALTER TABLE `media`
  ADD CONSTRAINT `media_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `menu_items_menu_id_foreign` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_items_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_post_category_id_foreign` FOREIGN KEY (`post_category_id`) REFERENCES `post_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `posts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `power_requirements`
--
ALTER TABLE `power_requirements`
  ADD CONSTRAINT `power_requirements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_component_type_id_foreign` FOREIGN KEY (`component_type_id`) REFERENCES `component_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_specifications`
--
ALTER TABLE `product_specifications`
  ADD CONSTRAINT `product_specifications_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_specifications_specification_key_id_foreign` FOREIGN KEY (`specification_key_id`) REFERENCES `specification_keys` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviews_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_builds`
--
ALTER TABLE `saved_builds`
  ADD CONSTRAINT `saved_builds_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `specification_keys`
--
ALTER TABLE `specification_keys`
  ADD CONSTRAINT `specification_keys_component_type_id_foreign` FOREIGN KEY (`component_type_id`) REFERENCES `component_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

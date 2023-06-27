/*
 Navicat Premium Data Transfer

 Source Server         : shaven
 Source Server Type    : MySQL
 Source Server Version : 100427 (10.4.27-MariaDB)
 Source Host           : localhost:3306
 Source Schema         : db_isp

 Target Server Type    : MySQL
 Target Server Version : 100427 (10.4.27-MariaDB)
 File Encoding         : 65001

 Date: 23/02/2023 17:00:38
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for tbl_admin_level
-- ----------------------------
DROP TABLE IF EXISTS `tbl_admin_level`;
CREATE TABLE `tbl_admin_level`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_lvl` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of tbl_admin_level
-- ----------------------------
INSERT INTO `tbl_admin_level` VALUES (1, 'Superadmin');
INSERT INTO `tbl_admin_level` VALUES (2, 'Admin');
INSERT INTO `tbl_admin_level` VALUES (3, 'User');

-- ----------------------------
-- Table structure for tbl_payments
-- ----------------------------
DROP TABLE IF EXISTS `tbl_payments`;
CREATE TABLE `tbl_payments`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_date` datetime NULL DEFAULT NULL,
  `payment_uid` int NULL DEFAULT NULL,
  `status` int NULL DEFAULT 0,
  `p_timestamp` datetime NULL DEFAULT NULL,
  `payment_month` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `payment_day` int NULL DEFAULT NULL,
  `payment_amount` double NULL DEFAULT NULL,
  `postedby` int NULL DEFAULT NULL,
  `payment_status` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 239 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '1- Normal Payment\r\n2- Advance Payment' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of tbl_payments
-- ----------------------------
INSERT INTO `tbl_payments` VALUES (230, NULL, 28, 0, '2023-02-23 16:23:10', 'February', 1, 250, NULL, 1);
INSERT INTO `tbl_payments` VALUES (231, '2023-02-23 16:24:03', 29, 1, '2023-02-23 16:23:10', 'February', 1, 250, NULL, 1);
INSERT INTO `tbl_payments` VALUES (232, '2023-02-23 16:23:58', 30, 1, '2023-02-23 16:23:10', 'February', 2, 500, NULL, 1);
INSERT INTO `tbl_payments` VALUES (233, '2023-02-23 16:24:13', 31, 1, '2023-02-23 16:23:10', 'February', 3, 500, NULL, 1);
INSERT INTO `tbl_payments` VALUES (234, NULL, 28, 0, '2023-03-23 16:26:29', 'March', 1, 250, NULL, 1);
INSERT INTO `tbl_payments` VALUES (235, NULL, 29, 0, '2023-03-23 16:26:30', 'March', 1, 250, NULL, 1);
INSERT INTO `tbl_payments` VALUES (236, NULL, 30, 0, '2023-03-23 16:26:30', 'March', 2, 500, NULL, 1);
INSERT INTO `tbl_payments` VALUES (237, NULL, 31, 0, '2023-03-23 16:26:30', 'March', 3, 500, NULL, 1);
INSERT INTO `tbl_payments` VALUES (238, NULL, 32, 0, '2023-02-23 16:42:53', 'February', 3, 1000, NULL, 1);

-- ----------------------------
-- Table structure for tbl_plans
-- ----------------------------
DROP TABLE IF EXISTS `tbl_plans`;
CREATE TABLE `tbl_plans`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `p_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `p_speed_kb` int NULL DEFAULT NULL,
  `p_monthly_amount` double NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of tbl_plans
-- ----------------------------
INSERT INTO `tbl_plans` VALUES (1, 'Plan 250', 2500, 250);
INSERT INTO `tbl_plans` VALUES (2, 'Plan 500', 4500, 500);
INSERT INTO `tbl_plans` VALUES (3, 'Plan 1000', 11000, 1000);
INSERT INTO `tbl_plans` VALUES (4, 'Plan 1500', 16000, 1500);

-- ----------------------------
-- Table structure for tbl_subs_profile
-- ----------------------------
DROP TABLE IF EXISTS `tbl_subs_profile`;
CREATE TABLE `tbl_subs_profile`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `s_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `plan_id` int NULL DEFAULT NULL,
  `s_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `s_doi` date NULL DEFAULT NULL,
  `timestamp` datetime NULL DEFAULT NULL,
  `status` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 34 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of tbl_subs_profile
-- ----------------------------
INSERT INTO `tbl_subs_profile` VALUES (27, 'Jaime Joaquin', 2, 'Maralag, Dumingag, Zamboanga Del Sur', '2020-12-27', '2023-02-23 15:50:02', 1);
INSERT INTO `tbl_subs_profile` VALUES (28, 'Wilbert Rupinta', 1, 'Maralag, Dumingag, Zamboanga Del Sur', '2021-01-01', '2023-02-23 15:50:30', 1);
INSERT INTO `tbl_subs_profile` VALUES (29, 'Buloy Adaza', 1, 'Maralag, Dumingag, Zamboanga Del Sur', '2021-07-01', '2023-02-23 15:50:53', 1);
INSERT INTO `tbl_subs_profile` VALUES (30, 'Narding Uy', 2, 'Maralag, Dumingag, Zamboanga Del Sur', '2021-11-02', '2023-02-23 15:51:16', 1);
INSERT INTO `tbl_subs_profile` VALUES (31, 'Badilla', 2, 'Maralag, Dumingag, Zamboanga Del Sur', '2021-11-03', '2023-02-23 15:51:35', 1);
INSERT INTO `tbl_subs_profile` VALUES (32, 'Dummy Account', 3, 'Maralag, Dumingag, Zamboanga Del Sur\r\n', '2023-01-03', '2023-02-23 16:42:40', 1);
INSERT INTO `tbl_subs_profile` VALUES (33, 'Dummy Accouunt 2', 1, 'Maralag, DUmignag', '2023-02-20', '2023-02-23 16:43:34', 1);

-- ----------------------------
-- Table structure for tbl_sys_users
-- ----------------------------
DROP TABLE IF EXISTS `tbl_sys_users`;
CREATE TABLE `tbl_sys_users`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `sys_user_fullname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `sys_user_dor` datetime NULL DEFAULT NULL,
  `sys_level` int NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of tbl_sys_users
-- ----------------------------
INSERT INTO `tbl_sys_users` VALUES (1, 'admin', 'admin', 'Administrator', '2023-02-22 11:07:39', 1);

SET FOREIGN_KEY_CHECKS = 1;

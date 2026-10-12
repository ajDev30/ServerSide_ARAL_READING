-- MySQL dump 10.13  Distrib 8.0.36, for Linux (x86_64)
--
-- Host: localhost    Database: aral_reading_db
-- ------------------------------------------------------
-- Server version	8.0.36-2ubuntu3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `course_assessments`
--

DROP TABLE IF EXISTS `course_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_assessments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `test_type` enum('Pre-Test','Post-Test') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pre-Test',
  `target_profile` enum('Instructional','Frustration') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Instructional',
  `target_grade` int DEFAULT '7',
  `passage_text` text COLLATE utf8mb4_general_ci,
  `questions_json` text COLLATE utf8mb4_general_ci,
  `status` enum('Draft','Published') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Draft',
  `available_from` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gst_assessments`
--

DROP TABLE IF EXISTS `gst_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gst_assessments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade_level` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `time_limit_minutes` int DEFAULT '0',
  `questions_json` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_level` (`grade_level`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gst_results`
--

DROP TABLE IF EXISTS `gst_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gst_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `grade_level` int NOT NULL,
  `score` int NOT NULL,
  `total_items` int NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `gst_category` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `starting_grade` int DEFAULT NULL,
  `needs_individual_assessment` tinyint(1) DEFAULT '1',
  `language` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'English',
  `completed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reading_attempts`
--

DROP TABLE IF EXISTS `reading_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `passage_id` int NOT NULL,
  `passage_grade` int DEFAULT NULL,
  `phase` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `transcript` text COLLATE utf8mb4_general_ci,
  `accuracy_score` float DEFAULT '0',
  `reading_time` int DEFAULT '0',
  `reading_speed` float DEFAULT '0',
  `miscues_json` text COLLATE utf8mb4_general_ci,
  `answers_json` text COLLATE utf8mb4_general_ci,
  `evaluation_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `comprehension_score` float DEFAULT '0',
  `oral_reading_profile` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Pending Review',
  `audio_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `passage_id` (`passage_id`),
  CONSTRAINT `reading_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `reading_attempts_ibfk_2` FOREIGN KEY (`passage_id`) REFERENCES `reading_passages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reading_passages`
--

DROP TABLE IF EXISTS `reading_passages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_passages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `grade_level` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `passage_text` text COLLATE utf8mb4_general_ci,
  `questions_json` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `passage_2` text COLLATE utf8mb4_general_ci,
  `passage_3` text COLLATE utf8mb4_general_ci,
  `passage_4` text COLLATE utf8mb4_general_ci,
  `expiration_time` int DEFAULT '0',
  `max_attempts` int DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reading_profiles`
--

DROP TABLE IF EXISTS `reading_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_profiles` (
  `user_id` int NOT NULL,
  `independent_grade` int DEFAULT NULL,
  `instructional_grade` int DEFAULT NULL,
  `frustration_grade` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recommendation_rules`
--

DROP TABLE IF EXISTS `recommendation_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recommendation_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_name` varchar(100) NOT NULL,
  `outcome` varchar(50) NOT NULL,
  `target_profile` varchar(50) DEFAULT 'Any',
  `recommendation_template` text NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `setting_key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `student_recommendations`
--

DROP TABLE IF EXISTS `student_recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_recommendations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `course_group` varchar(255) NOT NULL,
  `condition_tag` varchar(100) DEFAULT NULL,
  `recommendation_text` text NOT NULL,
  `teacher_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_course` (`user_id`,`course_group`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fname` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mname` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lname` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `age` int DEFAULT NULL,
  `grade` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `section` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `grade_level` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('teacher','student') COLLATE utf8mb4_general_ci DEFAULT 'student',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `lrn` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'Not Assigned',
  `current_level` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `test_status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-12 20:42:51

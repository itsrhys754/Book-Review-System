-- MySQL dump 10.13  Distrib 5.7.24, for osx11.1 (x86_64)
--
-- Host: localhost    Database: awd
-- ------------------------------------------------------
-- Server version	9.1.0-commercial

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `book`
--

DROP TABLE IF EXISTS `book`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `book` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pages` int NOT NULL,
  `summary` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approved` tinyint(1) NOT NULL,
  `image_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_CBE5A331A76ED395` (`user_id`),
  CONSTRAINT `FK_CBE5A331A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `book`
--

LOCK TABLES `book` WRITE;
/*!40000 ALTER TABLE `book` DISABLE KEYS */;
INSERT INTO `book` VALUES (6,9,'The Great Gatsby','F. Scott Fitzgerald',180,'A story of decadence and excess follows a cast of characters living in the fictional town of West Egg in the summer of 1922.','Classic Literature',1,'673a0f225db6f.jpg'),(7,10,'1984','George Orwell',328,'A dystopian social science fiction novel following Winston Smith in a totalitarian future society.','Science Fiction',1,'673a10627dbad.jpg'),(8,11,'Pride and Prejudice','Jane Austen',432,'The story follows the main character Elizabeth Bennet as she deals with issues of manners, upbringing, morality, education, and marriage.','Romance',1,'673a10d2ac757.jpg'),(9,9,'To Kill a Mockingbird','Harper Lee',281,'The story of racial injustice and the loss of innocence in the American South, told through the eyes of Scout Finch.','Classic Literature',1,'673a0f6cee8d7.jpg'),(10,10,'The Hobbit','J.R.R. Tolkien',310,'The tale of Bilbo Baggins, who embarks on a quest to help a group of dwarves reclaim their mountain home from a dragon.','Fantasy',1,'673a10909f56d.jpg'),(11,12,'Harry Potter and The Deathly Hollows','J.K. Rowling',510,'Wizards','Fiction',1,'673a0747032cf.jpg');
/*!40000 ALTER TABLE `book` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review`
--

DROP TABLE IF EXISTS `review`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review` (
  `id` int NOT NULL AUTO_INCREMENT,
  `book_id` int NOT NULL,
  `user_id` int NOT NULL,
  `content` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` int NOT NULL,
  `created_at` datetime NOT NULL,
  `approved` tinyint(1) NOT NULL,
  `contains_spoilers` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_794381C616A2B381` (`book_id`),
  KEY `IDX_794381C6A76ED395` (`user_id`),
  CONSTRAINT `FK_794381C616A2B381` FOREIGN KEY (`book_id`) REFERENCES `book` (`id`),
  CONSTRAINT `FK_794381C6A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review`
--

LOCK TABLES `review` WRITE;
/*!40000 ALTER TABLE `review` DISABLE KEYS */;
INSERT INTO `review` VALUES (4,11,12,'Great',10,'2024-11-17 15:22:06',1,0),(5,11,10,'Average',7,'2024-11-17 15:23:10',1,0),(7,11,13,'This is such a great book!',8,'2024-11-17 16:02:53',1,1),(8,6,13,'AA',8,'2024-11-17 16:03:48',1,0),(9,9,12,'aa',8,'2024-11-17 18:25:49',1,1);
/*!40000 ALTER TABLE `review` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notifications` json NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_IDENTIFIER_USERNAME` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (9,'admin2','[\"ROLE_ADMIN\"]','$2y$13$mdMwUVI9B8CjU8hKeYHmh.dKLJGJS8GlUsZFJFbbBLVMdk6ah.3Na',NULL,'[{\"isRead\": false, \"message\": \"Your book has been approved!\"}, {\"isRead\": false, \"message\": \"Your book has been approved!\"}, {\"isRead\": false, \"message\": \"Your book has been approved!\"}]'),(10,'john_reader','[\"ROLE_USER\"]','$2y$13$wz.Qesa6yLIKHSuUUb5N4Ow7L6XuLiiVon2u9Wx5XZVV/a/.OB0dq',NULL,'[{\"isRead\": false, \"message\": \"Your review has been approved!\"}, {\"isRead\": false, \"message\": \"Your book has been approved!\"}, {\"isRead\": false, \"message\": \"Your book has been approved!\"}]'),(11,'jane_bookworm','[\"ROLE_USER\"]','$2y$13$d6Y8DLAWSOnBkjwkym/3ouzWNPPaLTAzsVVS8/aLQeW35csY6ZR0S',NULL,'[{\"isRead\": false, \"message\": \"Your book has been approved!\"}]'),(12,'Rhys','[]','$2y$13$Zju7nLuLaQWmdkYtbQyl8OAYtrqJhBQ8E3RIZShX/4IMGmshKTO12','673a0727454d4.jpg','[{\"isRead\": false, \"message\": \"Your book has been approved!\"}, {\"isRead\": false, \"message\": \"Your review has been approved!\"}, {\"isRead\": false, \"message\": \"Your review has been approved!\"}]'),(13,'admin','[\"ROLE_ADMIN\", \"ROLE_MODERATOR\"]','$2y$13$hf/BOsQyR8o/nopebOloJ.FqRH3IjkGtw.X6033qW9D9R468Vctwu',NULL,'[]');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vote`
--

DROP TABLE IF EXISTS `vote`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vote` (
  `id` int NOT NULL AUTO_INCREMENT,
  `review_id` int NOT NULL,
  `user_id` int NOT NULL,
  `type` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_5A1085643E2E969B` (`review_id`),
  KEY `IDX_5A108564A76ED395` (`user_id`),
  CONSTRAINT `vote_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `review` (`id`),
  CONSTRAINT `vote_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `vote_chk_1` CHECK (`type` IN ('upvote', 'downvote'))
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vote`
--

LOCK TABLES `vote` WRITE;
/*!40000 ALTER TABLE `vote` DISABLE KEYS */;
INSERT INTO `vote` VALUES (1,4,13,'upvote'),(2,5,13,'upvote'),(11,8,9,'downvote'),(13,5,9,'upvote'),(14,7,9,'upvote'),(25,7,13,'upvote'),(26,9,13,'upvote');
/*!40000 ALTER TABLE `vote` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-11-17 18:34:14

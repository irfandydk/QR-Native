-- MySQL dump 10.13  Distrib 26.7.0, for macos27.0 (arm64)
--
-- Host: localhost    Database: qrsar
-- ------------------------------------------------------
-- Server version	26.7.0

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
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '8c9a4a54-bc5d-11f1-8bc2-4ef5e3923038:1-715';

--
-- Table structure for table `AppSettings`
--

DROP TABLE IF EXISTS `AppSettings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AppSettings` (
  `id` int NOT NULL DEFAULT '1',
  `logo_url` longtext COLLATE utf8mb4_unicode_ci,
  `satker_kode` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `satker_nama` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_telp` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `call_sign` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frekuensi_default` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_username` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_password` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_nama` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_nip` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_jabatan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_pangkat` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_no_hp` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_username` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_password` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_nama` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_nip` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_pangkat` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_jabatan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_no_hp` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expiry` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `AppSettings`
--

LOCK TABLES `AppSettings` WRITE;
/*!40000 ALTER TABLE `AppSettings` DISABLE KEYS */;
INSERT INTO `AppSettings` VALUES (1,'/uploads/satker_logo_1791763778.png','SAR-304','KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI','Jl. Kapten Piere Tendean No.91 Kec. Baruga, Kota Kendari, Sulawesi Tenggara.','08114034115','','KANSAR KENDARI','','admin','Kendari2026','irfandydk@gmail.com','Irfandy','199903212020121002','Pranata Pencarian dan Pertolongan Terampil','Pengatur Muda Tk.I (II/b)','085215906543','user','Kendari304','Petugas Siaga SAR','199501012020121001','PENGATUR (II/c)','Operator Komunikasi & Petugas Siaga SAR','operator.kendari@basarnas.go.id','0811-4091-115',NULL,NULL);
/*!40000 ALTER TABLE `AppSettings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `BraSar`
--

DROP TABLE IF EXISTS `BraSar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BraSar` (
  `id_bra` int NOT NULL AUTO_INCREMENT,
  `kategori` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_bra` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nomor_urut` int NOT NULL,
  `satker_kode` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bulan_tahun` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_operasi` int DEFAULT NULL,
  `nama_operasi` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panggilan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jenis` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nr_stn` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `derajat` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instruksi_penerusan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dari` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aksi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `info` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sifat` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menbra` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `perihal` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_waktu` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `twu` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `twb` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_kata` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pengirim` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama_smc` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pangkat_smc` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jabatan_smc` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `twt` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `twk` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sistem` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paraf` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode_data` text COLLATE utf8mb4_unicode_ci,
  `logo_url` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_bra`),
  UNIQUE KEY `idx_bra_no_bra` (`no_bra`),
  KEY `idx_bra_nomor_urut` (`nomor_urut`),
  KEY `idx_bra_operasi` (`id_operasi`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `BraSar`
--

LOCK TABLES `BraSar` WRITE;
/*!40000 ALTER TABLE `BraSar` DISABLE KEYS */;
INSERT INTO `BraSar` VALUES (1,'OPERASI_SAR','191/SAR-304/0926',191,'SAR-304','0926',2,'LAPORAN AWAL OPERASI SAR KONDISI MEMBAHAYAKAN MANUSIA, 1 ORANG HILANG DI HUTAN KENDARI TTK','','','','SGR','','KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI','KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN','DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN','','','LAPORAN AWAL OPERASI SAR KONDISI MEMBAHAYAKAN MANUSIA, 1 ORANG HILANG DI HUTAN KENDARI TTK','23 September 2026','0923 1657 H','0923 1642 H','160 KATA','KANTOR PENCARIAN DAN PERTOLONGAN KENDARI','AMIRUDDIN AS., S.Sos.','PEMBINA (IV/a)','SMC','','','','','http://localhost:3000/?viewBra=1','/uploads/satker_logo_1791763778.png','2026-09-23T08:57:43.542Z');
/*!40000 ALTER TABLE `BraSar` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `BraSection`
--

DROP TABLE IF EXISTS `BraSection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `BraSection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_bra` int NOT NULL,
  `kode` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `BraSection_id_bra_fkey` (`id_bra`),
  CONSTRAINT `BraSection_id_bra_fkey` FOREIGN KEY (`id_bra`) REFERENCES `BraSar` (`id_bra`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `BraSection`
--

LOCK TABLES `BraSection` WRITE;
/*!40000 ALTER TABLE `BraSection` DISABLE KEYS */;
INSERT INTO `BraSection` VALUES (1,1,'AAA','PADA TW 0923 1653 H TERIMA INFO DARI LA ACO (KELUARGA KORBAN / HP 0293013081) BAHWA TELAH TERJADI OPERASI SAR KONDISI MEMBAHAYAKAN MANUSIA, 1 ORANG HILANG DI HUTAN KENDARI DI KENDARI PADA KOORDINAT 04° 05\' 03\" S - 122° 25\' 42\" E TTK'),(2,1,'BBB','TERSEBUT AAA DIATAS MAKA PADA TW 0923 1655 H TIM RESCUE KANSAR KENDARI DIBERANGKATKAN MENUJU LKP DENGAN MENGGUNAKAN 1 UNIT RESCUE CAR DENGAN ETA TW 0923 1751 H TTK'),(3,1,'CCC','UNSUR SAR TERLIBAT TTK DUA\n* TIM RESCUE KANTOR SAR KENDARI : 6 ORANG\n\nALUT DAN PALSAR TTK DUA\n• 1 UNIT RESCUE CAR\n\nKONDISI CUACA TTK DUA\n• CUACA : CERAH\n• ANGIN : -\n• GELOMBANG : -'),(4,1,'DDD','LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI KRM TTK HBS');
/*!40000 ALTER TABLE `BraSection` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `DetailAlut`
--

DROP TABLE IF EXISTS `DetailAlut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DetailAlut` (
  `id_detail_alut` int NOT NULL AUTO_INCREMENT,
  `id_operasi` int NOT NULL,
  `id_alut` int NOT NULL,
  `nama_alut` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `satuan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_alut` int NOT NULL,
  PRIMARY KEY (`id_detail_alut`),
  KEY `DetailAlut_id_operasi_fkey` (`id_operasi`),
  CONSTRAINT `DetailAlut_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `DetailAlut`
--

LOCK TABLES `DetailAlut` WRITE;
/*!40000 ALTER TABLE `DetailAlut` DISABLE KEYS */;
INSERT INTO `DetailAlut` VALUES (1,1,1,'Rescue Car','Unit',1),(3,1,8,'Aqua Eye','Set',1),(6,1,0,'RIB (Rigid Inflatable Boat)','Unit',1),(10,1,0,'Palsar Evakuasi & Tandu','Set',1),(15,1,0,'Drone Thermal / Surveillance','Unit',1),(21,1,0,'Palsar Komunikasi & Alkom','Set',1),(28,1,0,'Palsar Medis (First Aid Kit)','Set',1),(36,1,0,'Aqua Eye (Sonar Scanner)','Unit',1),(45,1,0,'Peralatan Selam (Diving Gear)','Set',1),(46,2,1,'Rescue Car','Unit',1);
/*!40000 ALTER TABLE `DetailAlut` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `DetailTimBerangkat`
--

DROP TABLE IF EXISTS `DetailTimBerangkat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DetailTimBerangkat` (
  `id_detail_tim` int NOT NULL AUTO_INCREMENT,
  `id_operasi` int NOT NULL,
  `id_tim` int NOT NULL,
  `nama_tim` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_detail_tim`),
  KEY `DetailTimBerangkat_id_operasi_fkey` (`id_operasi`),
  CONSTRAINT `DetailTimBerangkat_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `DetailTimBerangkat`
--

LOCK TABLES `DetailTimBerangkat` WRITE;
/*!40000 ALTER TABLE `DetailTimBerangkat` DISABLE KEYS */;
INSERT INTO `DetailTimBerangkat` VALUES (1,1,1,'Tim Rescue Kansar Kendari'),(2,2,1,'Tim Rescue Kansar Kendari');
/*!40000 ALTER TABLE `DetailTimBerangkat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `DetailUnsur`
--

DROP TABLE IF EXISTS `DetailUnsur`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `DetailUnsur` (
  `id_detail_unsur` int NOT NULL AUTO_INCREMENT,
  `id_operasi` int NOT NULL,
  `nama_unsur` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_personil` int NOT NULL,
  PRIMARY KEY (`id_detail_unsur`),
  KEY `DetailUnsur_id_operasi_fkey` (`id_operasi`),
  CONSTRAINT `DetailUnsur_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `DetailUnsur`
--

LOCK TABLES `DetailUnsur` WRITE;
/*!40000 ALTER TABLE `DetailUnsur` DISABLE KEYS */;
INSERT INTO `DetailUnsur` VALUES (1,1,'Staf SMC',3),(3,1,'Tim Rescue Kansar Kendari',7),(6,1,'Polsek & Koramil Setempat',4),(7,2,'Tim Rescue Kantor SAR Kendari',6);
/*!40000 ALTER TABLE `DetailUnsur` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `GisCustomIcon`
--

DROP TABLE IF EXISTS `GisCustomIcon`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GisCustomIcon` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createdAt` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `GisCustomIcon`
--

LOCK TABLES `GisCustomIcon` WRITE;
/*!40000 ALTER TABLE `GisCustomIcon` DISABLE KEYS */;
/*!40000 ALTER TABLE `GisCustomIcon` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `GisPoint`
--

DROP TABLE IF EXISTS `GisPoint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GisPoint` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_operasi` int DEFAULT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pointType` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customIconUrl` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Tanpa Kategori',
  `alphabetChar` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'A',
  PRIMARY KEY (`id`),
  KEY `idx_point_type` (`pointType`),
  KEY `idx_point_cat` (`category`),
  KEY `idx_point_operasi` (`id_operasi`),
  CONSTRAINT `GisPoint_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `GisPoint`
--

LOCK TABLES `GisPoint` WRITE;
/*!40000 ALTER TABLE `GisPoint` DISABLE KEYS */;
INSERT INTO `GisPoint` VALUES ('pt-kansar',NULL,-4.0378881054100555,122.48664130561696,'Kantor SAR Kendari','custom_upload',NULL,'Pusat Komando KPP Kendari, Jl. Bandara Haluoleo','Kantor SAR','A');
/*!40000 ALTER TABLE `GisPoint` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `GisPolygon`
--

DROP TABLE IF EXISTS `GisPolygon`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GisPolygon` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_operasi` int DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `strokeColor` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `strokeWeight` double NOT NULL,
  `fillColor` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fillOpacity` double NOT NULL,
  `shapeType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'polygon',
  `centerLat` double DEFAULT NULL,
  `centerLng` double DEFAULT NULL,
  `radiusNm` double DEFAULT NULL,
  `boxWidthNm` double DEFAULT NULL,
  `boxHeightNm` double DEFAULT NULL,
  `areaNm2` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_poly_operasi` (`id_operasi`),
  CONSTRAINT `GisPolygon_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `GisPolygon`
--

LOCK TABLES `GisPolygon` WRITE;
/*!40000 ALTER TABLE `GisPolygon` DISABLE KEYS */;
INSERT INTO `GisPolygon` (`id`, `name`, `strokeColor`, `strokeWeight`, `fillColor`, `fillOpacity`) VALUES ('poly-default-alpha','Search Area Sektor Kendari - Saponda','#ea580c',2.5,'#ea580c',0.18);
/*!40000 ALTER TABLE `GisPolygon` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `GisPolygonPoint`
--

DROP TABLE IF EXISTS `GisPolygonPoint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GisPolygonPoint` (
  `id` int NOT NULL AUTO_INCREMENT,
  `polygonId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `GisPolygonPoint_polygonId_fkey` (`polygonId`),
  CONSTRAINT `GisPolygonPoint_polygonId_fkey` FOREIGN KEY (`polygonId`) REFERENCES `GisPolygon` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `GisPolygonPoint`
--

LOCK TABLES `GisPolygonPoint` WRITE;
/*!40000 ALTER TABLE `GisPolygonPoint` DISABLE KEYS */;
INSERT INTO `GisPolygonPoint` VALUES (1,'poly-default-alpha',-3.85,122.65,'Titik A'),(2,'poly-default-alpha',-3.85,122.88,'Titik B'),(3,'poly-default-alpha',-4.05,122.88,'Titik C'),(4,'poly-default-alpha',-4.05,122.65,'Titik D');
/*!40000 ALTER TABLE `GisPolygonPoint` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `GisRoute`
--

DROP TABLE IF EXISTS `GisRoute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GisRoute` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_operasi` int DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vesselSpeedKnots` double NOT NULL,
  `strokeColor` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `strokeWeight` double NOT NULL,
  `strokeOpacity` double NOT NULL,
  `createdAt` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_route_operasi` (`id_operasi`),
  CONSTRAINT `GisRoute_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `GisRoute`
--

LOCK TABLES `GisRoute` WRITE;
/*!40000 ALTER TABLE `GisRoute` DISABLE KEYS */;
INSERT INTO `GisRoute` VALUES ('route_sample_1',NULL,'Rute Kansar ke LKP Sumber Bahagia',25,'#059669',3.5,0.9,'Default Rute Siaga');
/*!40000 ALTER TABLE `GisRoute` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `GisRoutePoint`
--

DROP TABLE IF EXISTS `GisRoutePoint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GisRoutePoint` (
  `id` int NOT NULL AUTO_INCREMENT,
  `routeId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `GisRoutePoint_routeId_fkey` (`routeId`),
  CONSTRAINT `GisRoutePoint_routeId_fkey` FOREIGN KEY (`routeId`) REFERENCES `GisRoute` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `GisRoutePoint`
--

LOCK TABLES `GisRoutePoint` WRITE;
/*!40000 ALTER TABLE `GisRoutePoint` DISABLE KEYS */;
INSERT INTO `GisRoutePoint` VALUES (1,'route_sample_1',-4.015,122.585,'WP-1 (Kansar)'),(2,'route_sample_1',-3.85,122.75,'WP-2 (LKP)');
/*!40000 ALTER TABLE `GisRoutePoint` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `GisSearchPattern`
--

DROP TABLE IF EXISTS `GisSearchPattern`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GisSearchPattern` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_operasi` int DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `polygonId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `polygonName` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patternType` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trackSpacingNm` double NOT NULL,
  `legsCount` int NOT NULL,
  `poskoId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `poskoName` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `opacity` double DEFAULT '0.9',
  `strokeWeight` double DEFAULT '3',
  `totalDistanceNm` double NOT NULL,
  `createdAt` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pattern_operasi` (`id_operasi`),
  CONSTRAINT `GisSearchPattern_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `GisSearchPattern`
--

LOCK TABLES `GisSearchPattern` WRITE;
/*!40000 ALTER TABLE `GisSearchPattern` DISABLE KEYS */;
INSERT INTO `GisSearchPattern` (`id`, `id_operasi`, `name`, `polygonId`, `polygonName`, `patternType`, `trackSpacingNm`, `legsCount`, `poskoId`, `poskoName`, `color`, `totalDistanceNm`, `createdAt`) VALUES ('pattern-sample-alpha',NULL,'Pola Parallel Track - Search Area Kendari','poly-default-alpha','Search Area Sektor Kendari - Saponda','parallel_track',2,5,'pt-kansar','Kantor SAR Kendari','#0284c7',58.4,'2026-09-22 08:00 WITA');
/*!40000 ALTER TABLE `GisSearchPattern` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `GisSearchPatternWaypoint`
--

DROP TABLE IF EXISTS `GisSearchPatternWaypoint`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `GisSearchPatternWaypoint` (
  `id` int NOT NULL AUTO_INCREMENT,
  `searchPatternId` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `GisSearchPatternWaypoint_searchPatternId_fkey` (`searchPatternId`),
  CONSTRAINT `GisSearchPatternWaypoint_searchPatternId_fkey` FOREIGN KEY (`searchPatternId`) REFERENCES `GisSearchPattern` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `GisSearchPatternWaypoint`
--

LOCK TABLES `GisSearchPatternWaypoint` WRITE;
/*!40000 ALTER TABLE `GisSearchPatternWaypoint` DISABLE KEYS */;
INSERT INTO `GisSearchPatternWaypoint` VALUES (1,'pattern-sample-alpha',-3.87,122.67,'Entry S (Kansar)'),(2,'pattern-sample-alpha',-3.87,122.86,'Leg 1B'),(3,'pattern-sample-alpha',-3.91,122.86,'Leg 2A'),(4,'pattern-sample-alpha',-3.91,122.67,'Leg 2B'),(5,'pattern-sample-alpha',-3.95,122.67,'Leg 3A'),(6,'pattern-sample-alpha',-3.95,122.86,'Leg 3B'),(7,'pattern-sample-alpha',-3.99,122.86,'Leg 4A'),(8,'pattern-sample-alpha',-3.99,122.67,'Leg 4B'),(9,'pattern-sample-alpha',-4.03,122.67,'Leg 5A'),(10,'pattern-sample-alpha',-4.03,122.86,'Exit E (Selesai)');
/*!40000 ALTER TABLE `GisSearchPatternWaypoint` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Korban`
--

DROP TABLE IF EXISTS `Korban`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Korban` (
  `id_korban` int NOT NULL AUTO_INCREMENT,
  `id_operasi` int NOT NULL,
  `nama_korban` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `umur` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jk` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_korban` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_korban`),
  KEY `Korban_id_operasi_fkey` (`id_operasi`),
  CONSTRAINT `Korban_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Korban`
--

LOCK TABLES `Korban` WRITE;
/*!40000 ALTER TABLE `Korban` DISABLE KEYS */;
INSERT INTO `Korban` (`id_korban`, `id_operasi`, `nama_korban`, `umur`, `jk`, `alamat`, `status_korban`) VALUES (1,1,'lapuko','25 tahun','Laki-laki','kendari','S'),(2,2,'La Gambang','25 Tahun','Laki-laki','Kendari','DP');
/*!40000 ALTER TABLE `Korban` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `LaporanHarian`
--

DROP TABLE IF EXISTS `LaporanHarian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `LaporanHarian` (
  `id_laporan` int NOT NULL AUTO_INCREMENT,
  `id_operasi` int NOT NULL,
  `hari_ke` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_laporan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isi_laporan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_debrief` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuaca` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unsur_text` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alut_text` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_unsur` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_alut` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_text_wa` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_laporan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_laporan`),
  KEY `LaporanHarian_id_operasi_fkey` (`id_operasi`),
  CONSTRAINT `LaporanHarian_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `LaporanHarian`
--

LOCK TABLES `LaporanHarian` WRITE;
/*!40000 ALTER TABLE `LaporanHarian` DISABLE KEYS */;
INSERT INTO `LaporanHarian` (`id_laporan`, `id_operasi`, `hari_ke`, `jenis_laporan`, `isi_laporan`, `waktu_debrief`, `full_text_wa`, `waktu_laporan`) VALUES (1,1,'H.1','PERKEMBANGAN','Pada pelaksanaan operasi SAR hari pertama (H.1), Tim SAR Gabungan telah melaksanakan pergerakan dan penyisiran baik melalui jalur perairan maupun pemantauan darat sesuai dengan Rencana Operasi (Renops) yang telah ditetapkan di sekitar perairan Kendari. Dari rangkaian upaya pencarian tersebut, Tim SAR Gabungan berhasil menemukan korban dalam keadaan selamat di area sekitar Jembatan Bahteramas. Korban kemudian langsung dievakuasi dan diserahterimakan kepada pihak keluarga dalam kondisi stabil. Sehubungan dengan telah ditemukannya korban, maka rencana pergerakan operasi pencarian dihentikan, seluruh rangkaian Operasi SAR diusulkan untuk ditutup, dan seluruh unsur potensi SAR yang terlibat dikembalikan ke kesatuan masing-masing.',NULL,'FILE AKTIF\n____\n\nSEMANGAT QUICK ACTION SEARCH AND RESCUE\nSATU JIWA SATU RASA\nWE ARE FAMILY\nPROFESIONAL MODERN TERUJI\nLAPBANG OPS SAR H.1\n\nKANTOR PENCARIAN DAN PERTOLONGAN KENDARI\nSenin, 21 September 2026\n\nYTH : KABASARNAS\nCC : PIMTI MADYA & PRATAMA\n\nDengan hormat, mohon ijin melaporkan Perkembangan Ops operasi sar kecelakaan kapal di kendari.\n__________________________________________________\n\nHasil Ops SAR H.1:\n\nPada pelaksanaan operasi SAR hari pertama (H.1), Tim SAR Gabungan telah melaksanakan pergerakan dan penyisiran baik melalui jalur perairan maupun pemantauan darat sesuai dengan Rencana Operasi (Renops) yang telah ditetapkan di sekitar perairan Kendari. Dari rangkaian upaya pencarian tersebut, Tim SAR Gabungan berhasil menemukan korban dalam keadaan selamat di area sekitar Jembatan Bahteramas. Korban kemudian langsung dievakuasi dan diserahterimakan kepada pihak keluarga dalam kondisi stabil. Sehubungan dengan telah ditemukannya korban, maka rencana pergerakan operasi pencarian dihentikan, seluruh rangkaian Operasi SAR diusulkan untuk ditutup, dan seluruh unsur potensi SAR yang terlibat dikembalikan ke kesatuan masing-masing.\n\n1. Data Korban: 1 Orang\n• S : 1 ORANG\n• MD : 0 ORANG\n• DP : 0 ORANG\n• H : 0 ORANG\n\n2. Identitas Korban\n1. Nama : lapuko [Status: Selamat (S)]\n   Umur : 25 tahun\n   Jenis Kelamin : Laki-laki\n   Alamat : kendari\n\n3. Unsur SAR Terlibat\n• Staf SMC : 3 Orang\n• Tim Rescue Kansar Kendari : 7 Orang\n• Polsek & Koramil Setempat : 4 Orang\n\n4. Alut dan Palsar\n• 1 Unit Rescue Car\n• 1 Set Aqua Eye\n• 1 Unit RIB (Rigid Inflatable Boat)\n• 1 Set Palsar Evakuasi & Tandu\n• 1 Unit Drone Thermal / Surveillance\n• 1 Set Palsar Komunikasi & Alkom\n• 1 Set Palsar Medis (First Aid Kit)\n• 1 Unit Aqua Eye (Sonar Scanner)\n• 1 Set Peralatan Selam (Diving Gear)\n\nDemikian kami laporkan, mohon petunjuk dan arahan\n\nTtd\n\nKKS Kendari\n===========================\n🏢 Kantor Pencarian Dan Pertolongan Kendari\n📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116\n☎️ Telp. : (0401) 3196557\n📠 Fax. : (0401) 3196558\n📱 WhatsApp : +62811-4034-115\n🚨 Emergency Call : 115\n🌐 Website : Www.Kendari.Basarnas.go.id\n📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id\n📲 Medsos : @Basarnaskendari','2026-09-21T00:47:00.000Z'),(2,2,'H.7','PENUTUPAN','Tim SAR Gabungan telah melaksanakan operasi pencarian hari ketujuh (H.7) terhadap satu orang korban yang dilaporkan hilang di kawasan Hutan Kendari dengan mengacu pada Rencana Operasi (Renops) yang telah ditetapkan. Operasi difokuskan melalui pembagian Search and Rescue Unit (SRU) untuk melakukan penyisiran darat pada jalur hutan bervegetasi lebat serta penyisiran aliran air di sekitar sektor area pencarian yang dicurigai. Hingga pelaksanaan operasi hari ini dihentikan sementara pada petang hari, upaya pencarian masih memberikan hasil NIHIL dan belum ditemukan tanda-tanda keberadaan korban. Mengingat operasi telah memasuki hari ketujuh sesuai dengan standar operasional prosedur penanganan kecelakaan dan kondisi membahayakan manusia, SMC selanjutnya menggelar debriefing dan evaluasi menyeluruh bersama unsur SAR gabungan serta pihak keluarga korban guna menentukan tindak lanjut dan keputusan penutupan atau perpanjangan status Operasi SAR.','2026-09-28T23:14:00.000Z','FILE AKTIF\n____\n\nSEMANGAT QUICK ACTION SEARCH AND RESCUE\nSATU JIWA SATU RASA\nWE ARE FAMILY\nPROFESIONAL MODERN TERUJI\nLAPBANG OPS SAR H.7 DAN PENUTUPAN\n\nKANTOR PENCARIAN DAN PERTOLONGAN KENDARI\nSelasa, 29 September 2026\n\nYTH : KABASARNAS\nCC : PIMTI MADYA & PRATAMA\n\nDengan hormat, mohon ijin melaporkan Perkembangan Ops Operasi SAR Kondisi Membahayakan manusia, 1 Orang Hilang di Hutan Kendari.\n__________________________________________________\n\nHasil Ops SAR H.7:\n\nTim SAR Gabungan telah melaksanakan operasi pencarian hari ketujuh (H.7) terhadap satu orang korban yang dilaporkan hilang di kawasan Hutan Kendari dengan mengacu pada Rencana Operasi (Renops) yang telah ditetapkan. Operasi difokuskan melalui pembagian Search and Rescue Unit (SRU) untuk melakukan penyisiran darat pada jalur hutan bervegetasi lebat serta penyisiran aliran air di sekitar sektor area pencarian yang dicurigai. Hingga pelaksanaan operasi hari ini dihentikan sementara pada petang hari, upaya pencarian masih memberikan hasil NIHIL dan belum ditemukan tanda-tanda keberadaan korban. Mengingat operasi telah memasuki hari ketujuh sesuai dengan standar operasional prosedur penanganan kecelakaan dan kondisi membahayakan manusia, SMC selanjutnya menggelar debriefing dan evaluasi menyeluruh bersama unsur SAR gabungan serta pihak keluarga korban guna menentukan tindak lanjut dan keputusan penutupan atau perpanjangan status Operasi SAR.\n\n• Pada tanggal 29 September 2026 Pukul 07:14 WITA, Tim SAR Gabungan melakukan debriefing penutupan Ops SAR terhadap Operasi SAR Kondisi Membahayakan manusia, 1 Orang Hilang di Hutan Kendari, selanjutnya operasi SAR diusulkan ditutup dan unsur SAR yang terlibat kembali ke kesatuannya masing-masing dengan ucapan terima kasih.\n\n1. Data Korban: 1 Orang\n• S : 0 ORANG\n• MD : 0 ORANG\n• DP : 1 ORANG\n• H : 0 ORANG\n\n2. Identitas Korban\n1. Nama : La Gambang [Status: Dalam Pencarian (DP)]\n   Umur : 25 Tahun\n   Jenis Kelamin : Laki-laki\n   Alamat : Kendari\n\n3. Unsur SAR Terlibat\n• Tim Rescue Kantor SAR Kendari : 6 Orang\n• Damkar & Penyelamatan : 4 Orang\n• Keluarga Korban : 4 Orang\n• Dinas Kesehatan / Puskesmas : 4 Orang\n\n4. Alut dan Palsar\n• 1 Unit Rescue Car\n• 1 Unit Underwater Search Device (UWSD)\n• 1 Set Palsar Komunikasi & Alkom\n• 2 Unit Longboat / Perahu Masyarakat\n\nDemikian kami laporkan, mohon petunjuk dan arahan\n\nTtd\n\nKKS Kendari\n===========================\n🏢 Kantor Pencarian Dan Pertolongan Kendari\n📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116\n☎️ Telp. : (0401) 3196557\n📠 Fax. : (0401) 3196558\n📱 WhatsApp : +62811-4034-115\n🚨 Emergency Call : 115\n🌐 Website : Www.Kendari.Basarnas.go.id\n📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id\n📲 Medsos : @Basarnaskendari','2026-09-28T23:14:00.000Z');
/*!40000 ALTER TABLE `LaporanHarian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `MasterAlut`
--

DROP TABLE IF EXISTS `MasterAlut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MasterAlut` (
  `id_alut` int NOT NULL AUTO_INCREMENT,
  `nama_alut` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `satuan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_alut`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `MasterAlut`
--

LOCK TABLES `MasterAlut` WRITE;
/*!40000 ALTER TABLE `MasterAlut` DISABLE KEYS */;
INSERT INTO `MasterAlut` (`id_alut`, `nama_alut`, `satuan`) VALUES (1,'Rescue Car','Unit'),(2,'Rescue Carrier','Unit'),(3,'RB 210','Unit'),(4,'RB 307','Unit'),(5,'RIB','Unit'),(6,'Rubber Boat','Unit'),(7,'Longboat Masyarakat','Unit'),(8,'Aqua Eye','Set'),(9,'Drone Thermal','Unit'),(10,'Drone Underwater','Unit'),(11,'Palsar Medis','Set'),(12,'Palsar Evakuasi','Set'),(13,'Palsar Komunikasi','Set'),(14,'Palsar Selam','Set'),(15,'Palsar Mounteneering','Set'),(16,'Kantong Jenazah','Lembar');
/*!40000 ALTER TABLE `MasterAlut` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `MasterTim`
--

DROP TABLE IF EXISTS `MasterTim`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `MasterTim` (
  `id_tim` int NOT NULL AUTO_INCREMENT,
  `nama_tim` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_tim`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `MasterTim`
--

LOCK TABLES `MasterTim` WRITE;
/*!40000 ALTER TABLE `MasterTim` DISABLE KEYS */;
INSERT INTO `MasterTim` (`id_tim`, `nama_tim`) VALUES (1,'Tim Rescue Kansar Kendari'),(2,'Pos SAR Kolaka'),(3,'Pos SAR Bau Bau'),(4,'Pos SAR Wakatobi'),(5,'Pos SAR Konawe Utara'),(6,'Unit Siaga SAR Muna'),(7,'Unit Siaga SAR Konawe'),(8,'Unit Siaga SAR Kolaka Utara'),(9,'ABK KN SAR Pacitan'),(10,'ABK RB 210'),(11,'ABK RB 307');
/*!40000 ALTER TABLE `MasterTim` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Operasi`
--

DROP TABLE IF EXISTS `Operasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Operasi` (
  `id_operasi` int NOT NULL AUTO_INCREMENT,
  `nama_operasi` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_kejadian` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_pelapor` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hubungan_pelapor` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `no_telp_pelapor` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_kejadian` datetime NOT NULL,
  `waktu_lapor` datetime NOT NULL,
  `waktu_berangkat` datetime NOT NULL,
  `tim_berangkat` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `eta_tiba` datetime NOT NULL,
  `lokasi_kejadian` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `koordinat` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jarak` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `radial` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eta_lokasi` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kronologi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_pob` int NOT NULL,
  `unsur_terlibat` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `alut_palsar` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `faktor_penghambat` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuaca` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gelombang` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `angin` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `waktu_reopen` datetime DEFAULT NULL,
  `catatan_reopen` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hari_sebelum_reopen` int DEFAULT NULL,
  `full_text_wa` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_operasi`),
  KEY `idx_ops_status` (`status`),
  KEY `idx_ops_waktu` (`waktu_kejadian`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Operasi`
--

LOCK TABLES `Operasi` WRITE;
/*!40000 ALTER TABLE `Operasi` DISABLE KEYS */;
INSERT INTO `Operasi` (`id_operasi`, `nama_operasi`, `nama_pelapor`, `hubungan_pelapor`, `no_telp_pelapor`, `waktu_kejadian`, `waktu_lapor`, `waktu_berangkat`, `tim_berangkat`, `eta_tiba`, `lokasi_kejadian`, `koordinat`, `jarak`, `eta_lokasi`, `kronologi`, `jumlah_pob`, `unsur_terlibat`, `alut_palsar`, `faktor_penghambat`, `cuaca`, `gelombang`, `angin`, `status`, `waktu_selesai`, `full_text_wa`, `created_at`) VALUES (1,'operasi sar kecelakaan kapal di kendari','konawe','Masyarakat','9020310293019','2026-09-15T00:37:00.000Z','2026-09-21T00:38:00.000Z','2026-09-21T00:43:00.000Z','Tim Rescue Kansar Kendari','2026-09-21T01:37:00.000Z','Kendari','03° 58\' 30\" S - 122° 35\' 12\" E','16 Km','1 Jam','Pada 20 September 2026 pukul 08.37 WITA, korban dilaporkan pergi melaut untuk memancing ikan menggunakan 1 (satu) unit longboat di sekitar perairan Bungkutoko. Korban yang seharusnya telah kembali hingga saat ini belum tiba dan dinyatakan hilang kontak di sekitar LKP (Last Known Position). Upaya pencarian telah dilakukan secara mandiri oleh pihak keluarga bersama warga setempat di area perkiraan kejadian, namun korban belum berhasil ditemukan dengan hasil nihil. Selanjutnya, pihak keluarga melaporkan kejadian tersebut ke Kantor Pencarian dan Pertolongan (KPP) Kendari guna memohon bantuan SAR.',1,'Tim Rescue Kansar Kendari (7 Org), Staf SMC (3 Org)','1 Unit Rescue Car, 1 Set Aqua Eye, 1 Unit RIB (Rigid Inflatable Boat), 1 Set Palsar Evakuasi & Tandu, 1 Unit Drone Thermal / Surveillance, 1 Set Palsar Komunikasi & Alkom, 1 Set Palsar Medis (First Aid Kit), 1 Unit Aqua Eye (Sonar Scanner), 1 Set Peralatan Selam (Diving Gear)','Nihil','Cerah Berawan','0.1 - 0.5 M','Barat Daya - Barat Laut, 5 - 15 Knots','CLOSED','2026-09-28T16:00:00.000Z','FILE AKTIF\n____\n\nSEMANGAT QUICK ACTION SEARCH AND RESCUE\nSATU JIWA SATU RASA\nWE ARE FAMILY\nPROFESIONAL MODERN TERUJI\nLAPORAN AWAL OPS SAR\n\nKANTOR PENCARIAN DAN PERTOLONGAN KENDARI\nSenin, 21 September 2026\n\nYTH : KABASARNAS\nCC : PIMTI MADYA & PRATAMA\n\nDengan hormat, mohon ijin melaporkan operasi sar kecelakaan kapal di kendari.\n__________________________________________________\n\n1. Sumber Info kejadian :\nkonawe (Masyarakat / No. HP 9020310293019)\nPada Tanggal 21 September 2026 pukul 08:38 Wita\n\n2. Lokasi Kejadian :\nLokasi : Kendari\nKoordinat : 03° 58\' 30\" S - 122° 35\' 12\" E\njarak tempuh : 16 Km\nEstimasi waktu tempuh : 1 Jam\n\n3. Pelaksanaan SAR\nPada Tanggal 21 September 2026 pukul 08:43 Wita, Tim Rescue Kansar Kendari berangkat ke LKP untuk melaksanakan Operasi SAR, dengan estimasi waktu tempuh ETA 21 September 2026 pukul 09:37 WITA.\n\n4. Kronologi Kejadian\nPada 20 September 2026 pukul 08.37 WITA, korban dilaporkan pergi melaut untuk memancing ikan menggunakan 1 (satu) unit longboat di sekitar perairan Bungkutoko. Korban yang seharusnya telah kembali hingga saat ini belum tiba dan dinyatakan hilang kontak di sekitar LKP (Last Known Position). Upaya pencarian telah dilakukan secara mandiri oleh pihak keluarga bersama warga setempat di area perkiraan kejadian, namun korban belum berhasil ditemukan dengan hasil nihil. Selanjutnya, pihak keluarga melaporkan kejadian tersebut ke Kantor Pencarian dan Pertolongan (KPP) Kendari guna memohon bantuan SAR.\n\n5. Data Korban: 1 orang\n• S : 0 orang\n• MD : 0 orang\n• DP : 1 orang\n• H : 0 orang\n\nIdentitas Korban :\n1. Nama : lapuko\n   Umur : 25 tahun\n   Jenis Kelamin : Laki-laki\n   Alamat : kendari\n\n6. Unsur SAR Terlibat\n• Tim Rescue Kansar Kendari (7 Org)\n• Staf SMC (3 Org)\n\n7. Alut dan Palsar\n• 1 Unit Rescue Car\n• 1 Set Aqua Eye\n• 1 Unit RIB (Rigid Inflatable Boat)\n• 1 Set Palsar Evakuasi & Tandu\n• 1 Unit Drone Thermal / Surveillance\n• 1 Set Palsar Komunikasi & Alkom\n• 1 Set Palsar Medis (First Aid Kit)\n• 1 Unit Aqua Eye (Sonar Scanner)\n• 1 Set Peralatan Selam (Diving Gear)\n\n8. Faktor Penghambat\nNihil\n\n9. Kondisi Cuaca :\n• cuaca : Cerah Berawan\n• Gelombang : 0.1 - 0.5 M\n• Angin: Barat Daya - Barat Laut, 5 - 15 Knots\n\nDemikian kami laporkan, mohon petunjuk dan arahan\n\nTtd\n\nKKS Kendari\n===========================\n🏢 Kantor Pencarian Dan Pertolongan Kendari\n📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116\n☎️ Telp. : (0401) 3196557\n📠 Fax. : (0401) 3196558\n📱 WhatsApp : +62811-4034-115\n🚨 Emergency Call : 115\n🌐 Website : Www.Kendari.Basarnas.go.id\n📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id\n📲 Medsos : @Basarnaskendari','2026-09-21T00:43:22.131Z'),(2,'Operasi SAR Kondisi Membahayakan manusia, 1 Orang Hilang di Hutan Kendari','La ACO','Keluarga Korban','0293013081','2026-09-21T08:52:00.000Z','2026-09-23T08:53:00.000Z','2026-09-23T08:55:00.000Z','Tim Rescue Kansar Kendari','2026-09-23T09:51:00.000Z','Kendari','04° 05\' 03\" S - 122° 25\' 42\" E','20 Km','1 Jam','Pada hari Senin, 21 September 2026 sekitar pukul 16.52 WITA, telah terjadi Kondisi Membahayakan Manusia (KMM) terhadap 1 (satu) orang yang dilaporkan hilang di kawasan Hutan Kendari. Berdasarkan informasi yang dihimpun, korban melarikan diri ke dalam area hutan setelah dikejar oleh warga setempat, dengan titik terakhir korban terlihat ditetapkan sebagai Last Known Position (LKP). Upaya pencarian mandiri telah dilakukan oleh warga setempat di sekitar LKP, namun hingga saat ini korban hilang kontak dan belum berhasil ditemukan, sehingga kejadian tersebut dilaporkan ke KPP Kendari guna penanganan Operasi SAR lebih lanjut.',1,'Tim Rescue Kantor SAR Kendari (6 Org)','1 Unit Rescue Car','Nihil','Cerah','-','-','CLOSED','2026-09-28T23:14:00.000Z','FILE AKTIF\n____\n\nSEMANGAT QUICK ACTION SEARCH AND RESCUE\nSATU JIWA SATU RASA\nWE ARE FAMILY\nPROFESIONAL MODERN TERUJI\nLAPORAN AWAL OPS SAR\n\nKANTOR PENCARIAN DAN PERTOLONGAN KENDARI\nRabu, 23 September 2026\n\nYTH : KABASARNAS\nCC : PIMTI MADYA & PRATAMA\n\nDengan hormat, mohon ijin melaporkan Operasi SAR Kondisi Membahayakan manusia, 1 Orang Hilang di Hutan Kendari.\n__________________________________________________\n\n1. Sumber Info kejadian :\nLa ACO (Keluarga Korban / No. HP 0293013081)\nPada Tanggal 23 September 2026 pukul 16:53 Wita\n\n2. Lokasi Kejadian :\nLokasi : Kendari\nKoordinat : 04° 05\' 03\" S - 122° 25\' 42\" E\njarak tempuh : 20 Km\nEstimasi waktu tempuh : 1 Jam\n\n3. Pelaksanaan SAR\nPada Tanggal 23 September 2026 pukul 16:55 Wita, Tim Rescue Kansar Kendari berangkat ke LKP untuk melaksanakan Operasi SAR, dengan estimasi waktu tempuh ETA 23 September 2026 pukul 17:51 WITA.\n\n4. Kronologi Kejadian\nPada hari Senin, 21 September 2026 sekitar pukul 16.52 WITA, telah terjadi Kondisi Membahayakan Manusia (KMM) terhadap 1 (satu) orang yang dilaporkan hilang di kawasan Hutan Kendari. Berdasarkan informasi yang dihimpun, korban melarikan diri ke dalam area hutan setelah dikejar oleh warga setempat, dengan titik terakhir korban terlihat ditetapkan sebagai Last Known Position (LKP). Upaya pencarian mandiri telah dilakukan oleh warga setempat di sekitar LKP, namun hingga saat ini korban hilang kontak dan belum berhasil ditemukan, sehingga kejadian tersebut dilaporkan ke KPP Kendari guna penanganan Operasi SAR lebih lanjut.\n\n5. Data Korban: 1 orang\n• S : 0 orang\n• MD : 0 orang\n• DP : 1 orang\n• H : 0 orang\n\nIdentitas Korban :\n1. Nama : La Gambang\n   Umur : 25 Tahun\n   Jenis Kelamin : Laki-laki\n   Alamat : Kendari\n\n6. Unsur SAR Terlibat\n• Tim Rescue Kantor SAR Kendari (6 Org)\n\n7. Alut dan Palsar\n• 1 Unit Rescue Car\n\n8. Faktor Penghambat\nNihil\n\n9. Kondisi Cuaca :\n• cuaca : Cerah\n• Gelombang : -\n• Angin: -\n\nDemikian kami laporkan, mohon petunjuk dan arahan\n\nTtd\n\nKKS Kendari\n===========================\n🏢 Kantor Pencarian Dan Pertolongan Kendari\n📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116\n☎️ Telp. : (0401) 3196557\n📠 Fax. : (0401) 3196558\n📱 WhatsApp : +62811-4034-115\n🚨 Emergency Call : 115\n🌐 Website : Www.Kendari.Basarnas.go.id\n📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id\n📲 Medsos : @Basarnaskendari','2026-09-23T08:56:00.549Z');
/*!40000 ALTER TABLE `Operasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Pejabat`
--

DROP TABLE IF EXISTS `Pejabat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Pejabat` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nip` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pangkat` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jabatan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default_smc` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Pejabat`
--

LOCK TABLES `Pejabat` WRITE;
/*!40000 ALTER TABLE `Pejabat` DISABLE KEYS */;
INSERT INTO `Pejabat` (`id`, `nama`, `nip`, `pangkat`, `jabatan`, `is_default_smc`) VALUES ('pej-1790153950434-aeyl','Amiruddin A.S., S.Sos.','197707142002121003','Pembina (IV/a)','Kepala Kantor Pencarian dan Pertolongan Kelas A Kendari',1),('pej-1790154013879-bbd8','Syaiful Wijanarta,S.E','198406232005021001','Penata Tk.I (III/d)','Kepala Seksi Operasi dan Siaga',0);
/*!40000 ALTER TABLE `Pejabat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Renops`
--

DROP TABLE IF EXISTS `Renops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Renops` (
  `id_renops` int NOT NULL AUTO_INCREMENT,
  `id_operasi` int NOT NULL,
  `hari_ke` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_laporan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_briefing` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `waktu_mulai_ops` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuaca` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unsur_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `alut_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail_unsur` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detail_alut` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_text_wa` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_renops`),
  KEY `Renops_id_operasi_fkey` (`id_operasi`),
  CONSTRAINT `Renops_id_operasi_fkey` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi` (`id_operasi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Renops`
--

LOCK TABLES `Renops` WRITE;
/*!40000 ALTER TABLE `Renops` DISABLE KEYS */;
INSERT INTO `Renops` (`id_renops`, `id_operasi`, `hari_ke`, `tanggal`, `waktu_briefing`, `waktu_bergerak`, `cuaca`, `unsur_text`, `alut_text`, `full_text_wa`, `created_at`) VALUES (1,1,'H.2','2026-09-22','2026-09-21T23:00:00.000Z','2026-09-21T23:30:00.000Z','Cerah Berawan, Hujan Ringan','','','FILE AKTIF\n____\n\nSEMANGAT QUICK ACTION SEARCH AND RESCUE\nSATU JIWA SATU RASA\nWE ARE FAMILY\nPROFESIONAL MODERN TERUJI\nRENCANA OPS SAR H.2\n\nKANTOR PENCARIAN DAN PERTOLONGAN KENDARI\nSelasa, 22 September 2026\n\nYTH : KABASARNAS\nCC : PIMTI MADYA & PRATAMA\n\nDengan hormat, mohon ijin melaporkan Rencana Ops operasi sar kecelakaan kapal di kendari.\n====================\n\nRencana Ops H.2 :\n\n• PD TW 0922 0700 H Tim SAR Gabungan Melaksanakan Brifing Operasi.\n• Pada TW 0922 0730 H Tim SAR Gabungan Melanjutkan Operasi Pencarian H.2 Sesuai Rencana Operasi H.2 dengan Tim SAR gabungan menuju ke entri point masing-masing.\nDengan Tim dibagi 2 SRU dengan area pencarian sbb :\n\n*SRU 1 (Tim Rescue Kansar Kendari) melakukan penyisiran dgn koordinat\nA: 03° 58\' 30\" S - 122° 35\' 12\" E\nB: 03° 58\' 30\" S - 122° 40\' 00\" E\nC: 04° 03\' 00\" S - 122° 40\' 00\" E\nD: 04° 03\' 00\" S - 122° 35\' 12\" E\nLuas area pencarian 10 Nm²\nPola pencarian : Parallel Track Search\n\n*SRU 2 (Unsur Gabungan & Nelayan) melakukan penyisiran dgn koordinat\nPenyisiran visual di sepanjang pesisir pantai dan pulau terdekat\nLuas area pencarian 6 Nm²\nPola pencarian : Creeping Line Search\n\n1. Data Korban: 1 Orang\n• S : 0 ORANG\n• MD : 0 ORANG\n• DP : 1 ORANG\n• H : 0 ORANG\n\n2. Identitas Korban\n1. Nama : lapuko\n   Umur : 25 tahun\n   Jenis Kelamin : Laki-laki\n   Alamat : kendari\n\n3. Unsur SAR Terlibat\n• Staf SMC : 3 Orang\n• Tim Rescue Kansar Kendari : 7 Orang\n• Polsek & Koramil Setempat : 4 Orang\n\n4. Alut dan Palsar\n• 1 Unit Rescue Car\n• 1 Set Aqua Eye\n• 1 Unit RIB (Rigid Inflatable Boat)\n• 1 Set Palsar Evakuasi & Tandu\n• 1 Unit Drone Thermal / Surveillance\n• 1 Set Palsar Komunikasi & Alkom\n• 1 Set Palsar Medis (First Aid Kit)\n• 1 Unit Aqua Eye (Sonar Scanner)\n• 1 Set Peralatan Selam (Diving Gear)\n\n5. Kondisi Cuaca :\n• Cuaca: Cerah Berawan, Hujan Ringan\n(Sumber BMKG)\n\nDemikian kami laporkan, mohon petunjuk dan arahan\n\nTtd\n\nKKS Kendari\n===========================\n🏢 Kantor Pencarian Dan Pertolongan Kendari\n📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116\n☎️ Telp. : (0401) 3196557\n📠 Fax. : (0401) 3196558\n📱 WhatsApp : +62811-4034-115\n🚨 Emergency Call : 115\n🌐 Website : Www.Kendari.Basarnas.go.id\n📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id\n📲 Medsos : @Basarnaskendari','2026-09-21T00:44:56.898Z'),(2,1,'H.2','2026-09-22','2026-09-21T23:00:00.000Z','2026-09-21T23:30:00.000Z','Cerah Berawan','','','FILE AKTIF\n____\n\nSEMANGAT QUICK ACTION SEARCH AND RESCUE\nSATU JIWA SATU RASA\nWE ARE FAMILY\nPROFESIONAL MODERN TERUJI\nRENCANA OPS SAR H.2\n\nKANTOR PENCARIAN DAN PERTOLONGAN KENDARI\nSelasa, 22 September 2026\n\nYTH : KABASARNAS\nCC : PIMTI MADYA & PRATAMA\n\nDengan hormat, mohon ijin melaporkan Rencana Ops operasi sar kecelakaan kapal di kendari.\n====================\n\nRencana Ops H.2 :\n\n• PD TW 0922 0700 H Tim SAR Gabungan Melaksanakan Brifing Operasi.\n• Pada TW 0922 0730 H Tim SAR Gabungan Melanjutkan Operasi Pencarian H.2 Sesuai Rencana Operasi H.2 dengan Tim SAR gabungan menuju ke entri point masing-masing.\nDengan Tim dibagi 2 SRU dengan area pencarian sbb :\n\n*SRU 1 (Tim Rescue Kansar Kendari) melakukan penyisiran dgn koordinat\nA: 03° 58\' 30\" S - 122° 35\' 12\" E\nB: 03° 58\' 30\" S - 122° 40\' 00\" E\nC: 04° 03\' 00\" S - 122° 40\' 00\" E\nD: 04° 03\' 00\" S - 122° 35\' 12\" E\nLuas area pencarian 10 Nm²\nPola pencarian : Parallel Track Search\n\n*SRU 2 (Unsur Gabungan & Nelayan) melakukan penyisiran dgn koordinat\nPenyisiran visual di sepanjang pesisir pantai dan pulau terdekat\nLuas area pencarian 6 Nm²\nPola pencarian : Creeping Line Search\n\n1. Data Korban: 1 Orang\n• S : 1 ORANG\n• MD : 0 ORANG\n• DP : 0 ORANG\n• H : 0 ORANG\n\n2. Identitas Korban\n1. Nama : lapuko (Selamat (S))\n   Umur : 25 tahun\n   Jenis Kelamin : Laki-laki\n   Alamat : kendari\n\n3. Unsur SAR Terlibat\n• Staf SMC : 3 Orang\n• Tim Rescue Kansar Kendari : 7 Orang\n• Polsek & Koramil Setempat : 4 Orang\n\n4. Alut dan Palsar\n• 1 Unit Rescue Car\n• 1 Set Aqua Eye\n• 1 Unit RIB (Rigid Inflatable Boat)\n• 1 Set Palsar Evakuasi & Tandu\n• 1 Unit Drone Thermal / Surveillance\n• 1 Set Palsar Komunikasi & Alkom\n• 1 Set Palsar Medis (First Aid Kit)\n• 1 Unit Aqua Eye (Sonar Scanner)\n• 1 Set Peralatan Selam (Diving Gear)\n\n5. Kondisi Cuaca :\n• Cuaca: Cerah Berawan\n(Sumber BMKG)\n\nDemikian kami laporkan, mohon petunjuk dan arahan\n\nTtd\n\nKKS Kendari\n===========================\n🏢 Kantor Pencarian Dan Pertolongan Kendari\n📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116\n☎️ Telp. : (0401) 3196557\n📠 Fax. : (0401) 3196558\n📱 WhatsApp : +62811-4034-115\n🚨 Emergency Call : 115\n🌐 Website : Www.Kendari.Basarnas.go.id\n📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id\n📲 Medsos : @Basarnaskendari','2026-09-21T01:24:49.918Z');
/*!40000 ALTER TABLE `Renops` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `RenopsSRU`
--

DROP TABLE IF EXISTS `RenopsSRU`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `RenopsSRU` (
  `id_sru` int NOT NULL AUTO_INCREMENT,
  `id_renops` int NOT NULL,
  `nama_sru` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `koordinat_area` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `luas_area` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pola_pencarian` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_sapuan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_spacing` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_sru`),
  KEY `RenopsSRU_id_renops_fkey` (`id_renops`),
  CONSTRAINT `RenopsSRU_id_renops_fkey` FOREIGN KEY (`id_renops`) REFERENCES `Renops` (`id_renops`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `RenopsSRU`
--

LOCK TABLES `RenopsSRU` WRITE;
/*!40000 ALTER TABLE `RenopsSRU` DISABLE KEYS */;
INSERT INTO `RenopsSRU` (`id_sru`, `id_renops`, `nama_sru`, `koordinat_area`, `luas_area`, `pola_pencarian`) VALUES (1,1,'SRU 1 (Tim Rescue Kansar Kendari)','A: 03° 58\' 30\" S - 122° 35\' 12\" E\nB: 03° 58\' 30\" S - 122° 40\' 00\" E\nC: 04° 03\' 00\" S - 122° 40\' 00\" E\nD: 04° 03\' 00\" S - 122° 35\' 12\" E','10 Nm²','Parallel Track Search'),(2,1,'SRU 2 (Unsur Gabungan & Nelayan)','Penyisiran visual di sepanjang pesisir pantai dan pulau terdekat','6 Nm²','Creeping Line Search'),(3,2,'SRU 1 (Tim Rescue Kansar Kendari)','A: 03° 58\' 30\" S - 122° 35\' 12\" E\nB: 03° 58\' 30\" S - 122° 40\' 00\" E\nC: 04° 03\' 00\" S - 122° 40\' 00\" E\nD: 04° 03\' 00\" S - 122° 35\' 12\" E','10 Nm²','Parallel Track Search'),(4,2,'SRU 2 (Unsur Gabungan & Nelayan)','Penyisiran visual di sepanjang pesisir pantai dan pulau terdekat','6 Nm²','Creeping Line Search');
/*!40000 ALTER TABLE `RenopsSRU` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `SarUnit`
--

DROP TABLE IF EXISTS `SarUnit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `SarUnit` (
  `id_unit` int NOT NULL AUTO_INCREMENT,
  `nama_unit` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_unit` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wilayah` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `koordinat_dms` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_telp` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_unit`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `SarUnit`
--

LOCK TABLES `SarUnit` WRITE;
/*!40000 ALTER TABLE `SarUnit` DISABLE KEYS */;
INSERT INTO `SarUnit` (`id_unit`, `nama_unit`, `jenis_unit`, `wilayah`, `latitude`, `longitude`, `koordinat_dms`, `alamat`, `no_telp`, `is_default`) VALUES (1,'Kansar Kendari (Kantor Utama)','KANTOR_SAR','Kota Kendari / Konawe Selatan',-4.084194,122.428389,'04° 05\' 03\" S - 122° 25\' 42\" E','Jl. Poros Bandara Haluoleo No. 1, Desa Ambaipua, Kec. Ranomeeto, Kab. Konawe Selatan, Sultra','(0401) 3122115 / Emergency 115',1),(2,'Pos SAR Kolaka','POS_SAR','Kabupaten Kolaka',-4.053889,121.604167,'04° 03\' 14\" S - 121° 36\' 15\" E','Kompleks Pelabuhan Ferry Kolaka, Jl. Dermaga Ferry Kolaka, Kel. Kolakaasi, Kec. Latambaga, Kab. Kolaka','0811-4091-115',0),(3,'Pos SAR Baubau','POS_SAR','Kota Baubau & Buton',-5.463889,122.604167,'05° 27\' 50\" S - 122° 36\' 15\" E','Kawasan Pelabuhan Murhum / Jembatan Batu, Kel. Batulo, Kec. Wolio, Kota Baubau, Sulawesi Tenggara','0811-4092-115',0),(4,'Pos SAR Wakatobi','POS_SAR','Kabupaten Wakatobi',-5.326389,123.588889,'05° 19\' 35\" S - 123° 35\' 20\" E','Kawasan Pelabuhan Mandati / Pangulubelo, Wangi-Wangi Selatan, Kab. Wakatobi, Sulawesi Tenggara','0811-4093-115',0),(5,'Pos SAR Konawe Utara','POS_SAR','Kabupaten Konawe Utara',-3.513889,122.180556,'03° 30\' 50\" S - 122° 10\' 50\" E','Jl. Poros Trans Sulawesi Kendari - Morowali, Wanggudu / Kec. Asera, Kab. Konawe Utara, Sulawesi Tenggara','0811-4094-115',0),(6,'Unit Siaga SAR Muna','UNIT_SIAGA','Kabupaten Muna',-4.836111,122.719444,'04° 50\' 10\" S - 122° 43\' 10\" E','Kawasan Pelabuhan Nusantara Raha / Jl. By Pass, Kota Raha, Kab. Muna, Sulawesi Tenggara','0811-4095-115',0),(7,'Unit Siaga SAR Konawe','UNIT_SIAGA','Kabupaten Konawe',-3.905556,122.047222,'03° 54\' 20\" S - 122° 02\' 50\" E','Jl. Poros Kendari - Kolaka, Kota Unaaha, Kab. Konawe, Sulawesi Tenggara','0811-4096-115',0),(8,'Unit Siaga SAR Kolaka Utara','UNIT_SIAGA','Kabupaten Kolaka Utara',-3.425,120.897,'03° 25\' 30\" S - 120° 53\' 49\" E','Kawasan Pelabuhan Penyeberangan Tobaku / Jl. Poros Lasusua, Kec. Lasusua, Kab. Kolaka Utara','0811-4097-115',0),(9,'Dermaga SAR Kendari (KN SAR / RB)','KN_SAR','Kota Kendari (Bungkutoko)',-3.974167,122.585,'03° 58\' 27\" S - 122° 35\' 06\" E','Kawasan Pelabuhan Bungkutoko (Pangkalan KN SAR Pacitan / RB 210 / RB 307), Kota Kendari','(0401) 3122115',0);
/*!40000 ALTER TABLE `SarUnit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Users`
--

DROP TABLE IF EXISTS `Users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Users` (
  `id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nip` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `pangkat` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `jabatan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `no_hp` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `role` enum('ADMIN','USER') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USER',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_username` (`username`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Users`
--

LOCK TABLES `Users` WRITE;
/*!40000 ALTER TABLE `Users` DISABLE KEYS */;
INSERT INTO `Users` (`id`, `username`, `password_hash`, `nama`, `nip`, `pangkat`, `jabatan`, `email`, `no_hp`, `role`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES ('USR-ADMIN','admin','$2y$10$q0Oq7Y5Gq3m8r9h0g0k7e.6W7yV3d0s9b3j2f1h0g9k8l7m6n5o4p','Irfandy','199903212020121002','Pengatur Muda Tk.I (II/b)','Pranata Pencarian dan Pertolongan Terampil','irfandydk@gmail.com','085215906543','ADMIN',1,NULL,'2026-10-14 00:00:00','2026-10-14 00:00:00'),('USR-USER','user','$2y$10$eR0Z7I6jM1f6lF1K.Kj4vO8X9.h6g1r3y0f8d7s6a5b4c3d2e1f0g','Petugas Siaga SAR','199501012020121001','PENGATUR (II/c)','Operator Komunikasi & Petugas Siaga SAR','operator.kendari@basarnas.go.id','0811-4091-115','USER',1,NULL,'2026-10-14 00:00:00','2026-10-14 00:00:00');
/*!40000 ALTER TABLE `Users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `AuditLog`
--

DROP TABLE IF EXISTS `AuditLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `AuditLog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'system',
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'GUEST',
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_target` (`target_type`,`target_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-11 20:28:51

-- ===========================================================================
-- Panpacific University - Registrar Office
-- Digital Document Request & Physical Release System + SERVE Evaluation
-- Centralized MySQL database import file (InfinityFree / phpMyAdmin)
--
-- HOW TO USE
--   1. Create the database in the InfinityFree control panel (MySQL Databases).
--   2. Open phpMyAdmin for that database.
--   3. Import this file (Import tab -> choose file -> Go).
--   4. Put the same host/name/user/password in config.php.
--
-- This file is generated from inc/schema.php - run it only once.
-- ===========================================================================

SET NAMES utf8mb4;

-- Table: students
CREATE TABLE IF NOT EXISTS `students` (
  `StudentID` varchar(32) NOT NULL,
  `StudentNumber` varchar(40) NOT NULL DEFAULT '',
  `FullName` varchar(160) NOT NULL DEFAULT '',
  `Email` varchar(190) NOT NULL,
  `StudentType` varchar(40) NOT NULL DEFAULT 'Current Student',
  `Campus` varchar(60) NOT NULL DEFAULT 'ALL',
  `Program` varchar(120) NOT NULL DEFAULT '',
  `Status` varchar(20) NOT NULL DEFAULT 'Active',
  `CreatedAt` datetime NOT NULL,
  `UpdatedAt` datetime NOT NULL,
  PRIMARY KEY (`StudentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: document_types
CREATE TABLE IF NOT EXISTS `document_types` (
  `DocumentCode` varchar(16) NOT NULL,
  `DocumentName` varchar(160) NOT NULL,
  `Description` varchar(255) NOT NULL DEFAULT '',
  `BaseFee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `TypicalDays` int NOT NULL DEFAULT 3,
  `Requirements` varchar(255) NOT NULL DEFAULT '',
  `Active` tinyint(1) NOT NULL DEFAULT 1,
  `SortOrder` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`DocumentCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: requests
CREATE TABLE IF NOT EXISTS `requests` (
  `RequestID` varchar(32) NOT NULL,
  `StudentNumber` varchar(40) NOT NULL DEFAULT '',
  `StudentEmail` varchar(190) NOT NULL,
  `StudentName` varchar(160) NOT NULL DEFAULT '',
  `Campus` varchar(60) NOT NULL DEFAULT 'ALL',
  `DocumentCode` varchar(16) NOT NULL,
  `Purpose` varchar(160) NOT NULL DEFAULT '',
  `ReleaseMethod` varchar(60) NOT NULL DEFAULT '',
  `RepresentativeName` varchar(160) NOT NULL DEFAULT '',
  `RepresentativeRelation` varchar(80) NOT NULL DEFAULT '',
  `Status` varchar(40) NOT NULL DEFAULT 'Submitted',
  `CurrentStage` varchar(40) NOT NULL DEFAULT 'Submitted',
  `SubmittedAt` datetime NOT NULL,
  `UpdatedAt` datetime NOT NULL,
  `DueDate` datetime NULL,
  `Notes` text NULL,
  PRIMARY KEY (`RequestID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users
CREATE TABLE IF NOT EXISTS `users` (
  `UserID` varchar(32) NOT NULL,
  `Email` varchar(190) NOT NULL,
  `Name` varchar(160) NOT NULL DEFAULT '',
  `Role` varchar(40) NOT NULL DEFAULT 'Staff',
  `Campus` varchar(60) NOT NULL DEFAULT 'ALL',
  `Active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `LogID` varchar(32) NOT NULL,
  `RequestID` varchar(32) NOT NULL DEFAULT '',
  `ActorEmail` varchar(190) NOT NULL DEFAULT '',
  `Action` varchar(60) NOT NULL DEFAULT '',
  `OldValue` text NULL,
  `NewValue` text NULL,
  `Details` text NULL,
  `Timestamp` datetime NOT NULL,
  PRIMARY KEY (`LogID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `NotificationID` varchar(32) NOT NULL,
  `RequestID` varchar(32) NOT NULL DEFAULT '',
  `RecipientEmail` varchar(190) NOT NULL,
  `Type` varchar(80) NOT NULL DEFAULT '',
  `Subject` varchar(255) NOT NULL DEFAULT '',
  `Message` text NULL,
  `SentAt` datetime NOT NULL,
  `Status` varchar(40) NOT NULL DEFAULT 'Pending',
  PRIMARY KEY (`NotificationID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: otp_tokens
CREATE TABLE IF NOT EXISTS `otp_tokens` (
  `Token` varchar(64) NOT NULL,
  `Email` varchar(190) NOT NULL,
  `CodeHash` varchar(255) NOT NULL,
  `Attempts` int NOT NULL DEFAULT 0,
  `ExpiresAt` datetime NOT NULL,
  `CreatedAt` datetime NOT NULL,
  PRIMARY KEY (`Token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `SessionID` varchar(64) NOT NULL,
  `Email` varchar(190) NOT NULL,
  `Payload` text NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  `CreatedAt` datetime NOT NULL,
  PRIMARY KEY (`SessionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rate_limits
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `RateKey` varchar(191) NOT NULL,
  `ExpiresAt` datetime NOT NULL,
  `CreatedAt` datetime NOT NULL,
  PRIMARY KEY (`RateKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: staff
CREATE TABLE IF NOT EXISTS `staff` (
  `StaffID` varchar(32) NOT NULL,
  `Name` varchar(160) NOT NULL,
  `Position` varchar(120) NOT NULL DEFAULT '',
  `Active` tinyint(1) NOT NULL DEFAULT 1,
  `SortOrder` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`StaffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: evaluations
CREATE TABLE IF NOT EXISTS `evaluations` (
  `EvaluationID` varchar(32) NOT NULL,
  `Reference` varchar(40) NOT NULL,
  `StudentType` varchar(40) NOT NULL DEFAULT '',
  `Email` varchar(190) NOT NULL,
  `FullName` varchar(160) NOT NULL DEFAULT '',
  `StaffName` varchar(160) NOT NULL,
  `QuestionCount` int NOT NULL DEFAULT 0,
  `AverageRating` decimal(4,2) NOT NULL DEFAULT 0.00,
  `RatingsJson` text NOT NULL,
  `Strengths` text NULL,
  `Improve` text NULL,
  `CreatedAt` datetime NOT NULL,
  `IpAddress` varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`EvaluationID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Indexes (ignore "duplicate key name" errors when re-running this file)
-- ---------------------------------------------------------------------------
CREATE UNIQUE INDEX `uq_students_email` ON `students` (`Email`);
CREATE INDEX `idx_students_number` ON `students` (`StudentNumber`);
CREATE INDEX `idx_requests_email` ON `requests` (`StudentEmail`);
CREATE INDEX `idx_requests_status` ON `requests` (`Status`);
CREATE INDEX `idx_requests_submitted` ON `requests` (`SubmittedAt`);
CREATE INDEX `idx_audit_request` ON `audit_logs` (`RequestID`);
CREATE INDEX `idx_notifications_request` ON `notifications` (`RequestID`);
CREATE INDEX `idx_otp_email` ON `otp_tokens` (`Email`);
CREATE INDEX `idx_sessions_expiry` ON `sessions` (`ExpiresAt`);
CREATE UNIQUE INDEX `uq_staff_name` ON `staff` (`Name`);
CREATE UNIQUE INDEX `uq_evaluations_reference` ON `evaluations` (`Reference`);
CREATE INDEX `idx_evaluations_created` ON `evaluations` (`CreatedAt`);
CREATE INDEX `idx_evaluations_staff` ON `evaluations` (`StaffName`);

-- ---------------------------------------------------------------------------
-- Default data: document_types
-- ---------------------------------------------------------------------------
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('TOR', 'Transcript of Records', 'Official academic record', 100, 5, 'Valid ID;Student verification', 1, 10);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('COE', 'Certificate of Enrollment', 'Proof of enrollment', 100, 2, 'Valid ID;Student verification', 1, 20);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('COG', 'Certificate of Grades', 'Certified record of grades', 100, 3, 'Valid ID;Student verification', 1, 30);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('COGRA', 'Certificate of Graduation', 'Certification of graduation', 100, 3, 'Valid ID;Student verification', 1, 40);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('GM', 'Good Moral Certificate', 'Certificate of good moral standing', 100, 3, 'Valid ID;Student verification', 1, 50);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('DIP', 'Diploma', 'Official diploma / diploma release', 500, 7, 'Valid ID;Clearance', 1, 60);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('HD', 'Honorable Dismissal', 'Official honorable dismissal document', 150, 5, 'Valid ID;Clearance', 1, 70);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('CTC', 'Certified True Copy', 'Certified true copy of an academic record', 100, 3, 'Valid ID;Original/record reference', 1, 80);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('COC', 'Certificate of Completion', 'Certification of completion', 100, 3, 'Valid ID;Student verification', 1, 90);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('COA', 'Certificate of Attendance', 'Certification of attendance', 100, 3, 'Valid ID;Student verification', 1, 100);
INSERT INTO `document_types` (`DocumentCode`, `DocumentName`, `Description`, `BaseFee`, `TypicalDays`, `Requirements`, `Active`, `SortOrder`) VALUES ('OTH', 'Other Registrar Document', 'For documents not listed', 0, 5, 'Registrar assessment', 1, 110);

-- ---------------------------------------------------------------------------
-- Default data: users
-- ---------------------------------------------------------------------------
INSERT INTO `users` (`UserID`, `Email`, `Name`, `Role`, `Campus`, `Active`) VALUES ('USR-ADMIN', 'admin@panpacificu.edu.ph', 'Registrar Administrator', 'Admin', 'ALL', 1);

-- ---------------------------------------------------------------------------
-- Default data: staff
-- ---------------------------------------------------------------------------
INSERT INTO `staff` (`StaffID`, `Name`, `Position`, `Active`, `SortOrder`) VALUES ('STAFF-001', 'Registrar Staff 1', 'Registrar Office Staff', 1, 10);
INSERT INTO `staff` (`StaffID`, `Name`, `Position`, `Active`, `SortOrder`) VALUES ('STAFF-002', 'Registrar Staff 2', 'Registrar Office Staff', 1, 20);
INSERT INTO `staff` (`StaffID`, `Name`, `Position`, `Active`, `SortOrder`) VALUES ('STAFF-003', 'Registrar Staff 3', 'Registrar Office Staff', 1, 30);

-- Done. Open setup.php afterwards to verify the installation.

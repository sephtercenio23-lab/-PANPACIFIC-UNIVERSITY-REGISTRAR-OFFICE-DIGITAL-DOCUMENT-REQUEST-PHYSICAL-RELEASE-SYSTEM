<?php
/**
 * schema.php - the central database structure (MySQL / MariaDB).
 *
 * This file is the single source of truth for the tables. It is used by
 * setup.php (and by the optional setupSystem API action). The ready-to-import
 * file database/schema.mysql.sql contains exactly the same statements for
 * phpMyAdmin users.
 *
 * The statements are written so they also run on SQLite (used by the local
 * test harness): the "ENGINE=..." suffix is removed automatically for SQLite.
 */

/**
 * @return array{tables: array<string,string>, indexes: array<int,string>, seeds: array<string,array<int,array<int,mixed>>>}
 */
function schema_definition()
{
    $tables = [];

    /* ---------------- Students (registrar students) ---------------- */
    $tables['students'] = "CREATE TABLE IF NOT EXISTS `students` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- Document catalog ---------------- */
    $tables['document_types'] = "CREATE TABLE IF NOT EXISTS `document_types` (
        `DocumentCode` varchar(16) NOT NULL,
        `DocumentName` varchar(160) NOT NULL,
        `Description` varchar(255) NOT NULL DEFAULT '',
        `BaseFee` decimal(10,2) NOT NULL DEFAULT 0.00,
        `TypicalDays` int NOT NULL DEFAULT 3,
        `Requirements` varchar(255) NOT NULL DEFAULT '',
        `Active` tinyint(1) NOT NULL DEFAULT 1,
        `SortOrder` int NOT NULL DEFAULT 0,
        PRIMARY KEY (`DocumentCode`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- Document requests ---------------- */
    $tables['requests'] = "CREATE TABLE IF NOT EXISTS `requests` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- Registrar users / staff accounts ---------------- */
    $tables['users'] = "CREATE TABLE IF NOT EXISTS `users` (
        `UserID` varchar(32) NOT NULL,
        `Email` varchar(190) NOT NULL,
        `Name` varchar(160) NOT NULL DEFAULT '',
        `Role` varchar(40) NOT NULL DEFAULT 'Staff',
        `Campus` varchar(60) NOT NULL DEFAULT 'ALL',
        `Active` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`UserID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- Audit trail ---------------- */
    $tables['audit_logs'] = "CREATE TABLE IF NOT EXISTS `audit_logs` (
        `LogID` varchar(32) NOT NULL,
        `RequestID` varchar(32) NOT NULL DEFAULT '',
        `ActorEmail` varchar(190) NOT NULL DEFAULT '',
        `Action` varchar(60) NOT NULL DEFAULT '',
        `OldValue` text NULL,
        `NewValue` text NULL,
        `Details` text NULL,
        `Timestamp` datetime NOT NULL,
        PRIMARY KEY (`LogID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- Outgoing notifications ---------------- */
    $tables['notifications'] = "CREATE TABLE IF NOT EXISTS `notifications` (
        `NotificationID` varchar(32) NOT NULL,
        `RequestID` varchar(32) NOT NULL DEFAULT '',
        `RecipientEmail` varchar(190) NOT NULL,
        `Type` varchar(80) NOT NULL DEFAULT '',
        `Subject` varchar(255) NOT NULL DEFAULT '',
        `Message` text NULL,
        `SentAt` datetime NOT NULL,
        `Status` varchar(40) NOT NULL DEFAULT 'Pending',
        PRIMARY KEY (`NotificationID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- Login OTP tokens ---------------- */
    $tables['otp_tokens'] = "CREATE TABLE IF NOT EXISTS `otp_tokens` (
        `Token` varchar(64) NOT NULL,
        `Email` varchar(190) NOT NULL,
        `CodeHash` varchar(255) NOT NULL,
        `Attempts` int NOT NULL DEFAULT 0,
        `ExpiresAt` datetime NOT NULL,
        `CreatedAt` datetime NOT NULL,
        PRIMARY KEY (`Token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- Login sessions ---------------- */
    $tables['sessions'] = "CREATE TABLE IF NOT EXISTS `sessions` (
        `SessionID` varchar(64) NOT NULL,
        `Email` varchar(190) NOT NULL,
        `Payload` text NOT NULL,
        `ExpiresAt` datetime NOT NULL,
        `CreatedAt` datetime NOT NULL,
        PRIMARY KEY (`SessionID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- Short lived rate limits (OTP throttle) ---------------- */
    $tables['rate_limits'] = "CREATE TABLE IF NOT EXISTS `rate_limits` (
        `RateKey` varchar(191) NOT NULL,
        `ExpiresAt` datetime NOT NULL,
        `CreatedAt` datetime NOT NULL,
        PRIMARY KEY (`RateKey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- Staff directory (SERVE evaluation) ---------------- */
    $tables['staff'] = "CREATE TABLE IF NOT EXISTS `staff` (
        `StaffID` varchar(32) NOT NULL,
        `Name` varchar(160) NOT NULL,
        `Position` varchar(120) NOT NULL DEFAULT '',
        `Active` tinyint(1) NOT NULL DEFAULT 1,
        `SortOrder` int NOT NULL DEFAULT 0,
        PRIMARY KEY (`StaffID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    /* ---------------- SERVE student evaluations ---------------- */
    $tables['evaluations'] = "CREATE TABLE IF NOT EXISTS `evaluations` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $indexes = [
        'CREATE UNIQUE INDEX `uq_students_email` ON `students` (`Email`)',
        'CREATE INDEX `idx_students_number` ON `students` (`StudentNumber`)',
        'CREATE INDEX `idx_requests_email` ON `requests` (`StudentEmail`)',
        'CREATE INDEX `idx_requests_status` ON `requests` (`Status`)',
        'CREATE INDEX `idx_requests_submitted` ON `requests` (`SubmittedAt`)',
        'CREATE INDEX `idx_audit_request` ON `audit_logs` (`RequestID`)',
        'CREATE INDEX `idx_notifications_request` ON `notifications` (`RequestID`)',
        'CREATE INDEX `idx_otp_email` ON `otp_tokens` (`Email`)',
        'CREATE INDEX `idx_sessions_expiry` ON `sessions` (`ExpiresAt`)',
        'CREATE UNIQUE INDEX `uq_staff_name` ON `staff` (`Name`)',
        'CREATE UNIQUE INDEX `uq_evaluations_reference` ON `evaluations` (`Reference`)',
        'CREATE INDEX `idx_evaluations_created` ON `evaluations` (`CreatedAt`)',
        'CREATE INDEX `idx_evaluations_staff` ON `evaluations` (`StaffName`)',
    ];

    /* Default document catalog - identical to DEFAULT_DOCUMENTS in Code.gs */
    $documents = [
        ['TOR', 'Transcript of Records', 'Official academic record', 100, 5, 'Valid ID;Student verification', 1, 10],
        ['COE', 'Certificate of Enrollment', 'Proof of enrollment', 100, 2, 'Valid ID;Student verification', 1, 20],
        ['COG', 'Certificate of Grades', 'Certified record of grades', 100, 3, 'Valid ID;Student verification', 1, 30],
        ['COGRA', 'Certificate of Graduation', 'Certification of graduation', 100, 3, 'Valid ID;Student verification', 1, 40],
        ['GM', 'Good Moral Certificate', 'Certificate of good moral standing', 100, 3, 'Valid ID;Student verification', 1, 50],
        ['DIP', 'Diploma', 'Official diploma / diploma release', 500, 7, 'Valid ID;Clearance', 1, 60],
        ['HD', 'Honorable Dismissal', 'Official honorable dismissal document', 150, 5, 'Valid ID;Clearance', 1, 70],
        ['CTC', 'Certified True Copy', 'Certified true copy of an academic record', 100, 3, 'Valid ID;Original/record reference', 1, 80],
        ['COC', 'Certificate of Completion', 'Certification of completion', 100, 3, 'Valid ID;Student verification', 1, 90],
        ['COA', 'Certificate of Attendance', 'Certification of attendance', 100, 3, 'Valid ID;Student verification', 1, 100],
        ['OTH', 'Other Registrar Document', 'For documents not listed', 0, 5, 'Registrar assessment', 1, 110],
    ];

    return [
        'tables'  => $tables,
        'indexes' => $indexes,
        'seeds'   => [
            // Table => list of rows (associative arrays) inserted only when the
            // table is still empty.
            'document_types' => array_map(function ($row) {
                return [
                    'DocumentCode' => $row[0],
                    'DocumentName' => $row[1],
                    'Description'  => $row[2],
                    'BaseFee'      => $row[3],
                    'TypicalDays'  => $row[4],
                    'Requirements' => $row[5],
                    'Active'       => $row[6],
                    'SortOrder'    => $row[7],
                ];
            }, $documents),

            'users' => [
                [
                    'UserID' => 'USR-ADMIN',
                    'Email'  => 'admin@panpacificu.edu.ph',
                    'Name'   => 'Registrar Administrator',
                    'Role'   => 'Admin',
                    'Campus' => 'ALL',
                    'Active' => 1,
                ],
            ],

            // PLACEHOLDERS - replace these with the real registrar staff names
            // (phpMyAdmin -> staff table, or setup.php). The SERVE form lists
            // exactly the active rows of this table.
            'staff' => [
                ['StaffID' => 'STAFF-001', 'Name' => 'Registrar Staff 1', 'Position' => 'Registrar Office Staff', 'Active' => 1, 'SortOrder' => 10],
                ['StaffID' => 'STAFF-002', 'Name' => 'Registrar Staff 2', 'Position' => 'Registrar Office Staff', 'Active' => 1, 'SortOrder' => 20],
                ['StaffID' => 'STAFF-003', 'Name' => 'Registrar Staff 3', 'Position' => 'Registrar Office Staff', 'Active' => 1, 'SortOrder' => 30],
            ],
        ],
    ];
}

/** INSERT helper used by the installer. */
function schema_insert_row($table, array $row)
{
    $columns = array_keys($row);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES ('
        . implode(', ', array_fill(0, count($columns), '?')) . ')';
    db_exec($sql, array_values($row));
}

/**
 * Create every table, index and seed row. Safe to run repeatedly.
 *
 * @param callable|null $log  Receives a human readable progress line.
 * @return array<int,string>  Progress lines.
 */
function schema_install($log = null)
{
    $messages = [];
    $write = function ($message) use (&$messages, $log) {
        $messages[] = $message;
        if (is_callable($log)) {
            call_user_func($log, $message);
        }
    };

    $pdo = db();
    $driver = strtolower((string) config('db.driver', 'mysql'));
    $definition = schema_definition();

    foreach ($definition['tables'] as $name => $sql) {
        if ($driver === 'sqlite') {
            $sql = preg_replace('/\s*ENGINE\s*=.*$/is', '', $sql);
        }
        $pdo->exec($sql);
        $write('Table ready: ' . $name);
    }

    foreach ($definition['indexes'] as $sql) {
        try {
            $pdo->exec($sql);
            $write('Index ready: ' . preg_replace('/^CREATE (UNIQUE )?INDEX `([^`]+)`.*$/i', '$2', $sql));
        } catch (PDOException $e) {
            // Index already exists - nothing to do.
            $write('Index skipped (already exists)');
        }
    }

    foreach ($definition['seeds'] as $table => $rows) {
        $existing = (int) db_value('SELECT COUNT(*) FROM `' . $table . '`', [], 0);
        if ($existing > 0) {
            $write('Seed skipped: ' . $table . ' already has ' . $existing . ' row(s)');
            continue;
        }
        foreach ($rows as $row) {
            schema_insert_row($table, $row);
        }
        $write('Seed inserted: ' . count($rows) . ' row(s) into ' . $table);
    }

    $write('Installation finished.');
    return $messages;
}

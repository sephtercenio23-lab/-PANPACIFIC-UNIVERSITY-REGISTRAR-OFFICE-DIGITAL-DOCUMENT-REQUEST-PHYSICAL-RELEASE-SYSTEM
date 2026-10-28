SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS programs (
  id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY, student_number VARCHAR(30) DEFAULT '', full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE, student_type VARCHAR(40) NOT NULL DEFAULT 'Current Student',
  program_id INT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Active', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
  FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(150) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL,
  role ENUM('Registrar','Admin') NOT NULL DEFAULT 'Registrar', active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS document_types (
  code VARCHAR(10) PRIMARY KEY, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT '',
  base_fee DECIMAL(10,2) NOT NULL DEFAULT 0, typical_days INT NOT NULL DEFAULT 3, requirements VARCHAR(255) DEFAULT '', active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS requests (
  request_id VARCHAR(30) PRIMARY KEY, student_id INT NULL, student_number VARCHAR(30) DEFAULT '', student_email VARCHAR(150) NOT NULL,
  student_name VARCHAR(150) NOT NULL, program_id INT NULL, document_code VARCHAR(10) NOT NULL, purpose VARCHAR(100) NOT NULL,
  release_method VARCHAR(50) NOT NULL, rep_name VARCHAR(150) DEFAULT '', rep_relation VARCHAR(100) DEFAULT '',
  status VARCHAR(30) NOT NULL DEFAULT 'Submitted', status_reason TEXT NULL, submitted_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
  due_date DATE NOT NULL, notes TEXT NULL,
  INDEX (student_email), INDEX (status), INDEX (program_id), INDEX (document_code),
  FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS otp_codes (
  id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(150) NOT NULL, code_hash VARCHAR(255) NOT NULL, attempts INT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL, used TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, INDEX (email)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY, request_id VARCHAR(30) DEFAULT '', actor_email VARCHAR(150), action VARCHAR(50),
  old_value VARCHAR(100), new_value VARCHAR(100), details TEXT, created_at DATETIME NOT NULL, INDEX (request_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY, request_id VARCHAR(30) DEFAULT '', recipient_email VARCHAR(150), type VARCHAR(50),
  subject VARCHAR(255), message TEXT, sent_at DATETIME NOT NULL, status VARCHAR(20)
) ENGINE=InnoDB;

INSERT IGNORE INTO programs (code,name) VALUES
('BSIT','BS Information Technology'),('BSBA','BS Business Administration'),('BSED','BS Education'),('BSN','BS Nursing'),('BSCRIM','BS Criminology');
INSERT IGNORE INTO users (email,name,role,active) VALUES ('admin@panpacificu.edu.ph','Registrar Administrator','Admin',1);
INSERT IGNORE INTO document_types (code,name,description,base_fee,typical_days,requirements,active) VALUES
('TOR','Transcript of Records','Official academic record',100,5,'Valid ID;Student verification',1),
('COE','Certificate of Enrollment','Proof of enrollment',100,2,'Valid ID;Student verification',1),
('COG','Certificate of Grades','Certified record of grades',100,3,'Valid ID;Student verification',1),
('COGRA','Certificate of Graduation','Certification of graduation',100,3,'Valid ID;Student verification',1),
('GM','Good Moral Certificate','Certificate of good moral standing',100,3,'Valid ID;Student verification',1),
('DIP','Diploma','Official diploma / diploma release',500,7,'Valid ID;Clearance',1),
('HD','Honorable Dismissal','Official honorable dismissal document',150,5,'Valid ID;Clearance',1),
('CTC','Certified True Copy','Certified true copy of an academic record',100,3,'Valid ID;Original/record reference',1),
('COC','Certificate of Completion','Certification of completion',100,3,'Valid ID;Student verification',1),
('COA','Certificate of Attendance','Certification of attendance',100,3,'Valid ID;Student verification',1),
('OTH','Other Registrar Document','For documents not listed',0,5,'Registrar assessment',1);

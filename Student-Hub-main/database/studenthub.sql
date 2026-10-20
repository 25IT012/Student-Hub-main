-- =====================================================================
-- StudentHub Database Schema & Initial Data
-- Database Name: studenthub
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =====================================================================

-- 1. Create Database if it does not already exist
CREATE DATABASE IF NOT EXISTS `studenthub` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `studenthub`;

-- ---------------------------------------------------------------------
-- 2. Table Structure for `students`
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `registrations`;
DROP TABLE IF EXISTS `students`;

CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `enrollment_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique Student ID or Enrollment No',
  `username` VARCHAR(60) NOT NULL UNIQUE COMMENT 'Unique Username for login',
  `name` VARCHAR(100) NOT NULL COMMENT 'Full Name of the Student',
  `email` VARCHAR(120) NOT NULL UNIQUE COMMENT 'Institutional or Personal Email',
  `password` VARCHAR(255) NOT NULL COMMENT 'Securely hashed account password',
  `mobile` VARCHAR(20) DEFAULT NULL COMMENT 'Contact Phone Number',
  `course_name` VARCHAR(100) NOT NULL COMMENT 'Course or Branch Name (e.g. Computer Engineering)',
  `semester` INT NOT NULL DEFAULT 1 COMMENT 'Current Academic Semester',
  `attendance` DECIMAL(5,2) DEFAULT 85.00 COMMENT 'Current Attendance Percentage',
  `cgpa` DECIMAL(4,2) DEFAULT 8.50 COMMENT 'Cumulative Grade Point Average',
  `gender` ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
  `city` VARCHAR(100) DEFAULT 'Anand',
  `status` ENUM('Active', 'Pending', 'Inactive') DEFAULT 'Active',
  `role` ENUM('student', 'admin') DEFAULT 'student',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Table Structure for `events`
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `events`;

CREATE TABLE `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL COMMENT 'Event Title',
  `category` VARCHAR(50) NOT NULL COMMENT 'Technical, Workshop, Academic, Seminar, Cultural',
  `description` TEXT NOT NULL COMMENT 'Detailed Event Description',
  `event_date` DATE NOT NULL COMMENT 'Date of Event',
  `event_time` VARCHAR(50) NOT NULL COMMENT 'Time Slot (e.g., 10:00 AM - 4:00 PM)',
  `location` VARCHAR(150) NOT NULL COMMENT 'Venue / Room / Hall',
  `capacity` INT DEFAULT 100 COMMENT 'Max Registered Seats Allowed',
  `registration_open` TINYINT(1) DEFAULT 1 COMMENT '1 = Open, 0 = Closed',
  `poster_image` VARCHAR(255) DEFAULT NULL COMMENT 'Path or filename of event poster image',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Table Structure for `registrations` (Junction Table)
-- ---------------------------------------------------------------------
CREATE TABLE `registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL COMMENT 'Foreign Key referencing students(id)',
  `event_id` INT NOT NULL COMMENT 'Foreign Key referencing events(id)',
  `registration_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when student registered',
  `status` ENUM('Confirmed', 'Pending', 'Cancelled') DEFAULT 'Confirmed',
  `remarks` VARCHAR(255) DEFAULT NULL,
  CONSTRAINT `fk_registrations_student` FOREIGN KEY (`student_id`) 
    REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_registrations_event` FOREIGN KEY (`event_id`) 
    REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `unique_student_event_registration` UNIQUE (`student_id`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Supporting Table: `courses`
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `courses`;

CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_code` VARCHAR(20) NOT NULL UNIQUE,
  `course_name` VARCHAR(150) NOT NULL,
  `department` VARCHAR(100) NOT NULL DEFAULT 'Computer Engineering',
  `semester` INT NOT NULL DEFAULT 2,
  `credits` INT NOT NULL DEFAULT 4
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Table Structure for `remember_tokens` (Secure Remember-Me Persistence)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `remember_tokens`;

CREATE TABLE `remember_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `selector` VARCHAR(32) NOT NULL UNIQUE,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_remember_tokens_user` FOREIGN KEY (`user_id`)
    REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Table Structure for `audit_logs` (Admin Audit & Compliance Log)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;

CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL COMMENT 'ID of admin performing action',
  `user_name` VARCHAR(100) DEFAULT NULL COMMENT 'Name or username of admin',
  `action` VARCHAR(50) NOT NULL COMMENT 'e.g. STUDENT_CREATE, EVENT_DELETE, etc.',
  `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'student, event, registration, auth',
  `entity_id` INT DEFAULT NULL COMMENT 'ID of affected record',
  `details` TEXT DEFAULT NULL COMMENT 'JSON or text summary of change',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6 client address',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_action` (`action`),
  INDEX `idx_entity` (`entity_type`, `entity_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA INSERTION
-- =====================================================================

-- 6. Insert Sample Students (Matching StudentHub records)
INSERT INTO `students` (`id`, `enrollment_number`, `name`, `email`, `password`, `mobile`, `course_name`, `semester`, `attendance`, `cgpa`, `gender`, `city`, `status`, `role`) VALUES
(1, 'STU2026001', 'Pruthviraj', 'pruthviraj@example.com', 'student123', '+91 98765 43210', 'Computer Engineering', 2, 88.00, 8.85, 'Male', 'Anand', 'Active', 'student'),
(2, 'STU2026002', 'Rahul Patel', 'rahul.patel@example.com', 'student123', '+91 98765 43211', 'Computer Engineering', 4, 92.00, 8.40, 'Male', 'Ahmedabad', 'Active', 'student'),
(3, 'STU2026003', 'Priya Shah', 'priya.shah@example.com', 'student123', '+91 98765 43212', 'Information Technology', 2, 88.00, 9.10, 'Female', 'Vadodara', 'Active', 'student'),
(4, 'STU2026004', 'Aman Joshi', 'aman.joshi@example.com', 'student123', '+91 98765 43213', 'Computer Engineering', 6, 74.00, 7.60, 'Male', 'Surat', 'Pending', 'student'),
(5, 'STU2026005', 'Neha Mehta', 'neha.mehta@example.com', 'student123', '+91 98765 43214', 'Electronics & Communication', 4, 91.00, 8.95, 'Female', 'Rajkot', 'Active', 'student'),
(6, 'STU2026006', 'Ananya Sharma', 'ananya.s@example.com', 'student123', '+91 98765 43215', 'Information Technology', 6, 89.00, 8.70, 'Female', 'Anand', 'Active', 'student'),
(7, 'STU2026007', 'Karan Verma', 'karan.v@example.com', 'student123', '+91 98765 43216', 'Mechanical Engineering', 2, 68.00, 7.15, 'Male', 'Nadiad', 'Pending', 'student'),
(8, 'STU2026008', 'Sneha Roy', 'sneha.roy@example.com', 'student123', '+91 98765 43217', 'Civil Engineering', 4, 94.00, 9.30, 'Female', 'Ahmedabad', 'Active', 'student'),
(9, 'STU2026009', 'Devendra Kumar', 'devendra.k@example.com', 'student123', '+91 98765 43218', 'Electrical Engineering', 8, 62.00, 6.80, 'Male', 'Gandhinagar', 'Inactive', 'student'),
(10, 'STU2026010', 'Riya Desai', 'riya.d@example.com', 'student123', '+91 98765 43219', 'Computer Engineering', 2, 96.00, 9.50, 'Female', 'Surat', 'Active', 'student'),
(11, 'ADM2026001', 'System Administrator', 'admin@studenthub.edu', 'admin123', '+91 98765 00000', 'Administration', 0, 100.00, 10.00, 'Other', 'Changa', 'Active', 'admin');

-- 7. Insert Sample Campus Events
INSERT INTO `events` (`id`, `title`, `category`, `description`, `event_date`, `event_time`, `location`, `capacity`, `registration_open`) VALUES
(1, 'Tech Fest 2026', 'Technical', 'Join students for an exciting technology festival featuring project demos, hackathons, and robotics.', '2026-09-15', '10:00 AM - 4:00 PM', 'College Campus', 250, 1),
(2, 'Coding Workshop', 'Workshop', 'Learn programming fundamentals, data structures, and algorithms to improve problem-solving skills.', '2026-09-22', '11:00 AM - 2:00 PM', 'Computer Lab A', 60, 1),
(3, 'Project Exhibition', 'Academic', 'Showcase innovative engineering and software projects to industry professionals and faculty members.', '2026-10-05', '9:30 AM - 3:00 PM', 'Main Auditorium', 150, 1),
(4, 'Career Seminar', 'Seminar', 'Get valuable career guidance, resume tips, and learn about top internship opportunities after graduation.', '2026-10-18', '1:00 PM - 3:00 PM', 'Seminar Hall', 120, 0),
(5, 'AI & Machine Learning Bootcamp', 'Technical', 'Hands-on introduction to Python, Neural Networks, and creating computer vision applications.', '2026-11-02', '10:00 AM - 1:00 PM', 'Lab 3 & Online', 80, 1),
(6, 'Annual Cultural Gala', 'Cultural', 'Celebrate music, drama, dance, and creative performances from talented students across all departments.', '2026-11-14', '5:00 PM - 9:00 PM', 'Open Air Amphitheatre', 500, 1),
(7, 'Cybersecurity & Ethical Hacking', 'Workshop', 'Discover common vulnerabilities, network defenses, and penetration testing methodologies with guest experts.', '2026-11-28', '2:00 PM - 5:00 PM', 'IT Seminar Hall', 75, 1),
(8, 'Campus Placement Prep Talk', 'Seminar', 'Tips from alumni who secured roles at top tech companies, mock interviews, and aptitude training.', '2026-12-08', '11:30 AM - 1:30 PM', 'Conference Room B', 100, 0);

-- 8. Insert Sample Event Registrations
INSERT INTO `registrations` (`student_id`, `event_id`, `status`) VALUES
(1, 1, 'Confirmed'),
(1, 2, 'Confirmed'),
(1, 3, 'Confirmed'),
(2, 1, 'Confirmed'),
(2, 5, 'Confirmed'),
(3, 1, 'Confirmed'),
(3, 2, 'Confirmed'),
(4, 3, 'Pending'),
(5, 5, 'Confirmed'),
(6, 6, 'Confirmed'),
(8, 1, 'Confirmed'),
(10, 2, 'Confirmed');

-- 9. Insert Enrolled Courses
INSERT INTO `courses` (`course_code`, `course_name`, `department`, `semester`, `credits`) VALUES
('COURSE-101', 'Object Oriented Programming with Java', 'Computer Engineering', 2, 4),
('COURSE-102', 'Data Structures & Algorithms', 'Computer Engineering', 2, 4),
('COURSE-103', 'Database Management Systems', 'Computer Engineering', 2, 4),
('COURSE-104', 'Computer Networks & Security', 'Computer Engineering', 2, 3),
('COURSE-105', 'Discrete Mathematics', 'Computer Engineering', 2, 3);

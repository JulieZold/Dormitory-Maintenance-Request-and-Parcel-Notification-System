CREATE DATABASE IF NOT EXISTS dormitory_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dormitory_system;

DROP TABLE IF EXISTS parcels;
DROP TABLE IF EXISTS repair_requests;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS repair_categories;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS buildings;

CREATE TABLE buildings (
  building_id INT AUTO_INCREMENT PRIMARY KEY,
  building_name VARCHAR(100) NOT NULL
);

CREATE TABLE rooms (
  room_id INT AUTO_INCREMENT PRIMARY KEY,
  building_id INT NOT NULL,
  room_no VARCHAR(20) NOT NULL,
  floor INT NULL,
  status ENUM('ว่าง','ใช้งานอยู่','ซ่อมบำรุง') DEFAULT 'ว่าง',
  FOREIGN KEY (building_id) REFERENCES buildings(building_id)
);

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL,
  phone VARCHAR(30) NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  status ENUM('ใช้งานอยู่','ย้ายออก') DEFAULT 'ใช้งานอยู่',
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(room_id)
);

CREATE TABLE repair_categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(80) NOT NULL
);

CREATE TABLE repair_requests (
  request_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  room_id INT NOT NULL,
  category_id INT NOT NULL,
  description TEXT NOT NULL,
  urgency ENUM('ปกติ','ด่วน','ด่วนมาก') DEFAULT 'ปกติ',
  status ENUM('รออนุมัติ','กำลังดำเนินการ','เสร็จสิ้น','ยกเลิก') DEFAULT 'รออนุมัติ',
  appointment_date DATE NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (room_id) REFERENCES rooms(room_id),
  FOREIGN KEY (category_id) REFERENCES repair_categories(category_id)
);

CREATE TABLE parcels (
  parcel_id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  recorded_by INT NOT NULL,
  parcel_type VARCHAR(100) DEFAULT 'พัสดุทั่วไป',
  note TEXT NULL,
  quantity INT DEFAULT 1,
  image_path VARCHAR(255) NULL,
  status ENUM('รอรับ','รับแล้ว') DEFAULT 'รอรับ',
  received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  picked_up_at DATETIME NULL,
  FOREIGN KEY (room_id) REFERENCES rooms(room_id),
  FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

INSERT INTO buildings(building_name) VALUES ('อาคาร A');
INSERT INTO rooms(building_id, room_no, floor, status) VALUES
(1,'101',1,'ใช้งานอยู่'),(1,'102',1,'ใช้งานอยู่'),(1,'103',1,'ใช้งานอยู่'),
(1,'105',1,'ใช้งานอยู่'),(1,'210',2,'ใช้งานอยู่'),(1,'211',2,'ใช้งานอยู่'),(1,'303',3,'ใช้งานอยู่');

INSERT INTO users(room_id, full_name, email, phone, username, password, role, status, must_change_password) VALUES
(NULL,'J','admin@dorm.com','081-000-0000','admin','$2y$12$q1lSux70Wp6UIBsGQWODPelkIDOZswholkezrqeoBapA9xgkImliW','admin','ใช้งานอยู่',0),
(1,'ชัญญา สุข','abcd@gmail.com','081-234-5678','room101','$2y$12$ZM3HPQ0w8vRe1R8oPUzkGuyYg175Pc9y/KJPuuFBW5znPjkY1Tw3a','user','ใช้งานอยู่',1),
(2,'AAAA AAAA','abcd@gmail.com','081-234-5678','room102','$2y$12$ZM3HPQ0w8vRe1R8oPUzkGuyYg175Pc9y/KJPuuFBW5znPjkY1Tw3a','user','ใช้งานอยู่',1),
(3,'สมหญิง มีสุข','somying@gmail.com','081-222-3333','room103','$2y$12$ZM3HPQ0w8vRe1R8oPUzkGuyYg175Pc9y/KJPuuFBW5znPjkY1Tw3a','user','ใช้งานอยู่',1),
(5,'นิตา งามดี','nita@gmail.com','081-333-4444','room303','$2y$12$ZM3HPQ0w8vRe1R8oPUzkGuyYg175Pc9y/KJPuuFBW5znPjkY1Tw3a','user','ใช้งานอยู่',1),
(6,'สมชาย ใจดี','somchai@gmail.com','081-444-5555','room211','$2y$12$ZM3HPQ0w8vRe1R8oPUzkGuyYg175Pc9y/KJPuuFBW5znPjkY1Tw3a','user','ใช้งานอยู่',1);

INSERT INTO repair_categories(category_name) VALUES ('ไฟฟ้า'),('ประปา'),('แอร์'),('อื่น ๆ');

INSERT INTO repair_requests(user_id, room_id, category_id, description, urgency, status, appointment_date, created_at) VALUES
(5,5,3,'แอร์ไม่เย็น','ปกติ','รออนุมัติ','2026-04-29','2026-04-29 09:00:00'),
(6,6,2,'สายชำระรั่ว','ปกติ','กำลังดำเนินการ','2026-04-24','2026-04-24 10:00:00'),
(4,3,2,'ก๊อกน้ำรั่ว','ปกติ','เสร็จสิ้น','2026-04-20','2026-04-20 11:00:00');

INSERT INTO parcels(room_id, recorded_by, parcel_type, quantity, note, received_at) VALUES
(1,1,'พัสดุทั่วไป',2,'', '2026-04-30 13:00:00'),
(5,1,'พัสดุทั่วไป',1,'', '2026-04-30 11:00:00'),
(5,1,'พัสดุทั่วไป',1,'', '2026-04-30 12:00:00'),
(6,1,'พัสดุทั่วไป',3,'', '2026-04-30 16:30:00'),
(2,1,'พัสดุทั่วไป',3,'', '2026-04-20 09:00:00'),
(3,1,'พัสดุทั่วไป',7,'', '2026-04-21 09:00:00');

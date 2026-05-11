CREATE DATABASE IF NOT EXISTS medlink_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE medlink_ai;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  full_name VARCHAR(150) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','user') DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS drugs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  drug_name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS diseases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  disease_name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS predictions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  input_type VARCHAR(50) NOT NULL,
  input_name VARCHAR(255) NOT NULL,
  result_name VARCHAR(255) NOT NULL,
  score FLOAT DEFAULT 0,
  dataset VARCHAR(50) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS search_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  input_type VARCHAR(50) NOT NULL,
  keyword VARCHAR(255) NOT NULL,
  dataset VARCHAR(50) DEFAULT NULL,
  result TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users(username, full_name, email, password, role)
VALUES('admin','Admin MedLink','admin@medlink.local','$2y$10$6oYPNnrPcegrZFMD7c4xreQd6YzIRnHFtLWGOTtgnWxeV2mceyA1C','admin')
ON DUPLICATE KEY UPDATE username=username;

INSERT INTO drugs(drug_name, description) VALUES
('Acetaminophen','Demo drug'),('Aspirin','Demo drug'),('Ibuprofen','Demo drug'),('Metformin','Demo drug')
ON DUPLICATE KEY UPDATE drug_name=drug_name;

INSERT INTO diseases(disease_name, description) VALUES
('Fever','Demo disease'),('Pain','Demo disease'),('Diabetes','Demo disease'),('Inflammation','Demo disease')
ON DUPLICATE KEY UPDATE disease_name=disease_name;

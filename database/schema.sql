SET NAMES utf8mb4;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  family VARCHAR(100) NOT NULL,
  cellphone VARCHAR(20) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role TINYINT UNSIGNED NOT NULL DEFAULT 0,
  time INT UNSIGNED NOT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  last_login INT UNSIGNED DEFAULT NULL,
  last_ip VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_cellphone (cellphone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  cover_image VARCHAR(255) DEFAULT NULL,
  summary TEXT,
  content LONGTEXT NOT NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 1,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_user (user_id),
  KEY idx_category (category_id),
  CONSTRAINT fk_stories_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_stories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (title, slug) VALUES
('رمان', 'novel'),
('علمی', 'science'),
('کودک و نوجوان', 'kids'),
('تاریخ', 'history');

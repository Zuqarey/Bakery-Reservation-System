-- Run this SQL once in phpMyAdmin with bakery_reservation_system selected.
CREATE TABLE IF NOT EXISTS ingredients (
  ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  category VARCHAR(80) NOT NULL,
  quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
  unit VARCHAR(20) NOT NULL,
  status ENUM('ok','low','expiring','expired') NOT NULL DEFAULT 'ok',
  status_text VARCHAR(120) NOT NULL DEFAULT 'In Stock',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_recipe (
  recipe_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  category VARCHAR(80) NOT NULL,
  ingredients_count INT UNSIGNED NOT NULL DEFAULT 0,
  prep_time VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

-- Seed the existing nine sample ingredients only when they are not already present.
INSERT IGNORE INTO ingredients (name,category,quantity,unit,status,status_text) VALUES
('Unsalted Butter','Dairy',12,'kg','expiring','Expiring in 3 days'),
('Organic Bread Flour','Dry Goods',150,'kg','ok','In Stock'),
('Granulated Sugar','Dry Goods',4,'kg','low','Low Stock (Below 10kg)'),
('Heavy Cream 35%','Dairy',2,'L','expired','Expired yesterday'),
('Dark Chocolate 70%','Chocolate',8,'kg','low','Low Stock'),
('Fresh Strawberries','Produce',1.5,'kg','expiring','Expiring in 2 days'),
('Whole Milk','Dairy',0,'L','expired','Expired'),
('Active Dry Yeast','Baking Supplies',250,'g','low','Low Stock'),
('Vanilla Extract','Flavors',300,'ml','ok','In Stock');

-- Seed the four existing sample recipe summaries. Detailed ingredient mappings were not present in the source UI.
INSERT IGNORE INTO product_recipe (name,category,ingredients_count,prep_time) VALUES
('Strawberry Cloud Cake','Cake',6,'45 mins'),
('Artisan Sourdough Loaf','Bread',4,'24 hrs'),
('French Butter Croissant','Savory',5,'12 hrs'),
('Dark Chocolate Fudge Brownie','Sweets',7,'30 mins');

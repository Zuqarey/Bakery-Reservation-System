-- Safe to run once; if the column already exists this script leaves it unchanged.
SET @has_is_active := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'product'
    AND COLUMN_NAME = 'is_active'
);
SET @archive_sql := IF(
  @has_is_active = 0,
  'ALTER TABLE `product` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1',
  'SELECT \"product.is_active already exists\" AS migration_status'
);
PREPARE archive_stmt FROM @archive_sql;
EXECUTE archive_stmt;
DEALLOCATE PREPARE archive_stmt;

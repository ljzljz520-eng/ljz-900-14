-- 检查项改为“管理员上传时手动填写名称 + 扣分值”，本迁移：
--   1) records.item_id 允许为空（不再强制关联预置目录）
--   2) records.item_score_snapshot 改为 DECIMAL(6,2)，支持小数扣分值
-- 可重复执行（幂等）。
-- 执行示例：
--   docker compose exec -T db mysql -uroot -proot hygiene_audit < backend/database/migrate_item_score_decimal.sql

SET NAMES utf8mb4;
USE hygiene_audit;

SET @db = DATABASE();

-- 1) records.item_id 改为可空
SET @sql = (
  SELECT IF(
    (SELECT IS_NULLABLE
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'records' AND COLUMN_NAME = 'item_id') = 'NO',
    'ALTER TABLE `records` MODIFY COLUMN `item_id` int unsigned NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) records.item_score_snapshot 改为 DECIMAL(6,2)
SET @sql = (
  SELECT IF(
    (SELECT COLUMN_TYPE
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'records' AND COLUMN_NAME = 'item_score_snapshot') <> 'decimal(6,2)',
    'ALTER TABLE `records` MODIFY COLUMN `item_score_snapshot` DECIMAL(6,2) DEFAULT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 支持管理员上传时自由填写“检查项名称 + 扣分值”（可重复执行）
-- 1) records.item_id 允许为 NULL：自定义检查项不关联 inspection_items
-- 2) records.item_score_snapshot 改为 DECIMAL(6,2)：兼容小数扣分
-- 执行示例：
-- docker compose exec -T db mysql -uroot -proot hygiene_audit < backend/database/migrate_custom_check_items.sql

SET NAMES utf8mb4;
USE hygiene_audit;

SET @db = DATABASE();

-- records.item_id 改为可空（保留原位置 user_id 之后）
SET @sql = (
  SELECT IF(
    (SELECT IS_NULLABLE
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'records'
       AND COLUMN_NAME = 'item_id') = 'NO',
    'ALTER TABLE `records` MODIFY COLUMN `item_id` int unsigned NULL AFTER `user_id`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- records.item_score_snapshot 改为 DECIMAL(6,2)，可空
ALTER TABLE `records`
  MODIFY COLUMN `item_score_snapshot` DECIMAL(6,2) DEFAULT NULL;

-- 自定义检查项没有 item_id 时，名称快照必须保留；此处仅兜底，正常由后端保证
UPDATE `records`
SET `item_name_snapshot` = '未命名检查项'
WHERE `item_id` IS NULL
  AND (`item_name_snapshot` IS NULL OR `item_name_snapshot` = '');

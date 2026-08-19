-- SQL Table Creation for Map and Notebooks Tracking
-- Vox Portal Database

CREATE TABLE IF NOT EXISTS `map_interactions` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `session_hash` VARCHAR(64) NOT NULL,
    `action_type` VARCHAR(50) NOT NULL COMMENT 'layer_toggle, point_click, notebook_click',
    `item_name` VARCHAR(255) NOT NULL COMMENT 'Name of the layer, point, or notebook'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional indexes for faster querying in the admin panel
CREATE INDEX `idx_action_type` ON `map_interactions` (`action_type`);
CREATE INDEX `idx_timestamp` ON `map_interactions` (`timestamp`);
CREATE INDEX `idx_item_name` ON `map_interactions` (`item_name`);

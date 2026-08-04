<?php
/**
 * DairyBox – Low Stock Notification Helper
 * Call checkLowStockNotifications($db) after any stock change
 * to automatically create/update notifications for low items.
 */

if (!function_exists('checkLowStockNotifications')) {
    function checkLowStockNotifications(PDO $db): void {
        try {
            // Get all active products that are at or below reorder level
            $lowItems = $db->query("
                SELECT id, name, stock_qty, reorder_level, unit
                FROM coop_products
                WHERE is_active = 1 AND stock_qty <= reorder_level
            ")->fetchAll();

            foreach ($lowItems as $item) {
                $title   = "Low Stock: {$item['name']}";
                $qty     = number_format($item['stock_qty'], 1);
                $reorder = number_format($item['reorder_level'], 1);
                $msg     = "{$item['name']} is low on stock: {$qty} {$item['unit']} remaining (reorder level: {$reorder} {$item['unit']}). Please restock immediately.";
                $priority = $item['stock_qty'] == 0 ? 'urgent' : ($item['stock_qty'] <= $item['reorder_level'] * 0.5 ? 'high' : 'medium');

                // Check if a notification for this product already exists (unread)
                $existing = $db->prepare("
                    SELECT id FROM notifications
                    WHERE type = 'system'
                    AND title = ?
                    AND is_read = 0
                    AND target_role = 'dairy_cooperative'
                ");
                $existing->execute([$title]);
                $existingId = $existing->fetchColumn();

                if ($existingId) {
                    // Update existing notification with latest stock info
                    $db->prepare("
                        UPDATE notifications
                        SET message = ?, priority = ?, created_at = NOW()
                        WHERE id = ?
                    ")->execute([$msg, $priority, $existingId]);
                } else {
                    // Create new notification
                    $db->prepare("
                        INSERT INTO notifications
                        (type, title, message, target_role, is_read, priority, due_date)
                        VALUES ('system', ?, ?, 'dairy_cooperative', 0, ?, CURDATE())
                    ")->execute([$title, $msg, $priority]);
                }
            }

            // Also clear resolved notifications for items that are now back in stock
            $okItems = $db->query("
                SELECT name FROM coop_products
                WHERE is_active = 1 AND stock_qty > reorder_level
            ")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($okItems as $name) {
                $db->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE type = 'system'
                    AND title = ?
                    AND target_role = 'dairy_cooperative'
                    AND is_read = 0
                ")->execute(["Low Stock: {$name}"]);
            }

        } catch (Exception $e) {
            // Non-fatal — don't break the main operation
        }
    }
}

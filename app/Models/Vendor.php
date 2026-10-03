<?php

/*
=====================================================
VENDOR MODEL
Handles Vendors and Vendor product organization
Table: eco_vendors
=====================================================
*/

class Vendor {

    private static $tableReady = false;

    public static function ensureTable(): void {
        if (self::$tableReady) return;

        try {
            db()->exec("
                CREATE TABLE IF NOT EXISTS eco_vendors (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    name       VARCHAR(100) NOT NULL UNIQUE,
                    code       VARCHAR(50) DEFAULT NULL,
                    color      VARCHAR(30) DEFAULT '#2563eb',
                    is_default TINYINT(1) NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Exception $e) {}

        // Add vendor_id column to tasks table if not exists
        try {
            db()->exec("ALTER TABLE wp_eco_aplus_tasks ADD COLUMN vendor_id INT DEFAULT NULL");
        } catch (Exception $e) {}

        try {
            db()->exec("CREATE INDEX idx_task_vendor_id ON wp_eco_aplus_tasks (vendor_id)");
        } catch (Exception $e) {}

        // Seed default EcoQuality vendor if not exists
        try {
            $defaultExists = db()->query("SELECT id FROM eco_vendors WHERE is_default = 1 LIMIT 1")->fetchColumn();
            if (!$defaultExists) {
                // Check if EcoQuality exists
                $stmt = db()->prepare("SELECT id FROM eco_vendors WHERE LOWER(TRIM(name)) = 'ecoquality'");
                $stmt->execute();
                $existingId = $stmt->fetchColumn();
                if ($existingId) {
                    db()->prepare("UPDATE eco_vendors SET is_default = 1 WHERE id = ?")->execute([$existingId]);
                } else {
                    db()->prepare("
                        INSERT INTO eco_vendors (name, code, color, is_default)
                        VALUES ('EcoQuality', 'ecoquality', '#2563eb', 1)
                    ")->execute();
                }
            }

            // Assign existing products with NULL vendor_id to default vendor
            $defId = db()->query("SELECT id FROM eco_vendors WHERE is_default = 1 LIMIT 1")->fetchColumn();
            if ($defId) {
                db()->prepare("UPDATE wp_eco_aplus_tasks SET vendor_id = ? WHERE vendor_id IS NULL")->execute([$defId]);
            }
        } catch (Exception $e) {}

        self::$tableReady = true;
    }

    public static function getAll(): array {
        self::ensureTable();

        try {
            $rows = db()->query("
                SELECT v.*,
                       COUNT(t.id) AS product_count
                FROM eco_vendors v
                LEFT JOIN wp_eco_aplus_tasks t ON t.vendor_id = v.id AND t.deleted_at IS NULL
                GROUP BY v.id
                ORDER BY v.is_default DESC, v.name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            return $rows ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    public static function getDefault(): ?array {
        self::ensureTable();
        try {
            $stmt = db()->query("SELECT * FROM eco_vendors WHERE is_default = 1 LIMIT 1");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function getById(int $id): ?array {
        self::ensureTable();
        try {
            $stmt = db()->prepare("SELECT * FROM eco_vendors WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function save(int $id, string $name, string $color = '#2563eb'): array {
        self::ensureTable();

        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'Vendor name cannot be empty'];
        }

        $code = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name)));
        $color = trim($color) ?: '#2563eb';

        try {
            if ($id > 0) {
                // Check duplicate name
                $stmt = db()->prepare("SELECT id FROM eco_vendors WHERE LOWER(TRIM(name)) = LOWER(?) AND id != ?");
                $stmt->execute([$name, $id]);
                if ($stmt->fetchColumn()) {
                    return ['ok' => false, 'message' => 'Vendor with this name already exists'];
                }

                $stmt = db()->prepare("UPDATE eco_vendors SET name = ?, code = ?, color = ? WHERE id = ?");
                $stmt->execute([$name, $code, $color, $id]);
                return ['ok' => true, 'id' => $id, 'name' => $name, 'color' => $color];
            } else {
                // Check duplicate name
                $stmt = db()->prepare("SELECT id FROM eco_vendors WHERE LOWER(TRIM(name)) = LOWER(?)");
                $stmt->execute([$name]);
                if ($stmt->fetchColumn()) {
                    return ['ok' => false, 'message' => 'Vendor with this name already exists'];
                }

                $stmt = db()->prepare("INSERT INTO eco_vendors (name, code, color, is_default) VALUES (?, ?, ?, 0)");
                $stmt->execute([$name, $code, $color]);
                $newId = (int)db()->lastInsertId();
                return ['ok' => true, 'id' => $newId, 'name' => $name, 'color' => $color];
            }
        } catch (Exception $e) {
            return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public static function delete(int $id): array {
        self::ensureTable();

        $vendor = self::getById($id);
        if (!$vendor) {
            return ['ok' => false, 'message' => 'Vendor not found'];
        }

        if (!empty($vendor['is_default'])) {
            return ['ok' => false, 'message' => 'Cannot delete default vendor'];
        }

        try {
            // Find default vendor to reassign products
            $defVendor = self::getDefault();
            $fallbackId = $defVendor ? $defVendor['id'] : null;

            if ($fallbackId) {
                db()->prepare("UPDATE wp_eco_aplus_tasks SET vendor_id = ? WHERE vendor_id = ?")
                    ->execute([$fallbackId, $id]);
            }

            db()->prepare("DELETE FROM eco_vendors WHERE id = ?")->execute([$id]);
            return ['ok' => true, 'fallback_id' => $fallbackId];
        } catch (Exception $e) {
            return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    public static function moveToVendor(array $taskIds, int $targetVendorId): array {
        self::ensureTable();

        if (empty($taskIds)) {
            return ['ok' => false, 'message' => 'No products selected'];
        }

        $targetVendor = self::getById($targetVendorId);
        if (!$targetVendor) {
            return ['ok' => false, 'message' => 'Target vendor not found'];
        }

        $cleanIds = array_filter(array_map('intval', $taskIds));
        if (empty($cleanIds)) {
            return ['ok' => false, 'message' => 'Invalid product selection'];
        }

        try {
            $inQuery = implode(',', $cleanIds);
            $stmt = db()->prepare("
                UPDATE wp_eco_aplus_tasks
                SET vendor_id = ?, last_activity_at = NOW()
                WHERE id IN ($inQuery) AND deleted_at IS NULL
            ");
            $stmt->execute([$targetVendorId]);
            $updated = $stmt->rowCount();

            return [
                'ok' => true,
                'moved_count' => $updated,
                'vendor_id' => $targetVendorId,
                'vendor_name' => $targetVendor['name'],
                'vendor_color' => $targetVendor['color'],
            ];
        } catch (Exception $e) {
            return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

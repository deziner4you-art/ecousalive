<?php

/*
=====================================================
VENDOR CONTROLLER
API endpoints for vendors and vendor product routing
=====================================================
*/

require_once ROOT . '/app/Models/Vendor.php';

class VendorController {

    public function getAll(): void {
        AuthMiddleware::requireAuth();

        $vendors = Vendor::getAll();
        $default = Vendor::getDefault();

        json_success([
            'vendors'    => $vendors,
            'default_id' => $default ? (int)$default['id'] : 1,
        ]);
    }

    public function save(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id    = intval($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#2563eb');

        if ($name === '') {
            json_error('Vendor name is required');
        }

        $res = Vendor::save($id, $name, $color);
        if ($res['ok']) {
            json_success($res);
        } else {
            json_error($res['message'] ?? 'Error saving vendor');
        }
    }

    public function delete(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            json_error('Invalid vendor ID');
        }

        $res = Vendor::delete($id);
        if ($res['ok']) {
            json_success($res);
        } else {
            json_error($res['message'] ?? 'Error deleting vendor');
        }
    }

    public function moveProducts(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        if (!$user) {
            json_error('Authentication required', 401);
        }

        // Admin or users with edit/group permission (including Irfan / AI work) can move products between vendors
        $uname = strtolower($user['username'] ?? '');
        $isAdmin = ($user['role'] ?? '') === 'administrator';
        $canMove = $isAdmin || $uname === 'irfan' || ($user['role'] ?? '') === 'ai_work' || ModulePermission::can($user, 'products', 'edit') || ModulePermission::can($user, 'products', 'group');
        if (!$canMove) {
            json_error('Permission denied: You do not have permission to move products to another vendor.', 403);
        }

        verify_csrf();

        $vendorId = intval($_POST['vendor_id'] ?? $_POST['target_vendor_id'] ?? 0);
        $taskIdsRaw = $_POST['task_ids'] ?? [];

        if (!$vendorId) {
            json_error('Target vendor is required');
        }

        if (is_string($taskIdsRaw)) {
            $decoded = json_decode($taskIdsRaw, true);
            $taskIds = is_array($decoded) ? $decoded : explode(',', $taskIdsRaw);
        } else {
            $taskIds = is_array($taskIdsRaw) ? $taskIdsRaw : [];
        }

        $cleanIds = array_filter(array_map('intval', $taskIds));
        if (empty($cleanIds)) {
            json_error('Please select at least one product to move');
        }

        $res = Vendor::moveToVendor($cleanIds, $vendorId);
        if ($res['ok']) {
            json_success($res);
        } else {
            json_error($res['message'] ?? 'Error moving products');
        }
    }
}

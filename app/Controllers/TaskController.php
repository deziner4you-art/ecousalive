<?php

/*
=====================================================
TASK CONTROLLER
All task-related API actions
=====================================================
*/

class TaskController {

    public function getTasks(): void {
        AuthMiddleware::requireAuth();
        $user  = current_user();
        $tasks = Task::getTasksForRole($user);
        json_success(['data' => $tasks]);
    }

    public function bulkUpdateTasks(): void {
        AuthMiddleware::requireAuth();
        
        $u = current_user();
        $has_permission = false;
        if ($u['role'] === 'administrator' || $u['username'] === 'ilyaeco') {
            $has_permission = true;
        } else {
            $settings = null;
            try {
                $uperm_stmt = db()->prepare("SELECT settings FROM eco_user_permissions WHERE user_id=?");
                $uperm_stmt->execute([(int)$u['id']]);
                $settings = $uperm_stmt->fetchColumn();
            } catch(Exception $e){}

            if ($settings === false || $settings === null) {
                $stmt = db()->prepare("SELECT settings FROM eco_permissions WHERE role=?");
                $stmt->execute([$u['role']]);
                $settings = $stmt->fetchColumn();
            }
            $perm_settings = $settings ? json_decode($settings, true) : [];
            if (!empty($perm_settings['bulk_action'])) {
                $has_permission = true;
            }
        }

        if (!$has_permission) {
            json_error('Unauthorized', 403);
        }

        verify_csrf();

        $task_ids_raw = $_POST['task_ids'] ?? '';
        $bulk_action = $_POST['bulk_action'] ?? ''; // 'urgent' or 'hold'

        $task_ids = array_filter(array_map('intval', explode(',', $task_ids_raw)));

        if (empty($task_ids)) {
            json_error('No products selected');
        }

        if ($bulk_action === 'urgent') {
            Task::bulkMarkUrgent($task_ids);
        } elseif ($bulk_action === 'hold') {
            Task::bulkMarkHold($task_ids);
        } else {
            json_error('Invalid action');
        }

        json_success();
    }

    public function assignProduct(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $workerId = intval($_POST['worker_id'] ?? 0);
        $taskId   = intval($_POST['task_id']   ?? 0);
        if(!$workerId || !$taskId) json_error('Invalid data');

        $result = Task::assignProduct($taskId, $workerId);
        json_success($result);
    }

    public function updateWorkStatus(): void {
        AuthMiddleware::requireAuth();
        verify_csrf();
        $user   = current_user();
        $taskId = intval($_POST['task_id'] ?? 0);
        $status = trim($_POST['status']   ?? '');

        if($user['role'] !== 'worker' && $user['role'] !== 'ai_work' && !is_admin()) json_error('Unauthorized', 403);

        $ok = Task::updateWorkStatus($taskId, $status, $user);
        if($ok){
            json_success();
        } else {
            json_error('Yeh task aapko assign nahi hai. Admin se assign karwayein.');
        }
    }

    public function pauseWork(): void {
        AuthMiddleware::requireAuth();
        verify_csrf();
        $user   = current_user();
        $taskId = intval($_POST['task_id'] ?? 0);
        if($user['role'] !== 'worker' && !is_admin()) json_error('Unauthorized', 403);

        $result = Task::pauseWork($taskId, $user);
        if($result['ok']){
            json_success(['total_seconds' => $result['total_seconds']]);
        } else {
            json_error('Unauthorized');
        }
    }

    public function resumeWork(): void {
        AuthMiddleware::requireAuth();
        verify_csrf();
        $user   = current_user();
        $taskId = intval($_POST['task_id'] ?? 0);
        if($user['role'] !== 'worker' && !is_admin()) json_error('Unauthorized', 403);

        $ok = Task::resumeWork($taskId, $user);
        if($ok) json_success(); else json_error('Unauthorized');
    }

    public function submitQA(): void {
        AuthMiddleware::requireAuth();
        verify_csrf();
        $user = current_user();
        if(!in_array($user['role'],['qa','seo_manager','administrator'])) json_error('Unauthorized', 403);

        $taskId   = intval($_POST['task_id']     ?? 0);
        $media    = trim($_POST['media_link']    ?? '');
        $seoDoc   = trim($_POST['seo_doc_link']  ?? '');

        if(!$taskId || !$media) json_error('Media / Drive link required');

        $row = db()->prepare("SELECT product_type, status, content_approved_at, content_updated_at FROM wp_eco_aplus_tasks WHERE id=?");
        $row->execute([$taskId]); $row = $row->fetch();
        $isInfoStage = ($row && (
            ($row['product_type'] ?? '') === 'Infographics' ||
            (($row['product_type'] ?? '') === 'Info + A Plus' && in_array($row['status'] ?? '', ['Pending', 'AI DONE', 'Infographics']) && empty($row['content_approved_at']) && empty($row['content_updated_at']))
        ));

        if(!$isInfoStage && !$seoDoc && !isset($_POST['has_seo_content'])) json_error('Both links are required');

        $ok = Task::submitQA($taskId, $media, $seoDoc, $user);
        if($ok) json_success(); else json_error('Update failed — check work status');
    }

    public function sendForSEO(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        if($user['role'] !== 'qa' && !is_admin()) json_error('Unauthorized', 403);
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        $row = db()->prepare("SELECT product_type, status, content_approved_at, content_updated_at FROM wp_eco_aplus_tasks WHERE id=?");
        $row->execute([$taskId]); $row = $row->fetch();
        $isInfoStage = ($row && (
            ($row['product_type'] ?? '') === 'Infographics' ||
            (($row['product_type'] ?? '') === 'Info + A Plus' && in_array($row['status'] ?? '', ['Pending', 'AI DONE', 'Infographics']) && empty($row['content_approved_at']) && empty($row['content_updated_at']))
        ));
        if($isInfoStage) json_error('Infographics stage products cannot be sent for SEO');

        $ok     = Task::sendForSEO($taskId, $user['id']);
        if($ok) json_success(); else json_error('Status update failed');
    }

    public function saveFinalLinks(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $taskId = intval($_POST['task_id']    ?? 0);
        $media  = trim($_POST['media_link']   ?? '');
        $seoDoc = trim($_POST['seo_doc_link'] ?? '');

        if(!$taskId || !$media) json_error('Media link required');

        $row = db()->prepare("SELECT product_type FROM wp_eco_aplus_tasks WHERE id=?");
        $row->execute([$taskId]); $row = $row->fetch();
        $isInfoOnly = ($row && ($row['product_type'] ?? '') === 'Infographics');
        if(!$isInfoOnly && !$seoDoc && !isset($_POST['has_seo_content'])) json_error('Both links are required');

        Task::saveFinalLinks($taskId, $media, $seoDoc);
        json_success();
    }


    public function enableAplusService(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        if(!$taskId) json_error('Invalid task');

        $result = Task::startAplusWorkflow($taskId);
        if($result['ok']) json_success(['message' => $result['message']]);
        else json_error($result['message'] ?? 'Action failed');
    }

    public function startAplusWorkflow(): void {
        $this->enableAplusService();
    }

    public function forceStage(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        $stage  = trim($_POST['stage']     ?? '');
        $allowed = ['Pending','AI Work','AI DONE','Generated','Updated','Approved','Working','Paused','In QA','SEO Review','Work Done','Info Done','Republish','Changes','Changes in Content','Changing'];

        if(!$taskId || !in_array($stage, $allowed)) json_error('Invalid task or stage');

        Task::forceStage($taskId, $stage);
        json_success();
    }

    public function holdProduct(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        if($user['role'] !== 'eco_client' && $user['role'] !== 'administrator') json_error('Unauthorized', 403);
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        $hold   = intval($_POST['hold']    ?? 1);
        if(!$taskId) json_error('Invalid task');

        if($user['role'] === 'eco_client'){
            $stmt = db()->prepare("SELECT status FROM wp_eco_aplus_tasks WHERE id=?");
            $stmt->execute([$taskId]);
            $currStatus = $stmt->fetchColumn();
            if(!$currStatus) json_error('Task not found');
            if($hold && $currStatus !== 'Generated'){
                json_error('Unauthorized: Can only hold product at Generated stage', 403);
            }
            if(!$hold && $currStatus !== 'Hold'){
                json_error('Unauthorized: Can only unhold product if it is on Hold', 403);
            }
        }

        Task::holdProduct($taskId, $hold);
        json_success();
    }

    public function toggleUrgent(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        if($user['role'] !== 'eco_client' && $user['role'] !== 'administrator') json_error('Unauthorized', 403);
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        $urgent = intval($_POST['urgent']  ?? 0) ? 1 : 0;
        if(!$taskId) json_error('Invalid task');

        if($user['role'] === 'eco_client'){
            $stmt = db()->prepare("SELECT status FROM wp_eco_aplus_tasks WHERE id=?");
            $stmt->execute([$taskId]);
            $currStatus = $stmt->fetchColumn();
            if(!$currStatus) json_error('Task not found');
            if($currStatus !== 'Generated'){
                json_error('Unauthorized: Can only toggle urgent at Generated stage', 403);
            }
        }

        Task::toggleUrgent($taskId, $urgent);
        json_success();
    }

    public function addProduct(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();

        $hasPerm = false;
        if (($user['role'] ?? '') === 'administrator') {
            $hasPerm = true;
        } else {
            $userPerms = ModulePermission::getForUser($user);
            if (!empty($userPerms['products']['add'])) {
                $hasPerm = true;
            }
        }

        if (!$hasPerm) {
            json_error('Unauthorized', 403);
        }
        
        verify_csrf();

        $productNo    = trim($_POST['product_no']    ?? '');
        $title        = trim($_POST['title']         ?? '');
        $infoSubtasks = trim($_POST['info_subtasks'] ?? '');
        $productLink  = trim($_POST['product_link']  ?? '');

        if(!$productNo || !$title) json_error('Product No aur Title required hain');

        $id = Task::addProduct($productNo, $title, $infoSubtasks, $productLink);
        json_success(['id' => $id]);
    }

    public function sendForContent(): void {
        AuthMiddleware::requireAuth();
        $user   = current_user();
        $taskId = intval($_POST['task_id'] ?? 0);
        verify_csrf();

        $result = Task::sendForContent($taskId, $user);
        if($result['ok']) json_success(); else json_error($result['message'] ?? 'Error');
    }

    public function saveTask(): void {
        AuthMiddleware::requireAuth();
        verify_csrf();

        $id      = intval($_POST['id']     ?? 0);
        $content = $_POST['content']       ?? '';
        $status  = $_POST['status']        ?? 'Generated';
        $user    = current_user();

        $result = Task::saveTask($id, $content, $status, $user);
        if($result['ok']){
            json_success();
        } else {
            json_error($result['message'] ?? 'Error');
        }
    }

    public function clearTask(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id = intval($_POST['id'] ?? 0);
        Task::clearTask($id);
        json_success();
    }

    public function deleteTask(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id = intval($_POST['id'] ?? 0);
        if(!$id) json_error('Invalid ID');
        Task::deleteTask($id);
        json_success();
    }

    public function getDeletedTasks(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        $tasks = Task::getDeletedTasks();
        json_success(['data' => $tasks]);
    }

    public function restoreTask(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id = intval($_POST['task_id'] ?? 0);
        if(!$id) json_error('Invalid ID');
        Task::restoreTask($id);
        json_success();
    }

    public function permanentDeleteTask(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $id = intval($_POST['task_id'] ?? 0);
        if(!$id) json_error('Invalid ID');
        Task::permanentDeleteTask($id);
        json_success();
    }

    public function emptyRecycleBin(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        Task::emptyRecycleBin();
        json_success();
    }

    public function addToFamily(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        $productNo = trim($_POST['product_no'] ?? '');

        if(!$taskId || !$productNo) json_error('Missing task ID or product number');

        $result = Task::addToFamily($taskId, $productNo);
        if($result['ok']){
            json_success();
        } else {
            json_error($result['message'] ?? 'Error');
        }
    }

    public function removeFromFamily(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        if(!$taskId) json_error('Missing task ID');

        Task::removeFromFamily($taskId);
        json_success();
    }

    public function setProductType(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $taskId = intval($_POST['task_id']      ?? 0);
        $type   = trim($_POST['product_type']   ?? '');
        if(!in_array($type, ['','Info + A Plus'])) json_error('Invalid type');

        Task::setProductType($taskId, $type);
        json_success();
    }

    public function publishProduct(): void {
        AuthMiddleware::requireAuth();
        $user = current_user();
        if(!in_array($user['role'],['eco_listing','administrator'])) json_error('Unauthorized', 403);
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        $link   = trim($_POST['published_link'] ?? '') ?: null;
        if(!$taskId) json_error('Invalid task');

        Task::publishProduct($taskId, $user['id'], $link);
        json_success();
    }

    public function unpublishProduct(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        if(!$taskId) json_error('Invalid task');
        Task::unpublishProduct($taskId);
        json_success();
    }

    public function requestRevision(): void {
        AuthMiddleware::requireAuth();
        $u = current_user();
        if(!in_array($u['role'], ['eco_listing', 'administrator'])){
            json_error('Unauthorized', 403);
        }
        verify_csrf();

        $taskId  = intval($_POST['task_id'] ?? 0);
        $revType = trim($_POST['revision_type'] ?? ''); // 'design' or 'content'
        $comment = trim($_POST['comment'] ?? '');

        if(!$taskId || !in_array($revType, ['design', 'content'])){
            json_error('Invalid data');
        }

        $result = Task::requestRevision($taskId, $revType, $comment);
        if($result['ok']){
            json_success();
        } else {
            json_error($result['message'] ?? 'Error');
        }
    }

    public function getWorkers(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        $workers = User::getWorkers();
        json_success(['data' => $workers]);
    }

    public function updateProductServices(): void {
        AuthMiddleware::requireAuth();
        RoleMiddleware::requireAdmin();
        verify_csrf();

        $taskId = intval($_POST['task_id'] ?? 0);
        if(!$taskId) json_error('Invalid task ID');

        $services = [];
        if(isset($_POST['services'])){
            $services = is_array($_POST['services']) ? $_POST['services'] : explode(',', $_POST['services']);
        } else {
            if(!empty($_POST['has_ai'])) $services[] = 'AI Work';
            if(!empty($_POST['has_info'])) $services[] = 'Infographics';
            if(!empty($_POST['has_aplus'])) $services[] = 'A+ Banners';
        }

        $aiWorkerId    = !empty($_POST['ai_worker_id']) ? intval($_POST['ai_worker_id']) : null;
        $infoWorkerId  = !empty($_POST['info_worker_id']) ? intval($_POST['info_worker_id']) : null;
        $aplusWorkerId = !empty($_POST['aplus_worker_id']) ? intval($_POST['aplus_worker_id']) : null;
        $activeAssign  = trim($_POST['active_assign'] ?? 'unchanged');

        $res = Task::updateProductServices($taskId, $services, $aiWorkerId, $infoWorkerId, $aplusWorkerId, $activeAssign);
        if($res['ok']){
            json_success(['message' => $res['message']]);
        } else {
            json_error($res['message'] ?? 'Error updating services');
        }
    }

}

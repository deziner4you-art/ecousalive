<?php

/*
=====================================================
DASHBOARD CONTROLLER
Renders the appropriate view per user role
=====================================================
*/

class DashboardController {

    /* Role → dashboard view file mapping */
    private const VIEW_MAP = [
        'administrator' => 'admin',
        'eco_client'    => 'client',
        'worker'        => 'worker',
        'qa'            => 'qa',
        'd4u_writer'    => 'writer',
        'seo_manager'   => 'seo',
        'eco_listing'   => 'listing',
        'ai_work'       => 'worker',
    ];

    public function show(): void {

        /* Not logged in → show login page */
        if(!current_user()){
            $loginError = null;
            require_once ROOT.'/views/layouts/auth.php';
            return;
        }

        $user         = current_user();
        $filterPerms  = RoleMiddleware::getFilterPermissions();
        
        if ($user['username'] === 'ilyaeco') {
            $dashboardView = 'admin';
        } else {
            $dashboardView = self::VIEW_MAP[$user['role']] ?? 'worker';
        }

        require_once ROOT.'/views/layouts/app.php';

    }

}

<?php
define('ROOT', __DIR__);
require_once ROOT.'/config/database.php';
require_once ROOT.'/config/app.php';
require_once ROOT.'/app/Helpers/Database.php';

$stmt = db()->prepare("SELECT id, product_no, title, product_type, status, work_status, LEFT(content, 300) as c FROM wp_eco_aplus_tasks WHERE product_no LIKE ?");
$stmt->execute(['%EQ24UPETFL%']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
unlink(__FILE__);

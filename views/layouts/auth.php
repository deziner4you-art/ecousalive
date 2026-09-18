<?php
/*
=====================================================
ECO A+ PRO — Auth Layout (login page)
$loginError — string|null, shown if login failed
=====================================================
*/
$publicUrl = PUBLIC_URL;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#081223">
<title><?= htmlspecialchars(APP_NAME) ?> — Login</title>
<link rel="manifest" href="<?= $publicUrl ?>/assets/manifest.json">
<link rel="icon"     href="<?= $publicUrl ?>/assets/icon-192.png" type="image/png">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= $publicUrl ?>/css/app.css">
</head>
<body>
<?php require_once ROOT . '/views/auth/login.php'; ?>
</body>
</html>

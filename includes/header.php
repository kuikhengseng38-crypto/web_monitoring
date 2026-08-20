<?php
$currentPage = $currentPage ?? '';
$admin = current_admin();
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle ?? APP_NAME); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body<?php echo !empty($autoCheck) ? ' data-auto-check="1"' : ''; ?>>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">WM</div>
            <div>
                <strong>Website Monitor</strong>
                <small>Admin panel</small>
            </div>
        </div>
        <nav>
            <a class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
            <a class="<?php echo $currentPage === 'websites' ? 'active' : ''; ?>" href="websites.php">Websites</a>
            <a class="<?php echo $currentPage === 'logs' ? 'active' : ''; ?>" href="logs.php">Monitoring logs</a>
            <a class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>" href="settings.php">Settings</a>
            <a href="<?php echo h(public_status_url()); ?>" target="_blank" rel="noopener">Public status</a>
        </nav>
        <div class="sidebar-foot">
            <span><?php echo h($admin['username'] ?? 'Admin'); ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </aside>
    <main class="content">
        <?php if ($flash): ?>
            <div class="flash flash-<?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
        <?php endif; ?>

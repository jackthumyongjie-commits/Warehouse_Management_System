<?php
declare(strict_types=1);

$user = current_user();
$role = $user['role'] ?? 'user';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <span>📦</span> <?= $role === 'admin' ? 'WMS Admin' : 'WMS User' ?>
        </div>
    </div>
    <ul class="sidebar-menu">
        <?php if ($role === 'admin'): ?>
            <li>
                <a href="<?= url('admin/dashboard.php') ?>" class="sidebar-link <?= str_contains($currentScript, 'admin/dashboard.php') ? 'active' : '' ?>">
                    <span>📊</span> Dashboard
                </a>
            </li>
            <li>
                <a href="<?= url('admin/items.php') ?>" class="sidebar-link <?= str_contains($currentScript, 'admin/items.php') ? 'active' : '' ?>">
                    <span>📦</span> Item Inventory
                </a>
            </li>
            <li>
                <a href="<?= url('admin/movements.php') ?>" class="sidebar-link <?= str_contains($currentScript, 'admin/movements.php') ? 'active' : '' ?>">
                    <span>🔄</span> Stock Movements
                </a>
            </li>
            <li>
                <a href="<?= url('admin/reports.php') ?>" class="sidebar-link <?= str_contains($currentScript, 'admin/reports.php') ? 'active' : '' ?>">
                    <span>📈</span> Reports & Analytics
                </a>
            </li>
            <li>
                <a href="<?= url('admin/users.php') ?>" class="sidebar-link <?= str_contains($currentScript, 'admin/users.php') ? 'active' : '' ?>">
                    <span>👥</span> User Management
                </a>
            </li>
        <?php else: ?>
            <li>
                <a href="<?= url('user/dashboard.php') ?>" class="sidebar-link <?= str_contains($currentScript, 'user/dashboard.php') ? 'active' : '' ?>">
                    <span>📊</span> Dashboard
                </a>
            </li>
            <li>
                <a href="<?= url('user/items.php') ?>" class="sidebar-link <?= str_contains($currentScript, 'user/items.php') ? 'active' : '' ?>">
                    <span>📦</span> Inventory Catalog
                </a>
            </li>
        <?php endif; ?>
    </ul>
    <div class="sidebar-footer">
        <p>&copy; <?= date('Y') ?> Warehouse Management System</p>
    </div>
</aside>

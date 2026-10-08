<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = getDBConnection();

// Summary metrics queries
$totalItemsStmt = $pdo->query('SELECT COUNT(*) FROM items');
$totalItems = (int)$totalItemsStmt->fetchColumn();

$totalStockStmt = $pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM items');
$totalStock = (int)$totalStockStmt->fetchColumn();

$lowStockCountStmt = $pdo->query('SELECT COUNT(*) FROM items WHERE quantity <= reorder_level');
$lowStockCount = (int)$lowStockCountStmt->fetchColumn();

$today = date('Y-m-d');
$todayInStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'IN' AND DATE(movement_date) = :today");
$todayInStmt->execute(['today' => $today]);
$todayIn = (int)$todayInStmt->fetchColumn();

$todayOutStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'OUT' AND DATE(movement_date) = :today");
$todayOutStmt->execute(['today' => $today]);
$todayOut = (int)$todayOutStmt->fetchColumn();

// Fetch low stock items list
$lowStockStmt = $pdo->query('SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level FROM items WHERE quantity <= reorder_level ORDER BY quantity ASC');
$lowStockItems = $lowStockStmt->fetchAll();

// Fetch 5 recent stock movements
$recentMovementsStmt = $pdo->query('
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note, i.item_code, i.item_name, u.full_name as user_name
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    JOIN users u ON sm.created_by = u.id
    ORDER BY sm.movement_date DESC, sm.id DESC
    LIMIT 5
');
$recentMovements = $recentMovementsStmt->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Dashboard Overview</h1>
    <div>
        <a href="<?= url('admin/movements.php') ?>" class="btn btn-primary">+ Record Movement</a>
        <a href="<?= url('admin/items.php') ?>" class="btn btn-secondary">+ Add New Item</a>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid">
    <div class="stat-card info">
        <div class="stat-label">Total Items</div>
        <div class="stat-value"><?= format_number($totalItems) ?></div>
    </div>
    <div class="stat-card success">
        <div class="stat-label">Total Stock Quantity</div>
        <div class="stat-value"><?= format_number($totalStock) ?></div>
    </div>
    <div class="stat-card <?= $lowStockCount > 0 ? 'danger' : 'success' ?>">
        <div class="stat-label">Low Stock Items</div>
        <div class="stat-value"><?= format_number($lowStockCount) ?></div>
    </div>
    <div class="stat-card info">
        <div class="stat-label">Today's Stock IN</div>
        <div class="stat-value">+<?= format_number($todayIn) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="stat-label">Today's Stock OUT</div>
        <div class="stat-value">-<?= format_number($todayOut) ?></div>
    </div>
</div>

<!-- Low Stock Alert Table Section -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">⚠️ Low Stock Alerts (Requires Attention)</h2>
        <span class="badge badge-warning"><?= count($lowStockItems) ?> Items</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($lowStockItems)): ?>
            <div style="padding: 1.5rem; text-align: center; color: var(--text-muted);">
                🎉 All stock levels are currently healthy! No items are at or below reorder level.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Current Quantity</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockItems as $item): ?>
                            <tr class="table-row-warning">
                                <td><strong><?= e($item['item_code']) ?></strong></td>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= e($item['category']) ?></td>
                                <td><?= e($item['location']) ?></td>
                                <td>
                                    <strong class="text-danger" style="color: var(--danger-color);">
                                        <?= e((string)$item['quantity']) ?> <?= e($item['unit']) ?>
                                    </strong>
                                </td>
                                <td><?= e((string)$item['reorder_level']) ?> <?= e($item['unit']) ?></td>
                                <td>
                                    <?php if ($item['quantity'] == 0): ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Low Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= url('admin/movements.php?item_id=' . $item['id']) ?>" class="btn btn-primary btn-sm">
                                        + Restock IN
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Stock Movements -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">🕒 Recent Stock Activity</h2>
        <a href="<?= url('admin/movements.php') ?>" class="btn btn-secondary btn-sm">View All History</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reference / Note</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMovements)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                                No recent stock movements found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentMovements as $mov): ?>
                            <tr>
                                <td><?= format_datetime($mov['movement_date']) ?></td>
                                <td><strong><?= e($mov['item_code']) ?></strong></td>
                                <td><?= e($mov['item_name']) ?></td>
                                <td>
                                    <?php if ($mov['movement_type'] === 'IN'): ?>
                                        <span class="badge badge-success">STOCK IN</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">STOCK OUT</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>
                                        <?= $mov['movement_type'] === 'IN' ? '+' : '-' ?><?= format_number((int)$mov['quantity']) ?>
                                    </strong>
                                </td>
                                <td><?= e($mov['reference_note'] ?: '-') ?></td>
                                <td><?= e($mov['user_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

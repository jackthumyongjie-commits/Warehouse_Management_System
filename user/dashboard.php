<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('user');

$pdo = getDBConnection();

// Summary metrics
$totalItemsStmt = $pdo->query('SELECT COUNT(*) FROM items');
$totalItems = (int)$totalItemsStmt->fetchColumn();

$totalStockStmt = $pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM items');
$totalStock = (int)$totalStockStmt->fetchColumn();

$lowStockCountStmt = $pdo->query('SELECT COUNT(*) FROM items WHERE quantity <= reorder_level');
$lowStockCount = (int)$lowStockCountStmt->fetchColumn();

// Fetch 10 recent stock movements for view-only
$recentMovementsStmt = $pdo->query('
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note, 
           i.item_code, i.item_name, u.full_name as user_name
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    JOIN users u ON sm.created_by = u.id
    ORDER BY sm.movement_date DESC, sm.id DESC
    LIMIT 10
');
$recentMovements = $recentMovementsStmt->fetchAll();

$pageTitle = 'User Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Warehouse Inventory Dashboard</h1>
    <a href="<?= url('user/items.php') ?>" class="btn btn-primary">🔍 Browse Catalog</a>
</div>

<!-- Summary Metrics Cards -->
<div class="stats-grid">
    <div class="stat-card info">
        <div class="stat-label">Total Catalog Items</div>
        <div class="stat-value"><?= format_number($totalItems) ?></div>
    </div>
    <div class="stat-card success">
        <div class="stat-label">Total Stock Quantity</div>
        <div class="stat-value"><?= format_number($totalStock) ?></div>
    </div>
    <div class="stat-card <?= $lowStockCount > 0 ? 'warning' : 'success' ?>">
        <div class="stat-label">Low Stock Alerts</div>
        <div class="stat-value"><?= format_number($lowStockCount) ?></div>
    </div>
</div>

<!-- Recent Movements Activity (Read-Only) -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📜 Recent Inventory Movements History</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Movement Type</th>
                        <th>Quantity</th>
                        <th>Reference / Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMovements)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                                No recent activity logged.
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
                                    <strong><?= $mov['movement_type'] === 'IN' ? '+' : '-' ?><?= format_number((int)$mov['quantity']) ?></strong>
                                </td>
                                <td><?= e($mov['reference_note'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

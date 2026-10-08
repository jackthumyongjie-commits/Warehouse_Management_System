<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$pdo = getDBConnection();
$itemId = (int)($_GET['id'] ?? 0);

if ($itemId <= 0) {
    set_flash_message('danger', 'Invalid item ID.');
    redirect(is_admin() ? 'admin/items.php' : 'user/items.php');
}

// Fetch Item Details
$stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $itemId]);
$item = $stmt->fetch();

if (!$item) {
    set_flash_message('danger', 'Requested item was not found.');
    redirect(is_admin() ? 'admin/items.php' : 'user/items.php');
}

// Fetch stock movement history for this specific item
$movStmt = $pdo->prepare('
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note, u.full_name as user_name
    FROM stock_movements sm
    JOIN users u ON sm.created_by = u.id
    WHERE sm.item_id = :item_id
    ORDER BY sm.movement_date DESC, sm.id DESC
');
$movStmt->execute(['item_id' => $itemId]);
$movements = $movStmt->fetchAll();

$pageTitle = 'Item Details: ' . $item['item_code'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Item Details: <?= e($item['item_name']) ?></h1>
    <div class="btn-group">
        <?php if (is_admin()): ?>
            <a href="<?= url('admin/items.php?action=edit&id=' . $item['id']) ?>" class="btn btn-primary">✏️ Edit Item</a>
            <a href="<?= url('admin/movements.php?item_id=' . $item['id']) ?>" class="btn btn-success">+ Record Movement</a>
            <a href="<?= url('admin/items.php') ?>" class="btn btn-secondary">&larr; Back to Admin Items</a>
        <?php else: ?>
            <a href="<?= url('user/items.php') ?>" class="btn btn-secondary">&larr; Back to Catalog</a>
        <?php endif; ?>
    </div>
</div>

<!-- Item Overview Grid Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📦 Technical Information & Status</h2>
        <?php if ($item['quantity'] <= $item['reorder_level']): ?>
            <span class="badge badge-warning">Low Stock Alert</span>
        <?php else: ?>
            <span class="badge badge-success">Sufficient Stock</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div>
                <strong class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Item Code:</strong>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--primary-color);"><?= e($item['item_code']) ?></div>
            </div>
            <div>
                <strong class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Item Name:</strong>
                <div style="font-size: 1.1rem; font-weight: 600;"><?= e($item['item_name']) ?></div>
            </div>
            <div>
                <strong class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Category:</strong>
                <div><span class="badge badge-secondary"><?= e($item['category']) ?></span></div>
            </div>
            <div>
                <strong class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Warehouse Storage Location:</strong>
                <div>📍 <strong><?= e($item['location']) ?></strong></div>
            </div>
            <div>
                <strong class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Current Available Stock:</strong>
                <div style="font-size: 1.25rem; font-weight: 700;">
                    <?= format_number((int)$item['quantity']) ?> <?= e($item['unit']) ?>
                </div>
            </div>
            <div>
                <strong class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Reorder Level Threshold:</strong>
                <div><?= format_number((int)$item['reorder_level']) ?> <?= e($item['unit']) ?></div>
            </div>
            <div>
                <strong class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Created Date:</strong>
                <div><?= format_datetime($item['created_at']) ?></div>
            </div>
            <div>
                <strong class="text-muted" style="font-size: 0.8rem; text-transform: uppercase;">Last Updated:</strong>
                <div><?= format_datetime($item['updated_at']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Movement History for this item -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📜 Movement History Log</h2>
        <span class="badge badge-info"><?= count($movements) ?> Recorded Transactions</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reference / Reason</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 1.5rem; color: var(--text-muted);">
                                No transaction history exists for this item yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $m): ?>
                            <tr>
                                <td><?= format_datetime($m['movement_date']) ?></td>
                                <td>
                                    <?php if ($m['movement_type'] === 'IN'): ?>
                                        <span class="badge badge-success">IN</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">OUT</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= $m['movement_type'] === 'IN' ? '+' : '-' ?><?= format_number((int)$m['quantity']) ?> <?= e($item['unit']) ?></strong>
                                </td>
                                <td><?= e($m['reference_note'] ?: '-') ?></td>
                                <td><?= e($m['user_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

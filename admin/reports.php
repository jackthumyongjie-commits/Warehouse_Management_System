<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = getDBConnection();

// Fetch items & categories for filters
$allItems = $pdo->query('SELECT id, item_code, item_name FROM items ORDER BY item_name ASC')->fetchAll();
$categories = $pdo->query('SELECT DISTINCT category FROM items ORDER BY category ASC')->fetchAll(PDO::FETCH_COLUMN);

// Filter Parameters
$filterItem     = (int)($_GET['item_id'] ?? 0);
$filterCategory = trim($_GET['category'] ?? '');
$filterType     = strtoupper(trim($_GET['movement_type'] ?? ''));
$startDate      = trim($_GET['start_date'] ?? '');
$endDate        = trim($_GET['end_date'] ?? '');

$sql = '
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note,
           i.item_code, i.item_name, i.category, u.full_name as user_name
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    JOIN users u ON sm.created_by = u.id
    WHERE 1=1
';
$params = [];

if ($filterItem > 0) {
    $sql .= ' AND sm.item_id = :item_id';
    $params['item_id'] = $filterItem;
}

if (!empty($filterCategory)) {
    $sql .= ' AND i.category = :category';
    $params['category'] = $filterCategory;
}

if (in_array($filterType, ['IN', 'OUT'], true)) {
    $sql .= ' AND sm.movement_type = :movement_type';
    $params['movement_type'] = $filterType;
}

if (!empty($startDate)) {
    $sql .= ' AND DATE(sm.movement_date) >= :start_date';
    $params['start_date'] = $startDate;
}

if (!empty($endDate)) {
    $sql .= ' AND DATE(sm.movement_date) <= :end_date';
    $params['end_date'] = $endDate;
}

$sql .= ' ORDER BY sm.movement_date DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportRows = $stmt->fetchAll();

// Calculate Summary Totals
$totalIn = 0;
$totalOut = 0;

foreach ($reportRows as $row) {
    if ($row['movement_type'] === 'IN') {
        $totalIn += (int)$row['quantity'];
    } elseif ($row['movement_type'] === 'OUT') {
        $totalOut += (int)$row['quantity'];
    }
}

$netMovement = $totalIn - $totalOut;

$pageTitle = 'Inventory Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Stock Movement Audit & Reports</h1>
    <div class="no-print">
        <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Print Report</button>
    </div>
</div>

<!-- Filter Bar -->
<form action="<?= url('admin/reports.php') ?>" method="GET" class="filter-bar no-print">
    <div class="form-group" style="flex: 1; margin-bottom: 0;">
        <label for="item_id" class="form-label">Item</label>
        <select name="item_id" id="item_id" class="form-control">
            <option value="0">All Items</option>
            <?php foreach ($allItems as $item): ?>
                <option value="<?= e((string)$item['id']) ?>" <?= $filterItem === (int)$item['id'] ? 'selected' : '' ?>>
                    <?= e($item['item_code']) ?> - <?= e($item['item_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="flex: 1; margin-bottom: 0;">
        <label for="category" class="form-label">Category</label>
        <select name="category" id="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>>
                    <?= e($cat) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="flex: 1; margin-bottom: 0;">
        <label for="movement_type" class="form-label">Type</label>
        <select name="movement_type" id="movement_type" class="form-control">
            <option value="">All Types (IN & OUT)</option>
            <option value="IN" <?= $filterType === 'IN' ? 'selected' : '' ?>>STOCK IN Only</option>
            <option value="OUT" <?= $filterType === 'OUT' ? 'selected' : '' ?>>STOCK OUT Only</option>
        </select>
    </div>

    <div class="form-group" style="flex: 1; margin-bottom: 0;">
        <label for="start_date" class="form-label">From Date</label>
        <input type="date" name="start_date" id="start_date" class="form-control" value="<?= e($startDate) ?>">
    </div>

    <div class="form-group" style="flex: 1; margin-bottom: 0;">
        <label for="end_date" class="form-label">To Date</label>
        <input type="date" name="end_date" id="end_date" class="form-control" value="<?= e($endDate) ?>">
    </div>

    <div style="display: flex; gap: 0.5rem; align-self: flex-end;">
        <button type="submit" class="btn btn-primary">Generate</button>
        <?php if ($filterItem > 0 || !empty($filterCategory) || !empty($filterType) || !empty($startDate) || !empty($endDate)): ?>
            <a href="<?= url('admin/reports.php') ?>" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Summary Summary Cards -->
<div class="stats-grid">
    <div class="stat-card success">
        <div class="stat-label">Total Stock IN</div>
        <div class="stat-value">+<?= format_number($totalIn) ?></div>
    </div>
    <div class="stat-card danger">
        <div class="stat-label">Total Stock OUT</div>
        <div class="stat-value">-<?= format_number($totalOut) ?></div>
    </div>
    <div class="stat-card <?= $netMovement >= 0 ? 'info' : 'warning' ?>">
        <div class="stat-label">Net Movement</div>
        <div class="stat-value"><?= $netMovement >= 0 ? '+' : '' ?><?= format_number($netMovement) ?></div>
    </div>
</div>

<!-- Detailed Movement Report Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Detailed Movement Log Report</h2>
        <span class="badge badge-info"><?= count($reportRows) ?> Transactions Found</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>IN Qty</th>
                        <th>OUT Qty</th>
                        <th>Reference / Note</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportRows)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                No movement records match the report criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportRows as $row): ?>
                            <tr>
                                <td><?= format_datetime($row['movement_date']) ?></td>
                                <td><strong><?= e($row['item_code']) ?></strong></td>
                                <td><?= e($row['item_name']) ?></td>
                                <td><?= e($row['category']) ?></td>
                                <td style="color: var(--success-color); font-weight: 600;">
                                    <?= $row['movement_type'] === 'IN' ? '+' . format_number((int)$row['quantity']) : '-' ?>
                                </td>
                                <td style="color: var(--danger-color); font-weight: 600;">
                                    <?= $row['movement_type'] === 'OUT' ? '-' . format_number((int)$row['quantity']) : '-' ?>
                                </td>
                                <td><?= e($row['reference_note'] ?: '-') ?></td>
                                <td><?= e($row['user_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = getDBConnection();
$errors = [];
$preselectedItemId = (int)($_POST['item_id'] ?? $_GET['item_id'] ?? 0);
$preselectedType = strtoupper(trim($_POST['movement_type'] ?? 'IN'));
$preselectedQty = trim((string)($_POST['quantity'] ?? ''));
$preselectedNote = trim($_POST['reference_note'] ?? '');

// Process Stock Movement Creation POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_movement') {
    $itemId       = (int)($_POST['item_id'] ?? 0);
    $movementType = strtoupper(trim($_POST['movement_type'] ?? ''));
    $quantity     = (int)($_POST['quantity'] ?? 0);
    $reference    = trim($_POST['reference_note'] ?? '');
    $user         = current_user();

    // Validation
    if ($itemId <= 0) $errors[] = 'Please select a valid item.';
    if (!in_array($movementType, ['IN', 'OUT'], true)) $errors[] = 'Invalid movement type. Must be IN or OUT.';
    if ($quantity <= 0) $errors[] = 'Movement quantity must be greater than zero.';

    if (empty($errors)) {
        try {
            // Section 11: Database Transaction with FOR UPDATE Row Locking
            $pdo->beginTransaction();

            // 1 & 2. Lock item row and read current quantity
            $stmt = $pdo->prepare('SELECT id, item_name, quantity FROM items WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $itemId]);
            $itemData = $stmt->fetch();

            if (!$itemData) {
                throw new Exception('Selected item was not found in database.');
            }

            $currentStock = (int)$itemData['quantity'];

            // 3. Validate movement (Section 10: Never allow OUT > current stock)
            if ($movementType === 'OUT' && $quantity > $currentStock) {
                throw new Exception("Stock OUT failed! Requested quantity ({$quantity}) exceeds current available stock ({$currentStock}) for item '{$itemData['item_name']}'.");
            }

            // 4. Calculate new quantity
            $newStock = ($movementType === 'IN') ? ($currentStock + $quantity) : ($currentStock - $quantity);

            // 5. Insert stock movement record
            $insertMov = $pdo->prepare('
                INSERT INTO stock_movements (item_id, movement_type, quantity, movement_date, reference_note, created_by)
                VALUES (:item_id, :movement_type, :quantity, NOW(), :reference_note, :created_by)
            ');
            $insertMov->execute([
                'item_id'        => $itemId,
                'movement_type'  => $movementType,
                'quantity'       => $quantity,
                'reference_note' => $reference,
                'created_by'     => $user['id']
            ]);

            // 6. Update item quantity
            $updateItem = $pdo->prepare('UPDATE items SET quantity = :quantity WHERE id = :id');
            $updateItem->execute([
                'quantity' => $newStock,
                'id'       => $itemId
            ]);

            $pdo->commit();

            set_flash_message('success', "Stock movement recorded successfully! New stock level for '{$itemData['item_name']}': {$newStock}");
            redirect('admin/movements.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Stock Movement Transaction Error: ' . $e->getMessage());
            $errors[] = $e->getMessage();
        }
    }
}

// Fetch list of items for select dropdown
$allItemsStmt = $pdo->query('SELECT id, item_code, item_name, quantity, unit FROM items ORDER BY item_name ASC');
$allItems = $allItemsStmt->fetchAll();

// Filters for History Table
$filterItem     = (int)($_GET['filter_item'] ?? 0);
$filterType     = strtoupper(trim($_GET['filter_type'] ?? ''));
$filterStartDate = trim($_GET['start_date'] ?? '');
$filterEndDate   = trim($_GET['end_date'] ?? '');

$historySql = '
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note, 
           i.item_code, i.item_name, i.unit, u.full_name as user_name
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    JOIN users u ON sm.created_by = u.id
    WHERE 1=1
';
$params = [];

if ($filterItem > 0) {
    $historySql .= ' AND sm.item_id = :filter_item';
    $params['filter_item'] = $filterItem;
}

if (in_array($filterType, ['IN', 'OUT'], true)) {
    $historySql .= ' AND sm.movement_type = :filter_type';
    $params['filter_type'] = $filterType;
}

if (!empty($filterStartDate)) {
    $historySql .= ' AND DATE(sm.movement_date) >= :start_date';
    $params['start_date'] = $filterStartDate;
}

if (!empty($filterEndDate)) {
    $historySql .= ' AND DATE(sm.movement_date) <= :end_date';
    $params['end_date'] = $filterEndDate;
}

$historySql .= ' ORDER BY sm.movement_date DESC, sm.id DESC';

$historyStmt = $pdo->prepare($historySql);
$historyStmt->execute($params);
$movements = $historyStmt->fetchAll();

$pageTitle = 'Stock Movements';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Stock IN / OUT Transactions</h1>
    <a href="<?= url('admin/reports.php') ?>" class="btn btn-secondary">📈 Generate Movement Reports</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul style="margin-left: 1.25rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Record Stock Movement Form -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📝 Record Stock Movement</h2>
    </div>
    <div class="card-body">
        <form action="<?= url('admin/movements.php') ?>" method="POST" id="stockMovementForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_movement">

            <div class="form-grid">
                <div class="form-group">
                    <label for="item_id" class="form-label">Select Inventory Item <span class="text-danger">*</span></label>
                    <select name="item_id" id="item_id" class="form-control" required>
                        <option value="">-- Choose Item --</option>
                        <?php foreach ($allItems as $item): ?>
                            <option 
                                value="<?= e((string)$item['id']) ?>"
                                data-stock="<?= e((string)$item['quantity']) ?>"
                                <?= ($preselectedItemId === (int)$item['id']) ? 'selected' : '' ?>
                            >
                                <?= e($item['item_code']) ?> - <?= e($item['item_name']) ?> (Available: <?= e((string)$item['quantity']) ?> <?= e($item['unit']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="movement_type" class="form-label">Transaction Type <span class="text-danger">*</span></label>
                    <select name="movement_type" id="movement_type" class="form-control" required>
                        <option value="IN" <?= $preselectedType === 'IN' ? 'selected' : '' ?>>📥 STOCK IN (Restock / Received)</option>
                        <option value="OUT" <?= $preselectedType === 'OUT' ? 'selected' : '' ?>>📤 STOCK OUT (Dispatch / Issued)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="movement_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                    <input 
                        type="number" 
                        name="quantity" 
                        id="movement_quantity" 
                        class="form-control" 
                        min="1" 
                        placeholder="Enter quantity" 
                        value="<?= e($preselectedQty) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="reference_note" class="form-label">Reference / Reason / Note</label>
                    <input 
                        type="text" 
                        name="reference_note" 
                        id="reference_note" 
                        class="form-control" 
                        placeholder="e.g. PO #9942, Customer Order #120, Damaged Stock"
                        value="<?= e($preselectedNote) ?>"
                    >
                </div>
            </div>

            <div style="margin-top: 1rem;">
                <button type="submit" class="btn btn-primary">Process Movement</button>
            </div>
        </form>
    </div>
</div>

<!-- History Filter Bar -->
<form action="<?= url('admin/movements.php') ?>" method="GET" class="filter-bar">
    <div class="form-group" style="flex: 1.5; margin-bottom: 0;">
        <label for="filter_item" class="form-label">Filter Item</label>
        <select name="filter_item" id="filter_item" class="form-control">
            <option value="0">All Items</option>
            <?php foreach ($allItems as $item): ?>
                <option value="<?= e((string)$item['id']) ?>" <?= $filterItem === (int)$item['id'] ? 'selected' : '' ?>>
                    <?= e($item['item_code']) ?> - <?= e($item['item_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="flex: 1; margin-bottom: 0;">
        <label for="filter_type" class="form-label">Movement Type</label>
        <select name="filter_type" id="filter_type" class="form-control">
            <option value="">All Types</option>
            <option value="IN" <?= $filterType === 'IN' ? 'selected' : '' ?>>IN Only</option>
            <option value="OUT" <?= $filterType === 'OUT' ? 'selected' : '' ?>>OUT Only</option>
        </select>
    </div>

    <div class="form-group" style="flex: 1; margin-bottom: 0;">
        <label for="start_date" class="form-label">Start Date</label>
        <input type="date" name="start_date" id="start_date" class="form-control" value="<?= e($filterStartDate) ?>">
    </div>

    <div class="form-group" style="flex: 1; margin-bottom: 0;">
        <label for="end_date" class="form-label">End Date</label>
        <input type="date" name="end_date" id="end_date" class="form-control" value="<?= e($filterEndDate) ?>">
    </div>

    <div style="display: flex; gap: 0.5rem; align-self: flex-end;">
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <?php if ($filterItem > 0 || !empty($filterType) || !empty($filterStartDate) || !empty($filterEndDate)): ?>
            <a href="<?= url('admin/movements.php') ?>" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Movement History Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">📜 Stock Movement History Log</h2>
        <span class="badge badge-info"><?= count($movements) ?> Records</span>
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
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                No stock movements recorded matching your filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $mov): ?>
                            <tr>
                                <td><?= format_datetime($mov['movement_date']) ?></td>
                                <td><strong><?= e($mov['item_code']) ?></strong></td>
                                <td><?= e($mov['item_name']) ?></td>
                                <td>
                                    <?php if ($mov['movement_type'] === 'IN'): ?>
                                        <span class="badge badge-success">IN</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">OUT</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong>
                                        <?= $mov['movement_type'] === 'IN' ? '+' : '-' ?><?= format_number((int)$mov['quantity']) ?> <?= e($mov['unit']) ?>
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

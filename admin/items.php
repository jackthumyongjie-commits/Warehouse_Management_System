<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$pdo = getDBConnection();
$errors = [];
$action = $_GET['action'] ?? 'list';
$editItem = null;

// Handle Delete Action via POST (CSRF protected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    
    if ($itemId > 0) {
        // Check if item has movement history before deletion
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE item_id = :item_id');
        $checkStmt->execute(['item_id' => $itemId]);
        $movementCount = (int)$checkStmt->fetchColumn();

        if ($movementCount > 0) {
            set_flash_message('danger', "Cannot delete this item because it has {$movementCount} recorded stock movement(s). Archival/history must be preserved.");
        } else {
            try {
                $deleteStmt = $pdo->prepare('DELETE FROM items WHERE id = :id');
                $deleteStmt->execute(['id' => $itemId]);
                set_flash_message('success', 'Item deleted successfully.');
            } catch (PDOException $e) {
                error_log('Delete Item Error: ' . $e->getMessage());
                set_flash_message('danger', 'Failed to delete item due to a database constraint.');
            }
        }
    }
    redirect('admin/items.php');
}

// Handle Add / Edit Item POST form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action_add']) || isset($_POST['action_edit']))) {
    $action = isset($_POST['action_edit']) ? 'edit' : 'add';
    $itemId       = (int)($_POST['item_id'] ?? 0);
    $itemCode     = strtoupper(trim($_POST['item_code'] ?? ''));
    $itemName     = trim($_POST['item_name'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $location     = trim($_POST['location'] ?? '');
    $unit         = trim($_POST['unit'] ?? 'pcs');
    $reorderLevel = (int)($_POST['reorder_level'] ?? 0);
    $quantity     = (int)($_POST['quantity'] ?? 0);

    // Validation
    if (empty($itemCode)) $errors[] = 'Item code is required.';
    if (empty($itemName)) $errors[] = 'Item name is required.';
    if (empty($category)) $errors[] = 'Category is required.';
    if (empty($location)) $errors[] = 'Location is required.';
    if (empty($unit))     $errors[] = 'Unit is required.';
    if ($reorderLevel < 0) $errors[] = 'Reorder level cannot be negative.';
    if ($quantity < 0)     $errors[] = 'Quantity cannot be negative.';

    // Check unique item code
    if (!empty($itemCode)) {
        if (isset($_POST['action_edit']) && $itemId > 0) {
            $uniqueStmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE item_code = :code AND id != :id');
            $uniqueStmt->execute(['code' => $itemCode, 'id' => $itemId]);
        } else {
            $uniqueStmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE item_code = :code');
            $uniqueStmt->execute(['code' => $itemCode]);
        }
        if ((int)$uniqueStmt->fetchColumn() > 0) {
            $errors[] = "Item code '{$itemCode}' is already in use by another item.";
        }
    }

    if (empty($errors)) {
        if (isset($_POST['action_add'])) {
            try {
                $pdo->beginTransaction();
                
                $insertStmt = $pdo->prepare('
                    INSERT INTO items (item_code, item_name, category, location, quantity, unit, reorder_level)
                    VALUES (:item_code, :item_name, :category, :location, :quantity, :unit, :reorder_level)
                ');
                $insertStmt->execute([
                    'item_code'     => $itemCode,
                    'item_name'     => $itemName,
                    'category'      => $category,
                    'location'      => $location,
                    'quantity'      => $quantity,
                    'unit'          => $unit,
                    'reorder_level' => $reorderLevel
                ]);

                $newItemId = (int)$pdo->lastInsertId();

                // Record initial stock movement if initial quantity > 0
                if ($quantity > 0) {
                    $currentUser = current_user();
                    $movStmt = $pdo->prepare('
                        INSERT INTO stock_movements (item_id, movement_type, quantity, movement_date, reference_note, created_by)
                        VALUES (:item_id, "IN", :quantity, NOW(), "Initial Opening Balance", :created_by)
                    ');
                    $movStmt->execute([
                        'item_id'    => $newItemId,
                        'quantity'   => $quantity,
                        'created_by' => $currentUser['id']
                    ]);
                }

                $pdo->commit();
                set_flash_message('success', "Item '{$itemName}' added successfully!");
                redirect('admin/items.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Add Item Error: ' . $e->getMessage());
                $errors[] = 'Failed to add item due to system error: ' . $e->getMessage();
            }
        } elseif (isset($_POST['action_edit']) && $itemId > 0) {
            try {
                // Update item metadata (Note: quantity is updated via Stock Movements to ensure data integrity)
                $updateStmt = $pdo->prepare('
                    UPDATE items 
                    SET item_code = :item_code,
                        item_name = :item_name,
                        category = :category,
                        location = :location,
                        unit = :unit,
                        reorder_level = :reorder_level
                    WHERE id = :id
                ');
                $updateStmt->execute([
                    'item_code'     => $itemCode,
                    'item_name'     => $itemName,
                    'category'      => $category,
                    'location'      => $location,
                    'unit'          => $unit,
                    'reorder_level' => $reorderLevel,
                    'id'            => $itemId
                ]);

                set_flash_message('success', "Item '{$itemName}' updated successfully.");
                redirect('admin/items.php');
            } catch (PDOException $e) {
                error_log('Edit Item Error: ' . $e->getMessage());
                $errors[] = 'Failed to update item information.';
            }
        }
    }
}

// Handle fetching item for edit mode
if ($action === 'edit') {
    $editId = (int)($_GET['id'] ?? $_POST['item_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editItem = $stmt->fetch();
    if (!$editItem && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        set_flash_message('danger', 'Item not found.');
        redirect('admin/items.php');
    }
}

// Fetch list with filters
$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$sql = 'SELECT * FROM items WHERE 1=1';
$params = [];

if (!empty($search)) {
    $sql .= ' AND (item_code LIKE :search OR item_name LIKE :search OR location LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (!empty($categoryFilter)) {
    $sql .= ' AND category = :category';
    $params['category'] = $categoryFilter;
}

$sql .= ' ORDER BY item_code ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Fetch distinct categories for filter dropdown
$categoriesStmt = $pdo->query('SELECT DISTINCT category FROM items ORDER BY category ASC');
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Item Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Item Inventory Management</h1>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <a href="<?= url('admin/items.php?action=add') ?>" class="btn btn-primary">+ Add New Item</a>
    <?php else: ?>
        <a href="<?= url('admin/items.php') ?>" class="btn btn-secondary">&larr; Back to Items List</a>
    <?php endif; ?>
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

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- Add / Edit Item Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= $action === 'edit' ? 'Edit Item Details' : 'Create New Inventory Item' ?></h2>
        </div>
        <div class="card-body">
            <form action="<?= url('admin/items.php') ?>" method="POST">
                <?= csrf_field() ?>
                <?php if ($action === 'edit' && $editItem): ?>
                    <input type="hidden" name="action_edit" value="1">
                    <input type="hidden" name="item_id" value="<?= e((string)$editItem['id']) ?>">
                <?php else: ?>
                    <input type="hidden" name="action_add" value="1">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="item_code" class="form-label">Item Code <span class="text-danger">*</span></label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input 
                                type="text" 
                                id="item_code" 
                                name="item_code" 
                                class="form-control" 
                                value="<?= e($editItem ? $editItem['item_code'] : ($_POST['item_code'] ?? '')) ?>" 
                                placeholder="e.g. ITEM-1001" 
                                required
                            >
                            <?php if ($action === 'add'): ?>
                                <button type="button" class="btn btn-secondary btn-sm" id="js-generate-item-code" title="Auto Generate Code">
                                    Generate
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="item_name" class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            id="item_name" 
                            name="item_name" 
                            class="form-control" 
                            value="<?= e($editItem ? $editItem['item_name'] : ($_POST['item_name'] ?? '')) ?>" 
                            placeholder="e.g. Wireless Mouse" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            id="category" 
                            name="category" 
                            class="form-control" 
                            value="<?= e($editItem ? $editItem['category'] : ($_POST['category'] ?? '')) ?>" 
                            placeholder="e.g. Electronics, Hardware, Packaging" 
                            list="category-suggestions"
                            required
                        >
                        <datalist id="category-suggestions">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label for="location" class="form-label">Storage Location <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            id="location" 
                            name="location" 
                            class="form-control" 
                            value="<?= e($editItem ? $editItem['location'] : ($_POST['location'] ?? '')) ?>" 
                            placeholder="e.g. Shelf A-1, Bin 4" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="unit" class="form-label">Measurement Unit <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            id="unit" 
                            name="unit" 
                            class="form-control" 
                            value="<?= e($editItem ? $editItem['unit'] : ($_POST['unit'] ?? 'pcs')) ?>" 
                            placeholder="pcs, boxes, kg, units" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="reorder_level" class="form-label">Reorder Alert Level <span class="text-danger">*</span></label>
                        <input 
                            type="number" 
                            id="reorder_level" 
                            name="reorder_level" 
                            class="form-control" 
                            min="0" 
                            value="<?= e((string)($editItem ? $editItem['reorder_level'] : ($_POST['reorder_level'] ?? 5))) ?>" 
                            required
                        >
                    </div>

                    <?php if ($action === 'add'): ?>
                        <div class="form-group">
                            <label for="quantity" class="form-label">Initial Opening Stock Quantity</label>
                            <input 
                                type="number" 
                                id="quantity" 
                                name="quantity" 
                                class="form-control" 
                                min="0" 
                                value="<?= e((string)($_POST['quantity'] ?? 0)) ?>"
                            >
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label class="form-label">Current Quantity</label>
                            <input type="text" class="form-control" value="<?= e((string)$editItem['quantity']) ?> <?= e($editItem['unit']) ?>" disabled readonly>
                            <small class="text-muted" style="font-size: 0.75rem; color: var(--text-muted);">
                                Note: Use Stock Movements page to adjust inventory quantities safely.
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary">
                        <?= $action === 'edit' ? 'Save Changes' : 'Create Item' ?>
                    </button>
                    <a href="<?= url('admin/items.php') ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>

    <!-- Filter & Search Bar -->
    <form action="<?= url('admin/items.php') ?>" method="GET" class="filter-bar">
        <div class="form-group" style="flex: 2; margin-bottom: 0;">
            <label for="search" class="form-label">Search Items</label>
            <input 
                type="text" 
                id="search" 
                name="search" 
                class="form-control" 
                placeholder="Search by code, name, location..." 
                value="<?= e($search) ?>"
            >
        </div>

        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label for="category" class="form-label">Category</label>
            <select name="category" id="category" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                        <?= e($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem; align-self: flex-end;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if (!empty($search) || !empty($categoryFilter)): ?>
                <a href="<?= url('admin/items.php') ?>" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Items Data Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Warehouse Items</h2>
            <span class="badge badge-info"><?= count($items) ?> Total Records</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Quantity</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    No items match your query.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): 
                                $isLowStock = $item['quantity'] <= $item['reorder_level'];
                            ?>
                                <tr class="<?= $isLowStock ? 'table-row-warning' : '' ?>">
                                    <td><strong><?= e($item['item_code']) ?></strong></td>
                                    <td><?= e($item['item_name']) ?></td>
                                    <td><span class="badge badge-secondary"><?= e($item['category']) ?></span></td>
                                    <td><?= e($item['location']) ?></td>
                                    <td>
                                        <strong><?= format_number((int)$item['quantity']) ?> <?= e($item['unit']) ?></strong>
                                    </td>
                                    <td><?= format_number((int)$item['reorder_level']) ?> <?= e($item['unit']) ?></td>
                                    <td>
                                        <?php if ($item['quantity'] == 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif ($isLowStock): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?= url('user/item-details.php?id=' . $item['id']) ?>" class="btn btn-secondary btn-sm" title="View Details">
                                                👁️
                                            </a>
                                            <a href="<?= url('admin/items.php?action=edit&id=' . $item['id']) ?>" class="btn btn-primary btn-sm" title="Edit Item">
                                                ✏️
                                            </a>
                                            <a href="<?= url('admin/movements.php?item_id=' . $item['id']) ?>" class="btn btn-success btn-sm" title="Stock Movement">
                                                🔄
                                            </a>
                                            <form action="<?= url('admin/items.php') ?>" method="POST" style="display: inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="item_id" value="<?= e((string)$item['id']) ?>">
                                                <button 
                                                    type="submit" 
                                                    class="btn btn-danger btn-sm js-confirm-delete" 
                                                    data-name="<?= e($item['item_name']) ?>"
                                                    title="Delete Item"
                                                >
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

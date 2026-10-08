<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('user');

$pdo = getDBConnection();

$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$sql = 'SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level FROM items WHERE 1=1';
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

// Fetch categories for dropdown
$categoriesStmt = $pdo->query('SELECT DISTINCT category FROM items ORDER BY category ASC');
$categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Inventory Catalog';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Warehouse Catalog</h1>
</div>

<!-- Search & Filter Bar -->
<form action="<?= url('user/items.php') ?>" method="GET" class="filter-bar">
    <div class="form-group" style="flex: 2; margin-bottom: 0;">
        <label for="search" class="form-label">Search Items</label>
        <input 
            type="text" 
            id="search" 
            name="search" 
            class="form-control" 
            placeholder="Search item code, name, location..." 
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
            <a href="<?= url('user/items.php') ?>" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Items Catalog Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Available Inventory Items</h2>
        <span class="badge badge-info"><?= count($items) ?> Items Listed</span>
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
                        <th>Current Quantity</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                No items matching your search criteria.
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
                                    <a href="<?= url('user/item-details.php?id=' . $item['id']) ?>" class="btn btn-secondary btn-sm">
                                        👁️ Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php';

// Authentication Check: User must be logged in to query the API
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Authentication session required.'
    ]);
    exit;
}

$pdo = getDBConnection();

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$sql = 'SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level, created_at, updated_at FROM items WHERE 1=1';
$params = [];

if (!empty($search)) {
    $sql .= ' AND (item_code LIKE :search OR item_name LIKE :search OR location LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (!empty($category)) {
    $sql .= ' AND category = :category';
    $params['category'] = $category;
}

$sql .= ' ORDER BY item_name ASC LIMIT 100';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count'   => count($items),
        'data'    => $items
    ], JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    error_log('API Search Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error while searching items.'
    ]);
}

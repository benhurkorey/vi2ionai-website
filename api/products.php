<?php
/**
 * Vi2ionai Fleet — Products API
 * GET  /api/products.php           → list all visible products
 * GET  /api/products.php?id=1      → single product
 *
 * Replaces the Google Sheets Apps Script URL entirely.
 */

require_once __DIR__ . '/../includes/db.php';

// CORS for same-origin (just in case)
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60'); // 1-minute cache

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDB();

    // Single product
    if (!empty($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $db->prepare(
            'SELECT * FROM products WHERE id = ? AND is_visible = 1 LIMIT 1'
        );
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }
        echo json_encode(formatProduct($product));
        exit;
    }

    // All visible products
    $stmt = $db->query(
        'SELECT * FROM products WHERE is_visible = 1 ORDER BY sort_order ASC, id ASC'
    );
    $products = $stmt->fetchAll();

    echo json_encode(array_map('formatProduct', $products));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

function formatProduct(array $p): array {
    return [
        'id'          => (int)  $p['id'],
        'slug'        => $p['slug'],
        'name'        => $p['name'],
        'price'       => (float) $p['price'],
        'category'    => $p['category'],
        'badge'       => $p['badge']      ?? '',
        'badgeClass'  => $p['badge_class'] ?? 'badge-dark',
        'description' => $p['description'] ?? '',
        'benefits'    => $p['benefits']   ?? '',
        'hover_text'  => $p['hover_text'] ?? '',
        'stripeLink'  => $p['stripe_link'] ?? '',
        'image'       => $p['image_path']
                            ? (UPLOAD_URL . ltrim($p['image_path'], '/'))
                            : '',
        'sort_order'  => (int) $p['sort_order'],
    ];
}

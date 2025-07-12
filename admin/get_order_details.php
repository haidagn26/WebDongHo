<?php
include '../db_connect.php';

header('Content-Type: application/json');

// Kiểm tra kết nối cơ sở dữ liệu
if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Kiểm tra order_id
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    echo json_encode(['error' => 'Invalid or missing order ID']);
    exit;
}

$order_id = (int)$_GET['order_id'];

try {
    $stmt = $conn->prepare("
        SELECT o.id, o.customer_name, o.phone, o.address, o.total_amount, 
               o.payment_status, o.order_status, o.created_at,
               od.product_id, od.quantity, od.price, p.name AS product_name
        FROM orders o
        LEFT JOIN order_details od ON o.id = od.order_id
        LEFT JOIN products p ON od.product_id = p.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) {
        $order = [
            'id' => $rows[0]['id'],
            'customer_name' => $rows[0]['customer_name'],
            'phone' => $rows[0]['phone'],
            'address' => $rows[0]['address'],
            'total_amount' => (float)$rows[0]['total_amount'],
            'payment_status' => $rows[0]['payment_status'],
            'order_status' => $rows[0]['order_status'],
            'created_at' => $rows[0]['created_at'],
            'details' => []
        ];

        foreach ($rows as $row) {
            if ($row['product_id']) {
                $order['details'][] = [
                    'product_name' => $row['product_name'] ?? 'Unknown Product',
                    'quantity' => (int)$row['quantity'],
                    'price' => (float)$row['price']
                ];
            }
        }

        // Kiểm tra nếu không có chi tiết đơn hàng
        if (empty($order['details'])) {
            $order['details_message'] = 'No products found for this order';
        }

        echo json_encode($order);
    } else {
        echo json_encode(['error' => 'Order not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
}
?>
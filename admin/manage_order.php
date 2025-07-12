<?php
session_start();
include '../db_connect.php';

// Kiểm tra quyền admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Kiểm tra kết nối cơ sở dữ liệu
if (!$conn) {
    die('Database connection failed');
}

// Xử lý cập nhật trạng thái thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {
    $order_id = (int)$_POST['order_id'];
    $payment_status = $_POST['payment_status'];

    // Kiểm tra payment_status hợp lệ
    if (!in_array($payment_status, ['pending', 'paid'])) {
        $error = "Invalid payment status";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
            $stmt->execute([$payment_status, $order_id]);
            $success = "Payment status updated successfully";
        } catch (PDOException $e) {
            $error = "Error updating payment status: " . $e->getMessage();
            error_log($error, 3, 'errors.log');
        }
    }
}

// Xử lý cập nhật trạng thái đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = (int)$_POST['order_id'];
    $order_status = $_POST['order_status'];

    // Kiểm tra order_status hợp lệ
    if (!in_array($order_status, ['pending', 'approved', 'canceled'])) {
        $error = "Invalid order status";
    } else {
        try {
            // Kiểm tra logic: không cho phép hủy đơn hàng đã thanh toán
            $stmt = $conn->prepare("SELECT payment_status FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($order_status === 'canceled' && $order['payment_status'] === 'paid') {
                $error = "Cannot cancel a paid order";
            } else {
                $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
                $stmt->execute([$order_status, $order_id]);
                $success = "Order status updated successfully";
            }
        } catch (PDOException $e) {
            $error = "Error updating order status: " . $e->getMessage();
            error_log($error, 3, 'errors.log');
        }
    }
}

// Lấy danh sách đơn hàng (thêm phân trang)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $stmt = $conn->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);

    $stmt = $conn->prepare("SELECT * FROM orders ORDER BY created_at DESC LIMIT ? OFFSET ?");
    // Ràng buộc tham số với kiểu integer
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching orders: " . $e->getMessage();
    error_log($error, 3, 'errors.log');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Jewelry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/order.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'nav_bar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>Manage Orders</h1>
            </header>
            <section>
                <!-- Hiển thị thông báo -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Total (VND)</th>
                                <th>Payment Status</th>
                                <th>Order Status</th>
                                <th>Order Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No orders found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <select name="payment_status" onchange="this.form.submit()">
                                                    <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                </select>
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="update_payment_status" value="1">
                                            </form>
                                        </td>
                                        <td>
                                            <span class="status-<?php echo $order['order_status']; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                            <div class="mt-1">
                                                <?php if ($order['order_status'] !== 'approved'): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to approve this order?');">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="order_status" value="approved">
                                                        <input type="hidden" name="update_order_status" value="1">
                                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($order['order_status'] !== 'canceled'): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="order_status" value="canceled">
                                                        <input type="hidden" name="update_order_status" value="1">
                                                        <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary view-order-btn" data-order-id="<?php echo $order['id']; ?>" data-bs-toggle="modal" data-bs-target="#orderDetailsModal">View</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="order-details-content">
                    <!-- Nội dung chi tiết đơn hàng sẽ được điền bằng AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Xử lý nút View để lấy chi tiết đơn hàng
            document.querySelectorAll('.view-order-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    fetch(`get_order_details.php?order_id=${orderId}`)
                        .then(response => response.json())
                        .then(data => {
                            const content = document.getElementById('order-details-content');
                            if (data.error) {
                                content.innerHTML = `<p class="text-danger">${data.error}</p>`;
                            } else {
                                content.innerHTML = `
                                    <p><strong>Order ID:</strong> #${data.id}</p>
                                    <p><strong>Customer:</strong> ${data.customer_name}</p>
                                    <p><strong>Phone:</strong> ${data.phone}</p>
                                    <p><strong>Address:</strong> ${data.address}</p>
                                    <p><strong>Payment Status:</strong> ${data.payment_status}</p>
                                    <p><strong>Order Status:</strong> ${data.order_status}</p>
                                    <p><strong>Order Date:</strong> ${new Date(data.created_at).toLocaleString('vi-VN')}</p>
                                    <h6>Products:</h6>
                                    <ul>
                                        ${data.details.length ? data.details.map(item => `
                                            <li>${item.quantity}x ${item.product_name} - ${Number(item.price).toLocaleString('vi-VN')} VND</li>
                                        `).join('') : '<li>No products found</li>'}
                                    </ul>
                                    <p><strong>Total:</strong> ${Number(data.total_amount).toLocaleString('vi-VN')} VND</p>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            document.getElementById('order-details-content').innerHTML = `<p class="text-danger">Lỗi khi tải chi tiết đơn hàng.</p>`;
                        });
                });
            });

            // Đóng thông báo sau 5 giây
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => alert.remove());
            }, 5000);
        });
    </script>
</body>
</html>
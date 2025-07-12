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

try {
    // Lấy dữ liệu cho Dashboard
    $total_products = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $total_orders = $conn->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $total_admins = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

    // Thống kê doanh thu (tổng doanh thu và theo danh mục)
    $total_revenue = $conn->query("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'paid'")->fetchColumn() ?: 0;

    // Thống kê doanh thu theo danh mục sản phẩm
    $stmt = $conn->query("
        SELECT p.category, SUM(od.quantity * od.price) as revenue
        FROM order_details od
        JOIN products p ON od.product_id = p.id
        JOIN orders o ON od.order_id = o.id
        WHERE o.payment_status = 'paid'
        GROUP BY p.category
    ");
    $category_revenue = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $category_revenue[$row['category']] = (float)$row['revenue'] ?: 0;
    }

    // Thống kê doanh thu theo tháng (cho biểu đồ)
    $stmt = $conn->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as revenue
        FROM orders
        WHERE payment_status = 'paid'
        GROUP BY month
        ORDER BY month
    ");
    $monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $months = array_column($monthly_revenue, 'month');
    $revenues = array_column($monthly_revenue, 'revenue');
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage(), 3, 'errors.log');
    die('Database query failed');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Jewelry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'nav_bar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>Chào mừng bạn đến với Bảng Điều Khiển</h1>
            </header>
            <section>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <h3>Tổng số sản phẩm</h3>
                            <p><?php echo $total_products ?: '<span class="zero-value">0</span>'; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <h3>Tổng số đơn đặt hàng</h3>
                            <p><?php echo $total_orders ?: '<span class="zero-value">0</span>'; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <h3>Tổng số quản trị viên</h3>
                            <p><?php echo $total_admins ?: '<span class="zero-value">0</span>'; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Biểu đồ doanh thu theo tháng -->
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>

                <!-- Biểu đồ doanh thu theo danh mục -->
                <div class="chart-container">
                    <canvas id="categoryRevenueChart"></canvas>
                </div>
            </section>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Biểu đồ doanh thu theo tháng
        const revenueChart = new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("', '", $months) . "'"; ?>],
                datasets: [{
                    label: 'Monthly Revenue (VND)',
                    data: [<?php echo implode(", ", $revenues); ?>],
                    backgroundColor: 'rgba(231, 76, 60, 0.2)',
                    borderColor: 'rgba(231, 76, 60, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Revenue (VND)' }
                    },
                    x: {
                        title: { display: true, text: 'Month' }
                    }
                },
                plugins: {
                    legend: { display: true }
                }
            }
        });

        // Biểu đồ doanh thu theo danh mục
        const categoryRevenueChart = new Chart(document.getElementById('categoryRevenueChart'), {
            type: 'pie',
            data: {
                labels: [
                    <?php
                    $labels = array_keys($category_revenue);
                    echo count($labels) ? "'" . implode("', '", $labels) . "'" : "'No Data'";
                    ?>
                ],
                datasets: [{
                    label: 'Revenue by Category',
                    data: [
                        <?php
                        $data = array_values($category_revenue);
                        echo count($data) ? implode(", ", $data) : "0";
                        ?>
                    ],
                    backgroundColor: [
                        'rgba(231, 76, 60, 0.6)',
                        'rgba(46, 204, 113, 0.6)',
                        'rgba(52, 152, 219, 0.6)',
                        'rgba(241, 196, 15, 0.6)',
                        'rgba(155, 89, 182, 0.6)'
                    ],
                    borderColor: [
                        'rgba(231, 76, 60, 1)',
                        'rgba(46, 204, 113, 1)',
                        'rgba(52, 152, 219, 1)',
                        'rgba(241, 196, 15, 1)',
                        'rgba(155, 89, 182, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Revenue by Category' }
                }
            }
        });
    </script>
</body>
</html>



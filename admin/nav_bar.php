<!-- filepath: /c:/wamp64/www/Jewerlry/admin/nav_bar.php -->
<div class="sidebar">
    <h2>Trang Quản Trị Viên</h2>
    <ul>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
            <a href="index.php">
                <i class="bi bi-speedometer2"></i> Bảng Điều Khiên
            </a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage_product.php' ? 'active' : ''; ?>">
            <a href="manage_product.php">
                <i class="bi bi-box"></i> Sản Phẩm
            </a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage_order.php' ? 'active' : ''; ?>">
            <a href="manage_order.php">
                <i class="bi bi-cart"></i> Đơn Đặt
            </a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'manage_admin.php' ? 'active' : ''; ?>">
            <a href="manage_admin.php">
                <i class="bi bi-people"></i> Quản Trị Viên
            </a>
        </li>
        <li>
            <a href="../index.php">
                <i class="bi bi-house"></i> Trở Về Trang Chủ
            </a>
        </li>
    </ul>
</div>
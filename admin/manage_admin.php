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

// Xử lý thêm admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Kiểm tra định dạng email và username
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        try {
            // Kiểm tra username và email trùng lặp
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username or email already exists";
            } else {
                $password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
                $stmt->execute([$username, $email, $password]);
                $success = "Admin added successfully";
            }
        } catch (PDOException $e) {
            $error = "Error adding admin: " . $e->getMessage();
            error_log($error, 3, 'errors.log');
        }
    }
}

// Xử lý chỉnh sửa admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {
    $admin_id = (int)$_POST['admin_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Kiểm tra định dạng email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        try {
            // Kiểm tra username và email trùng lặp (trừ admin hiện tại)
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $admin_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username or email already exists";
            } else {
                if ($password) {
                    $password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ? AND role = 'admin'");
                    $stmt->execute([$username, $email, $password, $admin_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ? AND role = 'admin'");
                    $stmt->execute([$username, $email, $admin_id]);
                }
                $success = "Admin updated successfully";
            }
        } catch (PDOException $e) {
            $error = "Error updating admin: " . $e->getMessage();
            error_log($error, 3, 'errors.log');
        }
    }
}

// Xử lý xóa admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $admin_id = (int)$_POST['admin_id'];
    // Ngăn xóa chính tài khoản đang đăng nhập
    if ($admin_id === (int)$_SESSION['user_id']) {
        $error = "Cannot delete your own account";
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
            $stmt->execute([$admin_id]);
            $success = "Admin deleted successfully";
        } catch (PDOException $e) {
            $error = "Error deleting admin: " . $e->getMessage();
            error_log($error, 3, 'errors.log');
        }
    }
}

// Lấy danh sách admins
try {
    $stmt = $conn->query("SELECT * FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching admins: " . $e->getMessage();
    error_log($error, 3, 'errors.log');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - Jewelry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'nav_bar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <header>
                <h1>Manage Admins</h1>
            </header>
            <section>
                <!-- Hiển thị thông báo -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="bi bi-plus"></i> Add New Admin
                </button>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?php echo $admin['id']; ?></td>
                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($admin['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editAdminModal_<?php echo $admin['id']; ?>">Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                            <input type="hidden" name="delete_admin" value="1">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Admin Modal -->
                                <div class="modal fade" id="editAdminModal_<?php echo $admin['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Admin #<?php echo $admin['id']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form id="editAdminForm_<?php echo $admin['id']; ?>" method="POST">
                                                    <div class="mb-3">
                                                        <label class="form-label">Username</label>
                                                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Email</label>
                                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">New Password (leave blank to keep current)</label>
                                                        <input type="password" name="password" class="form-control">
                                                    </div>
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                    <input type="hidden" name="edit_admin" value="1">
                                                </form>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" form="editAdminForm_<?php echo $admin['id']; ?>" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($admins)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No admins found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Đóng thông báo sau 5 giây
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.remove());
        }, 5000);
    </script>
</body>
</html>
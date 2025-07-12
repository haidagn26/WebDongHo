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

// Đường dẫn thư mục lưu ảnh
$upload_dir = 'D:/Zalo/New folder (2)/Jewelry/uploads/';
$upload_url = '/Jewelry/uploads/';

// Tạo thư mục uploads nếu chưa tồn tại
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        $error = "Failed to create uploads directory";
    }
}

// Xử lý thêm sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $price = (float)$_POST['price'];
    $size = trim($_POST['size']) ?: null;
    $material = trim($_POST['material']) ?: null;
    $description = trim($_POST['description']) ?: null;
    $stock_quantity = (int)($_POST['stock_quantity'] ?: 0);

    $images = [];

    // Kiểm tra dữ liệu đầu vào
    $valid_categories = ['đồng_hồ_cơ', 'đồng_hồ_điện_tử'];
    if (!$name || !in_array($category, $valid_categories) || $price <= 0) {
        $error = "Invalid product name, category, or price";
    } else {
        // Xử lý upload ảnh
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            foreach ($_FILES['images']['name'] as $key => $image_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK && $image_name) {
                    $image_tmp_name = $_FILES['images']['tmp_name'][$key];
                    $image_type = $_FILES['images']['type'][$key];
                    $image_size = $_FILES['images']['size'][$key];
                    if (in_array($image_type, $allowed_types) && $image_size <= $max_size) {
                        $unique_image_name = 'product_' . time() . '_' . $key . '_' . basename($image_name);
                        $image_path = $upload_dir . $unique_image_name;
                        if (move_uploaded_file($image_tmp_name, $image_path)) {
                            $images[] = $unique_image_name;
                        } else {
                            $error = "Failed to upload image: $image_name";
                        }
                    } else {
                        $error = "Invalid image type or size for: $image_name";
                    }
                }
            }
        }

        if (!isset($error)) {
            $image_string = !empty($images) ? implode(',', $images) : null;
            try {
                $stmt = $conn->prepare("INSERT INTO products (name, category, price, size, material, description, image_url, stock_quantity, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $category, $price, $size, $material, $description, $image_string, $stock_quantity]);
                $success = "Product added successfully";
            } catch (PDOException $e) {
                $error = "Error adding product: " . $e->getMessage();
                error_log($error, 3, 'errors.log');
            }
        }
    }
}

// Xử lý chỉnh sửa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $product_id = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $category = $_POST['category'];
    $price = (float)$_POST['price'];
    $size = trim($_POST['size']) ?: null;
    $material = trim($_POST['material']) ?: null;
    $description = trim($_POST['description']) ?: null;
    $stock_quantity = (int)($_POST['stock_quantity'] ?: 0);

    // Kiểm tra dữ liệu đầu vào
    $valid_categories = ['đồng_hồ_cơ', 'đồng_hồ_điện_tử'];
    if (!$name || !in_array($category, $valid_categories) || $price <= 0) {
        $error = "Invalid product name, category, or price";
    } else {
        try {
            // Lấy danh sách ảnh cũ
            $stmt = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $old_images = $stmt->fetchColumn();
            $images = $old_images ? explode(',', $old_images) : [];

            // Xử lý upload ảnh mới
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                foreach ($_FILES['images']['name'] as $key => $image_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK && $image_name) {
                        $image_tmp_name = $_FILES['images']['tmp_name'][$key];
                        $image_type = $_FILES['images']['type'][$key];
                        $image_size = $_FILES['images']['size'][$key];
                        if (in_array($image_type, $allowed_types) && $image_size <= $max_size) {
                            $unique_image_name = 'product_' . time() . '_' . $key . '_' . basename($image_name);
                            $image_path = $upload_dir . $unique_image_name;
                            if (move_uploaded_file($image_tmp_name, $image_path)) {
                                $images[] = $unique_image_name;
                            } else {
                                $error = "Failed to upload image: $image_name";
                            }
                        } else {
                            $error = "Invalid image type or size for: $image_name";
                        }
                    }
                }
            }

            if (!isset($error)) {
                $image_string = !empty($images) ? implode(',', $images) : null;
                $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, price = ?, size = ?, material = ?, description = ?, image_url = ?, stock_quantity = ? WHERE id = ?");
                $stmt->execute([$name, $category, $price, $size, $material, $description, $image_string, $stock_quantity, $product_id]);
                $success = "Product updated successfully";
            }
        } catch (PDOException $e) {
            $error = "Error updating product: " . $e->getMessage();
            error_log($error, 3, 'errors.log');
        }
    }
}

// Xử lý xóa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM order_details WHERE product_id = ?");
        $stmt->execute([$product_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Cannot delete product because it is included in orders";
        } else {
            $stmt = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $images = $stmt->fetchColumn();
            $images_array = $images ? explode(',', $images) : [];

            foreach ($images_array as $image) {
                if ($image && file_exists($upload_dir . $image)) {
                    unlink($upload_dir . $image);
                }
            }

            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $success = "Product deleted successfully";
        }
    } catch (PDOException $e) {
        $error = "Error deleting product: " . $e->getMessage();
        error_log($error, 3, 'errors.log');
    }
}

// Xử lý xóa một ảnh cụ thể
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image'])) {
    $product_id = (int)$_POST['product_id'];
    $image_to_delete = $_POST['image_to_delete'];
    try {
        $stmt = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $images = $stmt->fetchColumn();
        $images_array = $images ? explode(',', $images) : [];

        $images_array = array_filter($images_array, fn($img) => $img !== $image_to_delete);
        if (file_exists($upload_dir . $image_to_delete)) {
            unlink($upload_dir . $image_to_delete);
        }

        $image_string = !empty($images_array) ? implode(',', $images_array) : null;
        $stmt = $conn->prepare("UPDATE products SET image_url = ? WHERE id = ?");
        $stmt->execute([$image_string, $product_id]);
        $success = "Image deleted successfully";
    } catch (PDOException $e) {
        $error = "Error deleting image: " . $e->getMessage();
        error_log($error, 3, 'errors.log');
    }
}

// Lấy danh sách sản phẩm
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $stmt = $conn->query("SELECT COUNT(*) FROM products");
    $total_products = $stmt->fetchColumn();
    $total_pages = ceil($total_products / $limit);

    $stmt = $conn->prepare("SELECT * FROM products ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching products: " . $e->getMessage();
    error_log($error, 3, 'errors.log');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Jewelry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/product.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'nav_bar.php'; ?>

        <div class="main-content">
            <header>
                <h1>Quản Lý Sản Phẩm</h1>
            </header>
            <section>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php elseif (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="bi bi-plus"></i> Thêm sản phẩm mới
                </button>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hình ảnh</th>
                                <th>Tên</th>
                                <th>Loại</th>
                                <th>Giá (VND)</th>
                                <th>Kích thước</th>
                                <th>Vật liệu</th>
                                <th>Số lượng</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No products found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo $product['id']; ?></td>
                                        <td>
                                            <?php
                                            $images = $product['image_url'] ? explode(',', $product['image_url']) : [];
                                            if ($images): ?>
                                                <?php foreach ($images as $image): ?>
                                                    <img src="<?php echo $upload_url . htmlspecialchars($image); ?>" alt="Product Image" class="product-image">
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                No Images
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo str_replace('_', ' ', $product['category']); ?></td>
                                        <td><?php echo number_format($product['price'], 2, ',', '.'); ?></td>
                                        <td><?php echo $product['size'] ?: '-'; ?></td>
                                        <td><?php echo $product['material'] ?: '-'; ?></td>
                                        <td><?php echo $product['stock_quantity'] ?: '-'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editProductModal_<?php echo $product['id']; ?>">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="delete_product" value="1">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editProductModal_<?php echo $product['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Product #<?php echo $product['id']; ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form id="editProductForm_<?php echo $product['id']; ?>" method="POST" enctype="multipart/form-data">
                                                        <div class="mb-3">
                                                            <label class="form-label">Product Name</label>
                                                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Category</label>
                                                            <select name="category" class="form-control" required>
                                                                <option value="đồng_hồ_cơ" <?php echo $product['category'] === 'đồng_hồ_cơ' ? 'selected' : ''; ?>>Đồng hồ cơ</option>
                                                                <option value="đồng_hồ_điện_tử" <?php echo $product['category'] === 'đồng_hồ_điện_tử' ? 'selected' : ''; ?>>Đồng hồ điện tử</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Price (VND)</label>
                                                            <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $product['price']; ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Size</label>
                                                            <input type="text" name="size" class="form-control" value="<?php echo htmlspecialchars($product['size'] ?? ''); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Material</label>
                                                            <input type="text" name="material" class="form-control" value="<?php echo htmlspecialchars($product['material'] ?? ''); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Stock Quantity</label>
                                                            <input type="number" name="stock_quantity" class="form-control" value="<?php echo htmlspecialchars($product['stock_quantity'] ?? ''); ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea name="description" class="form-control"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Current Images</label>
                                                            <div class="image-preview">
                                                                <?php
                                                                $images = $product['image_url'] ? explode(',', $product['image_url']) : [];
                                                                foreach ($images as $image): ?>
                                                                    <div>
                                                                        <img src="<?php echo $upload_url . htmlspecialchars($image); ?>" alt="Product Image">
                                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this image?');">
                                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                                            <input type="hidden" name="image_to_delete" value="<?php echo htmlspecialchars($image); ?>">
                                                                            <input type="hidden" name="delete_image" value="1">
                                                                            <button type="submit" class="delete-image">Delete</button>
                                                                        </form>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                <?php if (empty($images)): ?>
                                                                    <p>No images available</p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Add More Images</label>
                                                            <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                                                        </div>
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="edit_product" value="1">
                                                    </form>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" form="editProductForm_<?php echo $product['id']; ?>" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

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

    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Thêm Sản Phẩm Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addProductForm" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Tên</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Loại</label>
                            <select name="category" class="form-control" required>
                                <option value="đồng_hồ_cơ">Đồng hồ cơ</option>
                                <option value="đồng_hồ_điện_tử">Đồng hồ điện tử</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (VND)</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Size</label>
                            <input type="text" name="size" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Material</label>
                            <input type="text" name="material" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock_quantity" class="form-control" min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Images</label>
                            <input type="file" name="images[]" class="form-control" accept="image/jpeg,image/png,image/gif" multiple>
                        </div>
                        <input type="hidden" name="add_product" value="1">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addProductForm" class="btn btn-primary">Add Product</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.remove());
        }, 5000);
    </script>
</body>
</html>
<?php
ob_start();
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
restrictAccess(['admin'], 'home content');

// Database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Database connection error.");
}
$conn->set_charset('utf8mb4');

// Log page access
logAction('home_content_access', 'Admin accessed home content management page', 'info');

// Handle form submissions
$success = $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user']['id'];

    // Notices
    if (isset($_POST['add_notice']) || isset($_POST['edit_notice'])) {
        $notice_id = isset($_POST['notice_id']) ? (int)$_POST['notice_id'] : 0;
        $title = $conn->real_escape_string(trim($_POST['notice_title']));
        $content = $conn->real_escape_string(trim($_POST['notice_content']));

        if (empty($title) || empty($content)) {
            $error = "Notice title and content are required.";
        } else {
            if ($notice_id) {
                // Update notice
                $stmt = $conn->prepare("UPDATE notices SET title = ?, content = ?, user_id = ? WHERE id = ?");
                $stmt->bind_param("ssii", $title, $content, $user_id, $notice_id);
                $action = 'update_notice';
                $details = "Updated notice ID: $notice_id";
            } else {
                // Add notice
                $stmt = $conn->prepare("INSERT INTO notices (title, content, user_id) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $title, $content, $user_id);
                $action = 'add_notice';
                $details = "Added notice: $title";
            }

            if ($stmt->execute()) {
                logAction($action, $details, 'info');
                $success = "Notice " . ($notice_id ? 'updated' : 'added') . " successfully!";
            } else {
                logAction($action . '_failed', "Failed to $action: " . $stmt->error, 'error');
                $error = "Failed to " . ($notice_id ? 'update' : 'add') . " notice.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_notice'])) {
        $notice_id = (int)$_POST['notice_id'];
        $stmt = $conn->prepare("DELETE FROM notices WHERE id = ?");
        $stmt->bind_param("i", $notice_id);
        if ($stmt->execute()) {
            logAction('delete_notice', "Deleted notice ID: $notice_id", 'info');
            $success = "Notice deleted successfully!";
        } else {
            logAction('delete_notice_failed', "Failed to delete notice ID: $notice_id: " . $stmt->error, 'error');
            $error = "Failed to delete notice.";
        }
        $stmt->close();
    }
    // Gallery Images
    elseif (isset($_POST['upload_image'])) {
        $file = $_FILES['image'];
        $caption = $conn->real_escape_string(trim($_POST['caption']));
        $target_dir = dirname(__DIR__, 2) . '/assets/images/gallery/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        if (!in_array($file['type'], $allowed_types) || $file['size'] > $max_size) {
            logAction('upload_image_failed', 'Invalid image file type or size', 'error');
            $error = "Invalid file type or size (max 2MB, JPEG/PNG/GIF only).";
        } else {
            $target_file = $target_dir . 'gallery_' . time() . '_' . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                $relative_path = 'assets/images/gallery/' . basename($target_file);
                $stmt = $conn->prepare("INSERT INTO gallery_images (image_path, caption, user_id) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $relative_path, $caption, $user_id);
                if ($stmt->execute()) {
                    logAction('upload_image', "Uploaded gallery image: $relative_path", 'info');
                    $success = "Image uploaded successfully!";
                } else {
                    logAction('upload_image_failed', "Failed to save image to database: " . $stmt->error, 'error');
                    $error = "Failed to save image.";
                }
                $stmt->close();
            } else {
                logAction('upload_image_failed', 'Failed to upload image', 'error');
                $error = "Failed to upload image.";
            }
        }
    } elseif (isset($_POST['delete_image'])) {
        $image_id = (int)$_POST['image_id'];
        $stmt = $conn->prepare("SELECT image_path FROM gallery_images WHERE id = ?");
        $stmt->bind_param("i", $image_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $image_path = dirname(__DIR__, 2) . '/' . $row['image_path'];
            $stmt->close();
            $stmt = $conn->prepare("DELETE FROM gallery_images WHERE id = ?");
            $stmt->bind_param("i", $image_id);
            if ($stmt->execute()) {
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
                logAction('delete_image', "Deleted gallery image ID: $image_id", 'info');
                $success = "Image deleted successfully!";
            } else {
                logAction('delete_image_failed', "Failed to delete image ID: $image_id: " . $stmt->error, 'error');
                $error = "Failed to delete image.";
            }
        }
        $stmt->close();
    }
    // Announcements
    elseif (isset($_POST['add_announcement']) || isset($_POST['edit_announcement'])) {
        $announcement_id = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
        $title = $conn->real_escape_string(trim($_POST['announcement_title']));
        $content = $conn->real_escape_string(trim($_POST['announcement_content']));
        $expiry_date = !empty($_POST['expiry_date']) ? $conn->real_escape_string($_POST['expiry_date']) : null;

        if (empty($title) || empty($content)) {
            $error = "Announcement title and content are required.";
        } else {
            if ($announcement_id) {
                // Update announcement
                $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, expiry_date = ?, user_id = ? WHERE id = ?");
                $stmt->bind_param("sssii", $title, $content, $expiry_date, $user_id, $announcement_id);
                $action = 'update_announcement';
                $details = "Updated announcement ID: $announcement_id";
            } else {
                // Add announcement
                $stmt = $conn->prepare("INSERT INTO announcements (title, content, expiry_date, user_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $title, $content, $expiry_date, $user_id);
                $action = 'add_announcement';
                $details = "Added announcement: $title";
            }

            if ($stmt->execute()) {
                logAction($action, $details, 'info');
                $success = "Announcements " . ($announcement_id ? 'updated' : 'added') . " successfully!";
            } else {
                logAction($action . '_failed', "Failed to $action: " . $stmt->error, 'error');
                $error = "Failed to " . ($announcement_id ? 'update' : 'add') . " announcement.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_announcement'])) {
        $announcement_id = (int)$_POST['announcement_id'];
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $announcement_id);
        if ($stmt->execute()) {
            logAction('delete_announcement', "Deleted announcement ID: $announcement_id", 'info');
            $success = "Announcement deleted successfully!";
        } else {
            logAction('delete_announcement_failed', "Failed to delete announcement ID: $announcement_id: " . $stmt->error, 'error');
            $error = "Failed to delete announcement.";
        }
        $stmt->close();
    }
}

// Fetch content
$notices = $conn->query("SELECT n.id, n.title, n.content, n.created_at, u.username 
                         FROM notices n 
                         JOIN users u ON n.user_id = u.id 
                         ORDER BY n.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$gallery_images = $conn->query("SELECT g.id, g.image_path, g.caption, g.created_at, u.username 
                                FROM gallery_images g 
                                JOIN users u ON g.user_id = u.id 
                                ORDER BY g.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$announcements = $conn->query("SELECT a.id, a.title, a.content, a.expiry_date, a.created_at, u.username 
                               FROM announcements a 
                               JOIN users u ON a.user_id = u.id 
                               ORDER BY a.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Content Management - LIMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
       
        .content.collapsed {
            margin-left: 60px;
        }
        h2.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 25px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            background: #fff;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            color: #1e40af;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            background: #3498db;
            color: #fff;
        }
        .table-responsive {
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background: #3498db;
            color: #fff;
            font-weight: 500;
        }
        tr:hover {
            background: #f1f5f9;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .gallery-item {
            position: relative;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .gallery-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .gallery-item .caption {
            padding: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            position: absolute;
            bottom: 0;
            width: 100%;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .btn-primary {
            background: #3498db;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            color: #fff;
        }
        .btn-danger {
            background: #dc3545;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            color: #fff;
        }
        .alert {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 6px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 15px;
            }
            th, td {
                font-size: 14px;
                padding: 8px;
            }
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center">Home Content Management</h2>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#notices">Notices</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#gallery">Gallery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#announcements">Announcements</a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Notices -->
                <div class="tab-pane fade show active" id="notices">
                    <div class="card">
                        <h3>Add/Edit Notice</h3>
                        <form method="POST">
                            <input type="hidden" name="notice_id" id="notice_id" value="">
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" name="notice_title" id="notice_title" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Content</label>
                                <textarea name="notice_content" id="notice_content" class="form-control" rows="5" required></textarea>
                            </div>
                            <button type="submit" name="add_notice" id="submit_notice" class="btn btn-primary">Add Notice</button>
                        </form>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Content</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notices as $notice): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($notice['id']); ?></td>
                                            <td><?php echo htmlspecialchars($notice['title']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($notice['content'], 0, 100)) . (strlen($notice['content']) > 100 ? '...' : ''); ?></td>
                                            <td><?php echo htmlspecialchars($notice['username']); ?></td>
                                            <td><?php echo htmlspecialchars($notice['created_at']); ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm edit-notice" data-id="<?php echo $notice['id']; ?>" data-title="<?php echo htmlspecialchars($notice['title']); ?>" data-content="<?php echo htmlspecialchars($notice['content']); ?>">Edit</button>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="notice_id" value="<?php echo $notice['id']; ?>">
                                                    <button type="submit" name="delete_notice" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Gallery -->
                <div class="tab-pane fade" id="gallery">
                    <div class="card">
                        <h3>Upload Image</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Image (JPEG, PNG, GIF, max 2MB)</label>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif" required>
                            </div>
                            <div class="form-group">
                                <label>Caption</label>
                                <input type="text" name="caption" class="form-control">
                            </div>
                            <button type="submit" name="upload_image" class="btn btn-primary">Upload Image</button>
                        </form>
                        <div class="gallery-grid">
                            <?php foreach ($gallery_images as $image): ?>
                                <div class="gallery-item">
                                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($image['image_path']); ?>" alt="Gallery Image">
                                    <div class="caption"><?php echo htmlspecialchars($image['caption'] ?? 'No caption'); ?></div>
                                    <form method="POST" style="position: absolute; top: 10px; right: 10px;">
                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                        <button type="submit" name="delete_image" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Announcements -->
                <div class="tab-pane fade" id="announcements">
                    <div class="card">
                        <h3>Add/Edit Announcement</h3>
                        <form method="POST">
                            <input type="hidden" name="announcement_id" id="announcement_id" value="">
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" name="announcement_title" id="announcement_title" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Content</label>
                                <textarea name="announcement_content" id="announcement_content" class="form-control" rows="5" required></textarea>
                            </div>
                            <div class="form-group">
                                <label>Expiry Date (Optional)</label>
                                <input type="date" name="expiry_date" id="expiry_date" class="form-control">
                            </div>
                            <button type="submit" name="add_announcement" id="submit_announcement" class="btn btn-primary">Add Announcement</button>
                        </form>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Content</th>
                                        <th>Expiry Date</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($announcement['id']); ?></td>
                                            <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . (strlen($announcement['content']) > 100 ? '...' : ''); ?></td>
                                            <td><?php echo htmlspecialchars($announcement['expiry_date'] ?? 'None'); ?></td>
                                            <td><?php echo htmlspecialchars($announcement['username']); ?></td>
                                            <td><?php echo htmlspecialchars($announcement['created_at']); ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm edit-announcement" data-id="<?php echo $announcement['id']; ?>" data-title="<?php echo htmlspecialchars($announcement['title']); ?>" data-content="<?php echo htmlspecialchars($announcement['content']); ?>" data-expiry="<?php echo htmlspecialchars($announcement['expiry_date'] ?? ''); ?>">Edit</button>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                    <button type="submit" name="delete_announcement" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Notice
        document.querySelectorAll('.edit-notice').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const title = button.getAttribute('data-title');
                const content = button.getAttribute('data-content');
                document.getElementById('notice_id').value = id;
                document.getElementById('notice_title').value = title;
                document.getElementById('notice_content').value = content;
                document.getElementById('submit_notice').name = 'edit_notice';
                document.getElementById('submit_notice').textContent = 'Update Notice';
            });
        });

        // Edit Announcement
        document.querySelectorAll('.edit-announcement').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const title = button.getAttribute('data-title');
                const content = button.getAttribute('data-content');
                const expiry = button.getAttribute('data-expiry');
                document.getElementById('announcement_id').value = id;
                document.getElementById('announcement_title').value = title;
                document.getElementById('announcement_content').value = content;
                document.getElementById('expiry_date').value = expiry;
                document.getElementById('submit_announcement').name = 'edit_announcement';
                document.getElementById('submit_announcement').textContent = 'Update Announcement';
            });
        });
    </script>
    <?php ob_end_flush(); ?>
</body>
</html>
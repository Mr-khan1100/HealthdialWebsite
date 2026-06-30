<?php
require_once 'connection.inc.php';
require_once 'push_helper.php';
requireLogin();
requireAccess('news');

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_news'])) {
        $title = trim($_POST['title']);
        $short_description = trim($_POST['short_description']);
        $full_content = trim($_POST['full_content']);
        $publish_date = $_POST['publish_date'];
        $status = (int)$_POST['status'];
        
        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = './uploads/news/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['image']['name']);
            $target_file = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = $filename;
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO news (title, short_description, full_content, image, publish_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $title, $short_description, $full_content, $image, $publish_date, $status);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "News added successfully!";
            if ($status == 1) {
                sendPushNotificationToAll($conn, "📰 New Health Article", $title, ["type" => "news", "title" => $title]);
            }
        } else {
            $_SESSION['error'] = "Error adding news: " . $conn->error;
        }
        header("Location: News.php"); exit();
    }
    
    if (isset($_POST['edit_news'])) {
        $id = intval($_POST['id']);
        $title = trim($_POST['title']);
        $short_description = trim($_POST['short_description']);
        $full_content = trim($_POST['full_content']);
        $publish_date = $_POST['publish_date'];
        $status = (int)$_POST['status'];
        $image = $_POST['current_image'];
        
        if (!empty($_FILES['image']['name'])) {
            $upload_dir = './uploads/news/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filename = time() . '_' . basename($_FILES['image']['name']);
            $target_file = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                if($image && file_exists($upload_dir . $image)) unlink($upload_dir . $image);
                $image = $filename;
            }
        }
        
        $stmt = $conn->prepare("UPDATE news SET title=?, short_description=?, full_content=?, image=?, publish_date=?, status=? WHERE id=?");
        $stmt->bind_param("sssssii", $title, $short_description, $full_content, $image, $publish_date, $status, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "News updated!";
        } else {
            $_SESSION['error'] = "Error updating news.";
        }
        header("Location: News.php"); exit();
    }
}

// Delete with prepared statement
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT image FROM news WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $imgRes = $stmt->get_result();
    if($row = $imgRes->fetch_assoc()) {
        if($row['image'] && file_exists('./uploads/news/' . $row['image'])) {
            unlink('./uploads/news/' . $row['image']);
        }
    }
    $delStmt = $conn->prepare("DELETE FROM news WHERE id = ?");
    $delStmt->bind_param("i", $id);
    $delStmt->execute();
    $_SESSION['success'] = "News deleted!";
    header("Location: News.php"); exit();
}

$result = $conn->query("SELECT * FROM news ORDER BY publish_date DESC");
$totalNews = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>
            <div class="admin-content">
                <div class="page-header">
                    <h1 class="page-title">News Management</h1>
                    <p class="page-subtitle"><?php echo $totalNews; ?> article<?php echo $totalNews !== 1 ? 's' : ''; ?></p>
                </div>

                <div style="display:grid;grid-template-columns:380px 1fr;gap:20px;">
                    <!-- Form -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-<?php echo isset($_GET['edit']) ? 'edit' : 'plus-circle'; ?>" style="margin-right:8px;color:var(--primary);"></i>
                                <?php echo isset($_GET['edit']) ? 'Edit' : 'Add'; ?> Article
                            </h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <?php
                                $news = [];
                                if(isset($_GET['edit'])) {
                                    $eid = intval($_GET['edit']);
                                    $eStmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
                                    $eStmt->bind_param("i", $eid);
                                    $eStmt->execute();
                                    $news = $eStmt->get_result()->fetch_assoc();
                                }
                                if(isset($_GET['edit'])): ?>
                                <input type="hidden" name="id" value="<?php echo $news['id']; ?>">
                                <input type="hidden" name="current_image" value="<?php echo $news['image']; ?>">
                                <input type="hidden" name="edit_news" value="1">
                                <?php else: ?>
                                <input type="hidden" name="add_news" value="1">
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label class="form-label">Title *</label>
                                    <input type="text" name="title" value="<?php echo htmlspecialchars($news['title'] ?? ''); ?>" required class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Short Description *</label>
                                    <textarea name="short_description" rows="3" required class="form-textarea" maxlength="255"><?php echo htmlspecialchars($news['short_description'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Full Content</label>
                                    <textarea name="full_content" rows="6" class="form-textarea"><?php echo htmlspecialchars($news['full_content'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Image</label>
                                    <input type="file" name="image" accept="image/*" class="form-input">
                                    <?php if(isset($news['image']) && $news['image']): ?>
                                    <img src="./uploads/news/<?php echo $news['image']; ?>" style="width:100%;height:100px;object-fit:cover;border-radius:var(--radius-md);margin-top:8px;">
                                    <?php endif; ?>
                                </div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div class="form-group">
                                        <label class="form-label">Publish Date *</label>
                                        <input type="date" name="publish_date" value="<?php echo $news['publish_date'] ?? date('Y-m-d'); ?>" required class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="1" <?php echo ($news['status'] ?? 1) == 1 ? 'selected' : ''; ?>>Published</option>
                                            <option value="0" <?php echo ($news['status'] ?? '') == 0 ? 'selected' : ''; ?>>Draft</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:flex;gap:10px;">
                                    <?php if(isset($_GET['edit'])): ?>
                                    <a href="News.php" class="btn btn-secondary" style="flex:1;">Cancel</a>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-primary" style="flex:1;">
                                        <i class="fas fa-save"></i> <?php echo isset($_GET['edit']) ? 'Update' : 'Publish'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Articles List -->
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header">
                            <h3 class="card-title">All Articles</h3>
                        </div>
                        <div style="padding:0;">
                            <?php if($totalNews > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <div style="display:flex;gap:16px;padding:20px 24px;border-bottom:1px solid var(--border-light);transition:background var(--transition-fast);" onmouseover="this.style.background='var(--primary-50)'" onmouseout="this.style.background='transparent'">
                                <?php if($row['image']): ?>
                                <img src="./uploads/news/<?php echo $row['image']; ?>" style="width:100px;height:70px;object-fit:cover;border-radius:var(--radius-md);flex-shrink:0;">
                                <?php endif; ?>
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                                        <h4 style="font-size:14px;font-weight:700;margin:0 0 4px;"><?php echo htmlspecialchars($row['title']); ?></h4>
                                        <div style="display:flex;gap:6px;flex-shrink:0;margin-left:12px;">
                                            <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm"><i class="fas fa-edit"></i></a>
                                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" style="color:var(--status-danger);" onclick="return confirm('Delete this article?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </div>
                                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($row['short_description']); ?></p>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <span style="font-size:11px;color:var(--text-muted);"><i class="far fa-calendar"></i> <?php echo date('d M Y', strtotime($row['publish_date'])); ?></span>
                                        <span class="badge <?php echo $row['status'] == 1 ? 'badge-success' : 'badge-warning'; ?>"><?php echo $row['status'] == 1 ? 'Published' : 'Draft'; ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-newspaper"></i></div>
                                <h3>No articles yet</h3>
                                <p>Create your first news article using the form.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
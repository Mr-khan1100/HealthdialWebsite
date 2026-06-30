<?php
require_once 'connection.inc.php';
requireLogin();
requireAccess('categories');

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $name = $_POST['name'];
        $icon = $_POST['icon'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("INSERT INTO categories (name, icon, status) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $icon, $status);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Category added successfully!";
        } else {
            $_SESSION['error'] = "Error adding category: " . $conn->error;
        }
        header("Location: Categories.php");
        exit();
    }
    
    if (isset($_POST['edit_category'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $icon = $_POST['icon'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE categories SET name=?, icon=?, status=? WHERE id=?");
        $stmt->bind_param("ssii", $name, $icon, $status, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Category updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating category: " . $conn->error;
        }
        header("Location: Categories.php");
        exit();
    }
}

// Delete category
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM categories WHERE id = $id");
    $_SESSION['success'] = "Category deleted successfully!";
    header("Location: Categories.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - HealthDial</title>
    <link rel="stylesheet" href="assets/admin.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include 'header.php'; ?>
            
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Add/Edit Form -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                                <?php echo isset($_GET['edit']) ? 'Edit Category' : 'Add New Category'; ?>
                            </h3>
                            
                            <form method="POST">
                                <?php
                                $category = [];
                                if(isset($_GET['edit'])) {
                                    $id = $_GET['edit'];
                                    $result = $conn->query("SELECT * FROM categories WHERE id = $id");
                                    $category = $result->fetch_assoc();
                                }
                                
                                if(isset($_GET['edit'])): ?>
                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                <input type="hidden" name="edit_category" value="1">
                                <?php else: ?>
                                <input type="hidden" name="add_category" value="1">
                                <?php endif; ?>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Category Name *</label>
                                        <input type="text" name="name" value="<?php echo $category['name'] ?? ''; ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Icon Class (FontAwesome)</label>
                                        <input type="text" name="icon" value="<?php echo $category['icon'] ?? 'fas fa-heartbeat'; ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <p class="text-xs text-gray-500 mt-1">Example: fas fa-hospital, fas fa-clinic-medical</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                        <select name="status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="1" <?php echo ($category['status'] ?? 1) == 1 ? 'selected' : ''; ?>>Active</option>
                                            <option value="0" <?php echo ($category['status'] ?? '') == 0 ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="flex space-x-3">
                                        <?php if(isset($_GET['edit'])): ?>
                                        <a href="Categories.php" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                                        <?php endif; ?>
                                        <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                            <?php echo isset($_GET['edit']) ? 'Update Category' : 'Add Category'; ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Categories List -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-800">All Categories</h3>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Listings</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php
                                        $result = $conn->query("
                                            SELECT c.*, COUNT(l.id) as listing_count 
                                            FROM categories c 
                                            LEFT JOIN listings l ON c.id = l.category_id 
                                            GROUP BY c.id 
                                            ORDER BY c.id
                                        ");
                                        
                                        while($row = $result->fetch_assoc()):
                                        ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">#<?php echo $row['id']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <?php if($row['icon']): ?>
                                                    <i class="<?php echo $row['icon']; ?> text-xl mr-3 text-blue-600"></i>
                                                    <?php endif; ?>
                                                    <span class="font-medium"><?php echo htmlspecialchars($row['name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <code class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo $row['icon']; ?></code>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo $row['status'] == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo $row['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                                                    <?php echo $row['listing_count']; ?> listings
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <div class="flex space-x-2">
                                                    <a href="?edit=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-900" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if($row['listing_count'] == 0): ?>
                                                    <a href="?delete=<?php echo $row['id']; ?>" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirm('Are you sure?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
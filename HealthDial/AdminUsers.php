<?php
require_once 'connection.inc.php';
requireLogin();
requireRole('admin'); // Only admin can access this page

// Handle admin user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_admin'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        
        $stmt = $conn->prepare("INSERT INTO admin_users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $password, $role);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Admin user added successfully!";
        } else {
            $_SESSION['error'] = "Error adding admin user: " . $conn->error;
        }
        header("Location: AdminUsers.php");
        exit();
    }
    
    if (isset($_POST['edit_admin'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        
        // Update password only if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin_users SET name=?, email=?, password=?, role=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $email, $password, $role, $id);
        } else {
            $stmt = $conn->prepare("UPDATE admin_users SET name=?, email=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $email, $role, $id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Admin user updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating admin user: " . $conn->error;
        }
        header("Location: AdminUsers.php");
        exit();
    }
}

// Delete admin user (cannot delete yourself)
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $id = $_GET['delete'];
    if($id != $_SESSION['admin_id']) {
        $conn->query("DELETE FROM admin_users WHERE id = $id");
        $_SESSION['success'] = "Admin user deleted successfully!";
    } else {
        $_SESSION['error'] = "You cannot delete your own account!";
    }
    header("Location: AdminUsers.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users - HealthDial</title>
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
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Add/Edit Form -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                                <?php echo isset($_GET['edit']) ? 'Edit Admin User' : 'Add Admin User'; ?>
                            </h3>
                            
                            <form method="POST">
                                <?php
                                $admin = [];
                                if(isset($_GET['edit'])) {
                                    $id = $_GET['edit'];
                                    $result = $conn->query("SELECT * FROM admin_users WHERE id = $id");
                                    $admin = $result->fetch_assoc();
                                }
                                
                                if(isset($_GET['edit'])): ?>
                                <input type="hidden" name="id" value="<?php echo $admin['id']; ?>">
                                <input type="hidden" name="edit_admin" value="1">
                                <?php else: ?>
                                <input type="hidden" name="add_admin" value="1">
                                <?php endif; ?>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                        <input type="text" name="name" value="<?php echo $admin['name'] ?? ''; ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                        <input type="email" name="email" value="<?php echo $admin['email'] ?? ''; ?>" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            <?php echo isset($_GET['edit']) ? 'New Password (leave blank to keep current)' : 'Password *'; ?>
                                        </label>
                                        <input type="password" name="password" <?php echo isset($_GET['edit']) ? '' : 'required'; ?> class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                                        <select name="role" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="manager" <?php echo ($admin['role'] ?? '') == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                            <option value="admin" <?php echo ($admin['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </div>
                                    
                                    <div class="flex space-x-3">
                                        <?php if(isset($_GET['edit'])): ?>
                                        <a href="AdminUsers.php" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-center">Cancel</a>
                                        <?php endif; ?>
                                        <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                            <?php echo isset($_GET['edit']) ? 'Update User' : 'Add User'; ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Admin Users List -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-semibold text-gray-800">Admin Users</h3>
                                <p class="text-gray-600 text-sm mt-1">Manage admin access to the panel</p>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin User</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php
                                        $result = $conn->query("SELECT * FROM admin_users ORDER BY created_at DESC");
                                        
                                        while($row = $result->fetch_assoc()):
                                            $role_color = [
                                                'admin' => 'bg-purple-100 text-purple-800',
                                                'manager' => 'bg-blue-100 text-blue-800'
                                            ];
                                        ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">#<?php echo $row['id']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-3">
                                                        <i class="fas fa-user-shield text-gray-600"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($row['name']); ?></p>
                                                        <p class="text-sm text-gray-500"><?php echo $row['email']; ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs rounded-full <?php echo $role_color[$row['role']]; ?>">
                                                    <?php echo ucfirst($row['role']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <div class="flex space-x-2">
                                                    <a href="?edit=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-900" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if($row['id'] != $_SESSION['admin_id']): ?>
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
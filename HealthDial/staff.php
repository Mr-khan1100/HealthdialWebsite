<?php
session_start();
require_once 'connection.inc.php';
requireLogin();

/* ---------------- ERROR REPORTING ---------------- */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

/* IMPORTANT */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


/* ---------------- DELETE USER ---------------- */

if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];

    // 1️⃣ Check role first
    $stmt = $conn->prepare("SELECT role FROM admin_users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    // ❌ If Admin → JS Alert only
    if ($user && $user['role'] === 'admin') {
        echo "<script>
                alert('Admin user cannot be deleted ❌');
                window.location='staff.php';
              </script>";
        exit();
    }

    // ✅ Normal delete
    $stmt = $conn->prepare("DELETE FROM admin_users WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "User deleted successfully ✔";
    } else {
        $_SESSION['error'] = "Delete failed ❌";
    }

    header("Location: staff.php");
    exit();
}



/* ---------------- CREATE USER ---------------- */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);   // no hash as you said

    $permissions = isset($_POST['permissions'])
        ? implode(',', $_POST['permissions'])
        : '';

    $role = 'manager';
        $name = 'User';


    try {

        $stmt = $conn->prepare("
            INSERT INTO admin_users
            (name,email,password,role,permissions,is_active)
            VALUES (?,?,?,?,?,1)
        ");

        $stmt->bind_param("sssss",
        $name,
            $username,
            $password,
            $role,
            $permissions
        );

        $stmt->execute();

        $_SESSION['success'] = "Staff created ✔";

    } catch (mysqli_sql_exception $e) {

        if ($e->getCode() == 1062) {
            $_SESSION['error'] = "Email already exists ❌";
        } else {
            $_SESSION['error'] = "Database error ❌";
        }
    }

    header("Location: staff.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - HealthDial</title>
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
    <div class="bg-green-100 text-green-700 p-3 mb-4 rounded">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
    <div class="bg-red-100 text-red-700 p-3 mb-4 rounded">
        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

                
                <form method="POST" 
      class="bg-white rounded-2xl shadow p-8 space-y-8 font-sans text-gray-800">

    <!-- USER INFO -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <input type="text" name="username" placeholder="Username"
               required
               class="border rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-500">
               <p class="form-text text-danger">Add '@' in username</p>

        <input type="password" name="password" placeholder="Password"
               required
               class="border rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-500">
    </div>

    <!-- TITLE -->
    <h2 class="text-2xl font-bold tracking-wide">Assign Permissions</h2>

    <!-- PERMISSIONS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 text-lg">

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="dashboard" class="w-5 h-5">
            Dashboard
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="listings" class="w-5 h-5">
            Listings Management
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="categories" class="w-5 h-5">
            Categories
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="users" class="w-5 h-5">
            Users
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="reviews" class="w-5 h-5">
            Reviews
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="notification" class="w-5 h-5">
            Notification Maintain
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="documents" class="w-5 h-5">
            Documents
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="news" class="w-5 h-5">
            News
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="enquiry" class="w-5 h-5">
            Enquiry
        </label>

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="staff" class="w-5 h-5">
            Staff Management
        </label>

        

        <label class="flex items-center gap-3">
            <input type="checkbox" name="permissions[]" value="settings" class="w-5 h-5">
            Settings
        </label>

    </div>

    <!-- BUTTON -->
    <div class="text-center pt-6">
        <button type="submit"
                class="bg-blue-600 text-white px-10 py-3 text-lg rounded-xl hover:bg-blue-700 shadow">
            Register Staff
        </button>
    </div>

</form>

<?php

$result = $conn->query("
    SELECT id, email, role, permissions, created_at, is_active
    FROM admin_users
    ORDER BY id DESC
");

?>


<table style="width:100%;border-collapse:collapse;font-family:Arial, sans-serif;">
    <thead style="background:#2563eb;color:white;">
        <tr>
            <th style="padding:10px;border:1px solid #ddd;">ID</th>
            <th style="padding:10px;border:1px solid #ddd;">Email</th>
            <th style="padding:10px;border:1px solid #ddd;">Role</th>
            <th style="padding:10px;border:1px solid #ddd;">Permissions</th>
            <th style="padding:10px;border:1px solid #ddd;">Status</th>
            <th style="padding:10px;border:1px solid #ddd;">Action</th>
        </tr>
    </thead>

    <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr style="text-align:center;">
            <td style="padding:8px;border:1px solid #ddd;">
                <?= $row['id']; ?>
            </td>

            <td style="padding:8px;border:1px solid #ddd;">
                <?= htmlspecialchars($row['email']); ?>
            </td>

            <td style="padding:8px;border:1px solid #ddd;">
                <?= $row['role']; ?>
            </td>

            <td style="padding:8px;border:1px solid #ddd;font-size:13px;">
                <?= $row['permissions']; ?>
            </td>

            <td style="padding:8px;border:1px solid #ddd;">
                <?= $row['is_active'] ? 'Active' : 'Inactive'; ?>
            </td>

            <td style="padding:8px;border:1px solid #ddd;">
                <a href="?delete=<?= $row['id']; ?>"
                   onclick="return confirm('Delete this user?')"
                   style="color:red;font-size:18px;text-decoration:none;">
                   🗑
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

            </main>
        </div>
    </div>

 

    <script>
        function viewUser(id) {
            fetch('ajax.php?action=get_user&id=' + id)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('userDetails').innerHTML = `
                        <div class="flex items-center mb-4">
                            <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                                <i class="fas fa-user text-blue-600 text-2xl"></i>
                            </div>
                            <div>
                                <h4 class="text-xl font-bold">${data.name}</h4>
                                <p class="text-gray-600">${data.email}</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Mobile</p>
                                <p class="font-medium">${data.mobile || 'N/A'}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Joined</p>
                                <p class="font-medium">${data.joined_date}</p>
                            </div>
                        </div>
                        
                        ${data.latitude ? `
                        <div>
                            <p class="text-sm text-gray-500">Location</p>
                            <p class="font-medium">${data.latitude}, ${data.longitude}</p>
                            <a href="https://maps.google.com/?q=${data.latitude},${data.longitude}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-map-marker-alt mr-1"></i> View on Map
                            </a>
                        </div>
                        ` : ''}
                        
                        <div>
                            <p class="text-sm text-gray-500">Total Reviews</p>
                            <p class="font-medium">${data.review_count} reviews</p>
                        </div>
                    `;
                    
                    document.getElementById('userModal').classList.remove('hidden');
                });
        }
        
        function closeModal() {
            document.getElementById('userModal').classList.add('hidden');
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('userModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
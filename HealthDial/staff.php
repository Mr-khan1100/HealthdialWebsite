<?php
session_start();
require_once 'connection.inc.php';
requireAdmin();

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



/* ---------------- CREATE / UPDATE STAFF ---------------- */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $editId   = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');   // no hash as you said

    // Admin ticks the sidebar sections this staff can access. Whitelist the posted
    // keys against the registry (admin_sections.php) so only real keys are stored.
    $selected = (isset($_POST['perms']) && is_array($_POST['perms'])) ? $_POST['perms'] : [];
    $selected = array_values(array_intersect($selected, admin_section_keys()));
    $permissions = implode(',', $selected);   // CSV — matches index.php login explode()

    $role = 'manager';
    $name = 'User';

    /* ----- UPDATE existing staff (role<>'admin' guard: never touch an admin) ----- */
    if ($editId > 0) {
        try {
            if ($password !== '') {
                $stmt = $conn->prepare("UPDATE admin_users SET permissions=?, password=? WHERE id=? AND role<>'admin'");
                $stmt->bind_param("ssi", $permissions, $password, $editId);
            } else {
                $stmt = $conn->prepare("UPDATE admin_users SET permissions=? WHERE id=? AND role<>'admin'");
                $stmt->bind_param("si", $permissions, $editId);
            }
            $stmt->execute();
            $_SESSION['success'] = "Staff permissions updated ✔";
        } catch (mysqli_sql_exception $e) {
            $_SESSION['error'] = "Database error ❌";
        }
        header("Location: staff.php");
        exit();
    }

    /* ----- CREATE new staff ----- */
    // Username must be an email-style id (must contain '@').
    if (strpos($username, '@') === false) {
        $_SESSION['error'] = "Username must contain '@' ❌";
        header("Location: staff.php");
        exit();
    }

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

/* ---------------- EDIT PREFILL ---------------- */
// ?edit=<id> loads an existing staff member into the form (admins are not editable here).
$editStaff = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT id, email, permissions FROM admin_users WHERE id=? AND role<>'admin' LIMIT 1");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $editStaff = $stmt->get_result()->fetch_assoc();
    if (!$editStaff) {
        $_SESSION['error'] = "Staff not found ❌";
        header("Location: staff.php");
        exit();
    }
}
$editPerms = $editStaff ? array_filter(array_map('trim', explode(',', $editStaff['permissions']))) : [];
$isEdit    = (bool)$editStaff;
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

    <?php if($isEdit): ?>
        <input type="hidden" name="edit_id" value="<?= (int)$editStaff['id']; ?>">
    <?php endif; ?>

    <h2 class="text-xl font-bold"><?= $isEdit ? 'Edit Staff Permissions' : 'Create Staff Account'; ?></h2>

    <!-- USER INFO -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div>
            <input type="email" name="username" placeholder="Username (must contain @)"
                   value="<?= $isEdit ? htmlspecialchars($editStaff['email']) : ''; ?>"
                   <?= $isEdit ? 'readonly' : 'required'; ?>
                   title="Username must contain '@'"
                   class="w-full border rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-500 <?= $isEdit ? 'bg-gray-100 text-gray-500' : ''; ?>">
            <p class="text-sm text-gray-500 mt-1">Add '@' in username</p>
        </div>

        <input type="password" name="password"
               placeholder="<?= $isEdit ? 'New password (leave blank to keep)' : 'Password'; ?>"
               <?= $isEdit ? '' : 'required'; ?>
               class="border rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-blue-500">
    </div>

    <!-- SECTION ACCESS (auto-built from admin_sections.php) -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-gray-700">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-lg font-bold"><i class="fas fa-user-shield text-blue-600 mr-1"></i> Section access</h2>
            <div class="text-sm">
                <button type="button" onclick="togglePerms(true)" class="text-blue-600 hover:underline">Select all</button>
                <span class="text-gray-400 mx-1">|</span>
                <button type="button" onclick="togglePerms(false)" class="text-blue-600 hover:underline">Clear</button>
            </div>
        </div>
        <p class="text-sm leading-relaxed mb-4">Tick the sidebar sections this staff member can access. Admin-only areas
            (Users, Payment Gateway, Sponsored, Analytics, Settings, Staff, etc.) are never available to staff.</p>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <?php foreach(admin_sections() as $key => $sec):
                $checked = in_array($key, $editPerms, true) ? 'checked' : ''; ?>
            <label class="flex items-center gap-2 bg-white border rounded-lg px-3 py-2 cursor-pointer hover:border-blue-400">
                <input type="checkbox" name="perms[]" value="<?= htmlspecialchars($key); ?>" <?= $checked; ?>
                       class="perm-cb" style="accent-color:#2563eb;">
                <i class="fas <?= htmlspecialchars($sec['icon']); ?> text-blue-500" style="width:16px;text-align:center;"></i>
                <span class="text-sm"><?= htmlspecialchars($sec['label']); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- BUTTON -->
    <div class="text-center pt-2 flex items-center justify-center gap-3">
        <button type="submit"
                class="bg-blue-600 text-white px-10 py-3 text-lg rounded-xl hover:bg-blue-700 shadow">
            <?= $isEdit ? 'Update Staff' : 'Register Staff'; ?>
        </button>
        <?php if($isEdit): ?>
        <a href="staff.php" class="px-6 py-3 text-lg rounded-xl border hover:bg-gray-100">Cancel</a>
        <?php endif; ?>
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

            <td style="padding:8px;border:1px solid #ddd;font-size:13px;text-align:left;">
                <?php
                if ($row['role'] === 'admin') {
                    echo 'All sections';
                } else {
                    $keys = array_filter(array_map('trim', explode(',', $row['permissions'])));
                    if (empty($keys)) {
                        echo '<span style="color:#b91c1c;">No access</span>';
                    } else {
                        $secs = admin_sections();
                        $labels = [];
                        foreach ($keys as $k) { if (isset($secs[$k])) $labels[] = $secs[$k]['label']; }
                        echo htmlspecialchars(implode(', ', $labels));
                    }
                }
                ?>
            </td>

            <td style="padding:8px;border:1px solid #ddd;">
                <?= $row['is_active'] ? 'Active' : 'Inactive'; ?>
            </td>

            <td style="padding:8px;border:1px solid #ddd;">
                <?php if($row['role'] !== 'admin'): ?>
                <a href="?edit=<?= $row['id']; ?>"
                   title="Edit permissions"
                   style="font-size:18px;text-decoration:none;margin-right:8px;">
                   ✏️
                </a>
                <?php endif; ?>
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
        // Bulk-toggle the section-access checkboxes on the create/edit form.
        function togglePerms(state) {
            document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = state);
        }

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
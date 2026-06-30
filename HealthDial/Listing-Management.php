<?php
require_once 'connection.inc.php';
requireLogin();
requireAccess('listings');

$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$current_page = ($current_page && $current_page > 0) ? $current_page : 1;

$filter_category = $_GET['category'] ?? '';
$filter_status   = $_GET['status'] ?? '';
$filter_search   = $_GET['search'] ?? '';
// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Add/Edit Listing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_listing'])) {
        $category_id = $_POST['category_id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $address = $_POST['address'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $mobile = $_POST['mobile'];
        $whatsapp = $_POST['whatsapp'];
        $email = $_POST['email'];
        $open_time = $_POST['open_time'];
        $close_time = $_POST['close_time'];
        $is_24x7 = isset($_POST['is_24x7']) ? 1 : 0;
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("INSERT INTO listings (category_id, name, description, address, latitude, longitude, mobile, whatsapp, email, open_time, close_time, is_24x7, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssddsssssis", $category_id, $name, $description, $address, $latitude, $longitude, $mobile, $whatsapp, $email, $open_time, $close_time, $is_24x7, $status);
        
        if ($stmt->execute()) {
            $listing_id = $stmt->insert_id;
            
            // Handle image upload
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = './uploads/listings/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    $filename = time() . '_' . basename($_FILES['images']['name'][$key]);
                    $target_file = $upload_dir . $filename;
                    
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $is_primary = ($key == 0) ? 1 : 0;
                        $imgStmt = $conn->prepare("INSERT INTO listing_images (listing_id, image_path, is_primary) VALUES (?, ?, ?)");
                        $imgStmt->bind_param("isi", $listing_id, $filename, $is_primary);
                        $imgStmt->execute();
                    }
                }
            }
            
            $_SESSION['success'] = "Listing added successfully!";
        } else {
            $_SESSION['error'] = "Error adding listing: " . $conn->error;
        }
        header("Location: Listing-Management.php");
        exit();
    }
    
    if (isset($_POST['edit_listing'])) {
        $id = $_POST['id'];
        $category_id = $_POST['category_id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $address = $_POST['address'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $mobile = $_POST['mobile'];
        $whatsapp = $_POST['whatsapp'];
        $email = $_POST['email'];
        $open_time = $_POST['open_time'];
        $close_time = $_POST['close_time'];
        $is_24x7 = isset($_POST['is_24x7']) ? 1 : 0;
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE listings SET category_id=?, name=?, description=?, address=?, latitude=?, longitude=?, mobile=?, whatsapp=?, email=?, open_time=?, close_time=?, is_24x7=?, status=? WHERE id=?");
        $stmt->bind_param("isssddsssssisi", $category_id, $name, $description, $address, $latitude, $longitude, $mobile, $whatsapp, $email, $open_time, $close_time, $is_24x7, $status, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Listing updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating listing: " . $conn->error;
        }
        header("Location: Listing-Management.php");
        exit();
    }
}

// Delete action (SQL injection fixed)
if ($action == 'delete' && $id > 0) {
    $delStmt = $conn->prepare("DELETE FROM listings WHERE id = ?");
    $delStmt->bind_param("i", $id);
    $delStmt->execute();
    $_SESSION['success'] = "Listing deleted successfully!";
    header("Location: Listing-Management.php");
    exit();
}

// Status change (SQL injection fixed)
if ($action == 'status' && isset($_GET['status'])) {
    $allowed = ['pending', 'approved', 'rejected'];
    $newStatus = $_GET['status'];
    if(in_array($newStatus, $allowed)) {
        $statusStmt = $conn->prepare("UPDATE listings SET status=? WHERE id=?");
        $statusStmt->bind_param("si", $newStatus, $id);
        $statusStmt->execute();
        $_SESSION['success'] = "Listing status updated!";
    }
    header("Location: Listing-Management.php");
    exit();
}

// NEW: Process CSV in batches
function processCSVBatch($batch_data, $options, $conn) {
    $results = [
        'success' => 0,
        'failed' => 0,
        'updated' => 0,
        'errors' => [],
        'details' => []
    ];
    
    // Category cache
    static $category_cache = [];
    
    foreach ($batch_data as $row_item) {
        $row_number = $row_item['row_num'];
        $data = $row_item['data'];
        $headers = $row_item['headers'];
        
        try {
            // Map data to headers
            $row_data = [];
            foreach ($headers as $index => $header) {
                $normalized_header = strtolower(trim($header, ' "\''));
                $row_data[$normalized_header] = isset($data[$index]) ? trim($data[$index]) : '';
            }
            
            // Validate required fields
            $required = ['name', 'category', 'phone', 'address'];
            foreach ($required as $field) {
                if (empty($row_data[$field] ?? '')) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Get category ID
            $category_name = trim($row_data['category']);
            if (!isset($category_cache[$category_name])) {
                $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->bind_param("s", $category_name);
                $stmt->execute();
                $res = $stmt->get_result();
                
                if ($res->num_rows === 0) {
                    throw new Exception("Category not found: $category_name");
                }
                
                $category_cache[$category_name] = $res->fetch_assoc()['id'];
            }
            $category_id = $category_cache[$category_name];
            
            // Extract lat/long
            $latitude = $longitude = null;
            if (!empty($row_data['google_maps_url'])) {
                $coords = extractLatLongFromGoogleMaps($row_data['google_maps_url']);
                $latitude = $coords[0];
                $longitude = $coords[1];
            }
            
            // Parse hours
            $open_time = '09:00:00';
            $close_time = '18:00:00';
            $is_24x7 = 0;
            if (!empty($row_data['hours'])) {
                $hours = strtolower(trim($row_data['hours']));
                if ($hours === '24/7' || $hours === '24x7') {
                    $is_24x7 = 1;
                }
            }
            
            $description = "Location: " . trim($row_data['city'] ?? '');
            $address = trim($row_data['address']);
            $mobile = trim($row_data['phone']);
            $whatsapp = $mobile;
            $email = '';
            $name = trim($row_data['name']);
            
            // Check if listing exists
            if ($options['update_existing']) {
                $stmt = $conn->prepare("SELECT id FROM listings WHERE name = ?");
                $stmt->bind_param("s", $name);
                $stmt->execute();
                $existing = $stmt->get_result();
                
                if ($existing->num_rows > 0) {
                    // Update existing
                    $listing_id = $existing->fetch_assoc()['id'];
                    $stmt = $conn->prepare("UPDATE listings SET category_id=?, description=?, address=?, latitude=?, longitude=?, mobile=?, whatsapp=?, open_time=?, close_time=?, is_24x7=?, status=?, updated_at=NOW() WHERE id=?");
                    $stmt->bind_param("issddssssisi", $category_id, $description, $address, $latitude, $longitude, $mobile, $whatsapp, $open_time, $close_time, $is_24x7, $options['default_status'], $listing_id);
                    
                    if ($stmt->execute()) {
                        $results['updated']++;
                        $results['details'][] = ['row' => $row_number, 'status' => 'updated', 'name' => $name];
                    } else {
                        throw new Exception("Update failed: " . $stmt->error);
                    }
                    continue;
                }
            }
            
            // Insert new listing
            $stmt = $conn->prepare("INSERT INTO listings (category_id, name, description, address, latitude, longitude, mobile, whatsapp, email, open_time, close_time, is_24x7, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("isssddsssssis", $category_id, $name, $description, $address, $latitude, $longitude, $mobile, $whatsapp, $email, $open_time, $close_time, $is_24x7, $options['default_status']);
            
            if ($stmt->execute()) {
                $results['success']++;
                $results['details'][] = ['row' => $row_number, 'status' => 'success', 'name' => $name];
            } else {
                // Check for duplicate entry
                if (strpos($stmt->error, 'Duplicate entry') !== false) {
                    $results['failed']++;
                    $results['details'][] = ['row' => $row_number, 'status' => 'duplicate', 'name' => $name];
                    $results['errors'][] = "Row $row_number: Duplicate entry - $name";
                } else {
                    throw new Exception("Insert failed: " . $stmt->error);
                }
            }
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = "Row $row_number: " . $e->getMessage();
            $results['details'][] = ['row' => $row_number, 'status' => 'error', 'name' => $row_data['name'] ?? 'Unknown', 'message' => $e->getMessage()];
            
            if (!$options['skip_errors']) {
                break;
            }
        }
    }
    
    return $results;
}

function extractLatLongFromGoogleMaps($url) {
    if (preg_match('/@([-\d.]+),([-\d.]+)/', $url, $m)) {
        return [(float)$m[1], (float)$m[2]];
    }
    if (preg_match('/!3d([-\d.]+)!4d([-\d.]+)/', $url, $m)) {
        return [(float)$m[1], (float)$m[2]];
    }
    if (preg_match('/[?&]q=([-\d.]+),([-\d.]+)/', $url, $m)) {
        return [(float)$m[1], (float)$m[2]];
    }
    if (preg_match('/[?&]ll=([-\d.]+),([-\d.]+)/', $url, $m)) {
        return [(float)$m[1], (float)$m[2]];
    }
    return [null, null];
}

// ================= PAGINATION SETUP (SAFE) =================
// Get current page – ensure it's an integer >= 1

// $current_page = (int)$current_page;
// $total_pages = (int)$total_pages;

$items_per_page = 20; // Show 20 records per page
// $offset = ($current_page - 1) * $items_per_page;

// Count total listings (with filters)
$count_sql = "
    SELECT COUNT(*) as total
    FROM listings l
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE 1
";
if (!empty($filter_category)) {
    $count_sql .= " AND l.category_id = " . intval($filter_category);
}
if (!empty($filter_status)) {
    $count_sql .= " AND l.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if (!empty($filter_search)) {
    $s = $conn->real_escape_string($filter_search);
    $count_sql .= " AND (l.name LIKE '%$s%' OR l.address LIKE '%$s%' OR l.mobile LIKE '%$s%')";
}

$total_result = $conn->query($count_sql);
if (!$total_result) {
    die("Count query failed: " . $conn->error);
}
$total_row = $total_result->fetch_assoc();
$total_listings = $total_row['total'];
$total_pages = ceil($total_listings / $items_per_page);
error_log("DEBUG: page=$current_page total_listings=$total_listings total_pages=$total_pages");

// Redirect if requested page is out of range
if ($current_page > $total_pages && $total_pages > 0) {
    // only redirect if page is actually different to avoid loops
    if ((int)$current_page !== (int)$total_pages) {
        $params = $_GET;
        $params['page'] = $total_pages;
        header("Location: ?" . http_build_query($params));
        exit();
    }
}

// If no records, ensure page is 1
if ($total_pages == 0) {
    $current_page = 1;
}

// Calculate offset for SQL LIMIT (ensure $current_page is integer)
$offset = ((int)$current_page - 1) * $items_per_page;

// =============== MAIN QUERY WITH PAGINATION ===============
$sql = "
    SELECT l.*, c.name as category_name
    FROM listings l
    LEFT JOIN categories c ON l.category_id = c.id
    WHERE 1
";
// Apply same filters as count query
if (!empty($filter_category)) {
    $sql .= " AND l.category_id = " . intval($filter_category);
}
if (!empty($filter_status)) {
    $sql .= " AND l.status = '" . $conn->real_escape_string($filter_status) . "'";
}
if (!empty($filter_search)) {
    $s = $conn->real_escape_string($filter_search);
    $sql .= " AND (l.name LIKE '%$s%' OR l.address LIKE '%$s%' OR l.mobile LIKE '%$s%')";
}
$sql .= " ORDER BY l.created_at DESC LIMIT $offset, $items_per_page";
error_log("DEBUG SQL: $sql");

$listings_query = $conn->query($sql);
if (!$listings_query) {
    die("Main query failed: " . $conn->error);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listings — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    .active-page {
        background-color: #2563eb !important;
        color: white !important;
    }

    .progress-bar {
        height: 25px;
        background-color: #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        margin: 20px 0;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981 0%, #3b82f6 100%);
        transition: width 0.5s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 14px;
    }

    .spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3b82f6;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include 'header.php'; ?>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <?php if(isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php 
                        echo $_SESSION['success']; 
                        unset($_SESSION['success']); 
                        ?>
                </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']); 
                        ?>
                </div>
                <?php endif; ?>

                <?php if($action == 'list'): ?>
                <!-- Listing Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">All Listings</h2>
                        <div class="flex space-x-4">
                            <a href="add-listing.php"
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                                <i class="fas fa-plus mr-2"></i> Add New Listing
                            </a>
                            <a href="bulk-upload.php"
                                class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center">
                                <i class="fas fa-file-import mr-2"></i> Bulk Import CSV
                            </a>
                        </div>
                    </div>

                    <div class="p-6">
                        <!-- Filters -->
                        <form method="GET" class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <select name="category" class="border border-gray-300 rounded-lg px-4 py-2">
                                <option value="">All Categories</option>
                                <?php
                                $categories = $conn->query("SELECT * FROM categories WHERE status=1");
                                while($cat = $categories->fetch_assoc()):
                                ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo (isset($_GET['category']) && $_GET['category']==$cat['id'])?'selected':''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>

                            <select name="status" class="border border-gray-300 rounded-lg px-4 py-2">
                                <option value="">All Status</option>
                                <option value="pending"
                                    <?php echo (isset($_GET['status']) && $_GET['status']=='pending')?'selected':''; ?>>
                                    Pending</option>
                                <option value="approved"
                                    <?php echo (isset($_GET['status']) && $_GET['status']=='approved')?'selected':''; ?>>
                                    Approved</option>
                                <option value="rejected"
                                    <?php echo (isset($_GET['status']) && $_GET['status']=='rejected')?'selected':''; ?>>
                                    Rejected</option>
                            </select>

                            <input type="text" name="search" placeholder="Search listings..."
                                class="border border-gray-300 rounded-lg px-4 py-2"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                            <button type="submit"
                                class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900">Filter</button>
                        </form>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ID</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Category</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Contact</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Created</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200" id="listingsTable">
                                    <?php while($row = $listings_query->fetch_assoc()): ?>
                                    <tr class="listing-row">
                                        <!-- <td class="px-4 py-3 whitespace-nowrap">#<?php echo $row['id']; ?></td> -->
                                        <td class="px-4 py-3 whitespace-nowrap">

                                            <?php echo $row['id']; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?php
                                                $image = $conn->query("SELECT * FROM listing_images WHERE listing_id={$row['id']} AND is_primary=1 LIMIT 1");
                                                if($image && $image->num_rows > 0):
                                                    $img = $image->fetch_assoc();
                                                    if(isset($img['is_external_url']) && $img['is_external_url'] == 1):
                                                ?>
                                                <img src="<?php echo htmlspecialchars($img['image_path']); ?>"
                                                    class="w-10 h-10 rounded-lg object-cover mr-3"
                                                    onerror="this.onerror=null; this.src='/assets/logodefine.png';">
                                                <?php else: ?>
                                                <img src="./uploads/listings/<?php echo htmlspecialchars($img['image_path']); ?>"
                                                    class="w-10 h-10 rounded-lg object-cover mr-3"
                                                    onerror="this.onerror=null; this.src='/assets/logodefine.png';">
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="font-medium text-gray-900 listing-name">
                                                        <?php echo htmlspecialchars($row['name']); ?></p>
                                                    <p class="text-sm text-gray-500 listing-address">
                                                        <?php echo substr($row['address'], 0, 50); ?>...</p>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap listing-category">
                                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                                <?php echo $row['category_name']; ?>
                                            </span>
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap listing-contact">
                                            <p class="text-sm"><?php echo $row['mobile']; ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $row['email']; ?></p>
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap listing-status">
                                            <?php
                                            $status_color = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'approved' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800'
                                            ];
                                            $row_status = $row['status'];
                                            $color = $status_color[$row_status] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $color; ?>">
                                                <?php echo ucfirst($row_status); ?>
                                            </span>
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                                        </td>

                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <div class="flex space-x-2">
                                                <a href="view-listing.php?view_id=<?php echo $row['id']; ?>"
                                                    class="text-blue-600 hover:text-blue-900" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <a href="update-listing.php?edit_id=<?php echo $row['id']; ?>"
                                                    class="text-green-600 hover:text-green-900" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <a href="?action=status&id=<?php echo $row['id']; ?>&status=approved"
                                                    class="text-green-600 hover:text-green-900" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </a>

                                                <a href="?action=status&id=<?php echo $row['id']; ?>&status=rejected"
                                                    class="text-red-600 hover:text-red-900" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </a>

                                                <a href="?action=delete&id=<?php echo $row['id']; ?>"
                                                    class="text-red-600 hover:text-red-900" title="Delete"
                                                    onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="flex items-center justify-between mt-6">

                            <div class="text-sm text-gray-500">
                                Showing <?php echo ($total_listings > 0) ? $offset + 1 : 0; ?>
                                to <?php echo min($offset + $items_per_page, $total_listings); ?>
                                of <?php echo $total_listings; ?> entries
                            </div>

                            <div class="flex space-x-1">

                                <?php
        // Preserve filters
        $query_params = array_filter([
            'category' => $filter_category ?? '',
            'status'   => $filter_status ?? '',
            'search'   => $filter_search ?? ''
        ], function($v) { return $v !== ''; });

        // Previous button
        if ((int)$current_page > 1):
            $query_params['page'] = ((int)$current_page - 1);
        ?>
                                <a href="?<?php echo http_build_query($query_params); ?>"
                                    class="px-3 py-1 border rounded hover:bg-gray-100">
                                    Prev
                                </a>
                                <?php endif; ?>


                                <?php
        // Show 10 pages max
        $current_pages_to_show = 10;

        $half = (int) floor($current_pages_to_show / 2);
        $start = max(1, (int)$current_page - $half);
        $end   = min($total_pages, $start + $current_pages_to_show - 1);

        if ($end - $start < $current_pages_to_show - 1) {
            $start = max(1, $end - $current_pages_to_show + 1);
        }

        for ($i = $start; $i <= $end; $i++):
            $query_params['page'] = $i;
        ?>
                                <a href="?<?php echo http_build_query($query_params); ?>"
                                    class="px-3 py-1 border rounded <?php echo ($i == $current_page) ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>


                                <?php
        // Next button
        if ((int)$current_page < $total_pages):
            $query_params['page'] = ((int)$current_page + 1);
        ?>
                                <a href="?<?php echo http_build_query($query_params); ?>"
                                    class="px-3 py-1 border rounded hover:bg-gray-100">

                                    Next
                                </a>
                                <?php endif; ?>

                            </div>
                        </div>

                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>


    <script>
    $(document).ready(function() {
        // CSV Drag and Drop
        const csvUpload = document.getElementById('csvUpload');
        if (csvUpload) {
            const uploadArea = csvUpload.closest('label');
            if (uploadArea) {
                ['dragenter', 'dragover'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        uploadArea.classList.add('border-blue-500', 'bg-blue-50');
                    });
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    uploadArea.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        uploadArea.classList.remove('border-blue-500', 'bg-blue-50');

                        if (eventName === 'drop') {
                            const files = e.dataTransfer.files;
                            if (files.length > 0) {
                                csvUpload.files = files;
                                updateFileInfo(files[0]);
                            }
                        }
                    });
                });

                csvUpload.addEventListener('change', function(e) {
                    if (this.files.length > 0) {
                        updateFileInfo(this.files[0]);
                    }
                });

                function updateFileInfo(file) {
                    const fileNameDisplay = document.createElement('p');
                    fileNameDisplay.className = 'text-green-600 font-medium mt-2';
                    fileNameDisplay.textContent =
                        `Selected: ${file.name} (${(file.size / (1024*1024)).toFixed(2)} MB)`;

                    const existingFileName = uploadArea.querySelector('.file-name');
                    if (existingFileName) existingFileName.remove();

                    fileNameDisplay.classList.add('file-name');
                    uploadArea.appendChild(fileNameDisplay);

                    if (file.size > 5 * 1024 * 1024) {
                        const warning = document.createElement('p');
                        warning.className = 'text-yellow-600 text-sm mt-1';
                        warning.innerHTML =
                            '<i class="fas fa-exclamation-triangle mr-1"></i> Large file will be processed in background';
                        warning.classList.add('file-warning');

                        const existingWarning = uploadArea.querySelector('.file-warning');
                        if (existingWarning) existingWarning.remove();
                        uploadArea.appendChild(warning);
                    }
                }
            }
        }

        // Import progress monitoring (only runs when action=import_progress)
        <?php if ($action == 'import_progress' && isset($_GET['job_id'])): ?>
        const jobId = '<?php echo $_GET['job_id']; ?>';
        let progressInterval;

        function checkProgress() {
            $.ajax({
                url: 'Listing-Management.php?action=check_progress&job_id=' + jobId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'processing' || response.status === 'pending') {
                        $('#totalRows').text(response.total);
                        $('#processedRows').text(response.processed);
                        $('#successCount').text(response.success);
                        $('#failedCount').text(response.failed);
                        $('#progressFill').css('width', response.progress + '%').text(response
                            .progress + '%');
                        $('#statusText').text('Processing: ' + response.processed + ' of ' +
                            response.total + ' rows');
                    } else if (response.status === 'completed') {
                        $('#statusText').text('Processing completed!');
                        $('#progressFill').css('width', '100%').text('100%');
                        clearInterval(progressInterval);
                        setTimeout(() => {
                            window.location.href =
                                'Listing-Management.php?action=import_results&job_id=' +
                                jobId;
                        }, 2000);
                    } else if (response.status === 'failed') {
                        $('#statusText').text('Processing failed');
                        clearInterval(progressInterval);
                    }
                },
                error: function() {
                    $('#statusText').text('Error checking progress');
                }
            });
        }

        progressInterval = setInterval(checkProgress, 3000);
        checkProgress();

        setTimeout(() => {
            $.ajax({
                url: 'Listing-Management.php?action=start_import&job_id=' + jobId,
                type: 'GET'
            });
        }, 1000);
        <?php endif; ?>
    });
    </script>
</body>

</html>
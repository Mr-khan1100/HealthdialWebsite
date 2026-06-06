<?php
require_once 'connection.inc.php';
requireLogin();

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
                        $conn->query("INSERT INTO listing_images (listing_id, image_path, is_primary) VALUES ($listing_id, '$filename', $is_primary)");
                    }
                }
            }
            
            $_SESSION['success'] = "Listing added successfully!";
        } else {
            $_SESSION['error'] = "Error adding listing: " . $conn->error;
        }
        header("Location: Listing Management.php");
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
        header("Location: Listing Management.php");
        exit();
    }
}

// Delete action
if ($action == 'delete' && $id > 0) {
    $conn->query("DELETE FROM listings WHERE id = $id");
    $_SESSION['success'] = "Listing deleted successfully!";
    header("Location: Listing Management.php");
    exit();
}

// Status change
if ($action == 'status' && isset($_GET['status'])) {
    $status = $_GET['status'];
    $conn->query("UPDATE listings SET status='$status' WHERE id=$id");
    $_SESSION['success'] = "Listing status updated!";
    header("Location: Listing Management.php");
    exit();
}

// CSV Import Processing - ASYNCHRONOUS/BACKGROUND PROCESSING
if ($action == 'process_import' && isset($_POST['process_import'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $default_status = $_POST['default_status'];
        $skip_errors = isset($_POST['skip_errors']) ? 1 : 0;
        $update_existing = isset($_POST['update_existing']) ? 1 : 0;
        
        // Generate unique job ID
        $job_id = uniqid('import_');
        $csv_file = $_FILES['csv_file']['tmp_name'];
        $original_filename = $_FILES['csv_file']['name'];
        
        // Move uploaded file to temp directory
        $temp_dir = './temp_imports/';
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0777, true);
        }
        $temp_file = $temp_dir . $job_id . '.csv';
        move_uploaded_file($csv_file, $temp_file);
        
        // Store job details in database for background processing
        $stmt = $conn->prepare("INSERT INTO import_jobs (job_id, filename, status, total_rows, processed_rows, success_count, failed_count, options, created_at) VALUES (?, ?, 'pending', 0, 0, 0, 0, ?, NOW())");
        $options = json_encode([
            'default_status' => $default_status,
            'skip_errors' => $skip_errors,
            'update_existing' => $update_existing
        ]);
        $stmt->bind_param("sss", $job_id, $original_filename, $options);
        $stmt->execute();
        
        // Redirect to progress page
        $_SESSION['import_job_id'] = $job_id;
        header("Location: Listing Management.php?action=import_progress&job_id=" . $job_id);
        exit();
    } else {
        $_SESSION['error'] = "Please select a valid CSV file to upload.";
        header("Location: Listing Management.php?action=bulk_import");
        exit();
    }
}

// Start background import processing
if ($action == 'start_import' && isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    
    // Create import_jobs table if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS import_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id VARCHAR(50) UNIQUE,
        filename VARCHAR(255),
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        total_rows INT DEFAULT 0,
        processed_rows INT DEFAULT 0,
        success_count INT DEFAULT 0,
        failed_count INT DEFAULT 0,
        errors TEXT,
        options TEXT,
        results TEXT,
        created_at DATETIME,
        updated_at DATETIME
    )");
    
    // Check if job exists and is pending
    $result = $conn->query("SELECT * FROM import_jobs WHERE job_id = '$job_id' AND status = 'pending'");
    if ($result->num_rows > 0) {
        // Update status to processing
        $conn->query("UPDATE import_jobs SET status = 'processing', updated_at = NOW() WHERE job_id = '$job_id'");
        
        // Start background processing
        $temp_file = './temp_imports/' . $job_id . '.csv';
        if (file_exists($temp_file)) {
            // Get job options
            $job = $result->fetch_assoc();
            $options = json_decode($job['options'], true);
            
            // Count total rows (excluding header)
            $total_rows = 0;
            if (($handle = fopen($temp_file, "r")) !== FALSE) {
                fgetcsv($handle); // Skip header
                while (fgetcsv($handle) !== FALSE) {
                    $total_rows++;
                }
                fclose($handle);
            }
            
            // Update total rows
            $conn->query("UPDATE import_jobs SET total_rows = $total_rows WHERE job_id = '$job_id'");
            
            // Process in batches
            $batch_size = 500; // Process 500 rows per batch
            $results = [
                'success' => 0,
                'failed' => 0,
                'updated' => 0,
                'errors' => [],
                'details' => []
            ];
            
            // Process CSV in batches
            $processed = 0;
            $success = 0;
            $failed = 0;
            $updated = 0;
            $errors = [];
            
            if (($handle = fopen($temp_file, "r")) !== FALSE) {
                $headers = fgetcsv($handle);
                
                // Process in batches
                $batch_data = [];
                $batch_count = 0;
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $processed++;
                    $batch_data[] = [
                        'row_num' => $processed + 1,
                        'data' => $data,
                        'headers' => $headers
                    ];
                    
                    if (count($batch_data) >= $batch_size) {
                        $batch_result = processCSVBatch($batch_data, $options, $conn);
                        $success += $batch_result['success'];
                        $failed += $batch_result['failed'];
                        $updated += $batch_result['updated'];
                        $errors = array_merge($errors, $batch_result['errors']);
                        $results['details'] = array_merge($results['details'], $batch_result['details']);
                        
                        // Update progress
                        $conn->query("UPDATE import_jobs SET 
                            processed_rows = $processed,
                            success_count = $success,
                            failed_count = $failed,
                            updated_at = NOW()
                            WHERE job_id = '$job_id'");
                        
                        $batch_data = []; // Reset batch
                        $batch_count++;
                        
                        // Small sleep to prevent server overload
                        if ($batch_count % 10 == 0) {
                            sleep(1);
                        }
                    }
                }
                
                // Process remaining rows
                if (!empty($batch_data)) {
                    $batch_result = processCSVBatch($batch_data, $options, $conn);
                    $success += $batch_result['success'];
                    $failed += $batch_result['failed'];
                    $updated += $batch_result['updated'];
                    $errors = array_merge($errors, $batch_result['errors']);
                    $results['details'] = array_merge($results['details'], $batch_result['details']);
                    
                    $conn->query("UPDATE import_jobs SET 
                        processed_rows = $processed,
                        success_count = $success,
                        failed_count = $failed,
                        updated_at = NOW()
                        WHERE job_id = '$job_id'");
                }
                
                fclose($handle);
            }
            
            // Finalize job
            $results['success'] = $success;
            $results['failed'] = $failed;
            $results['updated'] = $updated;
            $results['errors'] = $errors;
            
            $results_json = json_encode($results);
            $errors_json = json_encode(array_slice($errors, 0, 50)); // Store only first 50 errors
            
            $conn->query("UPDATE import_jobs SET 
                status = 'completed',
                results = '" . $conn->real_escape_string($results_json) . "',
                errors = '" . $conn->real_escape_string($errors_json) . "',
                updated_at = NOW()
                WHERE job_id = '$job_id'");
            
            // Cleanup temp file
            unlink($temp_file);
        }
    }
    
    echo json_encode(['status' => 'started']);
    exit();
}

// Check import progress
if ($action == 'check_progress' && isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    $result = $conn->query("SELECT * FROM import_jobs WHERE job_id = '$job_id'");
    
    if ($result->num_rows > 0) {
        $job = $result->fetch_assoc();
        echo json_encode([
            'status' => $job['status'],
            'total' => $job['total_rows'],
            'processed' => $job['processed_rows'],
            'success' => $job['success_count'],
            'failed' => $job['failed_count'],
            'progress' => $job['total_rows'] > 0 ? round(($job['processed_rows'] / $job['total_rows']) * 100) : 0
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
    exit();
}

// Show import progress
if ($action == 'import_progress' && isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    $result = $conn->query("SELECT * FROM import_jobs WHERE job_id = '$job_id'");
    $job = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    
    if (!$job) {
        $_SESSION['error'] = "Import job not found.";
        header("Location: Listing Management.php?action=bulk_import");
        exit();
    }
}

// Show import results
if ($action == 'import_results' && isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    $result = $conn->query("SELECT * FROM import_jobs WHERE job_id = '$job_id'");
    
    if ($result->num_rows > 0) {
        $job = $result->fetch_assoc();
        $import_results = json_decode($job['results'], true);
    } else {
        $_SESSION['error'] = "Import results not found.";
        header("Location: Listing Management.php");
        exit();
    }
}

// Download CSV Template
if ($action == 'download_template') {
    $filename = "listing_import_template.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    
    fputcsv($output, ['name', 'category', 'city', 'google_maps_url', 'phone', 'address', 'hours', 'image']);
    fputcsv($output, ['City Hospital', 'Hospitals', 'New York', 'https://maps.google.com/?q=40.7128,-74.0060', '+1234567890', '123 Main St', '9:00 AM - 6:00 PM', 'https://example.com/image1.jpg']);
    
    fclose($output);
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

// For pagination
$items_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Get total listings count
$total_result = $conn->query("SELECT COUNT(*) as total FROM listings");
$total_row = $total_result->fetch_assoc();
$total_listings = $total_row['total'];
$total_pages = ceil($total_listings / $items_per_page);

// Get listings with pagination
$listings_query = $conn->query("
    SELECT l.*, c.name as category_name 
    FROM listings l 
    LEFT JOIN categories c ON l.category_id = c.id 
    ORDER BY l.created_at DESC
    LIMIT $offset, $items_per_page
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listings Management - HealthDial</title>
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if($action == 'list'): ?>
                <!-- Listing Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">All Listings</h2>
                        <div class="flex space-x-4">
                            <a href="?action=add" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
                                <i class="fas fa-plus mr-2"></i> Add New Listing
                            </a>
                            <a href="?action=bulk_import" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center">
                                <i class="fas fa-file-import mr-2"></i> Bulk Import CSV
                            </a>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <!-- Filters -->
                        <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <select id="categoryFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Categories</option>
                                <?php
                                $categories = $conn->query("SELECT * FROM categories WHERE status=1");
                                while($cat = $categories->fetch_assoc()):
                                ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                            
                            <select id="statusFilter" class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            
                            <input type="text" id="searchFilter" placeholder="Search listings..." class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            
                            <button id="filterButton" class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-900">Filter</button>
                        </div>
                        
                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200" id="listingsTable">
                                    <?php while($row = $listings_query->fetch_assoc()): ?>
                                    <tr class="listing-row">
                                        <td class="px-4 py-3 whitespace-nowrap">#<?php echo $row['id']; ?></td>
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
                                                     onerror="this.src='https://via.placeholder.com/40x40?text=IMG'">
                                                <?php else: ?>
                                                <img src="./uploads/listings/<?php echo htmlspecialchars($img['image_path']); ?>" 
                                                     class="w-10 h-10 rounded-lg object-cover mr-3"
                                                     onerror="this.src='https://via.placeholder.com/40x40?text=IMG'">
                                                <?php endif; ?>
                                                <?php endif; ?>
                                                <div>
                                                    <p class="font-medium text-gray-900 listing-name"><?php echo htmlspecialchars($row['name']); ?></p>
                                                    <p class="text-sm text-gray-500 listing-address"><?php echo substr($row['address'], 0, 50); ?>...</p>
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
                                            $status = $row['status'];
                                            $color = $status_color[$status] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $color; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <div class="flex space-x-2">
                                                <a href="?action=view&id=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?action=edit&id=<?php echo $row['id']; ?>" class="text-green-600 hover:text-green-900" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?action=status&id=<?php echo $row['id']; ?>&status=approved" class="text-green-600 hover:text-green-900" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="?action=status&id=<?php echo $row['id']; ?>&status=rejected" class="text-red-600 hover:text-red-900" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                                <a href="?action=delete&id=<?php echo $row['id']; ?>" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirm('Are you sure?')">
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
                                Showing <?php echo min(($page - 1) * $items_per_page + 1, $total_listings); ?> 
                                to <?php echo min($page * $items_per_page, $total_listings); ?> 
                                of <?php echo $total_listings; ?> entries
                            </div>
                            <div class="flex space-x-2">
                                <?php if($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 border border-gray-300 rounded text-gray-600 hover:bg-gray-50">Previous</a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <a href="?page=<?php echo $i; ?>" class="px-3 py-1 border border-gray-300 rounded text-gray-600 hover:bg-gray-50 <?php echo $i == $page ? 'active-page' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 border border-gray-300 rounded text-gray-600 hover:bg-gray-50">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif($action == 'bulk_import'): ?>
                <!-- Bulk Import Form -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">Bulk Import Listings from CSV</h2>
                        <p class="text-sm text-gray-600 mt-1">Upload a CSV file with listing data</p>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Upload Form -->
                            <div class="lg:col-span-2">
                                <form method="POST" enctype="multipart/form-data" action="?action=process_import" id="importForm">
                                    <div class="space-y-6">
                                        <!-- CSV Upload -->
                                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                                            <input type="file" name="csv_file" accept=".csv" class="hidden" id="csvUpload" required>
                                            <label for="csvUpload" class="cursor-pointer flex flex-col items-center">
                                                <i class="fas fa-file-csv text-5xl text-gray-400 mb-4"></i>
                                                <p class="text-lg font-medium text-gray-700 mb-2">Upload CSV File</p>
                                                <p class="text-sm text-gray-500 mb-4">Click to upload or drag and drop</p>
                                                <p class="text-xs text-gray-500">Supports .csv files only. Max file size: 100MB</p>
                                            </label>
                                        </div>
                                        
                                        <!-- Template Download -->
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <h4 class="font-medium text-blue-800 mb-2">
                                                <i class="fas fa-download mr-2"></i>Download CSV Template
                                            </h4>
                                            <p class="text-sm text-blue-700 mb-3">Use this template to ensure proper formatting of your CSV file.</p>
                                            <a href="?action=download_template" class="inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
                                                <i class="fas fa-file-download mr-2"></i> Download Template
                                            </a>
                                        </div>
                                        
                                        <!-- Required Columns -->
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                            <h4 class="font-medium text-yellow-800 mb-2">
                                                <i class="fas fa-exclamation-triangle mr-2"></i>Important Instructions
                                            </h4>
                                            <ul class="text-sm text-yellow-700 list-disc pl-5 space-y-1">
                                                <li>CSV must include these headers: <code>name, category, city, google_maps_url, phone, address, hours, image</code></li>
                                                <li>First row should be the header row</li>
                                                <li>Category names must match existing categories in the system</li>
                                                <li>google_maps_url column should contain full Google Maps URLs</li>
                                                <li>Hours format: "9:00 AM - 6:00 PM" or "24/7"</li>
                                                <li>Image column should contain comma-separated image URLs (optional)</li>
                                                <li>Phone numbers should be in international format (e.g., +1234567890)</li>
                                                <li>Large files (10,000+ rows) will be processed in the background</li>
                                            </ul>
                                        </div>
                                        
                                        <!-- Import Options -->
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-700 mb-4">Import Options</h3>
                                            <div class="space-y-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Default Status</label>
                                                    <select name="default_status" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                        <option value="pending">Pending</option>
                                                        <option value="approved">Approved</option>
                                                        <option value="rejected">Rejected</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="skip_errors" id="skip_errors" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                    <label for="skip_errors" class="ml-2 block text-sm text-gray-700">Skip rows with errors and continue</label>
                                                </div>
                                                
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="update_existing" id="update_existing" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" checked>
                                                    <label for="update_existing" class="ml-2 block text-sm text-gray-700">Update existing listings (match by name)</label>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                                            <a href="Listing Management.php" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                                Cancel
                                            </a>
                                            <button type="submit" name="process_import" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center" id="importButton">
                                                <i class="fas fa-upload mr-2"></i> Start Import
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Sample Data Preview -->
                            <div>
                                <div class="bg-gray-50 rounded-lg p-6">
                                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Sample CSV Data</h3>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">name</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">category</th>
                                                    <th class="px-3 py-2 text-left font-medium text-gray-700">city</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <tr>
                                                    <td class="px-3 py-2">City Hospital</td>
                                                    <td class="px-3 py-2">Hospitals</td>
                                                    <td class="px-3 py-2">New York</td>
                                                </tr>
                                                <tr>
                                                    <td class="px-3 py-2">Medicare Clinic</td>
                                                    <td class="px-3 py-2">Clinics</td>
                                                    <td class="px-3 py-2">Los Angeles</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-3">Note: This shows only first 3 columns. Your CSV should include all required columns.</p>
                                </div>
                                
                                <!-- Performance Tips -->
                                <div class="mt-6 bg-purple-50 border border-purple-200 rounded-lg p-4">
                                    <h4 class="font-medium text-purple-800 mb-2">
                                        <i class="fas fa-rocket mr-2"></i>Performance Tips
                                    </h4>
                                    <ul class="text-sm text-purple-700 list-disc pl-5 space-y-1">
                                        <li>Large files are processed in the background</li>
                                        <li>You can close the browser - processing continues</li>
                                        <li>Check import progress from the import history</li>
                                        <li>For 10,000+ rows, allow 5-10 minutes for processing</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif($action == 'import_progress' && isset($_GET['job_id'])): ?>
                <!-- Import Progress -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">Import Progress</h2>
                        <p class="text-sm text-gray-600 mt-1">Job ID: <?php echo htmlspecialchars($_GET['job_id']); ?></p>
                    </div>
                    
                    <div class="p-6">
                        <div class="text-center py-8">
                            <div class="spinner mx-auto mb-4"></div>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">Processing CSV Import</h3>
                            <p class="text-gray-600 mb-6">Your file is being processed in the background. This may take several minutes for large files.</p>
                            
                            <div class="max-w-2xl mx-auto">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progressFill" style="width: 0%">0%</div>
                                </div>
                                
                                <div class="grid grid-cols-4 gap-4 mt-6 text-center">
                                    <div class="bg-blue-50 p-4 rounded-lg">
                                        <p class="text-2xl font-bold text-blue-700" id="totalRows">0</p>
                                        <p class="text-blue-600 font-medium">Total Rows</p>
                                    </div>
                                    <div class="bg-green-50 p-4 rounded-lg">
                                        <p class="text-2xl font-bold text-green-700" id="successCount">0</p>
                                        <p class="text-green-600 font-medium">Successful</p>
                                    </div>
                                    <div class="bg-red-50 p-4 rounded-lg">
                                        <p class="text-2xl font-bold text-red-700" id="failedCount">0</p>
                                        <p class="text-red-600 font-medium">Failed</p>
                                    </div>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-2xl font-bold text-gray-700" id="processedRows">0</p>
                                        <p class="text-gray-600 font-medium">Processed</p>
                                    </div>
                                </div>
                                
                                <div class="mt-8 text-left">
                                    <h4 class="font-medium text-gray-700 mb-2">Status:</h4>
                                    <p class="text-lg" id="statusText">Initializing...</p>
                                </div>
                                
                                <div class="mt-8 flex justify-center space-x-4">
                                    <button onclick="checkProgress()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        <i class="fas fa-sync mr-2"></i> Refresh Status
                                    </button>
                                    <a href="Listing Management.php" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                        Back to Listings
                                    </a>
                                </div>
                                
                                <div class="mt-8 text-sm text-gray-500">
                                    <p><i class="fas fa-info-circle mr-2"></i> You can close this page and check back later. The import will continue processing in the background.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif($action == 'import_results' && isset($import_results)): ?>
                <!-- Import Results -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">CSV Import Results</h2>
                        <p class="text-sm text-gray-600">Job ID: <?php echo htmlspecialchars($_GET['job_id']); ?></p>
                    </div>
                    
                    <div class="p-6">
                        <!-- Summary -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                                <p class="text-3xl font-bold text-green-700"><?php echo $import_results['success']; ?></p>
                                <p class="text-green-600 font-medium">Successful</p>
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center">
                                <p class="text-3xl font-bold text-blue-700"><?php echo $import_results['updated'] ?? 0; ?></p>
                                <p class="text-blue-600 font-medium">Updated</p>
                            </div>
                            
                            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                                <p class="text-3xl font-bold text-red-700"><?php echo $import_results['failed']; ?></p>
                                <p class="text-red-600 font-medium">Failed</p>
                            </div>
                            
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                <p class="text-3xl font-bold text-gray-700"><?php echo ($import_results['success'] + ($import_results['updated'] ?? 0) + $import_results['failed']); ?></p>
                                <p class="text-gray-600 font-medium">Total Processed</p>
                            </div>
                        </div>
                        
                        <?php if (!empty($import_results['errors'])): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <h4 class="font-medium text-red-800 mb-2">
                                <i class="fas fa-exclamation-circle mr-2"></i>Import Errors (First 50 shown)
                            </h4>
                            <ul class="text-red-700 list-disc pl-5 max-h-60 overflow-y-auto">
                                <?php foreach (array_slice($import_results['errors'], 0, 50) as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($import_results['details']) && count($import_results['details']) > 0): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-700 mb-4">Import Details (First 100 rows)</h3>
                            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Row</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Message</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach (array_slice($import_results['details'], 0, 100) as $detail): ?>
                                        <tr>
                                            <td class="px-4 py-3 text-sm"><?php echo $detail['row']; ?></td>
                                            <td class="px-4 py-3 text-sm font-medium"><?php echo htmlspecialchars($detail['name']); ?></td>
                                            <td class="px-4 py-3 text-sm">
                                                <?php if ($detail['status'] == 'success'): ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Success</span>
                                                <?php elseif ($detail['status'] == 'updated'): ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Updated</span>
                                                <?php elseif ($detail['status'] == 'duplicate'): ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Duplicate</span>
                                                <?php else: ?>
                                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($detail['message'] ?? ''); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-8 flex justify-end space-x-4">
                            <a href="?action=bulk_import" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-upload mr-2"></i> Import Another File
                            </a>
                            <a href="Listing Management.php" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-list mr-2"></i> Back to Listings
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Filter table function
            function filterTable() {
                const category = $('#categoryFilter').val().toLowerCase();
                const status = $('#statusFilter').val().toLowerCase();
                const search = $('#searchFilter').val().toLowerCase();
                
                $('.listing-row').each(function() {
                    const row = $(this);
                    const rowCategory = row.find('.listing-category span').text().toLowerCase();
                    const rowStatus = row.find('.listing-status span').text().toLowerCase();
                    const rowName = row.find('.listing-name').text().toLowerCase();
                    const rowAddress = row.find('.listing-address').text().toLowerCase();
                    const rowContact = row.find('.listing-contact').text().toLowerCase();
                    const rowText = rowName + ' ' + rowAddress + ' ' + rowContact;
                    
                    const categoryMatch = category === '' || rowCategory.includes(category);
                    const statusMatch = status === '' || rowStatus.includes(status);
                    const searchMatch = search === '' || rowText.includes(search);
                    
                    row.toggle(categoryMatch && statusMatch && searchMatch);
                });
            }
            
            // Event listeners for filters
            $('#categoryFilter, #statusFilter, #searchFilter').on('change keyup', filterTable);
            $('#filterButton').on('click', filterTable);
            
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
                        fileNameDisplay.textContent = `Selected: ${file.name} (${(file.size / (1024*1024)).toFixed(2)} MB)`;
                        
                        const existingFileName = uploadArea.querySelector('.file-name');
                        if (existingFileName) existingFileName.remove();
                        
                        fileNameDisplay.classList.add('file-name');
                        uploadArea.appendChild(fileNameDisplay);
                        
                        // Warn about large files
                        if (file.size > 5 * 1024 * 1024) {
                            const warning = document.createElement('p');
                            warning.className = 'text-yellow-600 text-sm mt-1';
                            warning.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Large file will be processed in background';
                            warning.classList.add('file-warning');
                            
                            const existingWarning = uploadArea.querySelector('.file-warning');
                            if (existingWarning) existingWarning.remove();
                            uploadArea.appendChild(warning);
                        }
                    }
                }
            }
            
            // Import progress monitoring
            <?php if ($action == 'import_progress' && isset($_GET['job_id'])): ?>
            const jobId = '<?php echo $_GET['job_id']; ?>';
            let progressInterval;
            
            function checkProgress() {
                $.ajax({
                    url: 'Listing Management.php?action=check_progress&job_id=' + jobId,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'processing' || response.status === 'pending') {
                            $('#totalRows').text(response.total);
                            $('#processedRows').text(response.processed);
                            $('#successCount').text(response.success);
                            $('#failedCount').text(response.failed);
                            $('#progressFill').css('width', response.progress + '%').text(response.progress + '%');
                            $('#statusText').text('Processing: ' + response.processed + ' of ' + response.total + ' rows');
                        } else if (response.status === 'completed') {
                            $('#statusText').text('Processing completed!');
                            $('#progressFill').css('width', '100%').text('100%');
                            clearInterval(progressInterval);
                            setTimeout(() => {
                                window.location.href = 'Listing Management.php?action=import_results&job_id=' + jobId;
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
            
            // Start checking progress
            progressInterval = setInterval(checkProgress, 3000);
            checkProgress(); // Initial check
            
            // Start processing if not already started
            setTimeout(() => {
                $.ajax({
                    url: 'Listing Management.php?action=start_import&job_id=' + jobId,
                    type: 'GET'
                });
            }, 1000);
            <?php endif; ?>
        });
    </script>
</body>
</html>
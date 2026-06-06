<?php
session_start();
require_once 'connection.inc.php';
requireLogin();

/* ---------------- ERROR REPORTING ---------------- */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Increase execution time for large imports – set to 0 for unlimited
set_time_limit(0);
// Increase memory limit if needed
ini_set('memory_limit', '512M');

/* ---------------- CONFIGURATION ---------------- */
define('BATCH_SIZE', 5000); // Number of rows per transaction

/* ---------------- HELPER: map category name to ID ---------------- */
function mapCategoryNameToId($conn, $categoryName) {
    $map = [
        'hospital' => 1,
        'medical store' => 2,
        'pharmacy' => 2,
        'medical' => 2,
        'clinic' => 4,
        'lab' => 3,
        'laboratory' => 3
    ];

    $key = mb_strtolower(trim($categoryName));
    if (isset($map[$key])) return $map[$key];

    $stmt = $conn->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("s", $categoryName);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows) {
        $id = (int)$res->fetch_assoc()['id'];
        $stmt->close();
        return $id;
    }
    $stmt->close();
    return null;
}

/* ---------------- BULK IMAGE INSERT HELPER ---------------- */
function bulkInsertImages($conn, $imagesData) {
    if (empty($imagesData)) return;

    $imageSql = "INSERT INTO listing_images (listing_id, image_path, is_primary, created_at, is_external_url) VALUES ";
    $placeholders = [];
    $params = [];
    $types = "";

    foreach ($imagesData as $img) {
        $placeholders[] = "(?, ?, ?, NOW(), ?)";
        $params[] = $img['listing_id'];
        $params[] = $img['image_path'];
        $params[] = $img['is_primary'];
        $params[] = $img['is_external'];
        $types .= "issi";
    }

    $imageSql .= implode(", ", $placeholders);
    $stmt = $conn->prepare($imageSql);
    if (!$stmt) {
        throw new Exception("Prepare bulk image insert failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception("Bulk image insert failed: " . $stmt->error);
    }
    $stmt->close();
}

/* ---------------- CSV UPLOAD HANDLING ---------------- */

if (isset($_FILES['csv_file'])) {

    if ($_FILES['csv_file']['error'] != 0) {
        $_SESSION['error'] = "File upload failed";
        header("Location: bulk-import.php");
        exit;
    }

    $file = fopen($_FILES['csv_file']['tmp_name'], "r");

    if (!$file) {
        $_SESSION['error'] = "Unable to open file";
        header("Location: bulk-import.php");
        exit;
    }

    // Read header row (optional, but we skip it)
    $header = fgetcsv($file);

    // Expected columns in the cleaned CSV (0‑based):
    // 0: name, 1: category, 2: city, 3: latitude, 4: longitude,
    // 5: phone, 6: address, 7: open_time, 8: close_time, 9: is_24x7, 10: image
    $totalProcessed = 0;
    $totalSuccess = 0;
    $totalFail = 0;
    $errors = [];

    // Prepare the two listing statements (with and without user_id) once
    $stmtListingWithUser = $conn->prepare("INSERT INTO listings 
        (user_id, category_id, name, description, address, city, latitude, longitude, mobile, whatsapp, email, open_time, close_time, is_24x7, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtListingWithoutUser = $conn->prepare("INSERT INTO listings 
        (category_id, name, description, address, city, latitude, longitude, mobile, whatsapp, email, open_time, close_time, is_24x7, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmtListingWithUser || !$stmtListingWithoutUser) {
        $_SESSION['error'] = "Failed to prepare listing statements: " . $conn->error;
        header("Location: bulk-import.php");
        exit;
    }

    // Start first transaction
    $conn->begin_transaction();
    $rowCountInBatch = 0;
    $imagesData = [];   // holds images for current batch

    try {
        while (($row = fgetcsv($file)) !== FALSE) {
            // skip empty rows
            if (count($row) === 1 && trim($row[0]) === '') continue;

            // ---- Read columns from cleaned CSV ----
            $name        = isset($row[0]) ? trim($row[0]) : '';
            $categoryName = isset($row[1]) ? trim($row[1]) : '';
            $city        = isset($row[2]) ? trim($row[2]) : '';
            $latitude    = isset($row[3]) ? trim($row[3]) : '';
            $longitude   = isset($row[4]) ? trim($row[4]) : '';
            $phone       = isset($row[5]) ? trim($row[5]) : '';
            $address     = isset($row[6]) ? trim($row[6]) : '';
            $open_time   = isset($row[7]) ? trim($row[7]) : '';
            $close_time  = isset($row[8]) ? trim($row[8]) : '';
            $is_24x7     = isset($row[9]) ? (int)trim($row[9]) : 0;
            $imageUrl    = isset($row[10]) ? trim($row[10]) : '';

            // ---- Validation ----
            if (empty($name) || empty($categoryName) || empty($city)) {
                $totalFail++;
                $errors[] = "Skipped row: missing name, category, or city – name: '{$name}'";
                continue;
            }

            // Convert latitude/longitude to float (they should already be numbers)
            $latitude  = $latitude !== '' ? (float)$latitude : 0.0;
            $longitude = $longitude !== '' ? (float)$longitude : 0.0;

            // Map category name to ID
            $category_id = mapCategoryNameToId($conn, $categoryName);
            if ($category_id === null) {
                $totalFail++;
                $errors[] = "Category not found or unmapped: '{$categoryName}' for listing '{$name}'";
                continue;
            }

            // Defaults
            $description = '';
            $whatsapp = '';
            $email = '';
            $status = 'approved';
            $user_id = null;   // as per your requirement

            // Choose the right prepared statement
            try {
                if ($user_id === null) {
                    $stmt = $stmtListingWithoutUser;
                    $stmt->bind_param("issssddsssssis",
                        $category_id, $name, $description, $address, $city,
                        $latitude, $longitude, $phone, $whatsapp, $email,
                        $open_time, $close_time, $is_24x7, $status
                    );
                } else {
                    $stmt = $stmtListingWithUser;
                    $stmt->bind_param("iissssddsssssis",
                        $user_id, $category_id, $name, $description, $address, $city,
                        $latitude, $longitude, $phone, $whatsapp, $email,
                        $open_time, $close_time, $is_24x7, $status
                    );
                }

                if (!$stmt->execute()) {
                    throw new Exception("Insert listing failed: " . $stmt->error);
                }

                $listing_id = $conn->insert_id;
                $totalSuccess++;

                // Store image for bulk insert (if URL is present)
                if (!empty($imageUrl)) {
                    $imagesData[] = [
                        'listing_id'   => $listing_id,
                        'image_path'   => $imageUrl,
                        'is_primary'   => 1,
                        'is_external'  => 1
                    ];
                }

                $rowCountInBatch++;
                $totalProcessed++;

            } catch (Exception $e) {
                $totalFail++;
                $errors[] = "Row failed for '{$name}': " . $e->getMessage();
                continue;   // skip this row, continue with next
            }

            // If batch size reached, commit and start new transaction
            if ($rowCountInBatch >= BATCH_SIZE) {
                bulkInsertImages($conn, $imagesData);
                $conn->commit();

                // Reset batch data
                $imagesData = [];
                $rowCountInBatch = 0;
                $conn->begin_transaction();
            }
        }

        // After loop, insert any remaining images and commit final batch
        if ($rowCountInBatch > 0) {
            bulkInsertImages($conn, $imagesData);
            $conn->commit();
        }

    } catch (Exception $e) {
        // Rollback current batch
        $conn->rollback();
        $totalFail = 'batch'; // indicate batch failure
        $errors[] = "Fatal error – entire batch rolled back at row ~$totalProcessed: " . $e->getMessage();
    }

    fclose($file);

    // Close prepared statements
    $stmtListingWithUser->close();
    $stmtListingWithoutUser->close();

    // Prepare session message
    $msg = "Processed: $totalProcessed rows | Success: $totalSuccess | Failed: " . ($totalFail === 'batch' ? 'current batch rolled back' : $totalFail);
    if (!empty($errors)) {
        $_SESSION['import_errors'] = array_slice($errors, 0, 200);
        $msg .= " | See detailed errors";
    }
    $_SESSION['success'] = $msg;
    header("Location: Listing-Management.php");
    exit;
}
?>

<!-- HTML form – update the column description to match the cleaned CSV -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import - HealthDial</title>
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
                <div class="max-w-4xl mx-auto mt-10 bg-white shadow-lg rounded-xl p-8">
                    <h2 class="text-2xl font-bold mb-2">Bulk Import Listings</h2>
                    <p class="text-gray-500 mb-6">Upload cleaned CSV file to import listings</p>

                    <!-- SUCCESS / ERROR MESSAGES -->
                    <?php if(isset($_SESSION['success'])): ?>
                        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if(isset($_SESSION['import_errors'])): ?>
                        <div class="bg-yellow-50 text-yellow-800 p-3 rounded mb-4">
                            <strong>Some rows failed:</strong>
                            <ul class="list-disc ml-5">
                                <?php foreach($_SESSION['import_errors'] as $err) {
                                    echo "<li>".htmlspecialchars($err)."</li>";
                                } unset($_SESSION['import_errors']); ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if(isset($_SESSION['error'])): ?>
                        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- UPLOAD FORM -->
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div class="border-2 border-dashed border-gray-300 p-10 text-center rounded-lg">
                            <input type="file" name="csv_file" accept=".csv" required class="mb-4">
                            <p class="text-gray-500">
                                Cleaned CSV format: 
                                <strong>name, category, city, latitude, longitude, phone, address, open_time, close_time, is_24x7, image</strong>
                            </p>
                        </div>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                            Start Import
                        </button>
                    </form>
                    <div class="mt-8 bg-gray-50  rounded">
                        <a href="sample.csv" download class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Download Sample CSV</a>    
                    </div>
                </div>
            </main>
        </div>
    </div>
    <!-- (modal scripts if needed) -->
</body>
</html>
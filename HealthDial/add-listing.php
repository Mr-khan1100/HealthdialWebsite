<?php
require_once 'connection.inc.php';
requireLogin();
session_start();

// Enable full PHP error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_POST['submit'])) {

    $category_id = $_POST['category_id'];
    $name        = $_POST['name'];
    $description = $_POST['description'];
    $address     = $_POST['address'];

    // Validate latitude/longitude
    $latitude  = !empty($_POST['latitude']) && is_numeric($_POST['latitude']) ? floatval($_POST['latitude']) : 0.0;
    $longitude = !empty($_POST['longitude']) && is_numeric($_POST['longitude']) ? floatval($_POST['longitude']) : 0.0;

    $mobile      = $_POST['mobile'];
    $whatsapp    = $_POST['whatsapp'];
    $email       = $_POST['email'];
    $open_time   = $_POST['open_time'];
    $close_time  = $_POST['close_time'];
    $is_24x7     = isset($_POST['is_24x7']) ? 1 : 0;

    // Insert into listings table
    $stmt = $conn->prepare("
        INSERT INTO listings (
            category_id,
            name,
            description,
            address,
            latitude,
            longitude,
            mobile,
            whatsapp,
            email,
            open_time,
            close_time,
            is_24x7,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "isssddsssssi",
        $category_id,
        $name,
        $description,
        $address,
        $latitude,
        $longitude,
        $mobile,
        $whatsapp,
        $email,
        $open_time,
        $close_time,
        $is_24x7
    );

    if ($stmt->execute()) {
        // Get the newly inserted listing ID
        $listing_id = $stmt->insert_id;

        // Handle uploaded images
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = __DIR__ . '/uploads/listings/';
            
            // Create folder if not exists
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            foreach ($_FILES['images']['name'] as $index => $filename) {
                $tmp_name = $_FILES['images']['tmp_name'][$index];
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $new_name = uniqid('listing_') . '.' . $ext;
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($tmp_name, $destination)) {
                    // Insert into listing_images table
                    $is_primary = ($index === 0) ? 1 : 0; // First image is primary
                    $stmt_img = $conn->prepare("
                        INSERT INTO listing_images (
                            listing_id,
                            image_path,
                            is_primary,
                            created_at,
                            is_external_url
                        ) VALUES (?, ?, ?, NOW(), 0)
                    ");
                    $stmt_img->bind_param("isi", $listing_id, $new_name, $is_primary);
                    $stmt_img->execute();
                    $stmt_img->close();
                }
            }
        }

        $_SESSION['success'] = "Listing inserted successfully!";
        header("Location: Listing-Management.php"); // redirect to listing page
        exit();

    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
        header("Location: add-listing.php"); // stay on form page
        exit();
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listings Management - HealthDial</title>
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
<div class="bg-white rounded-lg shadow p-8 max-w-5xl mx-auto">

    <h2 class="text-2xl font-bold text-gray-800 mb-6">
        Add New Listing
    </h2>

    <form  method="POST" enctype="multipart/form-data" class="space-y-6">


<?php
$categories = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC");
?>


        <!-- Category -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Category *
            </label>
            <select name="category_id" required
    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">

    <option value="">Select Category</option>

    <?php while($cat = $categories->fetch_assoc()): ?>
        <option value="<?php echo $cat['id']; ?>">
            <?php echo htmlspecialchars($cat['name']); ?>
        </option>
    <?php endwhile; ?>

</select>

        </div>

        <!-- Name -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Listing Name *
            </label>
            <input type="text" name="name" required
                placeholder="Enter listing name"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Description -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Description
            </label>
            <textarea name="description" rows="4"
                placeholder="Enter description"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
        </div>

        <!-- Address -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Address *
            </label>
            <textarea name="address" rows="3" required
                placeholder="Enter full address"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
        </div>

        <!-- Latitude & Longitude -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Latitude
                </label>
                <input type="text"id="latitude" name="latitude" placeholder="Latitude"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Longitude
                </label>
                <input type="text" id="longitude" name="longitude" placeholder="Longitude"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500" required>
            </div>
            
                     <button type="button" onclick="getLocation()"
style="background:#0d6efd;color:white;padding:8px 15px;border:none;border-radius:6px;">
Use My Current Location to fetch Latitude and  Longitude  
</button>
        </div>

        <!-- Contact Info -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Mobile Number *
                </label>
                <input type="tel" name="mobile" required
                    placeholder="+1234567890"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    WhatsApp Number
                </label>
                <input type="tel" name="whatsapp"
                    placeholder="+1234567890"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Email Address
                </label>
                <input type="email" name="email"
                    placeholder="example@email.com"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <!-- Opening Hours -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Open Time
                </label>
                <input type="time" name="open_time"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Close Time
                </label>
                <input type="time" name="close_time"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex items-center space-x-2">
                <input type="checkbox" name="is_24x7" value="1"
                    class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label class="text-sm text-gray-700">
                    Open 24x7
                </label>
            </div>
        </div>

        <!-- Status -->
        <!--<div>-->
        <!--    <label class="block text-sm font-medium text-gray-700 mb-1">-->
        <!--        Status *-->
        <!--    </label>-->
        <!--    <select name="status" required-->
        <!--        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">-->
        <!--        <option value="pending">Pending</option>-->
        <!--        <option value="approved">Approved</option>-->
        <!--        <option value="rejected">Rejected</option>-->
        <!--    </select>-->
        <!--</div>-->

        <!-- Images -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Upload Images
            </label>
            <input type="file" name="images[]" multiple
                class="w-full border border-gray-300 rounded-lg px-4 py-2 bg-gray-50">
            <p class="text-xs text-gray-500 mt-1">
                You can select multiple images. First image will be primary.
            </p>
        </div>

        <!-- Buttons -->
        <div class="flex justify-end space-x-4 pt-6 border-t">
            <!--<button type="reset"-->
            <!--    class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">-->
            <!--    Reset-->
            <!--</button>-->
            
   


            <!--<button type="submit"-->
            <!--    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">-->
            <!--    Save Listing-->
            <!--</button>-->
            <button type="submit" name="submit"
    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
    Save Listing
</button>


        </div>

    </form>

</div>

              
            </main>
        </div>
    </div>
<script>
function getLocation() {

    if (!navigator.geolocation) {
        alert("Geolocation is not supported by your browser");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(position) {

            document.getElementById("latitude").value =
                position.coords.latitude;

            document.getElementById("longitude").value =
                position.coords.longitude;

            alert("Location captured successfully ✅");

        },
        function(error) {

            if (error.code == 1)
                alert("Please allow location permission");

            else if (error.code == 2)
                alert("Location unavailable");

            else
                alert("Error getting location");
        }
    );
}
</script>

</body>
</html>
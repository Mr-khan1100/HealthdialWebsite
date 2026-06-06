<?php
require_once 'connection.inc.php';
requireLogin();
session_start();

// Enable full PHP error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// DELETE IMAGE
if(isset($_GET['delete_image'])){

    $img_id = (int)$_GET['delete_image'];

    // Get image path first
    $stmt = $conn->prepare("SELECT image_path, is_external_url 
                            FROM listing_images 
                            WHERE id=?");
    $stmt->bind_param("i", $img_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $img = $result->fetch_assoc();

    if($img){

        // delete file if local image
        if(!$img['is_external_url']){
            $file = "./uploads/listings/".$img['image_path'];
            if(file_exists($file)){
                unlink($file);
            }
        }

        // delete from DB
        $stmt2 = $conn->prepare("DELETE FROM listing_images WHERE id=?");
        $stmt2->bind_param("i", $img_id);
        $stmt2->execute();
    }

    // reload page
header("Location: update-listing.php?edit_id=".$_GET['edit_id']);
exit;
    
}



// Check edit_id in URL
if (!isset($_GET['edit_id']) || !is_numeric($_GET['edit_id'])) {
    $_SESSION['error'] = "Invalid listing ID.";
    header("Location: Listing-Management.php");
    exit();
}

$edit_id = intval($_GET['edit_id']);

// Fetch existing listing
$stmt = $conn->prepare("SELECT * FROM listings WHERE id=?");
$stmt->bind_param("i", $edit_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Listing not found!";
    header("Location: Listing-Management.php");
    exit();
}
$listing = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if (isset($_POST['submit'])) {

    $category_id = $_POST['category_id'];
    $name        = $_POST['name'];
    $description = $_POST['description'];
    $address     = $_POST['address'];
    $latitude    = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : 0.0;
    $longitude   = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : 0.0;
    $mobile      = $_POST['mobile'];
    $whatsapp    = $_POST['whatsapp'];
    $email       = $_POST['email'];
    $open_time   = $_POST['open_time'];
    $close_time  = $_POST['close_time'];
    $is_24x7     = isset($_POST['is_24x7']) ? 1 : 0;

    // Update listing
    $stmt = $conn->prepare("
        UPDATE listings SET
            category_id=?, name=?, description=?, address=?, latitude=?, longitude=?,
            mobile=?, whatsapp=?, email=?, open_time=?, close_time=?, is_24x7=?, updated_at=NOW()
        WHERE id=?
    ");

    $stmt->bind_param(
        "isssddssssiii",
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
        $is_24x7,
        $edit_id
    );

    if ($stmt->execute()) {

        // Handle new uploaded images
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = __DIR__ . '/uploads/listings/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            foreach ($_FILES['images']['name'] as $index => $filename) {
                $tmp_name = $_FILES['images']['tmp_name'][$index];
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $new_name = uniqid('listing_') . '.' . $ext;
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($tmp_name, $destination)) {
                    $is_primary = ($index === 0) ? 1 : 0;
                    $stmt_img = $conn->prepare("
                        INSERT INTO listing_images (listing_id, image_path, is_primary, created_at, is_external_url)
                        VALUES (?, ?, ?, NOW(), 0)
                    ");
                    $stmt_img->bind_param("isi", $edit_id, $new_name, $is_primary);
                    $stmt_img->execute();
                    $stmt_img->close();
                }
            }
        }

        $_SESSION['success'] = "Listing updated successfully!";
        header("Location: Listing-Management.php");
        exit();

    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
        header("Location: update-listing.php?edit_id=" . $edit_id);
        exit();
    }

    $stmt->close();
}

// Fetch existing images
$images = $conn->query("SELECT * FROM listing_images WHERE listing_id={$edit_id} ORDER BY is_primary DESC, id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Listing - HealthDial</title>
    <link rel="stylesheet" href="assets/admin.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100">
<div class="flex h-screen">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php include 'header.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

            <div class="bg-white rounded-lg shadow p-8 max-w-6xl mx-auto">

                <h2 class="text-2xl font-bold text-gray-800 mb-6">Update Listing</h2>

                <!-- Display session messages -->
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 text-green-800 p-3 mb-4 rounded">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 text-red-800 p-3 mb-4 rounded">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-6">

                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select name="category_id" required
                                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Category</option>
                            <?php
                            $categories = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC");
                            while ($cat = $categories->fetch_assoc()):
                                $selected = ($listing['category_id'] == $cat['id']) ? "selected" : "";
                            ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Listing Name *</label>
                        <input type="text" name="name" required value="<?php echo htmlspecialchars($listing['name']); ?>"
                               placeholder="Enter listing name"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="4"
                                  placeholder="Enter description"
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($listing['description']); ?></textarea>
                    </div>

                    <!-- Address -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address *</label>
                        <textarea name="address" rows="3" required
                                  placeholder="Enter full address"
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($listing['address']); ?></textarea>
                    </div>

                    <!-- Latitude & Longitude -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                            <input type="text" id="latitude" name="latitude" required value="<?php echo htmlspecialchars($listing['latitude']); ?>"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                            <input type="text" id="longitude"   name="longitude" required value="<?php echo htmlspecialchars($listing['longitude']); ?>"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                         <button type="button" onclick="getLocation()"
style="background:#0d6efd;color:white;padding:8px 15px;border:none;border-radius:6px;">
Use My Current Location to fetch Latitude and  Longitude  
</button>

                    </div>

                    <!-- Contact Info -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number *</label>
                            <input type="tel" name="mobile" required value="<?php echo htmlspecialchars($listing['mobile']); ?>"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                            <input type="tel" name="whatsapp" value="<?php echo htmlspecialchars($listing['whatsapp']); ?>"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($listing['email']); ?>"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Opening Hours -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Open Time</label>
                            <input type="time" name="open_time" value="<?php echo htmlspecialchars($listing['open_time']); ?>"
                                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        
                       <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Close Time</label>
    <input type="time" name="close_time"
           value="<?php echo htmlspecialchars(substr($listing['close_time'], 0, 5)); ?>"
           class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
</div>

                        
                        <div class="flex items-center space-x-2">
                            <input type="checkbox" name="is_24x7" value="1" <?php echo ($listing['is_24x7'] == 1) ? "checked" : ""; ?>
                                   class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <label class="text-sm text-gray-700">Open 24x7</label>
                        </div>
                    </div>
                    

                    <!-- Existing Images -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Existing Images</label>
                        <div class="flex flex-wrap gap-4">
                            <?php while($img = $images->fetch_assoc()): ?>
                                <div class="relative w-24 h-24 border rounded overflow-hidden">
                                    <?php if($img['is_external_url']): ?>
                                        <img src="<?php echo htmlspecialchars($img['image_path']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <img src="./uploads/listings/<?php echo htmlspecialchars($img['image_path']); ?>" class="w-full h-full object-cover">
                                    <?php endif; ?>
                                    <!-- You can add delete logic later -->
                                    <span class="absolute top-1 right-1 bg-red-600 text-white rounded-full px-1 cursor-pointer delete-image" data-id="<?php echo $img['id']; ?>">×</span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Upload New Images -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload New Images</label>
                        <input type="file" name="images[]" multiple
                               class="w-full border border-gray-300 rounded-lg px-4 py-2 bg-gray-50">
                        <p class="text-xs text-gray-500 mt-1">You can select multiple images. First image will be primary.</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <!--<button type="reset"-->
                        <!--        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">Reset-->
                        <!--</button>-->
                        
<!--                          <button type="button" onclick="getLocation()"-->
<!--style="background:#0d6efd;color:white;padding:8px 15px;border:none;border-radius:6px;">-->
<!--Use My Current Location-->
<!--</button>-->

                        <button type="submit" name="submit"
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update Listing
                        </button>
                    </div>

                </form>
            </div>

        </main>
    </div>
</div>
<script>
$('.delete-image').click(function() {

    const id = $(this).data('id');

    if(confirm('Are you sure you want to delete this image?')) {

        window.location.href =
            'update-listing.php?delete_image=' + id +
            '&edit_id=<?php echo $edit_id; ?>';

    }
});

</script>

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

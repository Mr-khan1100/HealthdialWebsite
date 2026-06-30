<?php
require_once 'connection.inc.php';
requireLogin();
requireAccess('documents');

// Delete document
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $id = $_GET['delete'];
    
    // Get file path first
    $result = $conn->query("SELECT file_path FROM documents WHERE id = $id");
    if($row = $result->fetch_assoc()) {
        $file_path = './Backend/uploads/documents/' . $row['file_path'];
        if(file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    $conn->query("DELETE FROM documents WHERE id = $id");
    $_SESSION['success'] = "Document deleted successfully!";
    header("Location: Documents.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - HealthDial</title>
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
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">Documents Management</h2>
                        <p class="text-gray-600 text-sm mt-1">Manage all uploaded medical documents</p>
                    </div>
                    
                    <div class="p-6">
                        <!-- Filters -->
                        <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <select class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option>All Types</option>
                                <option value="prescription">Prescription</option>
                                <option value="report">Report</option>
                                <option value="bill">Bill</option>
                            </select>
                            
                            <select class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option>All Users</option>
                                <?php
                                $users = $conn->query("SELECT id, name FROM users");
                                while($user = $users->fetch_assoc()):
                                ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo $user['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                            
                            <input type="date" class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            
                            <input type="text" placeholder="Search..." class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <!-- Documents Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php
                            $result = $conn->query("
                                SELECT d.*, u.name as user_name 
                                FROM documents d 
                                JOIN users u ON d.user_id = u.id 
                                ORDER BY d.uploaded_at DESC
                            ");
                            
                            while($row = $result->fetch_assoc()):
                                $icon_color = [
                                    'prescription' => 'bg-green-100 text-green-600',
                                    'report' => 'bg-blue-100 text-blue-600',
                                    'bill' => 'bg-yellow-100 text-yellow-600'
                                ];
                                
                                $icon = [
                                    'prescription' => 'fas fa-prescription',
                                    'report' => 'fas fa-file-medical',
                                    'bill' => 'fas fa-receipt'
                                ];
                            ?>
                            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex items-center">
                                        <div class=" p-2 rounded-lg mr-3">
                                            <i class="text-xl"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium capitalize"><?php echo $row['document_type']; ?></p>
                                            <p class="text-sm text-gray-500"><?php echo date('d M Y', strtotime($row['uploaded_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="./Backend/uploads/documents/<?php echo $row['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800" title="View">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="text-red-600 hover:text-red-800" title="Delete" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="text-sm text-gray-500 mb-1">Uploaded By</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($row['user_name']); ?></p>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500 truncate">
                                        <?php echo $row['file_path']; ?>
                                    </span>
                                    <a href="./Backend/uploads/documents/<?php echo $row['file_path']; ?>" download class="text-blue-600 hover:text-blue-800 text-sm">
                                        <i class="fas fa-download mr-1"></i> Download
                                    </a>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <?php if($result->num_rows == 0): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-file-medical text-4xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700 mb-2">No Documents Found</h3>
                            <p class="text-gray-500">No medical documents have been uploaded yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
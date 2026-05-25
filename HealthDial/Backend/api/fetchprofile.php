<?php
require_once '../config.php'; // Your existing connection file

// Ensure we're handling JSON

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    fetchUserProfile();
} else {
    sendResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

function fetchUserProfile() {
    global $conn;
    
    // Authenticate user
    $user = authenticateUser();
    
    if (!$user) {
        sendResponse(['success' => false, 'error' => 'Authentication required'], 401);
        return;
    }
    
    $user_id = $user['id'];
    
    try {
        // Fetch user data with document count
        $query = "SELECT 
    u.id, 
    u.name, 
    u.email, 
    u.mobile, 
    u.address,
    u.state,
    u.diseases,
    u.profile_image,
    u.created_at,
    COALESCE(doc_count.document_count, 0) AS documents_count,
    COALESCE(med_count.medications_count, 0) AS medi
FROM users u
LEFT JOIN (
    SELECT user_id, COUNT(*) AS document_count 
    FROM documents
    GROUP BY user_id
) doc_count ON u.id = doc_count.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS medications_count
    FROM medications
    GROUP BY user_id
) med_count ON u.id = med_count.user_id
WHERE u.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i",  $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $userData = $result->fetch_assoc();
            
            // Format the response
            $profileData = [
                'success' => true,
                'profile' => [
                    'id' => $userData['id'],
                    'name' => $userData['name'] ?? '',
                    'email' => $userData['email'] ?? '',
                    'mobile' => $userData['mobile'] ?? 'Not available',
                    'address' => $userData['address'] ?? 'Not set',
                    'state' => $userData['state'] ?? '',
                    'diseases' => $userData['diseases'] ?? 'None',
                    'profile_image' => $userData['profile_image'] ? $userData['profile_image'] : null,
                    'documents_count' => (int)$userData['documents_count'],
                    'created_at' => $userData['created_at'],
                    'medi' => $userData['medi']
                ]
            ];
            
            sendResponse($profileData, 200);
        } else {
            sendResponse(['success' => false, 'error' => 'User not found'], 404);
        }
        
    } catch (Exception $e) {
        error_log("Profile fetch error: " . $e->getMessage());
        sendResponse(['success' => false, 'error' => 'Server error: ' . $e->getMessage()], 500);
    }
}
?>
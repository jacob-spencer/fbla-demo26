<?php
require_once '../functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isLoggedIn()) {
    sendErrorResponse('Authentication required', 401);
}

$user = getCurrentUser();
$db = Database::getInstance();

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['business_id']) || !isset($input['action'])) {
        sendErrorResponse('Invalid request data');
    }
    
    $businessId = sanitizeInput($input['business_id']);
    $action = sanitizeInput($input['action']);
    
    // Verify business exists
    $business = $db->fetch("SELECT id FROM businesses WHERE id = ?", [$businessId]);
    if (!$business) {
        sendErrorResponse('Business not found', 404);
    }
    
    try {
        if ($action === 'toggle' || $action === 'add') {
            // Check if bookmark already exists
            $bookmark = $db->fetch(
                "SELECT id FROM bookmarks WHERE user_id = ? AND business_id = ?",
                [$user['id'], $businessId]
            );
            
            if ($bookmark && $action === 'toggle') {
                // Remove bookmark
                $db->query(
                    "DELETE FROM bookmarks WHERE user_id = ? AND business_id = ?",
                    [$user['id'], $businessId]
                );
                sendJsonResponse(['success' => true, 'bookmarked' => false]);
            } elseif (!$bookmark) {
                // Add bookmark
                $bookmarkId = generateUuid();
                $db->query(
                    "INSERT INTO bookmarks (id, user_id, business_id, created_at) VALUES (?, ?, ?, NOW())",
                    [$bookmarkId, $user['id'], $businessId]
                );
                sendJsonResponse(['success' => true, 'bookmarked' => true]);
            } else {
                sendJsonResponse(['success' => true, 'bookmarked' => true, 'message' => 'Already bookmarked']);
            }
        } elseif ($action === 'remove') {
            // Remove bookmark
            $db->query(
                "DELETE FROM bookmarks WHERE user_id = ? AND business_id = ?",
                [$user['id'], $businessId]
            );
            sendJsonResponse(['success' => true, 'bookmarked' => false]);
        } else {
            sendErrorResponse('Invalid action');
        }
    } catch (Exception $e) {
        sendErrorResponse('Database error', 500);
    }
} elseif (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get bookmark status for a business
    if (isset($_GET['business_id'])) {
        $businessId = sanitizeInput($_GET['business_id']);
        
        $bookmark = $db->fetch(
            "SELECT id FROM bookmarks WHERE user_id = ? AND business_id = ?",
            [$user['id'], $businessId]
        );
        
        sendJsonResponse(['bookmarked' => (bool)$bookmark]);
    } else {
        // Get all user bookmarks
        $bookmarks = $db->fetchAll(
            "SELECT b.*, c.name as category_name FROM bookmarks bk 
             LEFT JOIN businesses b ON bk.business_id = b.id 
             LEFT JOIN categories c ON b.category_id = c.id 
             WHERE bk.user_id = ? ORDER BY bk.created_at DESC",
            [$user['id']]
        );
        
        sendJsonResponse($bookmarks);
    }
} else {
    sendErrorResponse('Method not allowed', 405);
}
?>
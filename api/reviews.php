<?php
require_once '../functions.php';

header('Content-Type: application/json');

$db = Database::getInstance();

$method = $_SERVER['REQUEST_METHOD'];

// If the form is sending a hidden _method field (e.g., _method=PUT)
// I'm too lazy to rewrite the review system to support editing so 
// this is what we get. The _method field is a hidden text input field
// that tells this how to interpret the request. Every request is 
// defaulted to POST, but if the field says otherwise, do that instead.
// This was convenient for sending PUT and DELETE from within the
// review modal. 
if (isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

if ($method === 'POST') {
    if (!isLoggedIn()) {
        if (isset($_POST['business_id'])) {
            header('Location: /auth.php');
            exit;
        }
        sendErrorResponse('Authentication required', 401);
    }
    
    $user = getCurrentUser();
    
    if (!empty($_POST)) {
        $businessId = isset($_POST['business_id']) ? sanitizeInput($_POST['business_id']) : '';
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment = isset($_POST['comment']) ? sanitizeInput($_POST['comment']) : '';
        $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['business_id']) || !isset($input['rating'])) {
            sendErrorResponse('Invalid request data');
        }
        $businessId = sanitizeInput($input['business_id']);
        $rating = (int)$input['rating'];
        $comment = isset($input['comment']) ? sanitizeInput($input['comment']) : '';
        $email = isset($input['email']) ? sanitizeInput($input['email']) : '';
    }
    
    if (!$businessId || !$rating) {
        if (isset($_POST['business_id'])) {
            header('Location: /business.php?id=' . urlencode($businessId) . '&error=Invalid+review+data');
            exit;
        }
        sendErrorResponse('Invalid request data');
    }
    
    if ($rating < 1 || $rating > 5) {
        sendErrorResponse('Rating must be between 1 and 5');
    }
    
    $business = $db->fetch("SELECT id, name FROM businesses WHERE id = ?", [$businessId]);
    if (!$business) {
        sendErrorResponse('Business not found', 404);
    }
    
    /* No longer necessary, all checks happen on clientside
    // Check if user already reviewed this business
    $existingReview = $db->fetch(
        "SELECT id FROM reviews WHERE user_id = ? AND business_id = ?",
        [$user['id'], $businessId]
    );
    
    if ($existingReview) {
        sendErrorResponse('You have already reviewed this business');
    }
    */ 
    
    try {
        $db->beginTransaction();
        
        // Create review
        $reviewId = generateUuid();
        $verificationCode = bin2hex(random_bytes(3)); 
        $verificationEmail = $email ?: $user['email'];
        
        $db->query("
            INSERT INTO reviews (id, business_id, user_id, rating, comment, is_verified, verification_email, verification_code, verification_expires_at, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())
        ", [$reviewId, $businessId, $user['id'], $rating, $comment, $verificationEmail, $verificationCode]);
        
        $avgResult = $db->fetch("
            SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
            FROM reviews 
            WHERE business_id = ? AND is_verified = 1
        ", [$businessId]);
        
        if ($avgResult && $avgResult['total_reviews'] > 0) {
            $averageRating = round($avgResult['avg_rating'], 2);
            $totalReviews = $avgResult['total_reviews'];
            
            $db->query("
                UPDATE businesses 
                SET average_rating = ?, total_reviews = ?, updated_at = NOW()
                WHERE id = ?
            ", [$averageRating, $totalReviews, $businessId]);
            
            error_log("Updated business {$businessId}: {$averageRating} stars ({$totalReviews} reviews)");
        } else {
            $db->query("
                UPDATE businesses 
                SET average_rating = 0, total_reviews = 0, updated_at = NOW()
                WHERE id = ?
            ", [$businessId]);
            
            error_log("No verified reviews for business {$businessId}, set to 0");
        }
        
        $db->commit();
        
        // Send verification email
        // Need to integrate CAPTCHA here
        if (isset($_POST['business_id'])) {
            header('Location: /business.php?id=' . urlencode($businessId) . '&success=Review+submitted+successfully');
            exit;
        } else {
            sendJsonResponse([
                'success' => true,
                'message' => 'Review submitted! Please check your email for verification.',
                'review_id' => $reviewId
            ]);
        }
        
    } catch (Exception $e) {
        $db->rollback();
        if (isset($_POST['business_id'])) {
            header('Location: /business.php?id=' . urlencode($businessId) . '&error=Failed+to+submit+review');
            exit;
        }
        sendErrorResponse('Failed to submit review', 500);
    }
    
} elseif ($method === 'GET') {
    // Get reviews for a business
    if (isset($_GET['business_id'])) {
        $businessId = sanitizeInput($_GET['business_id']);
        
        $reviews = $db->fetchAll("
            SELECT r.*, u.first_name, u.last_name, u.profile_image_url 
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.business_id = ?
            ORDER BY r.created_at DESC
        ", [$businessId]);
        
        sendJsonResponse($reviews);
    } else {
        sendErrorResponse('Business ID required');
    }
    
} elseif ($method === 'DELETE') {
    if (!isLoggedIn()) {
        sendErrorResponse('Authentication required', 401);
    }

    $user = getCurrentUser();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['review_id'])) {
        sendErrorResponse('Review ID required');
    }

    $reviewId = sanitizeInput($input['review_id']);

    // Check that review exists and belongs to user
    $review = $db->fetch("SELECT * FROM reviews WHERE id = ?", [$reviewId]);
    if (!$review) {
        sendErrorResponse('Review not found', 404);
    }
    if ($review['user_id'] !== $user['id']) {
        sendErrorResponse('Not authorized to delete this review', 403);
    }

    try {
        $db->beginTransaction();

        // Delete review
        $db->query("DELETE FROM reviews WHERE id = ?", [$reviewId]);

        // Update business averages
        $avgResult = $db->fetch("
            SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
            FROM reviews 
            WHERE business_id = ? AND is_verified = 1
        ", [$review['business_id']]);

        if ($avgResult && $avgResult['total_reviews'] > 0) {
            $averageRating = round($avgResult['avg_rating'], 2);
            $totalReviews = $avgResult['total_reviews'];
        } else {
            $averageRating = 0;
            $totalReviews = 0;
        }

        $db->query("
            UPDATE businesses 
            SET average_rating = ?, total_reviews = ?, updated_at = NOW()
            WHERE id = ?
        ", [$averageRating, $totalReviews, $review['business_id']]);

        $db->commit();

        sendJsonResponse([
            'success' => true,
            'message' => 'Review deleted successfully'
        ]);
    } catch (Exception $e) {
        $db->rollback();
        sendErrorResponse('Failed to delete review', 500);
    }
} elseif ($method === 'PUT') {
    if (!isLoggedIn()) sendErrorResponse('Authentication required', 401);
    
    $user = getCurrentUser();

    // Use POST if it's a form, otherwise read JSON
    $input = [];
    if (!empty($_POST)) {
        // Remove _method from form data
        $input = $_POST;
        unset($input['_method']);
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
    }

    // Validate
    if (empty($input['review_id']) || empty($input['rating']) || !isset($input['comment'])) {
        sendErrorResponse('Review ID, rating, and comment are required');
    }

    $reviewId = sanitizeInput($input['review_id']);
    $rating   = (int)$input['rating'];
    $comment  = sanitizeInput($input['comment']);

    // Fetch review
    $review = $db->fetch("SELECT * FROM reviews WHERE id = ?", [$reviewId]);
    if (!$review) sendErrorResponse('Review not found', 404);
    if ($review['user_id'] !== $user['id']) sendErrorResponse('Not authorized to edit this review', 403);

    try {
        $db->beginTransaction();

        $db->query("
            UPDATE reviews 
            SET rating = ?, comment = ?, updated_at = NOW()
            WHERE id = ?
        ", [$rating, $comment, $reviewId]);

        // Update business averages
        $avgResult = $db->fetch("
            SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
            FROM reviews 
            WHERE business_id = ? AND is_verified = 1
        ", [$review['business_id']]);

        $averageRating = $avgResult && $avgResult['total_reviews'] > 0 ? round($avgResult['avg_rating'], 2) : 0;
        $totalReviews  = $avgResult && $avgResult['total_reviews'] > 0 ? $avgResult['total_reviews'] : 0;

        $db->query("
            UPDATE businesses 
            SET average_rating = ?, total_reviews = ?, updated_at = NOW()
            WHERE id = ?
        ", [$averageRating, $totalReviews, $review['business_id']]);

        $db->commit();

        sendJsonResponse([
            'success' => true,
            'message' => 'Review updated successfully'
        ]);
    } catch (Exception $e) {
        $db->rollback();
        error_log($e->getMessage()); // log actual DB error for debugging
        sendErrorResponse('Failed to update review' . $e->getMessage(), 500);
    }
} else {
    sendErrorResponse('Method not allowed', 405);
}
?>
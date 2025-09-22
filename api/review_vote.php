<?php
ob_start();
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../functions.php';
$db = Database::getInstance();

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$reviewId = isset($data['review_id']) ? $data['review_id'] : 0;
$vote = isset($data['vote']) ? intval($data['vote']) : 0; // expect +1 or -1

if (!in_array($vote, [1, -1])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid vote value']);
    exit;
}

// Check that review exists
$review = $db->fetch("SELECT id FROM reviews WHERE id = ?", [$reviewId]);
if (!$review) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Review not found']);
    exit;
}

// (Optional) Check if user already voted this review
$existing = $db->fetch("SELECT vote FROM review_votes WHERE review_id = ? AND user_id = ?", [$reviewId, $userId]);
if ($existing) {
    if ($existing['vote'] === $vote) {
        // Same vote again: maybe undo? For now, return no-op
        echo json_encode(['success' => true, 'upvotes' => getUpvotes($reviewId)]);
        exit;
    } else {
        // Change vote: update
        $db->query(
            "UPDATE review_votes SET vote = ?, created_at = NOW() WHERE review_id = ? AND user_id = ?",
            [$vote, $reviewId, $userId]
        );
    }
} else {
    // Insert new vote
    $db->query(
      "INSERT INTO review_votes (review_id, user_id, vote) VALUES (?, ?, ?)",
      [$reviewId, $userId, $vote]
    );
}

// Recalculate upvotes sum for that review
$upvotes = getUpvotes($reviewId);

// Update reviews table if you want to keep denormalized count
$db->query(
    "UPDATE reviews SET upvotes = ? WHERE id = ?",
    [$upvotes, $reviewId]
);

echo json_encode(['success' => true, 'upvotes' => $upvotes]);
exit;


// helper function
function getUpvotes($rid) {
    global $db;
    $row = $db->fetch(
      "SELECT COALESCE(SUM(vote), 0) AS total FROM review_votes WHERE review_id = ?",
      [$rid]
    );
    return intval($row['total']);
}

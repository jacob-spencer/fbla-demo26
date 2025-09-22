<?php
require_once 'functions.php';

$db = Database::getInstance();
$businessId = isset($_GET['business_id']) ? sanitizeInput($_GET['business_id']) : '';

if ($businessId) {
    // Get business URL
    $business = $db->fetch("SELECT website FROM businesses WHERE id = ?", [$businessId]);

    if ($business && $business['website']) {
        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $analyticsId = uniqid();

        // Update existing conversion count
        $db->query("
            UPDATE analytics 
            SET conversions = conversions + 1, updated_at = NOW()
            WHERE business_id = ? AND period_start = ?
        ", [$businessId, $startOfWeek]);

        if ($db->rowCount() === 0) {
            // Insert new row if doesn't exist
            $db->query("
                INSERT INTO analytics (id, business_id, period_start, page_views, conversions) 
                VALUES (?, ?, ?, 0, 1)
            ", [$analyticsId, $businessId, $startOfWeek]);
        }

        // Redirect to actual website
        header("Location: " . $business['website']);
        exit;
    }
}

// Fallback
header("Location: /");
exit;

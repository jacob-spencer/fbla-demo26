<?php
require_once '../functions.php';

$db = Database::getInstance();

$data = json_decode(file_get_contents('php://input'), true);

$businessId = $data['business_id'] ?? null;
$eventType = $data['event_type'] ?? null;

if (!$businessId || !$eventType) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing data']);
    exit;
}

$eventId = uniqid();
$sessionId = session_id();
$userId = isLoggedIn() ? $_SESSION['user_id'] : null;
$pageUrl = $_SERVER['HTTP_REFERER'] ?? '';
$referrer = $_SERVER['HTTP_REFERER'] ?? null;
$deviceType = preg_match('/mobile/i', $_SERVER['HTTP_USER_AGENT']) ? 'mobile' : 'desktop';
$browser = getBrowser();
$os = getOS();

$db->query("
    INSERT INTO analytics (
        id, business_id, event_type, event_timestamp, session_id,
        user_id, page_url, referrer_url, device_type, browser, os,
        country, city, metadata
    ) VALUES (
        ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
", [
    $eventId, $businessId, $eventType, $sessionId,
    $userId, $pageUrl, $referrer, $deviceType, $browser, $os,
    null, null, null // no geolocation here
]);

echo json_encode(['success' => true]);

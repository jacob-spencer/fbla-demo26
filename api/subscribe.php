<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '/home/u414139450/domains/mainstreet.lt/public_html/config.php'; // Your database config

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['subscription'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid subscription data']);
    exit;
}

$subscription = $data['subscription'];
$endpoint = $subscription['endpoint'];
$keys = $subscription['keys'];
$publicKey = $keys['p256dh'];
$authToken = $keys['auth'];
$userId = $_SESSION['user_id'] ?? null; // If user is logged in

try {
    $pdo = new PDO(
    "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if subscription already exists
    $stmt = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmt->execute([$endpoint]);
    
    if ($stmt->rowCount() === 0) {
        // Insert new subscription
        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, public_key, auth_token) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $endpoint, $publicKey, $authToken]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
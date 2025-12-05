<?php
require_once 'config.php';
require_once 'functions.php';
$db = Database::getInstance();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'view' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $business = $db->fetch("
        SELECT 
            b.id,
            b.name,
            b.description,
            c.name AS category_name
        FROM businesses b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.id = ?
        LIMIT 1
    ", [$id]);

    if ($business) {
        echo json_encode($business);
    } else {
        echo json_encode(['error' => 'Business not found']);
    }
    exit;
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $db->query("
        UPDATE businesses 
        SET name = ?, description = ? 
        WHERE id = ?
    ", [$_POST['name'], $_POST['description'], $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $db->query("DELETE FROM businesses WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);

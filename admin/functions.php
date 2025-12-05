<?php
require_once 'database.php';

function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = Database::getInstance();
    return $db->fetch(
        "SELECT * FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
}

function getBrowser() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (strpos($ua, 'Firefox') !== false) return 'Firefox';
    if (strpos($ua, 'Chrome') !== false) return 'Chrome';
    if (strpos($ua, 'Safari') !== false) return 'Safari';
    if (strpos($ua, 'Edge') !== false) return 'Edge';
    return 'Other';
}

function getOS() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/windows/i', $ua)) return 'Windows';
    if (preg_match('/macintosh|mac os x/i', $ua)) return 'Mac';
    if (preg_match('/linux/i', $ua)) return 'Linux';
    if (preg_match('/iphone|ipad|ios/i', $ua)) return 'iOS';
    if (preg_match('/android/i', $ua)) return 'Android';
    return 'Other';
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: /auth.php');
        exit;
    }
}

function requireBusinessOwner() {
    $user = getCurrentUser();
    if (!$user || !$user['is_business_owner']) {
        http_response_code(403);
        echo json_encode(['error' => 'Business owner access required']);
        exit;
    }
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function sanitizeInput($data) {
    if ($data === null || $data === '') {
        return '';
    }
    $trimmed = trim($data);
    if ($trimmed === '') {
        return '';
    }
    return htmlspecialchars(strip_tags($trimmed));
}

function formatTime($time) {
    return date('g:i A', strtotime($time));
}

function renderStars($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '<span class="text-yellow-400">★</span>';
        } else {
            $stars .= '<span class="text-gray-300">★</span>';
        }
    }
    return $stars;
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    if ($time < 31536000) return floor($time/2592000) . 'mo ago';
    return floor($time/31536000) . 'y ago';
}

function sendJsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sendErrorResponse($message, $status = 400) {
    sendJsonResponse(['error' => $message], $status);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function getBusinessHours($openingHours) {
    if (!$openingHours) return [];
    
    $hours = json_decode($openingHours, true);
    $formatted = [];
    
    foreach ($hours as $day => $dayHours) {
        if (isset($dayHours['closed']) && $dayHours['closed']) {
            $formatted[] = [
                'day' => ucfirst($day),
                'hours' => 'Closed'
            ];
        } else {
            $formatted[] = [
                'day' => ucfirst($day),
                'hours' => formatTime($dayHours['open']) . ' - ' . formatTime($dayHours['close'])
            ];
        }
    }
    
    return $formatted;
}

function lookupTimezoneFromLatLong($lat, $lng) {
    try {
        // Create a DateTime object at UTC with the coordinates
        $timestamp = time();
        $location = new DateTimeImmutable("now", new DateTimeZone("UTC"));

        // Try to guess timezone by offset comparison
        $timezones = DateTimeZone::listIdentifiers();
        $targetOffset = (int) round($lng / 15) * 3600; // rough estimate

        foreach ($timezones as $tz) {
            $tzObj = new DateTimeZone($tz);
            $offset = $tzObj->getOffset($location);
            if ($offset === $targetOffset) {
                return $tz;
            }
        }

        // fallback: return UTC
        return "UTC";
    } catch (Exception $e) {
        return "UTC";
    }
}

function isBusinessOpen(string $openingHoursJson, string $timezone = 'UTC'): bool {
    $hours = json_decode($openingHoursJson, true);
    if (!$hours || !is_array($hours)) return false;

    try {
        $tz = new DateTimeZone($timezone);
        $now = new DateTime('now', $tz);
    } catch (Exception $e) {
        $now = new DateTime('now', new DateTimeZone('UTC'));
    }

    $day = $now->format('l'); // Monday, Tuesday, etc.

    if (!isset($hours[$day])) return false;

    $openTime = $hours[$day]['open'] ?? null;
    $closeTime = $hours[$day]['close'] ?? null;

    if (!$openTime || !$closeTime) return false;

    try {
        $open = DateTime::createFromFormat('H:i', $openTime, $tz);
        $close = DateTime::createFromFormat('H:i', $closeTime, $tz);

        if (!$open || !$close) return false;

        if ($close <= $open) {
            $close->modify('+1 day');
        }

        return $now >= $open && $now <= $close;
    } catch (Exception $e) {
        return false;
    }
}

function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}



?>
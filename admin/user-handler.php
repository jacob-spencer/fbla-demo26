<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require '/home/u414139450/vendor/autoload.php';
require_once 'config.php';
require_once 'functions.php';

$db = Database::getInstance();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    if ($action === 'list') {
        $users = $db->fetchAll("SELECT id, username, email, first_name, last_name, is_business_owner, is_banned, profile_image_url, bio, location, created_at FROM users ORDER BY created_at DESC LIMIT 1000");
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }

    if ($action === 'view') {
        $id = $_GET['id'] ?? null;
        if (!$id) throw new Exception('Missing id');
        $u = $db->fetch("SELECT id, username, email, first_name, last_name, profile_image_url, bio, location, is_business_owner, is_banned, created_at FROM users WHERE id = ? LIMIT 1", [$id]);
        if (!$u) throw new Exception('User not found');
        echo json_encode(['success' => true, 'user' => $u]);
        exit;
    }

    if ($action === 'edit') {
        $id = $_POST['id'] ?? null;
        if (!$id) throw new Exception('Missing id');
        // Basic allowed fields (don't allow password change here)
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? null);
        $last_name = trim($_POST['last_name'] ?? null);
        $is_business_owner = isset($_POST['is_business_owner']) ? (bool)$_POST['is_business_owner'] : false;

        // Basic validation
        if (empty($username) || empty($email)) throw new Exception('Username and email are required');

        // Update (using your Database wrapper execute method)
        $ok = $db->query(
            "UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, is_business_owner = ?, updated_at = NOW() WHERE id = ?",
            [$username, $email, $first_name, $last_name, $is_business_owner ? 't' : 'f', $id]
        );

        if (!$ok) throw new Exception('Failed to update user (maybe duplicate username/email)');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = $_GET['id'] ?? $_POST['id'] ?? null;
        if (!$id) throw new Exception('Missing id');
        $ok = $db->query("DELETE FROM users WHERE id = ?", [$id]);
        if (!$ok) throw new Exception('Failed to delete user');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'ban') {
        $id = $_POST['id'] ?? null;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 0; // days; 0 = permanent
        $reason = trim($_POST['reason'] ?? 'No reason provided');
        if (!$id) throw new Exception('Missing id');

        // Mark user as banned
        $ok = $db->query("UPDATE users SET is_banned = TRUE, updated_at = NOW() WHERE id = ?", [$id]);
        if (!$ok) throw new Exception('Failed to ban user');

        // Optionally log the ban in a bans table (not required but recommended)
        $expires_at = $length > 0 ? date('Y-m-d H:i:s', strtotime("+{$length} days")) : null;

        // Get user details for email
        $user = $db->fetch("SELECT email, username FROM users WHERE id = ? LIMIT 1", [$id]);
        if ($user && !empty($user['email'])) {
            $to = $user['email'];
            $subject = "You have been banned from MainStreet";
            $lengthText = $length === 0 ? 'Permanent' : ("{$length} day(s), until {$expires_at}");
            $body = "Hello " . ($user['username'] ?? '') . ",\n\n"
                  . "You have been banned from MainStreet (mainstreet.lt).\n\n"
                  . "Length: {$lengthText}\n"
                  . "Reason: {$reason}\n\n"
                  . "If you believe this was a mistake, please reply to bans@mainstreet.lt.\n\n"
                  . "Regards,\nMainStreet Team";

            // Try PHPMailer if available, else fallback to mail()
            $sent = false;
            if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                try {
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    // If your environment requires SMTP credentials set them here.
                    // For local sendmail fallback:
                    $mail->setFrom('bans@mainstreet.lt', 'MainStreet Bans');
                    $mail->addAddress($to, $user['username'] ?? '');
                    $mail->Subject = $subject;
                    $mail->Body = $body;
                    $mail->send();
                    $sent = true;
                } catch (\Exception $e) {
                    // fallback to mail(), but continue
                    error_log('PHPMailer error sending ban email: ' . $e->getMessage());
                }
            }

            if (!$sent) {
                // fallback to standard mail
                $headers = "From: MainStreet Bans <bans@mainstreet.lt>\r\n";
                $sent = mail($to, $subject, $body, $headers);
            }
            // we do not fail action if email doesn't send; we only log/send attempt
            if (!$sent) error_log("Failed to send ban email to {$to}");
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'unban') {
        $id = $_GET['id'] ?? $_POST['id'] ?? null;
        if (!$id) throw new Exception('Missing id');
        $ok = $db->query("UPDATE users SET is_banned = FALSE, updated_at = NOW() WHERE id = ?", [$id]);
        if (!$ok) throw new Exception('Failed to unban user');
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Exception $ex) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
    exit;
}

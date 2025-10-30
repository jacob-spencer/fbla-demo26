<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require '/home/u414139450/vendor/autoload.php';
require_once 'config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$successCount = $failureCount = 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $title = htmlspecialchars($_POST['title'] ?? '');
    $body = htmlspecialchars($_POST['body'] ?? '');
    $icon = htmlspecialchars($_POST['icon'] ?? '');
    $badge = htmlspecialchars($_POST['badge'] ?? '');
    $url = htmlspecialchars($_POST['url'] ?? '');

    if ($title && $body) {
        try {
            $auth = [
                'VAPID' => [
                    'subject' => 'mailto:no-reply@mainstreet.lt',
                    'publicKey' => 'BPqYynI8maPSJvcLLTWpvT25PQxW_PIh-8_ZazoQThczaVoWedvXCJuEPq8ZmmZmT_XipT8bbNfbTsowNP-k7BI',
                    'privateKey' => 'qa3FMC6KWny3W21jksNsjjrb85EgJIDGY2ZIiV3N190',
                ],
            ];

            $webPush = new WebPush($auth);

            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->query("SELECT * FROM push_subscriptions");
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($subscriptions)) {
                $message = "No subscriptions found.";
            } else {
                $payload = json_encode([
                    'title' => $title,
                    'body' => $body,
                    'icon' => $icon ?: '/icons/icon-192x192.png',
                    'badge' => $badge ?: '/icons/badge-72x72.png',
                    'data' => [
                        'url' => $url ?: 'https://mainstreet.lt/deals',
                        'timestamp' => time()
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                foreach ($subscriptions as $sub) {
                    try {
                        $subscription = Subscription::create([
                            'endpoint' => $sub['endpoint'],
                            'keys' => [
                                'p256dh' => $sub['public_key'],
                                'auth' => $sub['auth_token'],
                            ],
                        ]);

                        $report = $webPush->sendOneNotification($subscription, $payload);

                        if ($report->isSuccess()) {
                            $successCount++;
                        } else {
                            $failureCount++;
                            $response = $report->getResponse();
                            if ($response && $response->getStatusCode() === 410) {
                                $deleteStmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE id = ?");
                                $deleteStmt->execute([$sub['id']]);
                            }
                        }
                    } catch (Exception $e) {
                        $failureCount++;
                        error_log("Failed to send notification to {$sub['endpoint']}: " . $e->getMessage());
                    }
                }

                $message = "✅ Notifications sent: $successCount | ❌ Failed: $failureCount";
            }
        } catch (Throwable $e) {
            $message = "Error: " . htmlspecialchars($e->getMessage());
            error_log($e);
        }
    } else {
        $message = "Title and body are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Push Notification</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 50px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { text-align: center; margin-bottom: 20px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input[type="text"], textarea { width: 100%; padding: 10px; margin-top: 5px; border-radius: 4px; border: 1px solid #ccc; }
        button { margin-top: 20px; padding: 12px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; width: 100%; font-size: 16px; }
        button:hover { background: #45a049; }
        .message { margin-top: 20px; padding: 12px; background: #e7f3fe; border-left: 5px solid #2196F3; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Send Push Notification</h1>
        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>
        <label for="preset">Preset</label>
        
        <select id="preset" name="preset">
            <option value="none" selected>None</option>
            <option value="deal_alert">Deal Alert</option>
            <option value="discover_alert">Discover Alert</option>
            <option value="map_alert">Map Alert</option>
        </select>
        <script>
          // Build this feature out. Need JSON type notification data, to be selected on option.value
          // Definitely not a priority, but it needs done
          
          const selectElement = document.getElementById("preset");
          selectElement.addEventListener("change", function(event) {
            alert(`Loading preset ${event.target.value}`);
            // Integrate autofill
          });
        </script>
            
        <form method="post">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" placeholder="Notification Title" required>

            <label for="body">Body</label>
            <textarea id="body" name="body" rows="3" placeholder="Notification Body" required></textarea>

            <label for="icon">Icon URL</label>
            <input type="text" id="icon" name="icon" placeholder="/icons/icon-192x192.png">

            <label for="badge">Badge URL</label>
            <input type="text" id="badge" name="badge" placeholder="/icons/badge-72x72.png">

            <label for="url">Click URL</label>
            <input type="text" id="url" name="url" placeholder="https://example.com">

            <button type="submit">Send Notification</button>
        </form>
    </div>
</body>
</html>

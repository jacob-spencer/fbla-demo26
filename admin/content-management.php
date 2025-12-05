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
    
    <link href="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    
    <button data-drawer-target="default-sidebar" data-drawer-toggle="default-sidebar" aria-controls="default-sidebar" type="button" class="inline-flex items-center p-2 mt-2 ms-3 text-sm text-gray-500 rounded-lg sm:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
       <span class="sr-only">Open sidebar</span>
       <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
       <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
       </svg>
    </button>
    
    <aside id="default-sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0" aria-label="Sidebar">
       <div class="h-full px-3 py-4 overflow-y-auto bg-gray-50 dark:bg-gray-800">
          <ul class="space-y-2 font-medium">
             <li>
                <a href="index.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                   <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house-icon lucide-house"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                   <span class="flex-1 ms-3 whitespace-nowrap">Dashboard</span>
                </a>
             </li>
             <li>
                <a href="notifications.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                   <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bell-icon lucide-bell"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/></svg>
                   <span class="flex-1 ms-3 whitespace-nowrap">Notifications</span>
                </a>
             </li>
             <li>
                <a href="user-management.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                   <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                   <span class="flex-1 ms-3 whitespace-nowrap">User Management</span>
                </a>
             </li>
             <li>
                <a href="content-management.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                   <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-blocks-icon lucide-blocks"><path d="M10 22V7a1 1 0 0 0-1-1H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5a1 1 0 0 0-1-1H2"/><rect x="14" y="2" width="8" height="8" rx="1"/></svg>
                   <span class="flex-1 ms-3 whitespace-nowrap">Content Management</span>
                </a>
             </li>
             <li>
                <a href="business-management.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                   <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2-icon lucide-building-2"><path d="M10 12h4"/><path d="M10 8h4"/><path d="M14 21v-3a2 2 0 0 0-4 0v3"/><path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"/><path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/></svg>
                   <span class="flex-1 ms-3 whitespace-nowrap">Business Management</span>
                </a>
             </li>
             <li>
                <a href="security.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                   <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock-icon lucide-lock"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                   <span class="flex-1 ms-3 whitespace-nowrap">Security</span>
                </a>
             </li>
             <li>
                <a href="settings.php" class="flex items-center p-2 text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                   <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings-icon lucide-settings"><path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/></svg>
                   <span class="flex-1 ms-3 whitespace-nowrap">Settings</span>
                </a>
             </li>
          </ul>
       </div>
    </aside>
    
    <!--CONTENT-->
    <div class="p-4 sm:ml-64">
        <h1>Content Management</h1>
        
    </div>
</body>
</html>

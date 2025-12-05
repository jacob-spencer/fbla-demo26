<?php
// /api/ai-chat.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// --- basic POST validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// --- Extract variables ---
$session_id = isset($data['session_id']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $data['session_id']) : 'anon';
$user_message = trim($data['message'] ?? '');
$page_url = $data['page_url'] ?? '';
$page_path = $data['page_path'] ?? '';
$currentTime = date('Y-m-d H:i:s');
$timeZone = date_default_timezone_get();

// --- MEMORY FILE HANDLING ---
$memoryDir = __DIR__ . '/../chat_memory';
if (!is_dir($memoryDir)) mkdir($memoryDir, 0755, true);
$memoryFile = "$memoryDir/session_" . $session_id . ".json";

// --- Clear memory if requested ---
if (!empty($data['clear_memory'])) {
    if (file_exists($memoryFile)) unlink($memoryFile);
    echo json_encode(['reply' => 'Memory cleared successfully.']);
    exit;
}

// --- Check message length ---
if (strlen($user_message) > 500) {
    echo json_encode(['reply' => 'Sorry, that message is too long.']);
    exit;
}

// === CONFIG ===
$groq_key = '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'; // DELETE THIS !!
if (!$groq_key) {
    http_response_code(500);
    echo json_encode(['error' => 'Server not configured.']);
    exit;
}

// --- Load memory ---
$memory = [];
if (file_exists($memoryFile)) {
    $json = file_get_contents($memoryFile);
    $memory = json_decode($json, true);
    if (!is_array($memory)) $memory = [];
}

// Keep last 10 messages for context
$trimmedHistory = array_slice($memory, -1);

// --- Build messages for API ---
$messages = [
    ["role" => "system", "content" => "
        You are a friendly assistant that helps users navigate the Mainstreet website (at https://mainstreet.lt), find businesses, deals, and answer short questions clearly. 
        Your name is Donald Trump.
        
        These are the only links on the platform accessible to the user:
        https://mainstreet.lt/,https://mainstreet.lt/discover,https://mainstreet.lt/search,https://mainstreet.lt/deals,https://mainstreet.lt/map,https://mainstreet.lt/auth,https://mainstreet.lt/auth?register,https://mainstreet.lt/terms,https://mainstreet.lt/privacy,https://mainstreet.lt/analytics,https://mainstreet.lt/my-business
        
        Some, like my-business and analytics require a business account to be set up before they can be used.
        There are some pages shown in the top navigation (Discover, Search, Deals, Map, and for business owners My Business).
        No email verification or manual review of anything on the site is required.
        If asking the user to visit a page, include a link.
        
        Keep responses concise and to the point. Return your responses as standard HTML with tags <h1>, <h2>, <p>, <strong>, <ul>, <li>, and others.
        
        ### Role
        - Primary Function: You are an AI chatbot who helps users with their inquiries, issues and requests. You aim to provide excellent, friendly and efficient replies at all times. Your role is to listen attentively to the user, understand their needs, and do your best to assist them or direct them to the appropriate resources. If a question is not clear, ask clarifying questions. Make sure to end your replies with a positive note. Make sure your responses only include the required content, and nothing about how you got to the answer. 
                
        The user is currently on page: {$page_path} ({$page_url}). Ensure to search this page.
        The user's time is currently {$currentTime}, timezone {$timeZone}. Use this to determine if a business is open or closed.
    
        Use the memory provided.
    "]
];

foreach ($trimmedHistory as $msg) {
    $messages[] = ["role" => $msg['role'], "content" => $msg['content']];
}

$messages[] = ["role" => "user", "content" => $user_message];

// --- Send request to Groq API ---
$payload = [
    "model" => "groq/compound-mini",
    "messages" => $messages,
    "temperature" => 0.3,
    "max_tokens" => 500
];

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer {$groq_key}",
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 25,
]);

$response = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || $code < 200 || $code >= 300) {
    error_log("Groq API error: $err / HTTP $code / Response: $response");
    echo json_encode(['reply' => 'Sorry — I could not connect to the chat service.']);
    exit;
}

$respJson = json_decode($response, true);
$reply = $respJson['choices'][0]['message']['content'] ?? 'Sorry, I could not produce a reply.';

// --- Save memory ---
$memory[] = ["role" => "user", "content" => $user_message];
$memory[] = ["role" => "assistant", "content" => $reply];
$memory = array_slice($memory, -20);
file_put_contents($memoryFile, json_encode($memory, JSON_PRETTY_PRINT));

echo json_encode(['reply' => $reply]);

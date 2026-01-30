<?php
require_once 'functions.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireAuth();
$user = getCurrentUser();

$pageTitle = 'Scraper';
$pageDescription = 'Add data';

$db = Database::getInstance(); // PDO
$error = '';
$success = '';

/**
 * Download and store a single business image
 */
function downloadBusinessImage(?string $imageUrl, string $businessId): ?string {
    if (!$imageUrl) {
        return null;
    }

    $uploadDir = __DIR__ . '/uploads/businesses/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
    if (!$ext) {
        $ext = 'jpg';
    }

    $filename = $businessId . '-' . uniqid() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'BizScraper/1.0'
    ]);

    $imageData = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !$imageData) {
        return null;
    }

    file_put_contents($filepath, $imageData);

    return '/uploads/businesses/' . $filename;
}
/**
 *  Get category ID from business "type"
 */
function resolveCategoryId(array $biz, array $categoryMap): string
{
    $haystack = strtolower(
        ($biz['type'] ?? '') . ' ' .
        ($biz['name'] ?? '') . ' ' .
        ($biz['about']['summary'] ?? '')
    );

    $rules = [
        'pizza'        => 'Pizza',
        'restaurant'   => 'Restaurants',
        'cafe'         => 'Restaurants',
        'coffee'       => 'Coffee Shop',
        'bar'          => 'Bar',
        'nightclub'    => 'Nightlife',
        'club'         => 'Nightlife',

        'bakery'       => 'Bakeries',
        'ice cream'    => 'Ice Cream',
        'juice'        => 'Juice Bar',
        'vegan'        => 'Vegan',
        'seafood'      => 'Seafood',

        'gym'          => 'Gyms & Fitness',
        'fitness'      => 'Gyms & Fitness',
        'yoga'         => 'Gyms & Fitness',

        'spa'          => 'Spa',
        'salon'        => 'Hair Salon',
        'barber'       => 'Barbershop',

        'hotel'        => 'Hotel',
        'travel'       => 'Travel',

        'auto'         => 'Auto Repair',
        'repair'       => 'Auto Repair',

        'pet'          => 'Pet Care',
        'vet'          => 'Pet Care',

        'law'          => 'Legal',
        'attorney'     => 'Legal',

        'accounting'   => 'Accounting',
        'tax'          => 'Accounting',

        'medical'      => 'Medical Practitioners',
        'clinic'       => 'Medical Practitioners',
        'doctor'       => 'Medical Practitioners',

        'construction' => 'Construction',
        'landscaping'  => 'Landscaping',
        'cleaning'     => 'Cleaning Services',

        'school'       => 'Education',
        'academy'      => 'Education',

        'store'        => 'Retail',
        'bookstore'    => 'Bookstore',
        'clothing'     => 'Clothing',
        'electronics'  => 'Electronics',
    ];

    foreach ($rules as $keyword => $categoryName) {
        if (str_contains($haystack, $keyword)) {
            $key = strtolower($categoryName);
            if (isset($categoryMap[$key])) {
                return $categoryMap[$key];
            }
        }
    }

    return $categoryMap['error'] ?? array_values($categoryMap)[0];
}

function normalizeBusinessHours(array $biz): string
{
    $days = [
        'Monday', 'Tuesday', 'Wednesday',
        'Thursday', 'Friday', 'Saturday', 'Sunday'
    ];

    $result = [];

    foreach ($days as $day) {
        $result[$day] = [
            'open' => '09:00',
            'close' => '17:00',
            'shutdown' => true,
        ];
    }

    if (!empty($biz['working_hours']) && is_array($biz['working_hours'])) {
        foreach ($biz['working_hours'] as $day => $info) {

            $dayName = ucfirst(strtolower($day));

            if (!isset($result[$dayName])) {
                continue;
            }

            $result[$dayName] = [
                'open' => $info['open'] ?? '09:00',
                'close' => $info['close'] ?? '17:00',
                'shutdown' => empty($info['open']) || empty($info['close']),
            ];
        }
    }

    return json_encode($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $location = trim($_POST['zipcode'] ?? '');

    if ($location === '') {
        $error = 'Enter a location to fetch businesses.';
        goto render;
    }

    // ===============================
    // RapidAPI Bulk Search Request
    // ===============================
    $rapidApiKey = '43007bb5eamshd3fe83c6d35533cp15e00fjsn90da7a6af251';

    $payload = [
        'queries' => [
            "seafood in {$location}",
            "gyms in {$location}",
            "media in {$location}",
            "spa in {$location}",
            "bookstore in {$location}",
            "clothing in {$location}",
            "florist in {$location}",
            "pet store in {$location}",
            "hotel in {$location}",
            "barbershop in {$location}",
            "sports & recreation in {$location}",
            "transportation in {$location}",
            "travel in {$location}",
        ],
        'language' => 'en',
        'region' => 'us',
        'limit' => 5
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://local-business-data.p.rapidapi.com/search',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-rapidapi-host: local-business-data.p.rapidapi.com',
            'x-rapidapi-key: ' . $rapidApiKey
        ],
    ]);

    $responseJson = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode !== 200) {
        $error = "API error (HTTP {$httpCode})";
        goto render;
    }

    $response = json_decode($responseJson, true);

    if (!isset($response['data']) || !is_array($response['data'])) {
        $error = 'Unexpected API response format.';
        goto render;
    }
    
    // Load categories once
    $categories = $db->fetchAll("
        SELECT id, name, slug
        FROM categories
    ");
    
    $categoryMap = [];
    foreach ($categories as $cat) {
        $key = strtolower($cat['name']);
        $categoryMap[$key] = $cat['id'];
    }

    $sql = "
        INSERT INTO businesses (
            id, name, description, category_id, owner_id,
            address, phone, email, website,
            image_url, banner_image_url,
            latitude, longitude, price_range,
            is_open, opening_hours, average_rating,
            total_reviews, created_at, updated_at
        ) VALUES (
            :id, :name, :description, :category_id, :owner_id,
            :address, :phone, :email, :website,
            :image_url, :banner_image_url,
            :latitude, :longitude, :price_range,
            :is_open, :opening_hours, :average_rating,
            :total_reviews, NOW(), NOW()
        )
    ";
    $reviewSql = "
        INSERT INTO reviews (
            id, business_id, user_id, rating, is_verified, created_at
        ) VALUES (
            :id, :business_id, :user_id, :rating, :is_verified, NOW()
        )
    ";

    $inserted = 0;
    $db->beginTransaction();
    
    foreach ($response['data'] as $biz) {
    
        $businessId = bin2hex(random_bytes(16));
        $imageRemote =
            $biz['photos_sample'][0]['photo_url_large']
            ?? $biz['photos_sample'][0]['photo_url']
            ?? null;
    
        $image_url = downloadBusinessImage($imageRemote, $businessId);
        $categoryId = resolveCategoryId($biz, $categoryMap);

        $db->query($sql, [
            ':id' => $businessId,
            ':name' => $biz['name'] ?? '',
            ':description' => $biz['about']['summary'] ?? '',
            ':category_id' => $categoryId,
            ':owner_id' => '0',
    
            ':address' => $biz['full_address'] ?? '',
            ':phone' => $biz['phone_number'] ?? null,
            ':email' => null,
            ':website' => $biz['website'] ?? null,
    
            ':image_url' => $image_url,
            ':banner_image_url' => null,
    
            ':latitude' => $biz['latitude'] ?? null,
            ':longitude' => $biz['longitude'] ?? null,
    
            ':price_range' => $biz['price_level'] ?? "$$",
            ':is_open' => (($biz['business_status'] ?? '') === 'OPEN') ? 1 : 0,
            ':opening_hours' => normalizeBusinessHours($biz),
    
            ':average_rating' => (float) ($biz['rating'] ?? 0),
            ':total_reviews' => (int) ($biz['review_count'] ?? 0),
        ]);
    
        if (!empty($biz['reviews_per_rating']) && is_array($biz['reviews_per_rating'])) {
            foreach ($biz['reviews_per_rating'] as $rating => $count) {
        
                $rating = (int) $rating;
                $count  = (int) $count;
        
                if ($rating < 1 || $rating > 5 || $count <= 0) {
                    continue;
                }
        
                for ($i = 0; $i < $count; $i++) {
                    $db->query($reviewSql, [
                        ':id' => bin2hex(random_bytes(16)),
                        ':business_id' => $businessId,
                        ':user_id' => '0',
                        ':rating' => $rating,
                        ':is_verified' => '1',
                    ]);
                }
            }
        }

        $inserted++;
    }

    $db->commit();
    $success = "Inserted {$inserted} businesses.";
}

render:
include 'includes/header.php';
?>

<main class="min-h-screen bg-background py-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-foreground mb-2">Scraper</h1>
            <p class="text-muted-foreground">Add data</p>
        </div>

        <?php if ($error): ?>
            <div class="text-red-600 mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="text-green-700 mb-4"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="zipcode" class="block font-medium">Location:</label>
            <input type="text" name="zipcode" id="zipcode" class="border rounded px-3 py-2 font-medium w-full text-foreground mb-4">
            <button type="submit" class="btn btn-primary">Fetch & Save</button>
        </form>
    </div>
</main>

<script>
lucide.createIcons();
</script>

<?php include 'includes/footer.php'; ?>

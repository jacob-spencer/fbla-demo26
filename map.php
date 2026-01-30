<?php
require_once 'functions.php';

$pageTitle = 'Mainstreet';
$pageDescription = 'Discover and navigate local businesses';

class MapSearchService {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /* =========================
       GEOCODING
    ========================= */

    public function geocodeAddress(string $address): ?array {
        $address = trim($address);
        if ($address === '') return null;

        $hash = md5(mb_strtolower($address));

        // 1️⃣ Check cache explicitly
        $cached = $this->db->fetch(
            "SELECT lat, lng FROM geocode_cache WHERE hash = ? LIMIT 1",
            [$hash]
        );

        if ($cached !== false) {
            if ($cached['lat'] !== null && $cached['lng'] !== null) {
                echo "<script>console.log('Retrieving from cache');</script>";
                return [
                    'lat' => (float)$cached['lat'],
                    'lng' => (float)$cached['lng']
                ];
            }
            return null; // cached failure
        }
        else {
             // 2️⃣ Normalize + request
            $normalized = $this->normalizeAddress($address);
            $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q=" . urlencode($normalized);
    
            $context = stream_context_create([
                'http' => [
                    'header'  => "User-Agent: Mainstreet.lt/1.0\r\n",
                    'timeout' => 3
                ]
            ]);
    
            $response = @file_get_contents($url, false, $context);
            if (!$response) {
                echo "<script>console.log('Geocoder failed!');</script>";
                // NO $this->storeGeocodeFailure($hash, $address);
                return null;
            }
    
            $data = json_decode($response, true);
            if (!isset($data[0]['lat'], $data[0]['lon'])) {
                // do NOT do this -- it requires manual clearing of the DB when something inevitably breaks
                // $this->storeGeocodeFailure($hash, $address);
                return null;
            }
    
            $lat = (float)$data[0]['lat'];
            $lng = (float)$data[0]['lon'];
    
            // Reject Null Island
            if (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
                $this->storeGeocodeFailure($hash, $address);
                return null;
            }
    
            // 3️⃣ Store success
            $this->db->query(
                "INSERT INTO geocode_cache (hash, address, lat, lng)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng)",
                [$hash, $address, $lat, $lng]
            );
    
            return ['lat' => $lat, 'lng' => $lng];
        }
    }

    private function storeGeocodeFailure(string $hash, string $address): void {
        $this->db->query(
            "INSERT INTO geocode_cache (hash, address, lat, lng)
             VALUES (?, ?, NULL, NULL)
             ON DUPLICATE KEY UPDATE lat = NULL, lng = NULL",
            [$hash, $address]
        );
    }

    private function normalizeAddress(string $address): string {
        if (preg_match('/^\d{5}(-\d{4})?$/', $address)) {
            return $address . ', USA';
        }
        return $address;
    }

    /* =========================
       BUSINESS FETCH
    ========================= */

    public function getAllBusinesses(): array {
        $businesses = $this->db->fetchAll(
            "SELECT b.*, 
                    c.name AS category_name,
                    c.icon AS category_icon,
                    c.slug AS category_slug
             FROM businesses b
             LEFT JOIN categories c ON b.category_id = c.id
             ORDER BY b.average_rating DESC"
        );

        foreach ($businesses as &$b) {
            $needsGeocode =
                $b['latitude'] === null ||
                $b['longitude'] === null ||
                (float)$b['latitude'] === 0.0 ||
                (float)$b['longitude'] === 0.0;

            if ($needsGeocode && !empty($b['address'])) {
                $coords = $this->geocodeAddress($b['address']);

                if ($coords) {
                    // Persist to DB
                    $this->db->query(
                        "UPDATE businesses SET latitude = ?, longitude = ? WHERE id = ?",
                        [$coords['lat'], $coords['lng'], $b['id']]
                    );

                    $b['latitude']  = $coords['lat'];
                    $b['longitude'] = $coords['lng'];
                }
            }

            // Open/closed status
            $b['is_open'] = false;
            if (!empty($b['opening_hours']) && !empty($b['timezone'])) {
                $b['is_open'] = isBusinessOpen($b['opening_hours'], $b['timezone']);
            }
        }

        return $businesses;
    }
}

/* =========================
   BOOTSTRAP
========================= */

$searchService = new MapSearchService();
$db = Database::getInstance();

$businesses = $searchService->getAllBusinesses();
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name ASC");

$userBookmarks = [];
if (isLoggedIn()) {
    $rows = $db->fetchAll(
        "SELECT business_id FROM bookmarks WHERE user_id = ?",
        [$_SESSION['user_id']]
    );
    $userBookmarks = array_column($rows, 'business_id');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        background: 'hsl(214, 31%, 8%)',
                        foreground: 'hsl(213, 31%, 91%)',
                        card: 'hsl(214, 30%, 12%)',
                        'card-foreground': 'hsl(213, 31%, 88%)',
                        directionsColor: 'hsl(214, 30%, 12%)',
                        primary: 'hsl(217, 91%, 60%)',
                        'primary-foreground': 'hsl(0, 0%, 100%)',
                        secondary: 'hsl(228, 94%, 67%)',
                        'secondary-foreground': 'hsl(0, 0%, 100%)',
                        muted: 'hsl(214, 30%, 15%)',
                        'muted-foreground': 'hsl(214, 15%, 65%)',
                        accent: 'hsl(45, 93%, 58%)',
                        'accent-foreground': 'hsl(0, 0%, 100%)',
                        destructive: 'hsl(0, 84%, 60%)',
                        'destructive-foreground': 'hsl(0, 0%, 100%)',
                        border: 'hsl(214, 20%, 20%)',
                        input: 'hsl(214, 25%, 18%)',
                        ring: 'hsl(217, 91%, 60%)'
                    }
                }
            }
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    
    <style>
        * { box-sizing: border-box; }
        html, body { 
            margin: 0; 
            padding: 0; 
            height: 100%; 
            width: 100%; 
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            background-color: hsl(214, 31%, 8%);
            color: hsl(213, 31%, 91%);
        }
        
        #map {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
            filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
        }
        
        .bottom-navbar {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translate(-50%, -50%);
            height: 64px;
            background: rgba(30, 35, 45, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid hsl(214, 20%, 25%);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 0 8px;
            box-shadow: 0 -4px 30px rgba(0, 0, 0, 0.3);
        }
        
        .nav-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border-radius: 16px;
            transition: all 0.2s ease;
            cursor: pointer;
            color: hsl(214, 15%, 65%);
            background: transparent;
            border: none;
        }
        
        .nav-btn:hover, .nav-btn.active {
            background: hsl(217, 91%, 60%, 0.15);
            color: hsl(217, 91%, 60%);
        }
        
        .nav-btn span {
            font-size: 10px;
            margin-top: 4px;
            font-weight: 500;
        }
        
        .slide-panel {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background: hsl(214, 30%, 12%);
            border-top-left-radius: 24px;
            border-top-right-radius: 24px;
            z-index: 2000;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            max-height: 85vh;
            overflow-y: auto;
            border: 1px solid hsl(214, 20%, 20%);
        }
        
        .slide-panel.open {
            transform: translateY(0);
        }
        
        .panel-handle {
            width: 40px;
            height: 4px;
            background: hsl(214, 20%, 30%);
            border-radius: 2px;
            margin: 12px auto;
        }
        
        .business-card {
            background: hsl(214, 25%, 15%);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid hsl(214, 20%, 20%);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .business-card:hover {
            background: hsl(214, 25%, 18%);
            border-color: hsl(217, 91%, 60%);
        }
        
        .category-chip {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: hsl(214, 25%, 15%);
            border-radius: 20px;
            border: 1px solid hsl(214, 20%, 20%);
            margin: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        
        .category-chip:hover, .category-chip.active {
            background: hsl(217, 91%, 60%, 0.2);
            border-color: hsl(217, 91%, 60%);
            color: hsl(217, 91%, 60%);
        }
        
        .search-input {
            width: 100%;
            padding: 14px 16px;
            background: hsl(214, 25%, 18%);
            border: 1px solid hsl(214, 20%, 20%);
            border-radius: 12px;
            color: hsl(213, 31%, 91%);
            font-size: 16px;
            outline: none;
        }
        
        .search-input:focus {
            border-color: hsl(217, 91%, 60%);
        }
        
        .leaflet-routing-container {
            background: hsl(214, 30%, 12%) !important;
            color: hsl(213, 31%, 91%) !important;
            border-radius: 12px !important;
            padding: 12px !important;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .leaflet-popup-content-wrapper {
            background: hsl(214, 30%, 12%);
            color: hsl(213, 31%, 91%);
            border-radius: 12px;
        }
        
        .leaflet-popup-tip {
            background: hsl(214, 30%, 12%);
        }
        
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1500;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .overlay.visible {
            opacity: 1;
            pointer-events: auto;
        }
        
        .directions-panel {
            position: fixed;
            top: 16px;
            left: 10%;
            right: 10%;
            background: hsl(214, 30%, 12%);
            border-radius: 16px;
            padding: 16px;
            z-index: 1100;
            display: none;
            border: 1px solid hsl(214, 20%, 20%);
        }
        
        .directions-panel.active {
            display: block;
        }
        
        /* Hide LRM’s built-in panel WITHOUT breaking it */
        .leaflet-routing-container {
            opacity: 0 !important;
            pointer-events: none !important;
            height: 0 !important;
            overflow: hidden !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            background: transparent !important;
        }

        
        @media (max-width: 640px) {
            .nav-btn span { display: none; }
            .nav-btn { padding: 12px; }
            .bottom-navbar { height: 56px; }
        }
    </style>
</head>
<body>
    <script>
        function toggleDropdown(index) {
            const content = document.getElementById(`faq-content-${index}`);
            const icon = document.getElementById(`icon-${index}`);
            const isOpen = content.classList.contains('hidden') === false;
    
            if (isOpen) {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180');
            } else {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180');
            }
        }
    </script>
    
    <div id="map"></div>
    
    <div class="directions-panel" id="directionsPanel">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold">Directions</h3>
            <button onclick="closeDirections()" class="p-2 hover:bg-muted rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div id="directionsInfo" class="text-sm text-foreground"></div>
        
        <button
            class="w-full rounded-xl text-left pr-2 pt-2 flex justify-between items-center focus:outline-none"
            onclick="toggleDropdown(0)"
            aria-expanded="false"
            aria-controls="faq-content-0"
        >
            <span class="text-sm font-medium text-foreground">Turn-by-turn</span>
            <svg id="icon-0" class="w-5 h-5 text-muted-foreground transition-transform duration-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div id="faq-content-0" class="hidden px-2 py-4 text-muted-foreground">
            <div class="overflow-y-auto h-48">
                <ul id="directionsList" class="">
                    
                </ul>
            </div>
        </div>
    </div>
    
    <div class="bottom-navbar">
        <button class="nav-btn" onclick="backToHome()" id="btn-home">
            <i data-lucide="home" class="w-8 h-8"></i>
        </button>
        <button class="nav-btn" onclick="openPanel('search')" id="btn-search">
            <i data-lucide="search" class="w-8 h-8"></i>
        </button>
        <button class="nav-btn" onclick="openPanel('directions')" id="btn-directions">
            <i data-lucide="navigation" class="w-8 h-8"></i>
        </button>
        <button class="nav-btn" onclick="openPanel('categories')" id="btn-categories">
            <i data-lucide="grid-3x3" class="w-8 h-8"></i>
        </button>
        <button class="nav-btn" onclick="openPanel('bookmarks')" id="btn-bookmarks">
            <i data-lucide="bookmark" class="w-8 h-8"></i>
        </button>
        <button class="nav-btn" onclick="openPanel('account')" id="btn-account">
            <i data-lucide="user" class="w-8 h-8"></i>
        </button>
    </div>
    
    <div class="overlay" id="overlay" onclick="closeAllPanels()"></div>
    
    <div class="slide-panel" id="searchPanel">
        <div class="panel-handle"></div>
        <div class="p-4">
            <h2 class="text-xl font-bold mb-4">Search Businesses</h2>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by name, category, or location..." oninput="filterBusinesses()">
            <div id="searchResults" class="mt-4 max-h-96 overflow-y-auto"></div>
        </div>
    </div>
    
    <div class="slide-panel" id="directionsSelectPanel">
        <div class="panel-handle"></div>
        <div class="p-4">
            <h2 class="text-xl font-bold mb-4">Get Directions</h2>
            <p class="text-muted-foreground mb-4">Select a business to get directions from your current location</p>
            <input type="text" id="directionsSearchInput" class="search-input mb-4" placeholder="Search for a business..." oninput="filterDirectionsBusinesses()">
            <div id="directionsBusinessList" class="max-h-80 overflow-y-auto"></div>
        </div>
    </div>
    
    <div class="slide-panel" id="categoriesPanel">
        <div class="panel-handle"></div>
        <div class="p-4">
            <h2 class="text-xl font-bold mb-4">Filters</h2>
            <h2 class="text-lg font-bold mb-4">Category</h2>
            <div class="flex flex-wrap" id="categoryChips">
                <div class="category-chip active" onclick="filterByCategory('')">
                    <i data-lucide="layers" class="w-4 h-4 mr-2"></i>
                    All
                </div>
                <?php foreach ($categories as $cat): ?>
                <div class="category-chip" onclick="filterByCategory('<?php echo escapeHtml($cat['slug']); ?>')" data-category="<?php echo escapeHtml($cat['slug']); ?>">
                    <i data-lucide="<?php echo escapeHtml($cat['icon']); ?>" class="w-4 h-4 mr-2"></i>
                    <?php echo escapeHtml($cat['name']); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="slide-panel" id="bookmarksPanel">
        <div class="panel-handle"></div>
        <div class="p-4">
            <h2 class="text-xl font-bold mb-4">Saved Businesses</h2>
            <?php if (isLoggedIn()): ?>
            <div id="bookmarksList" class="max-h-96 overflow-y-auto"></div>
            <?php else: ?>
            <div class="text-center py-8">
                <i data-lucide="bookmark" class="w-12 h-12 text-muted-foreground mx-auto mb-4"></i>
                <p class="text-muted-foreground mb-4">Sign in to save your favorite businesses</p>
                <a href="/auth" class="inline-block px-6 py-3 bg-primary text-white rounded-lg font-medium">Sign In</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="slide-panel" id="accountPanel">
        <div class="panel-handle"></div>
        <div class="p-4">
            <h2 class="text-xl font-bold mb-4">Account</h2>
            <?php if (isLoggedIn()): 
                $user = getCurrentUser();
            ?>
            <div class="space-y-3">
                <div class="flex items-center space-x-4 p-4 bg-muted rounded-lg">
                    <?php if ($user['profile_image_url']): ?>
                    <img src="<?php echo escapeHtml($user['profile_image_url']); ?>" class="w-12 h-12 rounded-full object-cover">
                    <?php else: ?>
                    <div class="w-12 h-12 bg-primary/20 rounded-full flex items-center justify-center">
                        <i data-lucide="user" class="w-6 h-6 text-primary"></i>
                    </div>
                    <?php endif; ?>
                    <div>
                        <p class="font-semibold"><?php echo escapeHtml($user['first_name'] . ' ' . $user['last_name']); ?></p>
                        <p class="text-sm text-muted-foreground"><?php echo escapeHtml($user['email']); ?></p>
                    </div>
                </div>
                <a href="/my-profile" class="flex items-center p-3 hover:bg-muted rounded-lg transition-colors">
                    <i data-lucide="user" class="w-5 h-5 mr-3"></i>
                    My Profile
                </a>
                <?php if ($user['is_business_owner']): ?>
                <a href="/my-business" class="flex items-center p-3 hover:bg-muted rounded-lg transition-colors">
                    <i data-lucide="building-2" class="w-5 h-5 mr-3"></i>
                    My Business
                </a>
                <a href="/analytics" class="flex items-center p-3 hover:bg-muted rounded-lg transition-colors">
                    <i data-lucide="bar-chart-2" class="w-5 h-5 mr-3"></i>
                    Analytics
                </a>
                <?php endif; ?>
                <a href="/deals" class="flex items-center p-3 hover:bg-muted rounded-lg transition-colors">
                    <i data-lucide="tag" class="w-5 h-5 mr-3"></i>
                    Deals
                </a>
                <a href="/help" class="flex items-center p-3 hover:bg-muted rounded-lg transition-colors">
                    <i data-lucide="help-circle" class="w-5 h-5 mr-3"></i>
                    Help Center
                </a>
                <hr class="border-border">
                <a href="/logout" class="flex items-center p-3 hover:bg-muted rounded-lg transition-colors text-destructive">
                    <i data-lucide="log-out" class="w-5 h-5 mr-3"></i>
                    Sign Out
                </a>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <a href="/auth" class="flex items-center justify-center p-4 bg-primary text-white rounded-lg font-medium">
                    <i data-lucide="log-in" class="w-5 h-5 mr-2"></i>
                    Sign In
                </a>
                <a href="/auth?register" class="flex items-center justify-center p-4 bg-accent text-black rounded-lg font-medium">
                    <i data-lucide="user-plus" class="w-5 h-5 mr-2"></i>
                    Create Account
                </a>
                <hr class="border-border my-4">
                <a href="/deals" class="flex items-center p-3 hover:bg-muted rounded-lg transition-colors">
                    <i data-lucide="tag" class="w-5 h-5 mr-3"></i>
                    Browse Deals
                </a>
                <a href="/help" class="flex items-center p-3 hover:bg-muted rounded-lg transition-colors">
                    <i data-lucide="help-circle" class="w-5 h-5 mr-3"></i>
                    Help Center
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="slide-panel" id="businessPanel" style="max-height: 70vh;">
        <div class="panel-handle"></div>
        <div class="p-4" id="businessDetails"></div>
    </div>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            overflow: hidden;
            font-family: 'Geist', sans-serif;
        }

        #interactive-loader {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at center, #0f1117, #000);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999999;
            animation: fadeOut 0.8s ease forwards;
            animation-delay: 3s;
            pointer-events: none;
        }

        .loader-content {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .text-container {
            position: relative;
            font-family: 'Geist', sans-serif;
            font-weight: 800;
            font-size: 120px;
            letter-spacing: -2px;
            color: rgba(192, 192, 192, 0.25);
        }

        .liquid-fill {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            clip-path: polygon(0 100%, 100% 100%, 100% 100%, 0 100%);
            animation: fillUp 2.8s ease-out forwards;
        }

        .liquid-fill::before {
            content: 'Mainstreet';
            position: absolute;
            top: 0;
            left: 0;
            font-family: 'Geist', sans-serif;
            font-weight: 800;
            font-size: 120px;
            letter-spacing: -2px;
            color: #2563eb;
        }

        .loading-text {
            position: fixed;
            bottom: 40px;
            left: 40px;
            font-family: 'Helvetica', sans-serif;
            font-size: 18px;
            color: rgba(255, 255, 255, 0.7);
            letter-spacing: 0.5px;
        }

        @keyframes fillUp {
            0% {
                clip-path: polygon(0 100%, 100% 100%, 100% 100%, 0 100%);
            }
            100% {
                clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                visibility: hidden;
            }
        }

        @media (max-width: 768px) {
            .text-container,
            .liquid-fill::before {
                font-size: 60px;
            }
            .loading-text {
                font-size: 14px;
                bottom: 20px;
                left: 20px;
            }
        }
    </style>
    
    <div id="interactive-loader">
        <div class="loader-content">
            <div class="text-container">
                Mainstreet
                <div class="liquid-fill"></div>
            </div>
            <div class="loading-text">loading (<span id="percent">0</span>%)</div>
        </div>
    </div>

    <script>
        const percentEl = document.getElementById('percent');
        const duration = 2800;
        const startTime = Date.now();

        function updatePercent() {
            const elapsed = Date.now() - startTime;
            const progress = Math.min((elapsed / duration) * 100, 100);
            percentEl.textContent = Math.floor(progress);

            if (progress < 100) {
                requestAnimationFrame(updatePercent);
            }
        }

        updatePercent();

        setTimeout(() => {
            const loader = document.getElementById('interactive-loader');
            loader && loader.remove();
        }, 3800);
        
        const allBusinesses = <?php echo json_encode($businesses, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
        const userBookmarks = <?php echo json_encode($userBookmarks); ?>;
        const isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
        
        let map;
        let markers = [];
        let routingControl = null;
        let userLocation = null;
        let selectedBusiness = null;
        let currentCategory = '';
        
        function initMap() {
            map = L.map('map').setView([37.8, -96], 4);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        userLocation = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                        map.setView([userLocation.lat, userLocation.lng], 12);
                        
                        L.marker([userLocation.lat, userLocation.lng], {
                            icon: L.divIcon({
                                className: 'user-location-marker',
                                html: '<div style="width: 16px; height: 16px; background: hsl(217, 91%, 60%); border: 3px solid white; border-radius: 50%; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>',
                                iconSize: [16, 16],
                                iconAnchor: [8, 8]
                            })
                        }).addTo(map).bindPopup('You are here');
                    },
                    () => {
                        console.log('Geolocation denied, using default view');
                    }
                );
            }
            
            plotBusinesses(allBusinesses);
        }
        
        function plotBusinesses(businesses) {
            markers.forEach(m => map.removeLayer(m));
            markers = [];
            
            businesses.forEach(b => {
                if (!b.latitude || !b.longitude) return;
                
                const marker = L.marker([parseFloat(b.latitude), parseFloat(b.longitude)])
                    .addTo(map)
                    .on('click', () => showBusinessDetails(b));
                
                marker.bindTooltip(b.name, { direction: 'top', offset: [0, -10] });
                markers.push(marker);
            });
        }
        
        function renderStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += i <= rating 
                    ? '<span class="text-yellow-400">★</span>' 
                    : '<span class="text-gray-500">★</span>';
            }
            return stars;
        }
        
        function showBusinessDetails(business) {
            selectedBusiness = business;
            const isBookmarked = userBookmarks.includes(business.id);
            
            const html = `
                <div class="flex items-start space-x-4 mb-4">
                    <img src="${business.image_url || 'fbla-waves.png'}" class="w-20 h-20 rounded-xl object-cover" alt="${business.name}">
                    <div class="flex-1">
                        <h2 class="text-xl font-bold">${business.name}</h2>
                        <div class="flex items-center flex-wrap gap-2 mt-1">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-secondary/20 text-secondary">
                                ${business.category_name || 'Business'}
                            </span>
                            <span class="text-muted-foreground text-sm">${business.price_range || ''}</span>
                            ${business.is_open 
                                ? '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400"><span class="w-1.5 h-1.5 bg-green-400 rounded-full mr-1"></span>Open</span>'
                                : '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400"><span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Closed</span>'
                            }
                        </div>
                        <div class="flex items-center mt-2">
                            ${renderStars(Math.round(business.average_rating || 0))}
                            <span class="ml-2 text-sm font-medium">${Number(business.average_rating || 0).toFixed(1)}</span>
                            <span class="ml-1 text-sm text-muted-foreground">(${business.total_reviews || 0})</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex border-b border-border mb-4">
                    <button onclick="switchBusinessTab('info')" class="business-tab active px-4 py-2 text-sm font-medium border-b-2 border-primary text-primary" data-tab="info">Info</button>
                    <button onclick="switchBusinessTab('hours')" class="business-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-muted-foreground hover:text-foreground" data-tab="hours">Hours</button>
                    <button onclick="switchBusinessTab('reviews')" class="business-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-muted-foreground hover:text-foreground" data-tab="reviews">Reviews</button>
                    <button onclick="switchBusinessTab('deals')" class="business-tab px-4 py-2 text-sm font-medium border-b-2 border-transparent text-muted-foreground hover:text-foreground" data-tab="deals">Deals</button>
                </div>
                
                <div id="business-tab-info" class="business-tab-content h-80 overflow-y-auto">
                    ${business.description ? `<p class="text-muted-foreground text-sm mb-4">${business.description}</p>` : ''}
                    <div class="space-y-2 mb-4">
                        ${business.address ? `<div class="flex items-start space-x-3"><i data-lucide="map-pin" class="w-4 h-4 text-muted-foreground mt-0.5"></i><span class="text-sm">${business.address}</span></div>` : ''}
                        ${business.phone ? `<div class="flex items-center space-x-3"><i data-lucide="phone" class="w-4 h-4 text-muted-foreground"></i><a href="tel:${business.phone}" class="text-sm text-primary">${business.phone}</a></div>` : ''}
                        ${business.website ? `<div class="flex items-center space-x-3"><i data-lucide="globe" class="w-4 h-4 text-muted-foreground"></i><a href="${business.website}" target="_blank" class="text-sm text-primary truncate">${business.website}</a></div>` : ''}
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="getDirectionsToBusiness()" class="flex items-center justify-center p-2.5 bg-primary text-white rounded-lg text-sm font-medium">
                            <i data-lucide="navigation" class="w-4 h-4 mr-1.5"></i>Directions
                        </button>
                        <a href="/business?id=${business.id}" class="flex items-center justify-center p-2.5 bg-muted hover:bg-muted/80 rounded-lg text-sm font-medium">
                            <i data-lucide="external-link" class="w-4 h-4 mr-1.5"></i>Full Page
                        </a>
                        ${business.phone ? `<a href="tel:${business.phone}" class="flex items-center justify-center p-2.5 bg-muted hover:bg-muted/80 rounded-lg text-sm font-medium">
                            <i data-lucide="phone" class="w-4 h-4 mr-1.5"></i>Call
                        </a>` : ''}
                        ${isLoggedIn ? `<button onclick="toggleBookmark('${business.id}')" class="flex items-center justify-center p-2.5 ${isBookmarked ? 'bg-accent text-black' : 'bg-muted hover:bg-muted/80'} rounded-lg text-sm font-medium" id="bookmark-btn-${business.id}">
                            <i data-lucide="bookmark" class="w-4 h-4 mr-1.5 ${isBookmarked ? 'fill-current' : ''}"></i>${isBookmarked ? 'Saved' : 'Save'}
                        </button>` : ''}
                    </div>
                </div>
                
                <div id="business-tab-hours" class="business-tab-content hidden">
                    <div id="business-hours-content" class="text-sm">
                        <p class="text-muted-foreground">Loading hours...</p>
                    </div>
                </div>
                
                <div id="business-tab-reviews" class="business-tab-content hidden">
                    <div id="business-reviews-content">
                        <p class="text-muted-foreground text-sm">Loading reviews...</p>
                    </div>
                </div>
                
                <div id="business-tab-deals" class="business-tab-content hidden">
                    <div id="business-deals-content">
                        <p class="text-muted-foreground text-sm">Loading deals...</p>
                    </div>
                </div>
            `;
            
            document.getElementById('businessDetails').innerHTML = html;
            lucide.createIcons();
            
            closeAllPanels();
            openPanel('business');
            
            map.setView([parseFloat(business.latitude), parseFloat(business.longitude)], 15);
            
            loadBusinessHours(business.id);
            loadBusinessReviews(business.id);
            loadBusinessDeals(business.id);
        }
        
        function switchBusinessTab(tabName) {
            document.querySelectorAll('.business-tab').forEach(tab => {
                tab.classList.remove('active', 'border-primary', 'text-primary');
                tab.classList.add('border-transparent', 'text-muted-foreground');
            });
            document.querySelectorAll('.business-tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            const activeTab = document.querySelector(`.business-tab[data-tab="${tabName}"]`);
            if (activeTab) {
                activeTab.classList.add('active', 'border-primary', 'text-primary');
                activeTab.classList.remove('border-transparent', 'text-muted-foreground');
            }
            document.getElementById(`business-tab-${tabName}`).classList.remove('hidden');
        }
        
        function loadBusinessHours(businessId) {
            const business = allBusinesses.find(b => b.id === businessId);
            const container = document.getElementById('business-hours-content');
        
            if (!business || !business.opening_hours) {
                container.innerHTML = '<p class="text-muted-foreground">Hours not available</p>';
                return;
            }
        
            try {
                const hours = JSON.parse(business.opening_hours);
                const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                const today = new Date().toLocaleDateString('en-US', { weekday: 'long' });
        
                let html = '<div class="space-y-2 h-80">';
        
                days.forEach(day => {
                    const isToday = day === today;
                    const dayData = hours[day];
        
                    let displayText = 'Closed';
        
                    if (dayData && dayData.shutdown === false) {
                        displayText = `${dayData.open} – ${dayData.close}`;
                    }
        
                    html += `
                        <div class="flex justify-between py-1 ${isToday ? 'text-primary font-medium' : ''}">
                            <span>${day}${isToday ? ' (Today)' : ''}</span>
                            <span>${displayText}</span>
                        </div>
                    `;
                });
        
                html += '</div>';
                container.innerHTML = html;
        
            } catch (e) {
                console.error(e);
                container.innerHTML = '<p class="text-muted-foreground">Hours not available</p>';
            }
        }
        
        function loadBusinessReviews(businessId) {
            const container = document.getElementById('business-reviews-content');
            
            fetch(`/api/reviews-ajax.php?business_id=${businessId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.reviews || data.reviews.length === 0) {
                        container.innerHTML = `
                            <div class=" h-80"> <p class="text-muted-foreground text-sm mb-4">No reviews yet</p>
                            ${isLoggedIn ? `<a href="/business?id=${businessId}#reviews" class="text-primary text-sm">Be the first to review</a> </div>` : '</div>'}
                        `;
                        return;
                    }
                    
                    let html = '<div class="space-y-3 h-80 overflow-y-auto">';
                    // data.reviews.slice(0, 3).forEach(review => {
                    data.reviews.forEach(review => {
                        html += `<div class="p-3 bg-muted/50 rounded-lg">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-medium text-sm">${review.user_name || 'Anonymous'}</span>
                                <span class="text-xs text-muted-foreground">${review.created_at}</span>
                            </div>
                            <div class="mb-1">${renderStars(review.rating)}</div>
                            <p class="text-sm text-muted-foreground">${review.comment || ''}</p>
                        </div>`;
                    });
                    // html += `</div><a href="/business?id=${businessId}#reviews" class="block text-center text-primary text-sm mt-3">View all reviews</a>`;
                    html += `</div>`
                    container.innerHTML = html;
                })
                .catch(() => {
                    container.innerHTML = '<p class="text-muted-foreground text-sm">Could not load reviews</p>';
                });
        }
        
        function loadBusinessDeals(businessId) {
            const container = document.getElementById('business-deals-content');
            
            fetch(`/api/deals-ajax.php?business_id=${businessId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.deals || data.deals.length === 0) {
                        container.innerHTML = '<div class=" h-80"> <p class="text-muted-foreground text-sm">No active deals</p> </div>';
                        return;
                    }
                    
                    let html = '<div class="space-y-3 h-80 overflow-y-auto">';
                    data.deals.forEach(deal => {
                        const discount = deal.discount_type === 'percentage' 
                            ? `${deal.discount_value}% OFF` 
                            : `$${deal.discount_value} OFF`;
                        html += `<div class="p-3 bg-accent/10 border border-accent/30 rounded-lg">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-medium text-sm text-accent">${discount}</span>
                                <span class="text-xs text-muted-foreground">Expires ${deal.expires_at}</span>
                            </div>
                            <p class="text-sm font-medium">${deal.title}</p>
                            <p class="text-xs text-muted-foreground mt-1">${deal.description || ''}</p>
                            ${deal.discount_code ? `<button onclick="copyDealCode('${deal.discount_code}')" class="mt-2 px-3 py-1 bg-accent text-black text-xs rounded font-medium">Copy Code: ${deal.discount_code}</button>` : ''}
                        </div>`;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                })
                .catch(() => {
                    container.innerHTML = '<p class="text-muted-foreground text-sm">Could not load deals</p>';
                });
        }
        
        function copyDealCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                // do nothing
                // alert('Deal code copied: ' + code);
            });
        }
        
        function getDirectionsToBusiness() {
            if (!selectedBusiness) return;
            
            if (!userLocation) {
                alert('Please enable location services to get directions');
                return;
            }
            closeAllPanels();
            
            if (routingControl) {
                map.removeControl(routingControl);
            }
            
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(userLocation.lat, userLocation.lng),
                    L.latLng(parseFloat(selectedBusiness.latitude), parseFloat(selectedBusiness.longitude))
                ],
                routeWhileDragging: true,
                showAlternatives: false,
                fitSelectedRoutes: true,
                lineOptions: {
                    styles: [{ color: 'hsl(217, 91%, 60%)', weight: 5 }]
                },
                createMarker: function() { return null; }
            }).addTo(map);
            
            routingControl.on('routesfound', function(e) {
                const route = e.routes[0];
                const distance = (route.summary.totalDistance / 1609.34).toFixed(1);
                const time = Math.round(route.summary.totalTime / 60);
                
                document.getElementById('directionsInfo').innerHTML = `
                    <div class="flex items-center justify-between w-full">
                        <div>
                            <p class="font-medium">${selectedBusiness.name}</p>
                            <p>${distance} miles · ${time} min drive</p>
                        </div>
                
                        <button id="openGoogleMapsBtn"
                            class="flex items-center gap-2 px-3 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition ml-4 whitespace-nowrap">
                            <img src="https://www.google.com/images/branding/product/ico/maps_32dp.ico"
                                 alt="Google Maps" class="w-5 h-5">
                            Open in Maps
                        </button>
                    </div>
                `;

                
                document.getElementById("openGoogleMapsBtn").addEventListener("click", function () {
                    const start = `${userLocation.lat},${userLocation.lng}`;
                    const end = `${selectedBusiness.latitude},${selectedBusiness.longitude}`;
                
                    // Google Maps Directions Link
                    const url = `https://www.google.com/maps/dir/?api=1&origin=${start}&destination=${end}&travelmode=driving`;
                
                    window.open(url, "_blank");
                });

                document.getElementById('directionsPanel').classList.add('active');
                
                var summary = route.summary; 
                console.log('Total distance: ' + summary.totalDistance + ' meters');
            
                var instructions = route.instructions; 
                var formatter = new L.Routing.Formatter();
                instructions.forEach(function(instruction, index) {
                    var thisLine = '<li class="p-4 odd:bg-gray-800 even:bg-directionsColor">Instruction ' + (index + 1) + ': ' + formatter.formatInstruction(instruction) +'</li>';
                    document.getElementById('directionsList').innerHTML += thisLine;
                });
            });
        }
        
        function closeDirections() {
            if (routingControl) {
                map.removeControl(routingControl);
                routingControl = null;
            }
            document.getElementById('directionsPanel').classList.remove('active');
        }
        
        function openPanel(panel) {
            closeAllPanels();
            document.getElementById('overlay').classList.add('visible');
            
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            
            if (panel === 'search') {
                document.getElementById('searchPanel').classList.add('open');
                document.getElementById('btn-search').classList.add('active');
                renderSearchResults(allBusinesses.filter(b => currentCategory === '' || b.category_slug === currentCategory));
            } else if (panel === 'directions') {
                document.getElementById('directionsSelectPanel').classList.add('open');
                document.getElementById('btn-directions').classList.add('active');
                renderDirectionsBusinessList(allBusinesses);
            } else if (panel === 'categories') {
                document.getElementById('categoriesPanel').classList.add('open');
                document.getElementById('btn-categories').classList.add('active');
            } else if (panel === 'bookmarks') {
                document.getElementById('bookmarksPanel').classList.add('open');
                document.getElementById('btn-bookmarks').classList.add('active');
                if (isLoggedIn) {
                    renderBookmarks();
                }
            } else if (panel === 'account') {
                document.getElementById('accountPanel').classList.add('open');
                document.getElementById('btn-account').classList.add('active');
            } else if (panel === 'business') {
                document.getElementById('businessPanel').classList.add('open');
            }
            
            lucide.createIcons();
        }
        
        function closeAllPanels() {
            document.querySelectorAll('.slide-panel').forEach(p => p.classList.remove('open'));
            document.getElementById('overlay').classList.remove('visible');
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
        }
        
        function renderBusinessCard(b, showDirections = false) {
            return `
                <div class="business-card" onclick="${showDirections ? `selectBusinessForDirections('${b.id}')` : `showBusinessDetails(allBusinesses.find(x => x.id === '${b.id}'))`}">
                    <div class="flex items-center space-x-3">
                        <img src="${b.image_url || 'fbla-waves.png'}" class="w-14 h-14 rounded-lg object-cover" alt="${b.name}">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold truncate">${b.name}</h3>
                            <div class="flex items-center space-x-2 text-sm">
                                <span class="text-muted-foreground">${b.category_name || ''}</span>
                                ${b.price_range ? `<span class="text-muted-foreground">· ${b.price_range}</span>` : ''}
                            </div>
                            <div class="flex items-center mt-1">
                                ${renderStars(Math.round(b.average_rating || 0))}
                                <span class="ml-1 text-sm">${Number(b.average_rating || 0).toFixed(1)}</span>
                            </div>
                        </div>
                        ${showDirections ? '<i data-lucide="navigation" class="w-5 h-5 text-primary"></i>' : '<i data-lucide="chevron-right" class="w-5 h-5 text-muted-foreground"></i>'}
                    </div>
                </div>
            `;
        }
        
        function renderSearchResults(businesses) {
            const container = document.getElementById('searchResults');
            if (businesses.length === 0) {
                container.innerHTML = '<p class="text-center text-muted-foreground py-8">No businesses found</p>';
                return;
            }
            container.innerHTML = businesses.map(b => renderBusinessCard(b)).join('');
            lucide.createIcons();
        }
        
        function filterBusinesses() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const filtered = allBusinesses.filter(b => {
                const matchesSearch = b.name.toLowerCase().includes(query) || 
                    (b.category_name && b.category_name.toLowerCase().includes(query)) ||
                    (b.address && b.address.toLowerCase().includes(query)) ||
                    (b.description && b.description.toLowerCase().includes(query));
                const matchesCategory = currentCategory === '' || b.category_slug === currentCategory;
                return matchesSearch && matchesCategory;
            });
            renderSearchResults(filtered);
            plotBusinesses(filtered);
        }
        
        function renderDirectionsBusinessList(businesses) {
            const container = document.getElementById('directionsBusinessList');
            container.innerHTML = businesses.map(b => renderBusinessCard(b, true)).join('');
            lucide.createIcons();
        }
        
        function filterDirectionsBusinesses() {
            const query = document.getElementById('directionsSearchInput').value.toLowerCase();
            const filtered = allBusinesses.filter(b => 
                b.name.toLowerCase().includes(query) || 
                (b.category_name && b.category_name.toLowerCase().includes(query))
            );
            renderDirectionsBusinessList(filtered);
        }
        
        function selectBusinessForDirections(businessId) {
            selectedBusiness = allBusinesses.find(b => b.id === businessId);
            if (selectedBusiness) {
                closeAllPanels();
                getDirectionsToBusiness();
            }
        }
        
        function filterByCategory(slug) {
            currentCategory = slug;
            
            document.querySelectorAll('.category-chip').forEach(chip => {
                chip.classList.remove('active');
                if ((slug === '' && !chip.dataset.category) || chip.dataset.category === slug) {
                    chip.classList.add('active');
                }
            });
            
            const filtered = slug === '' 
                ? allBusinesses 
                : allBusinesses.filter(b => b.category_slug === slug);
            
            plotBusinesses(filtered);
            closeAllPanels();
        }
        
        function renderBookmarks() {
            const container = document.getElementById('bookmarksList');
            const bookmarkedBusinesses = allBusinesses.filter(b => userBookmarks.includes(b.id));
            
            if (bookmarkedBusinesses.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <i data-lucide="bookmark" class="w-12 h-12 text-muted-foreground mx-auto mb-4"></i>
                        <p class="text-muted-foreground">No saved businesses yet</p>
                    </div>
                `;
            } else {
                container.innerHTML = bookmarkedBusinesses.map(b => renderBusinessCard(b)).join('');
            }
            lucide.createIcons();
        }
        
        async function toggleBookmark(businessId) {
            if (!isLoggedIn) {
                window.location.href = '/auth';
                return;
            }
            
            try {
                const response = await fetch('/api/bookmarks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ business_id: businessId })
                });
                
                const data = await response.json();
                
                const idx = userBookmarks.indexOf(businessId);
                if (idx > -1) {
                    userBookmarks.splice(idx, 1);
                } else {
                    userBookmarks.push(businessId);
                }
                
                if (selectedBusiness && selectedBusiness.id === businessId) {
                    showBusinessDetails(selectedBusiness);
                }
            } catch (error) {
                console.error('Bookmark error:', error);
            }
        }
        
        // Service Worker and Push Notification Support
        const PUBLIC_VAPID_KEY = <?php echo json_encode(PUBLIC_VAPID_KEY); ?>;
        
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
        
        async function subscribeUser() {
            console.log("subscribing user...");
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                console.log('Push notifications not supported');
                return;
            }
            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(PUBLIC_VAPID_KEY)
                });
                const response = await fetch('/api/subscribe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subscription: subscription.toJSON() })
                });
                if (response.ok) {
                    console.log('Successfully subscribed to push notifications');
                }
            } catch (error) {
                console.error('Failed to subscribe:', error);
            }
        }
        
        let hasPrompted = false;
        function tryPromptNotification() {
            if (hasPrompted) return;
            hasPrompted = true;
            if (Notification.permission === "default") {
                Notification.requestPermission().then(permission => {
                    if (permission === "granted") {
                        subscribeUser();
                    } else {
                        console.log("User denied notifications");
                    }
                });
            } else if (Notification.permission === "granted") {
                subscribeUser();
            }
        }
        
        function backToHome() {
            window.location.href = "https://mainstreet.lt";
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            lucide.createIcons();
            
            // Register service worker
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/service-worker.js')
                    .then((registration) => {
                        console.log('Service Worker registered successfully:', registration.scope);
                    })
                    .catch((error) => {
                        console.log('Service Worker registration failed:', error);
                    });
            }
        });
        
        // Prompt for notifications on user interaction
        document.addEventListener("click", tryPromptNotification);
        document.addEventListener("keydown", (e) => {
            tryPromptNotification();
            if (e.key === 'Escape') {
                closeAllPanels();
            }
        });
        document.addEventListener("scroll", tryPromptNotification);
        document.addEventListener("mousemove", tryPromptNotification);
    </script>
</body>
</html>

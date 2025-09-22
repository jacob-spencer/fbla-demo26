<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (empty($_GET) && isset($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

require_once 'functions.php';

$pageTitle = 'Search';
$pageDescription = 'Search for local businesses';

class SearchService {
    private $db;
    private $geocodeCache = [];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function geocodeAddress($address) {
        if (empty($address)) return null;
        $cacheKey = md5($address);
        if (isset($this->geocodeCache[$cacheKey])) return $this->geocodeCache[$cacheKey];

        $normalizedAddress = $this->normalizeAddress($address);
        $encodedAddress = urlencode($normalizedAddress);
        $url = "https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=1&q=" . $encodedAddress;

        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: YourBusinessDirectory/1.0\r\n",
                'timeout' => 3,
                'method' => 'GET'
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $this->geocodeCache[$cacheKey] = null;
            return null;
        }

        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0]['lat'], $data[0]['lon'])) {
            $result = [
                'lat' => (float)$data[0]['lat'],
                'lng' => (float)$data[0]['lon']
            ];
            $this->geocodeCache[$cacheKey] = $result;
            return $result;
        }

        $this->geocodeCache[$cacheKey] = null;
        return null;
    }

    public function calculateDistance($lat1, $lng1, $lat2, $lng2, $unit = 'miles') {
        if ($lat1 == $lat2 && $lng1 == $lng2) return 0;

        $earthRadius = ($unit === 'miles') ? 3959 : 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    private function normalizeAddress($address) {
        $address = trim($address);
        if (preg_match('/^\d{5}(-\d{4})?$/', $address)) $address .= ", USA";
        return $address;
    }

    public function searchBusinesses($params) {
        $sql = "SELECT b.*, c.name as category_name, c.icon as category_icon 
                FROM businesses b 
                LEFT JOIN categories c ON b.category_id = c.id 
                WHERE 1=1";
        $sqlParams = [];

        if (!empty($params['search'])) {
            $sql .= " AND (b.name LIKE ? OR b.description LIKE ?)";
            $term = "%{$params['search']}%";
            $sqlParams[] = $term;
            $sqlParams[] = $term;
        }

        if (!empty($params['category'])) {
            $category = $this->db->fetch(
                "SELECT id FROM categories WHERE slug = ? OR id = ? LIMIT 1",
                [$params['category'], $params['category']]
            );
            if ($category) {
                $sql .= " AND b.category_id = ?";
                $sqlParams[] = $category['id'];
            }
        }

        if (!empty($params['price']) && $params['price'] !== 'all') {
            $sql .= " AND b.price_range = ?";
            $sqlParams[] = $params['price'];
        }

        $businesses = $this->db->fetchAll($sql, $sqlParams);

        if ($params['open'] !== null && $params['open'] !== '') {
            $businesses = array_filter($businesses, function($b) use ($params) {
                $openingHours = $b['opening_hours'] ?? '';
                $timezone = $b['timezone'] ?? 'UTC';       
                $open = isBusinessOpen($openingHours, $timezone);
                return $params['open'] ? $open : !$open;
            });
        }

        $sortClause = $this->getSortClause($params['sort'] ?? 'rating');
        if ($sortClause) {
            usort($businesses, function($a, $b) use ($sortClause) {
                return strcmp($a['name'], $b['name']);
            });
        }

        return $businesses;
    }

    private function getSortClause($sortBy) {
        switch ($sortBy) {
            case 'reviews': return "total_reviews"; 
            case 'newest': return "created_at";
            case 'alpha-asc': return "name";
            case 'alpha-desc': return "name DESC";
            default: return "average_rating DESC";
        }
    }

    public function processBusinessesWithLocation($businesses, $searchLocation, $radius = null) {
        if (empty($searchLocation)) {
            return ['businesses' => array_map(fn($b)=>['distance'=>null]+$b, $businesses), 'errors'=>[], 'searchCoords'=>null];
        }
        $searchCoords = $this->geocodeAddress($searchLocation);
        if (!$searchCoords) {
            return ['businesses'=>array_map(fn($b)=>['distance'=>null]+$b, $businesses), 'errors'=>["Could not find location: $searchLocation"], 'searchCoords'=>null];
        }

        $filtered = [];
        $errors = [];
        foreach ($businesses as $b) {
            if (empty($b['address'])) {
                $b['distance'] = null;
                $filtered[] = $b;
                continue;
            }
            $coords = $this->geocodeAddress($b['address']);
            if ($coords) {
                $distance = $this->calculateDistance($searchCoords['lat'], $searchCoords['lng'], $coords['lat'], $coords['lng']);
                $b['distance'] = $distance;
                $b['latitude'] = $coords['lat'];
                $b['longitude'] = $coords['lng'];
                if (!$radius || $distance <= $radius) $filtered[] = $b;
            } else {
                $b['distance'] = null;
                $filtered[] = $b;
                $errors[] = $b['name'] . " (couldn't locate address)";
            }
        }

        return ['businesses'=>$filtered, 'errors'=>$errors, 'searchCoords'=>$searchCoords];
    }

    public function sortBusinessesByDistance($businesses) {
        usort($businesses, function($a, $b) {
            if ($a['distance'] === null && $b['distance'] === null) return strcmp($a['name'], $b['name']);
            if ($a['distance'] === null) return 1;
            if ($b['distance'] === null) return -1;
            if ($a['distance'] == $b['distance']) return strcmp($a['name'], $b['name']);
            return $a['distance'] <=> $b['distance'];
        });
        return $businesses;
    }
}

$searchService = new SearchService();
$db = Database::getInstance();

$searchParams = [
    'search' => $_GET['q'] ?? '',
    'location' => $_GET['location'] ?? '',
    'category' => $_GET['category'] ?? '',
    'sort' => $_GET['sort'] ?? 'rating',
    'price' => $_GET['price'] ?? '',
    'radius' => isset($_GET['radius']) ? max(0,(int)$_GET['radius']) : null,
    'open' => array_key_exists('open', $_GET) ? ($_GET['open']===''?null:(bool)$_GET['open']) : null,
];

$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name ASC");

// Search businesses
$businesses = $searchService->searchBusinesses($searchParams);

// Process location filtering
$locationResult = $searchService->processBusinessesWithLocation($businesses, $searchParams['location'], $searchParams['radius']);
$businesses = $locationResult['businesses'];
$geocodingErrors = $locationResult['errors'];
$searchCoords = $locationResult['searchCoords'];

// Sort by distance if requested
if ($searchParams['sort'] === 'distance' && $searchCoords) {
    $businesses = $searchService->sortBusinessesByDistance($businesses);
}

// Pagination
$itemsPerPage = 25;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalBusinesses = count($businesses);
$totalPages = ceil($totalBusinesses / $itemsPerPage);
$businesses = array_slice($businesses, ($currentPage-1)*$itemsPerPage, $itemsPerPage);

// Extract variables for template
$searchQuery = $searchParams['search'];
$locationQuery = $searchParams['location'];
$categoryId = $searchParams['category'];
$sortBy = $searchParams['sort'];
$priceRange = $searchParams['price'];
$radius = $searchParams['radius'];
$isOpen = $searchParams['open'];

include 'includes/header.php';
?>


<main class="min-h-screen bg-background">
    <!-- Loading Overlay -->
    <div id="geo-loading-overlay" class="fixed inset-0 bg-background/70 backdrop-blur-lg flex items-center justify-center z-50 hidden">
        <div class="bg-card px-6 py-4 rounded-lg shadow-lg text-center border border-border">
            <svg class="animate-spin h-6 w-6 text-primary mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <p class="text-sm text-foreground">Detecting your location...</p>
        </div>
    </div>

    <!-- Search Header -->
    <section class="hero-gradient text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">
                Search <span class="text-accent">Businesses</span>
            </h1>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Search Form -->
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4" onsubmit="return cleanFormSubmission(this);">
                <div>
                    <input type="text" name="q" value="<?php echo escapeHtml($searchQuery); ?>" 
                           placeholder="What are you looking for?" 
                           class="w-full px-4 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors">
                        Search
                    </button>
                </div>
            </form>
            
            <?php if (!empty($geocodingErrors)): ?>
            <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                <div class="flex items-center">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-orange-500 mr-2"></i>
                    <span class="text-sm text-orange-700">
                        <?php echo escapeHtml(implode(', ', $geocodingErrors)); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-8">
        <div id="contentDiv" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                <!-- Filters Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-card rounded-xl border border-border p-6 sticky top-8">
                        <h3 class="font-semibold text-foreground mb-4">Filters</h3>
                        
                        <form method="GET" class="space-y-6" onsubmit="return cleanFormSubmission(this);">
                            <?php if ($searchQuery): ?>
                            <input type="hidden" name="q" value="<?php echo escapeHtml($searchQuery); ?>">
                            <?php endif; ?>
                            
                            <!-- Location & Distance Filter -->
                            <div>
                                <label class="text-sm font-medium text-foreground mb-2 block">Location & Distance</label>
                                
                                <input type="text" name="location" 
                                       value="<?php echo escapeHtml($locationQuery); ?>" 
                                       placeholder="Enter address, city, or ZIP code"
                                       class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary mb-2">
                                
                                <select name="radius" class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">Any Distance</option>
                                    <?php 
                                    $radiusOptions = [1, 5, 10, 25, 50, 100];
                                    foreach ($radiusOptions as $option): 
                                    ?>
                                    <option value="<?php echo $option; ?>" <?php echo $radius === $option ? 'selected' : ''; ?>>
                                        Within <?php echo $option; ?> mile<?php echo $option !== 1 ? 's' : ''; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <hr>
                            
                            <!-- Categories -->
                            <div>
                                <label class="text-sm font-medium text-foreground mb-2 block">Category</label>
                                <select name="category" class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo escapeHtml($category['slug']); ?>" 
                                            <?php echo $categoryId === $category['slug'] ? 'selected' : ''; ?>>
                                        <?php echo escapeHtml($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Sort By -->
                            <div>
                                <label class="text-sm font-medium text-foreground mb-2 block">Sort By</label>
                                <select name="sort" class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                    <?php 
                                    $sortOptions = [
                                        'rating' => 'Highest Rated',
                                        'reviews' => 'Most Reviews',
                                        'newest' => 'Newest',
                                        'alpha-asc' => 'Alphabetically (A-Z)',
                                        'alpha-desc' => 'Alphabetically (Z-A)'
                                    ];
                                    
                                    // Add distance option if location is provided
                                    if ($locationQuery) {
                                        $sortOptions = array_merge(['distance' => 'Nearest'], $sortOptions);
                                    }
                                    
                                    foreach ($sortOptions as $value => $label): 
                                    ?>
                                    <option value="<?php echo $value; ?>" <?php echo $sortBy === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Price Range -->
                            <div>
                                <label class="text-sm font-medium text-foreground mb-2 block">Price Range</label>
                                <select name="price" class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">All Prices</option>
                                    <?php 
                                    $priceOptions = [
                                        '$' => '$ - Budget',
                                        '$$' => '$$ - Moderate', 
                                        '$$$' => '$$$ - Expensive',
                                        '$$$$' => '$$$$ - Very Expensive'
                                    ];
                                    foreach ($priceOptions as $value => $label): 
                                    ?>
                                    <option value="<?php echo $value; ?>" <?php echo $priceRange === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Open Status -->
                            <div>
                                <label class="text-sm font-medium text-foreground mb-2 block">Status</label>
                                <select name="open" class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">All Businesses</option>
                                    <option value="1" <?php echo $isOpen === true ? 'selected' : ''; ?>>Open Now</option>
                                    <option value="0" <?php echo $isOpen === false ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>

                            <button type="submit" class="w-full px-4 py-2 bg-primary hover:bg-primary/90 text-primary-foreground font-medium rounded-lg transition-colors">
                                Apply Filters
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Results -->
                <div class="lg:col-span-3">
                    <div class="flex items-center justify-between mb-6">
                        <p class="text-muted-foreground">
                            <?php echo $totalBusinesses; ?> business<?php echo $totalBusinesses !== 1 ? 'es' : ''; ?> found
                            <?php if ($searchQuery): ?>
                                for "<?php echo escapeHtml($searchQuery); ?>"
                            <?php endif; ?>
                            <?php if ($locationQuery): ?>
                                near <?php echo escapeHtml($locationQuery); ?>
                                <?php if ($radius): ?>
                                    within <?php echo $radius; ?> mile<?php echo $radius !== 1 ? 's' : ''; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($businesses)): ?>
                    <div class="space-y-6">
                        <?php foreach ($businesses as $business): ?>
                        <div class="bg-card rounded-xl shadow-sm border border-border overflow-hidden business-card cursor-pointer hover:shadow-md transition-shadow"
                             onclick="window.location.href='/business.php?id=<?php echo urlencode($business['id']); ?>'">
                            <div class="flex h-40">
                                <div class="relative flex-shrink-0">
                                    <img src="<?php echo escapeHtml($business['image_url'] ?: 'fbla-waves.png'); ?>" 
                                         alt="<?php echo escapeHtml($business['name']); ?>" 
                                         class="w-48 h-full object-cover"
                                         loading="lazy">
                                    <button type="button" class="absolute top-2 right-2 w-7 h-7 bg-secondary/90 hover:bg-secondary rounded-full flex items-center justify-center">
                                        <i data-lucide="bookmark" class="w-3 h-3 text-secondary-foreground"></i>
                                    </button>
                                </div>
                                <div class="flex-1 p-6">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h3 class="text-xl font-semibold text-foreground mb-1"><?php echo escapeHtml($business['name']); ?></h3>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-secondary/20 text-secondary">
                                                <?php echo escapeHtml($business['category_name']); ?>
                                            </span>
                                        </div>
                                        <div class="text-right">
                                            <div class="flex items-center space-x-1 mb-1">
                                                <?php echo renderStars(round($business['average_rating'] ?: 0)); ?>
                                                <span class="text-sm font-semibold"><?php echo number_format($business['average_rating'] ?: 0, 1); ?></span>
                                            </div>
                                            <p class="text-xs text-muted-foreground"><?php echo $business['total_reviews']; ?> reviews</p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($business['description']): ?>
                                    <p class="text-foreground mb-3 text-sm">
                                        <?php echo escapeHtml(mb_substr($business['description'], 0, 120)) . (mb_strlen($business['description']) > 120 ? '...' : ''); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                                        <div class="flex items-center space-x-4">
                                            <div class="flex items-center space-x-1">
                                                <i data-lucide="map-pin" class="w-3 h-3"></i>
                                                <span>
                                                    <?php 
                                                    if (isset($business['distance']) && $business['distance'] !== null) {
                                                        echo number_format($business['distance'], 1) . ' miles';
                                                    } else {
                                                        // Show partial address when no distance available
                                                        if ($business['address']) {
                                                            $addressParts = explode(',', $business['address']);
                                                            $city = trim($addressParts[count($addressParts)-3] ?? '');
                                                            $state = trim($addressParts[count($addressParts)-2] ?? '');
                                                            echo escapeHtml($city . ($city && $state ? ', ' : '') . $state);
                                                        } else {
                                                            echo 'Address N/A';
                                                        }
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            <?php if ($business['phone']): ?>
                                            <div class="flex items-center space-x-1">
                                                <i data-lucide="phone" class="w-3 h-3"></i>
                                                <span><?php echo escapeHtml($business['phone']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($business['price_range']): ?>
                                        <span class="font-medium"><?php echo escapeHtml($business['price_range']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="mt-8 flex justify-center space-x-2">
                        <?php
                        $maxPagesToShow = 7;
                        $halfRange = floor($maxPagesToShow / 2);
                        $startPage = max(1, $currentPage - $halfRange);
                        $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                        
                        // Adjust start page if we're near the end
                        if ($endPage - $startPage + 1 < $maxPagesToShow) {
                            $startPage = max(1, $endPage - $maxPagesToShow + 1);
                        }
                        
                        // Previous button
                        if ($currentPage > 1):
                        ?>
                        <a href="?<?php 
                            $queryParams = $_GET;
                            $queryParams['page'] = $currentPage - 1;
                            echo http_build_query($queryParams);
                        ?>" class="px-3 py-1 rounded bg-card border border-border hover:bg-muted">
                            &laquo;
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                        <a href="?<?php 
                            $queryParams = $_GET;
                            $queryParams['page'] = $p;
                            echo http_build_query($queryParams);
                        ?>" 
                           class="px-3 py-1 rounded <?php echo $p === $currentPage ? 'bg-primary text-white' : 'bg-card border border-border hover:bg-muted'; ?>">
                            <?php echo $p; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                        <a href="?<?php 
                            $queryParams = $_GET;
                            $queryParams['page'] = $currentPage + 1;
                            echo http_build_query($queryParams);
                        ?>" class="px-3 py-1 rounded bg-card border border-border hover:bg-muted">
                            &raquo;
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-muted rounded-full flex items-center justify-center mx-auto mb-6">
                            <i data-lucide="search" class="w-8 h-8 text-muted-foreground"></i>
                        </div>
                        <h3 class="text-2xl font-semibold text-foreground mb-4">
                            <?php 
                            if ($searchQuery && $locationQuery) {
                                echo "No results for \"" . escapeHtml($searchQuery) . "\" near " . escapeHtml($locationQuery);
                            } elseif ($searchQuery) {
                                echo "No results for \"" . escapeHtml($searchQuery) . "\"";
                            } elseif ($locationQuery) {
                                echo "No businesses found near " . escapeHtml($locationQuery);
                            } else {
                                echo 'No businesses found';
                            }
                            ?>
                        </h3>
                        <p class="text-muted-foreground max-w-md mx-auto mb-6">
                            <?php 
                            if ($locationQuery && $radius) {
                                echo "Try expanding your search radius or adjusting your filters.";
                            } else {
                                echo "Try adjusting your search terms or filters to find what you're looking for.";
                            }
                            ?>
                        </p>
                        <a href="/search.php" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                            Clear All Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
lucide.createIcons();

// Geolocation functions with improved error handling
function getUserLocation() {
    const overlay = document.getElementById('geo-loading-overlay');
    const locationInput = document.querySelector('input[name="location"]');
    
    if (!locationInput || !navigator.geolocation) {
        return;
    }
    
    // Show loading state
    if (overlay) overlay.classList.remove('hidden');
    const originalPlaceholder = locationInput.placeholder;
    locationInput.placeholder = 'Detecting location...';
    
    // Get current position with improved options
    navigator.geolocation.getCurrentPosition(
        // Success callback
        async function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 5000);
                
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`,
                    {
                        headers: {
                            'User-Agent': 'FBLABusinessProject/1.0'
                        },
                        signal: controller.signal
                    }
                );
                
                clearTimeout(timeoutId);
                
                if (response.ok) {
                    const data = await response.json();
                    
                    if (data && data.address) {
                        const zip = data.address.postcode;
                        const city = data.address.city || data.address.town || data.address.village;
                        const state = data.address.state;
                        
                        let locationString = '';
                        
                        if (zip) {
                            locationString = zip;
                        } else if (city && state) {
                            locationString = `${city}, ${state}`;
                        } else if (city) {
                            locationString = city;
                        }
                        
                        if (locationString && (!locationInput.value || locationInput.value === '')) {
                            locationInput.value = locationString;
                            
                            // Auto-submit if no current search parameters
                            if (window.location.search === '' || !window.location.search.includes('location=')) {
                                const form = locationInput.closest('form');
                                if (form) form.submit();
                            }
                        }
                    }
                }
                
            } catch (error) {
                console.log('Error reverse geocoding:', error);
            } finally {
                locationInput.placeholder = originalPlaceholder;
                if (overlay) overlay.classList.add('hidden');
            }
        },
        // Error callback
        function(error) {
            console.log('Geolocation error:', error.message);
            locationInput.placeholder = originalPlaceholder;
            if (overlay) overlay.classList.add('hidden');
        },
        // Options
        {
            enableHighAccuracy: true,
            timeout: 8000,
            maximumAge: 300000 // 5 minutes cache
        }
    );
}

// Improved form submission helper
function cleanFormSubmission(form) {
    const inputs = form.querySelectorAll('input, select');
    const defaultValues = ['', 'rating', 'all'];
    
    inputs.forEach(input => {
        if (input.type === 'hidden') return;
        
        // Remove name attribute for empty or default values
        if (!input.value || defaultValues.includes(input.value)) {
            input.removeAttribute('name');
        }
    });
    
    return true;
}

// Debounced search function for better UX
let searchTimeout;
function debounceSearch(callback, delay = 300) {
    return function(...args) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => callback.apply(this, args), delay);
    };
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const locationInput = document.querySelector('input[name="location"]');
    
    // Auto-detect location if field is empty or has default value
    if (locationInput && (!locationInput.value || locationInput.value === '23451')) {
        getUserLocation();
    }
    
    // Add lazy loading for images if not supported natively
    if ('loading' in HTMLImageElement.prototype === false) {
        const images = document.querySelectorAll('img[loading="lazy"]');
        images.forEach(img => {
            img.style.opacity = '0';
            img.onload = () => img.style.opacity = '1';
        });
    }
});

// Performance optimization: Intersection Observer for business cards
if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });
    
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.business-card').forEach(card => {
            observer.observe(card);
        });
    });
}
</script>

<style>
.animate-fade-in {
    animation: fadeIn 0.6s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Improved loading states */
.business-card img {
    transition: opacity 0.3s ease;
}

.business-card:hover {
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

/* Better mobile responsiveness */
@media (max-width: 768px) {
    .business-card .flex {
        flex-direction: column;
        height: auto;
    }
    
    .business-card img {
        width: 100%;
        height: 200px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
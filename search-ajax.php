<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';

header('Content-Type: application/json');

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
            $result = ['lat' => (float)$data[0]['lat'], 'lng' => (float)$data[0]['lon']];
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

        $sortClause = $this->getSortClause($params['sort'] ?? 'rating');
        $sql .= " ORDER BY {$sortClause}";

        $businesses = $this->db->fetchAll($sql, $sqlParams);

        if ($params['open'] !== null && $params['open'] !== '') {
            $businesses = array_filter($businesses, function($b) use ($params) {
                $openingHours = $b['opening_hours'] ?? '';
                $timezone = $b['timezone'] ?? 'UTC';
                $open = isBusinessOpen($openingHours, $timezone);
                return $params['open'] ? $open : !$open;
            });
        }

        return $businesses;
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

    private function getSortClause(?string $sortBy): string {
        $allowed = [
            'rating'     => "average_rating DESC",
            'reviews'    => "total_reviews DESC",
            'newest'     => "created_at DESC",
            'alpha-asc'  => "name ASC",
            'alpha-desc' => "name DESC",
        ];
        return $allowed[$sortBy] ?? $allowed['rating'];
    }
}

// ================== Main ==================
$searchService = new SearchService();

$params = [
    'search' => $_GET['q'] ?? '',
    'location' => $_GET['location'] ?? '',
    'category' => $_GET['category'] ?? '',
    'sort' => $_GET['sort'] ?? 'rating',
    'price' => $_GET['price'] ?? '',
    'radius' => isset($_GET['radius']) ? max(0,(int)$_GET['radius']) : null,
    'open' => array_key_exists('open', $_GET) ? ($_GET['open']===''?null:(bool)$_GET['open']) : null,
    'page' => isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1,
    'per_page' => isset($_GET['per_page']) ? max(1,(int)$_GET['per_page']) : 4,
    'paginate_result' => isset($_GET['paginate_result'])
];

// 1️⃣ Fetch all matching businesses (without pagination)
$businesses = $searchService->searchBusinesses($params);

// 2️⃣ Process locations and radius filtering
$locationResult = $searchService->processBusinessesWithLocation($businesses, $params['location'], $params['radius']);
$businesses = $locationResult['businesses'];
$errors = $locationResult['errors'];
$searchCoords = $locationResult['searchCoords'];

// 3️⃣ Sort by distance if requested
if ($params['sort'] === 'distance' && $searchCoords) {
    $businesses = $searchService->sortBusinessesByDistance($businesses);
}

// 4️⃣ Paginate AFTER filtering if requested
if ($params['paginate_result'] == true) {
    $total = count($businesses);
    $perPage = $params['per_page'];
    $page = $params['page'];
    $offset = ($page - 1) * $perPage;
    $businesses = array_slice($businesses, $offset, $perPage);
}
else {
    $total = count($businesses);
    $perPage = $params['per_page'];
    $page = $params['page'];
}

// 5️⃣ Output JSON
echo json_encode([
    'businesses' => $businesses,
    'total' => $total,
    'per_page' => $perPage,
    'page' => $page,
    'errors' => $errors,
    'searchCoords' => $searchCoords
]);

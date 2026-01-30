<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';

header('Content-Type: application/json');

class SearchService {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Optimized Distance Calculation using the Haversine Formula
     * This is used for sorting/filtering after fetching from DB.
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2, string $unit = 'miles'): float {
        if ($lat1 === $lat2 && $lng1 === $lng2) return 0;
        
        $earthRadius = ($unit === 'miles') ? 3959 : 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function searchAndProcess(array $params): array {
        $sql = "SELECT b.*, c.name as category_name, c.icon as category_icon 
                FROM businesses b 
                LEFT JOIN categories c ON b.category_id = c.id 
                WHERE 1=1";
        $sqlParams = [];

        // 1. Text Search
        if (!empty($params['search'])) {
            $sql .= " AND (b.name LIKE ? OR b.description LIKE ?)";
            $term = "%{$params['search']}%";
            $sqlParams[] = $term; $sqlParams[] = $term;
        }

        // 2. Category Filter
        if (!empty($params['category'])) {
            $category = $this->db->fetch("SELECT id FROM categories WHERE slug = ? OR id = ? LIMIT 1", [$params['category'], $params['category']]);
            if ($category) {
                $sql .= " AND b.category_id = ?";
                $sqlParams[] = $category['id'];
            }
        }

        // 3. Price Filter
        if (!empty($params['price']) && $params['price'] !== 'all') {
            $sql .= " AND b.price_range = ?";
            $sqlParams[] = $params['price'];
        }

        // Apply Database Sorting
        $sql .= " ORDER BY " . $this->getSortClause($params['sort']);
        $businesses = $this->db->fetchAll($sql, $sqlParams);

        // 4. Processing (Distance & Open Status)
        $filtered = [];
        $searchCoords = $params['user_coords']; // Expecting ['lat' => x, 'lng' => y]

        foreach ($businesses as $b) {
            // Check Open Status
            if ($params['open'] !== null) {
                $isOpen = isBusinessOpen($b['opening_hours'] ?? '', $b['timezone'] ?? 'UTC');
                if ($params['open'] !== $isOpen) continue;
            }

            // Calculate Distance (Only if user provided their coordinates)
            $dist = null;
            if ($searchCoords && !empty($b['latitude']) && !empty($b['longitude'])) {
                $dist = $this->calculateDistance(
                    (float)$searchCoords['lat'], (float)$searchCoords['lng'], 
                    (float)$b['latitude'], (float)$b['longitude']
                );
                
                // Radius Filter
                if ($params['radius'] && $dist > $params['radius']) continue;
            }

            $b['distance'] = $dist;
            $filtered[] = $b;
        }

        // 5. Post-process Sorting (by Distance)
        if ($params['sort'] === 'distance' && $searchCoords) {
            usort($filtered, fn($a, $b) => ($a['distance'] ?? PHP_INT_MAX) <=> ($b['distance'] ?? PHP_INT_MAX));
        }

        return $filtered;
    }

    private function getSortClause(string $sortBy): string {
        $allowed = [
            'rating' => "average_rating DESC", 'reviews' => "total_reviews DESC",
            'newest' => "created_at DESC", 'alpha-asc' => "name ASC", 'alpha-desc' => "name DESC",
        ];
        return $allowed[$sortBy] ?? $allowed['rating'];
    }
}

// ================== Main ==================
$searchService = new SearchService();

// We now expect coordinates from the frontend (Browser Geolocation API) 
// instead of an address string to geocode.
$userCoords = (isset($_GET['lat']) && isset($_GET['lng'])) 
    ? ['lat' => (float)$_GET['lat'], 'lng' => (float)$_GET['lng']] 
    : null;

$params = [
    'search'      => $_GET['q'] ?? '',
    'category'    => $_GET['category'] ?? '',
    'sort'        => $_GET['sort'] ?? 'rating',
    'price'       => $_GET['price'] ?? '',
    'radius'      => isset($_GET['radius']) ? (int)$_GET['radius'] : null,
    'open'        => ($_GET['open'] ?? '') === '' ? null : (bool)$_GET['open'],
    'user_coords' => $userCoords,
    'page'        => max(1, (int)($_GET['page'] ?? 1)),
    'per_page'    => max(1, (int)($_GET['per_page'] ?? 10)),
    'paginate'    => isset($_GET['paginate_result'])
];

$businesses = $searchService->searchAndProcess($params);
$total = count($businesses);

if ($params['paginate']) {
    $businesses = array_slice($businesses, ($params['page'] - 1) * $params['per_page'], $params['per_page']);
}

echo json_encode([
    'businesses'   => array_values($businesses),
    'total'        => $total,
    'per_page'     => $params['per_page'],
    'page'         => $params['page'],
    'searchCoords' => $userCoords
]);
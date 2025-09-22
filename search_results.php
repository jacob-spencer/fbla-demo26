<?php
// search_results.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';
$db = Database::getInstance();

// Accept filter parameters via GET
$searchQuery = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
$locationQuery = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$categoryId = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$sortBy = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'rating';
$priceRange = isset($_GET['price']) ? sanitizeInput($_GET['price']) : '';
$radius = isset($_GET['radius']) ? (int)$_GET['radius'] : '';
$isOpen = isset($_GET['open']) ? (bool)$_GET['open'] : true;

// Helper: get filtered businesses
function getFilteredBusinesses($db, $filters) {
    $searchQuery = $filters['q'] ?? '';
    $locationQuery = $filters['location'] ?? '';
    $categoryId = $filters['category'] ?? '';
    $sortBy = $filters['sort'] ?? 'rating';
    $priceRange = $filters['price'] ?? '';
    $radius = $filters['radius'] ?? '';
    $isOpen = $filters['open'] ?? null;

    $sql = "SELECT b.*, c.name as category_name, c.icon as category_icon
            FROM businesses b
            LEFT JOIN categories c ON b.category_id = c.id
            WHERE 1=1";
    $params = [];

    if ($searchQuery) {
        $sql .= " AND (b.name LIKE ? OR b.description LIKE ?)";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
    }

    if ($categoryId) {
        $category = $db->fetch("SELECT id FROM categories WHERE slug=? OR id=?", [$categoryId, $categoryId]);
        if ($category) {
            $sql .= " AND b.category_id=?";
            $params[] = $category['id'];
        }
    }

    if ($priceRange && $priceRange !== 'all') {
        $sql .= " AND b.price_range=?";
        $params[] = $priceRange;
    }

    if ($isOpen !== null) {
        $sql .= " AND b.is_open=?";
        $params[] = $isOpen ? 1 : 0;
    }

    switch ($sortBy) {
        case 'reviews': $sql .= " ORDER BY b.total_reviews DESC"; break;
        case 'newest': $sql .= " ORDER BY b.created_at DESC"; break;
        default: $sql .= " ORDER BY b.average_rating DESC, b.total_reviews DESC"; break;
    }

    $sql .= " LIMIT 50";
    $businesses = $db->fetchAll($sql, $params);

    $locationQuery = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
    $radius = isset($_GET['radius']) ? (int)$_GET['radius'] : '';
    $sortBy = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'rating';
    
    // Get all businesses as before
    $businesses = $db->fetchAll($sql, $params);
    
    if ($locationQuery) {
        $searchCoords = geocodeAddress($locationQuery);
        $filtered = [];
        foreach ($businesses as $b) {
            if (!$b['address']) continue;
            $bCoords = geocodeAddress($b['address']);
            if ($bCoords) {
                $distance = calculateDistance($searchCoords['lat'], $searchCoords['lng'], $bCoords['lat'], $bCoords['lng']);
                $b['distance'] = $distance;
                if (!$radius || $distance <= $radius) $filtered[] = $b;
            } elseif (!$radius) {
                $b['distance'] = null;
                $filtered[] = $b;
            }
        }
        $businesses = $filtered;
    
        // Sort by distance
        if ($sortBy === 'distance') {
            usort($businesses, function($a,$b){
                if ($a['distance'] === null) return 1;
                if ($b['distance'] === null) return -1;
                return $a['distance'] <=> $b['distance'];
            });
        }
    }


    return $businesses;
}

// Geocoding & distance functions
function geocodeAddress($address) {
    $encoded = urlencode($address);
    $url = "https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=1&q=$encoded";
    $ctx = stream_context_create(['http'=>['header'=>"User-Agent: YourBusinessDirectory/1.0\r\n",'timeout'=>5]]);
    $res = @file_get_contents($url,false,$ctx);
    if(!$res) return null;
    $data = json_decode($res,true);
    return (!empty($data) && isset($data[0]['lat'],$data[0]['lon'])) ? ['lat'=>(float)$data[0]['lat'],'lng'=>(float)$data[0]['lon']] : null;
}
function calculateDistance($lat1,$lng1,$lat2,$lng2,$unit='miles'){
    if($lat1==$lat2 && $lng1==$lng2) return 0;
    $r=$unit==='miles'?3959:6371;
    $dLat=deg2rad($lat2-$lat1);
    $dLng=deg2rad($lng2-$lng1);
    $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)**2;
    $c=2*atan2(sqrt($a),sqrt(1-$a));
    return $r*$c;
}
function normalizeAddress($addr){$addr=trim($addr);if(preg_match('/^\d{5}(-\d{4})?$/',$addr))$addr.=', USA';return $addr;}

$businesses = getFilteredBusinesses($db, [
    'q'=>$searchQuery,'location'=>$locationQuery,'category'=>$categoryId,
    'sort'=>$sortBy,'price'=>$priceRange,'radius'=>$radius,'open'=>$isOpen
]);
?>

<?php if(!empty($businesses)): ?>
<div class="space-y-6">
    <?php foreach($businesses as $b): ?>
    <div class="bg-card rounded-xl shadow-sm border border-border overflow-hidden business-card cursor-pointer hover:shadow-md transition-shadow"
         onclick="window.location.href='/business.php?id=<?php echo urlencode($b['id']); ?>'">
        <div class="flex h-40">
            <div class="relative flex-shrink-0">
                <img src="<?php echo escapeHtml($b['image_url']?:'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&h=200'); ?>" 
                     alt="<?php echo escapeHtml($b['name']); ?>" class="w-48 h-full object-cover">
                <button class="absolute top-2 right-2 w-7 h-7 bg-secondary/90 hover:bg-secondary rounded-full flex items-center justify-center">
                    <i data-lucide="bookmark" class="w-3 h-3 text-secondary-foreground"></i>
                </button>
            </div>
            <div class="flex-1 p-6">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <h3 class="text-xl font-semibold text-foreground mb-1"><?php echo escapeHtml($b['name']); ?></h3>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-secondary/20 text-secondary">
                            <?php echo escapeHtml($b['category_name']); ?>
                        </span>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center space-x-1 mb-1">
                            <?php echo renderStars(round($b['average_rating']?:0)); ?>
                            <span class="text-sm font-semibold"><?php echo number_format($b['average_rating']?:0,1); ?></span>
                        </div>
                        <p class="text-xs text-muted-foreground"><?php echo $b['total_reviews']; ?> reviews</p>
                    </div>
                </div>
                <?php if($b['description']): ?>
                <p class="text-foreground mb-3 text-sm"><?php echo escapeHtml(substr($b['description'],0,70)).(strlen($b['description'])>70?'...':''); ?></p>
                <?php endif; ?>
                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-1">
                            <i data-lucide="map-pin" class="w-3 h-3"></i>
                            <span><?php 
                                if(isset($b['distance']) && $b['distance']!==null){ echo number_format($b['distance'],1).' miles'; }
                                else { echo $b['address'] ? escapeHtml(implode(', ', array_slice(explode(',',$b['address']),-3))) : 'Address N/A'; }
                            ?></span>
                        </div>
                        <?php if($b['phone']): ?>
                        <div class="flex items-center space-x-1">
                            <i data-lucide="phone" class="w-3 h-3"></i>
                            <span><?php echo escapeHtml($b['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="font-medium"><?php echo escapeHtml($b['price_range']); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-12">
    <div class="w-20 h-20 bg-muted rounded-full flex items-center justify-center mx-auto mb-6">
        <i data-lucide="search" class="w-8 h-8 text-muted-foreground"></i>
    </div>
    <h3 class="text-2xl font-semibold text-foreground mb-4">No businesses found</h3>
    <p class="text-muted-foreground max-w-md mx-auto mb-6">Try adjusting your search terms or filters.</p>
    <a href="/search.php" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">Clear All Filters</a>
</div>
<?php endif; ?>

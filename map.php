<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';

$pageTitle = 'Map';
$pageDescription = 'Map of local businesses';

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
                'header' => "User-Agent: Mainstreet.lt/1.0\r\n",
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

    public function processBusinessesWithLocation($businesses, $searchLocation = null, $radius = null) {
        $searchCoords = null;
        if (!empty($searchLocation)) {
            $searchCoords = $this->geocodeAddress($searchLocation);
            if (!$searchCoords) {
                $searchCoords = null; // fail silently
            }
        }
    
        $filtered = [];
        $errors = [];
        
        /*
        "Isn't stupid if it works"
        -JSpencer, 30OCT 2025
        */
        foreach ($businesses as &$b) {
            $coords = $this->geocodeAddress($b['address']); 
            if ($coords) {
                $b['latitude'] = $coords['lat'];
                $b['longitude'] = $coords['lng'];
            } else {
                $b['latitude'] = 0;     // if there's a McDonalds off the coast of Africa,
                $b['longitude'] = 0;    // something is most definitely wrong.
                                        // not like it should ever get to this point
                $errors[] = $b['name'] . " (couldn't locate address)";
            }
        
            $b['distance'] = ($searchCoords && $b['latitude'] && $b['longitude'])
                ? $this->calculateDistance($searchCoords['lat'], $searchCoords['lng'], $b['latitude'], $b['longitude'])
                : null;
        
            if (!$radius || $b['distance'] === null || $b['distance'] <= $radius) {
                $filtered[] = $b;
            }
            
            $this->db->query("UPDATE businesses SET latitude = ?, longitude = ? WHERE id = ?", [
                $coords['lat'], $coords['lng'], $b['id']
            ]);
        }
        
        return ['businesses' => $filtered, 'errors' => $errors, 'searchCoords' => $searchCoords];
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

$businesses = $searchService->searchBusinesses($searchParams);

$locationResult = $searchService->processBusinessesWithLocation($businesses, $searchParams['location'], $searchParams['radius']);
$businesses = $locationResult['businesses'];
$geocodingErrors = $locationResult['errors'];
$searchCoords = $locationResult['searchCoords'];

if ($searchParams['sort'] === 'distance' && $searchCoords) {
    $businesses = $searchService->sortBusinessesByDistance($businesses);
}

$searchQuery = $searchParams['search'];
$locationQuery = $searchParams['location'];
$categoryId = $searchParams['category'];
$sortBy = $searchParams['sort'];
$priceRange = $searchParams['price'];
$radius = $searchParams['radius'];
$isOpen = $searchParams['open'];

include 'includes/header.php';
?>

<style>
  html, body, #map {
    height: 100%;
    width: 100%;
    margin: 0;
    padding: 0;
  }

  #map {
    position: absolute;
    top: 0; left: 0;
    z-index: 1;
  }

  #sidebar {
    position: absolute;
    top: 0; left: 0;
    width: 320px;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    z-index: 1000; /* ensures sidebar is above map */
    overflow-y: auto;
    padding: 1rem;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
  }

  .business-card {
    border-bottom: 1px solid #ccc;
    padding: 0.5rem 0;
  }

  .business-card:last-child {
    border-bottom: none;
  }
</style>

<script>
    window.businessData = <?php echo json_encode($businesses, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

    document.addEventListener("DOMContentLoaded", () => {
      const loadingModal = document.getElementById("loading-modal");
      if (loadingModal) {
        loadingModal.style.display = "flex"; // show immediately
      }
    });
</script>

<main class="h-screen w-screen">
    <!-- Loading Overlay -->
    <div id="loading-modal" class="fixed inset-0 bg-card/70 backdrop-blur-lg flex items-center justify-center z-50">
        <div class="bg-card px-6 py-4 rounded-lg shadow-lg text-center border border-border">
            <svg class="animate-spin h-6 w-6 text-primary mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <p class="text-sm text-foreground">Detecting your location...</p>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="hidden md:flex absolute top-20 right-8 z-20 bg-card/40 backdrop-blur-sm rounded-lg shadow px-3 py-2 items-center space-x-2">
      <input 
        id="map-search-input"
        type="text" 
        placeholder="Search business or location..."
        class="w-full px-3 py-1 bg-input border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm"
      />
      <button id="map-search-btn" class="px-3 py-1 bg-primary text-white rounded text-sm">
        Search
      </button>
    </div>

    <!-- Fullscreen Map -->
    <div id="map" class="absolute inset-0 -mt-20 pt-32 pb-12" style="filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);"></div>

    <div id="map-sidebar-wrapper" 
     class="absolute top-0 left-0 h-full flex z-30 transform transition-transform duration-300 -translate-x-full md:translate-x-0">
    
      <!-- Sidebar -->
      <div id="map-sidebar" 
           class="relative w-80 bg-card/40 backdrop-blur-sm mt-16 shadow-lg h-full overflow-y-auto">
           
        <!-- Mobile searchbar -->
        <div class="p-4 border-b border-border md:hidden">
          <div class="flex space-x-2">
            <input 
              id="map-search-input-mobile"
              type="text"
              placeholder="Search business or location..."
              class="flex-1 px-3 py-2 bg-input border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm"
            />
            <button id="map-search-btn-mobile" class="px-3 py-2 bg-primary text-white rounded text-sm">
              Go
            </button>
          </div>
        </div>
    
        <!-- Filters -->
        <div class="p-6">
            <h3 class="font-semibold text-foreground mb-4">Filters</h3>
            <form method="GET" class="space-y-6" onsubmit="return cleanFormSubmission(this);">
                <?php if ($searchQuery): ?>
                <input type="hidden" name="q" value="<?php echo escapeHtml($searchQuery); ?>">
                <?php endif; ?>
                
                
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
      <button id="map-sidebar-toggle" 
              class="md:hidden bg-primary text-white px-3 py-2 rounded-r-lg shadow absolute top-1/2 -translate-y-1/2 -right-3 z-40">
        &raquo;
      </button>

    </div>

</main>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

<script>
document.addEventListener("DOMContentLoaded",function(){let e=L.map("map").setView([37.8,-96],4),t=document.getElementById("loading-modal"),a=document.querySelector("form"),n=a.querySelector('input[name="location"]'),s=[];async function i(){let t=document.getElementById("map-search-input").value.trim();if(!t)return;let a=window.businessData.find(e=>e.name.toLowerCase()===t.toLowerCase());if(a&&a.latitude&&a.longitude){e.setView([a.latitude,a.longitude],16);return}try{let n=`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(t)}`,s=await fetch(n,{headers:{"User-Agent":"Mainstreet.lt/1.0"}}),i=await s.json();i&&i[0]?e.setView([parseFloat(i[0].lat),parseFloat(i[0].lon)],13):alert("No results found.")}catch(o){console.error("Geocode failed:",o)}}async function o(a={}){t&&(t.style.display="flex");let n=new URL("/search-ajax.php",window.location.origin);Object.keys(a).forEach(e=>{""!==a[e]&&null!==a[e]&&n.searchParams.set(e,a[e])});try{let i=await fetch(n),o=await i.json();!function t(a,n=null){s.forEach(t=>e.removeLayer(t)),s=[];let i={};a.forEach(t=>{if(!t.latitude||!t.longitude)return;let a=parseFloat(t.latitude),n=parseFloat(t.longitude),o=`${a.toFixed(5)},${n.toFixed(5)}`;if(i[o]=(i[o]||0)+1,i[o]>1){let l=2*Math.random()*Math.PI,r=3e-4*Math.random();a+=r*Math.cos(l),n+=r*Math.sin(l)}let d=L.marker([a,n]).addTo(e).bindPopup(`
                <div class="flex items-start justify-between mb-2"> 
                    <div> 
                        <h3 class="text-xl font-semibold mb-1 whitespace-nowrap overflow-hidden text-ellipsis mr-4">${t.name}</h3> 
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-secondary/20 text-secondary"> ${t.category_name||""} </span> 
                    </div> 
                    <div class="text-right"> 
                        <div class="flex items-center space-x-1 mb-1"> 
                            ${function e(t){let a="";for(let n=1;n<=5;n++)n<=t?a+='<span class="text-yellow-400">★</span>':a+='<span class="text-gray-300">★</span>';return a}(t.average_rating)} 
                            <span class="text-sm font-semibold">${Number(t.average_rating).toFixed(1)}</span> 
                        </div> 
                        <p class="text-xs text-muted-foreground">${t.total_reviews} reviews</p> 
                    </div> 
                </div> 
                <a href="/business?id=${encodeURIComponent(t.id)}">View</a>
                `);d.on("popupopen",function(e){e.popup.getElement(),setTimeout(()=>e.popup.update(),0)}),s.push(d)}),n&&e.setView([n.lat,n.lng],14)}(o.businesses||[],o.searchCoords||null)}catch(l){console.error(l)}finally{t&&(t.style.display="none")}}async function l(){if(!n||""!==n.value.trim()||function e(){try{return"1"===sessionStorage.getItem("geoAutoSubmitted")}catch(t){return!1}}())return!1;t&&(t.style.display="flex");try{let e=await new Promise((e,t)=>{navigator.geolocation.getCurrentPosition(e,t,{timeout:8e3})}),a=e.coords.latitude,s=e.coords.longitude,i=await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${a}&lon=${s}`),o=await i.json(),l=o.address?.postcode,r=o.address?.city||o.address?.town||o.address?.village,d=o.address?.state,c=l||(r&&d?`${r}, ${d}`:r||"");if(c&&(n.value=c,function e(){try{sessionStorage.setItem("geoAutoSubmitted","1")}catch(t){}}(),!window.location.search.includes("location=")))return n.closest("form")?.submit(),!0}catch(u){console.log("Geolocation failed:",u)}finally{t&&(t.style.display="none")}return!1}a.addEventListener("submit",function(e){e.preventDefault();let t=new FormData(a),n={};for(let[s,i]of t.entries())n[s]=i;o(n)}),document.getElementById("map-search-btn").addEventListener("click",i),document.getElementById("map-search-input").addEventListener("keypress",e=>{"Enter"===e.key&&(e.preventDefault(),i())}),(async()=>{t&&(t.style.display="flex"),L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"\xa9 OpenStreetMap contributors"}).addTo(e),navigator.geolocation?navigator.geolocation.getCurrentPosition(t=>{let a=t.coords.latitude,n=t.coords.longitude;e.setView([a,n],13)},t=>{console.warn("Geolocation failed, fallback to nationwide"),e.setView([37.8,-96],4)}):e.setView([37.8,-96],4);let n=new FormData(a),s={};for(let[i,l]of n.entries())s[i]=l;o(s),t&&(t.style.display="hidden")})()}),document.addEventListener("DOMContentLoaded",()=>{let e=document.getElementById("map-sidebar-wrapper"),t=document.getElementById("map-sidebar-toggle"),a=!1;t.addEventListener("click",()=>{a=!a,e.classList.toggle("-translate-x-full"),t.innerHTML=a?"&laquo;":"&raquo;"});let n=document.getElementById("map-search-btn-mobile"),s=document.getElementById("map-search-input-mobile");n&&n.addEventListener("click",()=>{document.getElementById("map-search-input").value=s.value,document.getElementById("map-search-btn").click(),e.classList.add("-translate-x-full"),t.innerHTML="&raquo;",a=!1}),s&&s.addEventListener("keypress",e=>{"Enter"===e.key&&(e.preventDefault(),n.click())})});

</script>

<script>
lucide.createIcons();
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

@media (max-width: 768px) {
  #map-sidebar {
    width: 100%; /* full screen drawer on mobile */
    max-width: 90%;
  }
}
</style>
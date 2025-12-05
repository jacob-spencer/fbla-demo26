<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'functions.php';
require_once 'includes/shader-tool.php';

$pageTitle = 'Search';
$pageDescription = 'Search for local businesses';

// Fetch categories for filter
$db = Database::getInstance();
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name ASC");

$searchQuery = $_GET['q'] ?? '';
$locationQuery = $_GET['location'] ?? '';
$categoryId = $_GET['category'] ?? '';
$sortBy = $_GET['sort'] ?? 'rating';
$priceRange = $_GET['price'] ?? '';
$radius = $_GET['radius'] ?? '';
$isOpen = array_key_exists('open', $_GET) ? ($_GET['open']===''?null:(bool)$_GET['open']) : null;

include 'includes/header.php';
?>

<?php getLiquidGlassStyles(); ?>

<main class="min-h-screen">
    
<div>
    <div class="content-extern">
    <!-- Loading Overlay -->
            <div id="geo-loading-overlay" class="fixed inset-0 bg-background/70 backdrop-blur-lg flex items-center justify-center z-50 hidden">
                <div class="bg-card px-6 py-4 rounded-lg shadow-lg text-center border border-border">
                    <svg class="animate-spin h-6 w-6 text-primary mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <p id="loader-text" class="text-sm text-foreground">Loading...</p>
                </div>
            </div>
        
            <!-- Search Header -->
            <section class="text-white pt-36 pb-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 class="text-4xl md:text-5xl font-bold mb-4">
                        Search <span class="text-accent">Businesses</span>
                    </h1>
                </div>
                
                <div class="max-w-4xl mx-auto rounded-2xl p-6 liquid-glass shadow-lg">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <form id="searchForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="text" name="q" value="<?php echo escapeHtml($searchQuery); ?>" placeholder="What are you looking for?" class="w-full px-4 py-2 bg-card/20 backdrop-blur-sm border border-border rounded-lg">
                            <button type="submit" class="w-full px-4 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg">Search</button>
                        </form>
                    </div>
                </div>
            </section>
        
            <section class="py-8">
                <div id="contentDiv" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
                        <!-- Filters Sidebar -->
                        <div class="lg:col-span-1">
                            <div class="liquid-glass rounded-xl p-6 sticky top-8">
                                <h3 class="font-semibold text-foreground mb-4">Filters</h3>
                                <form id="filtersForm" class="space-y-6">
                                    <?php if ($searchQuery): ?>
                                    <input type="hidden" name="q" value="<?php echo escapeHtml($searchQuery); ?>">
                                    <?php endif; ?>
        
                                    <!-- Location & Distance Filter -->
                                    <div>
                                        <label class="text-sm font-medium text-foreground mb-2 block">Location & Distance</label>
                                        <input type="text" id="locationInput" name="location" value="<?php echo escapeHtml($locationQuery); ?>" placeholder="Enter ZIP code" class="w-full px-3 py-2 bg-card/40 backdrop-blur-sm border border-border rounded-lg mb-2">
                                        <select name="radius" class="w-full px-3 py-2 bg-card border border-border rounded-lg mb-2">
                                            <option value="">Any Distance</option>
                                            <?php foreach ([1,5,10,25,50,100] as $option): ?>
                                            <option value="<?php echo $option; ?>" <?php echo $radius==$option?'selected':''; ?>>Within <?php echo $option; ?> mile<?php echo $option!==1?'s':''; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <!-- NEW: "Use My Location" button -->
                                        <button type="button" id="useLocationBtn" class="w-full px-4 py-2 bg-primary hover:bg-secondary/80 text-foreground font-medium rounded-lg transition">
                                            <i data-lucide="map-pin" class="w-4 h-4 inline-block mr-1"></i> Use My Location
                                        </button>
                                    </div>
        
                                    <!-- Category -->
                                    <div>
                                        <label class="text-sm font-medium text-foreground mb-2 block">Category</label>
                                        <select name="category" class="w-full px-3 py-2 bg-card border border-border rounded-lg">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo escapeHtml($category['slug']); ?>" <?php echo $categoryId==$category['slug']?'selected':''; ?>>
                                                <?php echo escapeHtml($category['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
        
                                    <!-- Sort By -->
                                    <div>
                                        <label class="text-sm font-medium text-foreground mb-2 block">Sort By</label>
                                        <select name="sort" class="w-full px-3 py-2 bg-card border border-border rounded-lg">
                                            <?php 
                                            $sortOptions = ['rating'=>'Highest Rated','reviews'=>'Most Reviews','newest'=>'Newest','alpha-asc'=>'A-Z','alpha-desc'=>'Z-A'];
                                            if ($locationQuery) $sortOptions = array_merge(['distance'=>'Nearest'], $sortOptions);
                                            foreach($sortOptions as $value=>$label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo $sortBy==$value?'selected':''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
        
                                    <!-- Price Range -->
                                    <div>
                                        <label class="text-sm font-medium text-foreground mb-2 block">Price Range</label>
                                        <select name="price" class="w-full px-3 py-2 bg-card border border-border rounded-lg">
                                            <option value="">All Prices</option>
                                            <option value="$">$</option>
                                            <option value="$$">$$</option>
                                            <option value="$$$">$$$</option>
                                            <option value="$$$$">$$$$</option>
                                        </select>
                                    </div>
        
                                    <!-- Status -->
                                    <div>
                                        <label class="text-sm font-medium text-foreground mb-2 block">Status</label>
                                        <select name="open" class="w-full px-3 py-2 bg-card border border-border rounded-lg">
                                            <option value="">All Businesses</option>
                                            <option value="1" <?php echo $isOpen===true?'selected':''; ?>>Open Now</option>
                                            <option value="0" <?php echo $isOpen===false?'selected':''; ?>>Closed</option>
                                        </select>
                                    </div>
        
                                    <button type="submit" class="w-full px-4 py-2 bg-primary text-primary-foreground hover:bg-secondary/80 text-foreground font-medium rounded-lg transition">
                                        <i data-lucide="filter" class="w-4 h-4 inline-block mr-1"></i> Apply Filters
                                    </button>
                                </form>
                            </div>
                        </div>
        
                        <!-- Results -->
                        <div class="lg:col-span-3" id="resultsContainer"></div>
                    </div>
                </div>
            </section>
            </div>
    </div>
</main>

<?php renderLiquidGlassShader('/assets/purple.jpg', 1.2, 1.2); ?>

<script>
function loadWaves(){}function addParam(e,t){let r=new URL(window.location.href);r.searchParams.set(e,t),window.history.pushState({},"",r.toString())}function renderStars(e){let t="";for(let r=1;r<=5;r++)t+=r<=e?'<span class="text-yellow-400">★</span>':'<span class="text-gray-300">★</span>';return t}lucide.createIcons();let currentPage=1;function loadResults(e={}){let t=document.getElementById("geo-loading-overlay"),r=document.getElementById("resultsContainer"),a=document.getElementById("loader-text");t.classList.remove("hidden"),a.innerHTML="Searching...",r.innerHTML='<p class="text-center text-muted-foreground py-12">Loading...</p>';let o=new URL("/search-ajax.php",window.location.origin);Object.keys(e).forEach(t=>{let r=e[t];""!==r&&null!=r&&(o.searchParams.set(t,r),addParam(t,r))}),o.searchParams.set("paginate_result",!0),fetch(o).then(e=>e.json()).then(a=>{if(t.classList.add("hidden"),!a.businesses||!a.businesses.length){r.innerHTML='<p class="text-center py-12 text-muted-foreground">No results found.</p>';return}let o="";a.businesses.forEach(e=>{let t=Number(e.average_rating||0);o+=`
            <div class="group liquid-glass hover:bg-card/20 rounded-xl hover:border-primary/50 overflow-hidden mb-4 cursor-pointer" onclick="window.location.href='/business?id=${e.id}'">
                <div class="flex h-40">
                    <img src="${e.image_url||"fbla-waves.png"}" alt="${e.name}" class="w-48 h-full object-cover flex-shrink-0">
                    <div class="flex-1 p-6">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h3 class="text-xl font-semibold text-foreground mb-1">${e.name}</h3>
                                <span class="inline-block bg-secondary/20 text-secondary text-xs font-medium px-2.5 py-0.5 rounded-full">${e.category_name}</span>
                            </div>
                            <div class="text-right">
                                <div class="flex items-center space-x-1 mb-1">
                                    ${renderStars(t)} <span class="text-sm font-semibold">${t.toFixed(1)}</span>
                                </div>
                                <p class="text-xs text-muted-foreground">${e.total_reviews} reviews</p>
                            </div>
                        </div>
                        <p class="text-sm text-foreground mb-3">${e.description?e.description.substring(0,90)+(e.description.length>90?"...":""):""}</p>
                        <div class="flex justify-between items-center text-sm text-muted-foreground">
                            <div class="flex items-center space-x-3">
                                <span><i data-lucide="map-pin" class="w-3 h-3 inline"></i> ${null!==e.distance?e.distance.toFixed(1)+" mi":e.address||"Address N/A"}</span>
                                ${e.phone?`<span><i data-lucide="phone" class="w-3 h-3 inline"></i> ${e.phone}</span>`:""}
                            </div>
                            <span>${e.price_range||""}</span>
                        </div>
                    </div>
                </div>
            </div>`});let s=Math.ceil(a.total/a.per_page),n=Math.max(1,a.page-Math.floor(2.5)),d=n+5-1;d>s&&(n=Math.max(1,(d=s)-5+1)),o+='<div class="flex justify-center mt-6 space-x-2" id="pagination">',a.page>1?o+=`<button class="px-3 py-1 rounded-lg group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm transition-all duration-200 hover:border-primary/50 text-foreground" data-page="${a.page-1}">&laquo;</button>`:o+='<button class="px-3 py-1 rounded-lg group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm transition-all duration-200 hover:border-primary/50 text-foreground text-foreground" data-page="1">&laquo;</button>',n>1&&(o+='<button class="px-3 py-1 rounded-lg group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm transition-all duration-200 hover:border-primary/50 text-foreground text-foreground" data-page="1">1</button>',n>2&&(o+=`<span class="px-2 py-1 text-gray-500">…</span>`));for(let l=n;l<=d;l++)o+=`<button class="px-3 py-1 rounded-lg ${l===a.page?"group bg-primary/40 backdrop-blur-sm hover:bg-primary/60 backdrop-blur-sm transition-all duration-200 hover:border-primary/50 text-foreground text-white":"group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm transition-all duration-200 hover:border-primary/50 text-foreground text-foreground"}" data-page="${l}">${l}</button>`;d<s&&(d<s-1&&(o+=`<span class="px-2 py-1 text-gray-500">…</span>`),o+=`<button class="px-3 py-1 rounded-lg group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm rounded-lg transition-all duration-200 hover:border-primary/50 text-foreground text-foreground" data-page="${s}">${s}</button>`),a.page<s?o+=`<button class="px-3 py-1 rounded-lg group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm transition-all duration-200 hover:border-primary/50 text-foreground text-foreground" data-page="${a.page+1}">&raquo;</button>`:o+=`<button class="px-3 py-1 rounded-lg group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm transition-all duration-200 hover:border-primary/50 text-foreground text-foreground" data-page="${a.page}">&raquo;</button>`,o+="</div>",r.innerHTML=o,lucide.createIcons(),t.classList.add("hidden");let i=r.querySelectorAll("#pagination button");i.forEach(t=>{t.addEventListener("click",r=>{let o=parseInt(t.getAttribute("data-page"));if(o===a.page)return;let s={...e,page:o};loadResults(s),window.scrollTo({top:0,behavior:"smooth"})})}),loadWaves()}).catch(e=>{t.classList.add("hidden"),r.innerHTML='<p class="text-center text-red-500 py-12">Error loading results.</p>',console.error(e)})}function handleUseMyLocation(){let e=document.getElementById("geo-loading-overlay"),t=document.getElementById("loader-text"),r=document.getElementById("locationInput");if(e.classList.remove("hidden"),t.textContent="Detecting your location...",!navigator.geolocation){alert("Geolocation not supported."),e.classList.add("hidden");return}navigator.geolocation.getCurrentPosition(t=>{fetch(`https://nominatim.openstreetmap.org/reverse?lat=${t.coords.latitude}&lon=${t.coords.longitude}&format=json`).then(e=>e.json()).then(t=>{let a=t.address?.postcode||"";r.value=a,e.classList.add("hidden"),loadResults(Object.fromEntries(new FormData(document.getElementById("filtersForm"))))}).catch(t=>{console.error(t),e.classList.add("hidden")})},t=>{console.warn(t),e.classList.add("hidden")})}document.addEventListener("DOMContentLoaded",()=>{let e=document.getElementById("searchForm"),t=document.getElementById("filtersForm"),r=document.getElementById("useLocationBtn"),a=e=>Object.fromEntries(new FormData(e).entries());e.addEventListener("submit",t=>{t.preventDefault(),loadResults(a(e))}),t.addEventListener("submit",e=>{e.preventDefault(),loadResults(a(t))}),r.addEventListener("click",handleUseMyLocation),loadResults(Object.fromEntries(new URLSearchParams(window.location.search)))});
</script>

<?php include 'includes/footer.php'; ?>

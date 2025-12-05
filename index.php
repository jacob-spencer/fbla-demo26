<?php
require_once 'functions.php';
require_once 'includes/shader-tool.php';

$pageTitle = 'Home';
$pageDescription = 'Discover and connect with local businesses in your area';

// Get featured businesses
$db = Database::getInstance();
$featuredBusinesses = $db->fetchAll("
    SELECT b.*, c.name as category_name, c.icon as category_icon 
    FROM businesses b 
    LEFT JOIN categories c ON b.category_id = c.id 
    ORDER BY b.average_rating DESC, b.total_reviews DESC 
    LIMIT 4
");

// Get categories
$categories = $db->fetchAll("
    SELECT * FROM categories 
    ORDER BY name ASC 
    LIMIT 8
");

// Get featured deals
$deals = $db->fetchAll("
    SELECT d.*, b.name as business_name, b.image_url as business_image 
    FROM deals d 
    LEFT JOIN businesses b ON d.business_id = b.id 
    WHERE d.is_active = 1 AND d.expires_at > NOW() 
    ORDER BY d.created_at DESC 
    LIMIT 6
");

include 'includes/header.php';
?>

<style>
body {
    background-image: url("/assets/purple.jpg");
    background-repeat: no-repeat;
    background-size: 200% 200%;
    background-position: center center;
    background-attachment: fixed; /* Fixes the background */
}
</style>

<?php getLiquidGlassStyles(); ?>

<div>
    <div class="content-extern">
        <section class="text-white pt-40">
    <div class="max-w-7xl mx-auto text-center z-20 pb-10">
        <h1 class="text-5xl md:text-6xl font-bold mb-6">
            Discover Local
            <span class="text-accent">Businesses</span>
        </h1>
        <p class="text-xl md:text-2xl text-white/90 mb-8 max-w-3xl mx-auto">
            Connect with amazing local businesses in your community. Find services, read reviews, and discover exclusive deals.
        </p>
        
        <div class="max-w-4xl liquid-glass mx-auto rounded-2xl p-6 shadow-lg">
            <form action="/search" method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" name="q" placeholder="What are you looking for?" 
                               class="w-full pl-12 py-3 bg-card/20 border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                               <i data-lucide="search" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-muted-foreground w-4 h-4"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="relative">
                        
                        <input type="text" name="location" placeholder="Enter your ZIP" 
                               class="w-full pl-12 py-3 bg-card/20 border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                               <i data-lucide="map-pin" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-muted-foreground w-4 h-4"></i>
                    </div>
                </div>
                <button type="submit" class="px-8 py-3 font-semibold bg-accent hover:bg-accent/90 text-black rounded-lg transition-colors">
                    Search
                </button>
            </form>
        </div>
    </div>
    
    <div class="w-full h-full pt-10 pb-10"> 
    
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-foreground mb-4">Browse by Category</h2>
                <p class="text-muted-foreground">Find businesses by what you're looking for</p>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($categories as $category): ?>
                    <a href="/search?category=<?php echo urlencode($category['slug']); ?>" 
                       class="group liquid-glass hover:bg-card/20 rounded-xl p-6 text-center transition-all duration-200 hover:border-primary/50">
                        <div class="w-16 h-16 bg-primary/10 group-hover:bg-primary/20 backdrop-blur-sm rounded-xl flex items-center justify-center mx-auto mb-4 transition-colors">
                            <i data-lucide="<?php echo escapeHtml($category['icon']); ?>" class="w-8 h-8 text-primary"></i>
                        </div>
                        <h3 class="font-semibold text-foreground group-hover:text-primary transition-colors">
                            <?php echo escapeHtml($category['name']); ?>
                        </h3>
                    </a>
                    <?php endforeach; ?>
                </div>
        </div>
        
        <?php if (!empty($deals)): ?>
            <div class="max-w-7xl mx-auto mt-40 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between mb-12">
                    <div>
                        <h2 class="text-3xl font-bold text-foreground mb-4">Featured Deals</h2>
                        <p class="text-muted-foreground">Don't miss out on these amazing offers</p>
                    </div>
                    <a href="/deals" class="px-4 py-2 liquid-glass rounded-lg hover:bg-card/20 transition-all duration-200 transition-colors">
                        View All
                        <i data-lucide="arrow-right" class="w-4 h-4 ml-2 inline"></i>
                    </a>
                </div>
                
        
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($deals as $deal): ?>
                    <div class="group liquid-glass hover:bg-card/20 rounded-xl transition-all duration-200 hover:border-primary/50 overflow-hidden">
                        <div class="relative">
                            <img src="<?php echo escapeHtml($deal['business_image'] ?: 'fbla-waves.png'); ?>" 
                                 alt="<?php echo escapeHtml($deal['business_name']); ?>" 
                                 class="w-full h-48 object-cover">
                            <div class="absolute top-3 left-3 bg-secondary text-secondary-foreground px-3 py-1 rounded-full text-sm font-semibold">
                                <?php echo escapeHtml($deal['discount_value']); ?> OFF
                            </div>
                        </div>
                        <div class="p-6">
                            <h3 class="font-semibold text-foreground mb-2"><?php echo escapeHtml($deal['title']); ?></h3>
                            <p class="text-muted-foreground text-sm mb-3"><?php echo escapeHtml($deal['business_name']); ?></p>
                            <p class="text-sm text-foreground mb-4"><?php echo escapeHtml($deal['description']); ?></p>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-muted-foreground">
                                    Expires <?php echo date('M j, Y', strtotime($deal['expires_at'])); ?>
                                </span>
                                <a href="/business?id=<?php echo urlencode($deal['business_id']); ?>" 
                                   class="text-primary hover:text-primary/80 font-medium text-sm">
                                    View Business
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        <?php endif; ?>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-40">
            <div class="flex items-center justify-between mb-12">
                <div>
                    <h2 class="text-3xl font-bold text-foreground mb-4">Featured Businesses</h2>
                    <p class="text-muted-foreground">Discover highly rated businesses in your area</p>
                </div>
                
                <a href="/discover" class="px-4 py-2 liquid-glass rounded-lg hover:bg-card/20 transition-all duration-200 transition-colors">
                    View All
                    <i data-lucide="arrow-right" class="w-4 h-4 ml-2 inline"></i>
                </a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($featuredBusinesses as $business): ?>
                <div class="group liquid-glass hover:bg-card/20 rounded-xl transition-all duration-200 hover:border-primary/50 overflow-hidden"
                     onclick="window.location.href='/business?id=<?php echo urlencode($business['id']); ?>'">
                    <div class="flex h-56">
                        <div class="relative flex-shrink-0">
                            <img src="<?php echo escapeHtml($business['image_url'] ?: 'fbla-waves.png'); ?>" 
                                 alt="<?php echo escapeHtml($business['name']); ?>" 
                                 class="w-56 h-full object-cover" loading="lazy">
                            <button class="absolute top-2 right-2 w-7 h-7 bg-secondary/90 hover:bg-secondary rounded-full flex items-center justify-center">
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
                            <p class="text-foreground mb-3 text-sm"><?php echo escapeHtml(substr($business['description'], 0, 100)) . (strlen($business['description']) > 100 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between text-sm text-muted-foreground">
                                <div class="flex items-center space-x-1">
                                    <i data-lucide="map-pin" class="w-3 h-3"></i>
                                    <span>0.5 miles</span>
                                </div>
                                <span class="font-medium"><?php echo escapeHtml($business['price_range']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    
    </div>
</section>
    </div>
</div>

<script>
  const PUBLIC_VAPID_KEY = <?php echo json_encode(PUBLIC_VAPID_KEY); ?>;
</script>

<?php renderLiquidGlassShader('/assets/purple.jpg', 1.2, 1.2); ?>

<script>
lucide.createIcons();if ('serviceWorker' in navigator) {window.addEventListener('load', () => {navigator.serviceWorker.register('/service-worker.js').then((registration) => {console.log('Service Worker registered successfully:', registration.scope);}).catch((error) => {console.log('Service Worker registration failed:', error);});});} async function subscribeUser() {console.log("subscribing user...");if (!('serviceWorker' in navigator) || !('PushManager' in window)) {console.log('Push notifications not supported');return;}try {const registration = await navigator.serviceWorker.ready;const subscription = await registration.pushManager.subscribe({userVisibleOnly: true,applicationServerKey: urlBase64ToUint8Array(PUBLIC_VAPID_KEY)});const response = await fetch('/api/subscribe.php', {method: 'POST',headers: {'Content-Type': 'application/json',},body: JSON.stringify({subscription: subscription.toJSON()})});if (response.ok) {console.log('Successfully subscribed to push notifications');}} catch (error) {console.error('Failed to subscribe:', error);}} function urlBase64ToUint8Array(base64String) {const padding = '='.repeat((4 - base64String.length % 4) % 4);const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');const rawData = window.atob(base64);const outputArray = new Uint8Array(rawData.length);for (let i = 0; i < rawData.length; ++i) {outputArray[i] = rawData.charCodeAt(i);}return outputArray;}let hasPrompted = false;function tryPromptNotification() {if (hasPrompted) return;hasPrompted = true;if (Notification.permission === "default") {Notification.requestPermission().then(permission => {if (permission === "granted") {subscribeUser();} else {console.log("User denied notifications");}});} else if (Notification.permission === "granted") {subscribeUser();}} document.addEventListener("click", tryPromptNotification);document.addEventListener("keydown", tryPromptNotification);document.addEventListener("scroll", tryPromptNotification);document.addEventListener("mousemove", tryPromptNotification);
</script>

<?php include 'includes/footer.php'; ?>
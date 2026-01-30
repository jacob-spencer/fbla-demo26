<?php
require_once 'functions.php';

$pageTitle = 'Discover';
$pageDescription = 'Discover amazing local businesses in your community';

$db = Database::getInstance();

// Get all categories
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name ASC");

// Get featured businesses
$featuredBusinesses = $db->fetchAll("
    SELECT b.*, c.name as category_name, c.icon as category_icon 
    FROM businesses b 
    LEFT JOIN categories c ON b.category_id = c.id 
    ORDER BY b.average_rating DESC, b.total_reviews DESC 
    LIMIT 4
");

include 'includes/header.php';
?>

<section class="text-white pt-36 pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="geist text-4xl md:text-5xl font-bold mb-4">
            Discover Local <span class="text-accent">Businesses</span>
        </h1>
        <p class="text-xl text-white/90 mb-8 max-w-2xl mx-auto">
            Explore amazing businesses in your community and find exactly what you're looking for
        </p>
        <a href="/search" class="inline-flex items-center px-6 py-3 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors">
            Start Exploring
            <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
        </a>
    </div>
</section>

<!-- Categories Grid -->
<section class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="geist text-3xl font-bold text-foreground mb-4">Browse Categories</h2>
            <p class="text-muted-foreground">Find businesses by category</p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($categories as $category): ?>
            <a href="/search?category=<?php echo urlencode($category['slug']); ?>" 
               class="group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm rounded-xl p-6 text-center transition-all duration-200 border border-border hover:border-primary/50">
                <div class="w-16 h-16 bg-primary/10 group-hover:bg-primary/20 rounded-xl flex items-center justify-center mx-auto mb-4 transition-colors">
                    <i data-lucide="<?php echo escapeHtml($category['icon']); ?>" class="w-8 h-8 text-primary"></i>
                </div>
                <h3 class="font-semibold text-foreground group-hover:text-primary transition-colors">
                    <?php echo escapeHtml($category['name']); ?>
                </h3>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 py-20 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-12">
            <div>
                <h2 class="geist text-3xl font-bold text-foreground mb-4">Featured Businesses</h2>
                <p class="text-muted-foreground">Discover highly rated businesses in your area</p>
            </div>
            <a href="/search" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                View All
                <i data-lucide="arrow-right" class="w-4 h-4 ml-2 inline"></i>
            </a>
        </div>
        
        <?php if (!empty($featuredBusinesses)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($featuredBusinesses as $business): ?>
            <div class="group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm rounded-xl transition-all duration-200 border border-border hover:border-primary/50 overflow-hidden"
                 onclick="window.location.href='/business?id=<?php echo urlencode($business['id']); ?>'">
                <div class="flex h-40">
                    <div class="relative flex-shrink-0">
                        <img src="<?php echo escapeHtml($business['image_url'] ?: 'fbla-waves.png'); ?>" 
                             alt="<?php echo escapeHtml($business['name']); ?>" 
                             class="w-48 h-full object-cover">
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
        <?php else: ?>
        <div class="text-center py-12">
            <div class="w-20 h-20 bg-muted rounded-full flex items-center justify-center mx-auto mb-6">
                <i data-lucide="star" class="w-8 h-8 text-muted-foreground"></i>
            </div>
            <h3 class="text-2xl font-semibold text-foreground mb-4">No businesses yet</h3>
            <p class="text-muted-foreground">Check back soon as more businesses join our platform!</p>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="max-w-4xl mx-auto text-center px-4 py-20 sm:px-6 lg:px-8">
        <h2 class="geist text-3xl font-bold text-foreground mb-6">
            Can't find what you're looking for?
        </h2>
        <p class="text-xl text-muted-foreground mb-8">
            Use our advanced search to filter by location, price range, ratings, and more
        </p>
        <a href="/search" class="inline-flex items-center px-6 py-3 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-lg transition-colors">
            Try Advanced Search
            <i data-lucide="search" class="w-4 h-4 ml-2"></i>
        </a>
    </div>
</section>

<script>
lucide.createIcons();
</script>

<?php include 'includes/footer.php'; ?>
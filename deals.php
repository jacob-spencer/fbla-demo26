<?php
require_once 'functions.php';

$pageTitle = 'Deals';
$pageDescription = 'Discover exclusive deals and offers from local businesses';

$db = Database::getInstance();

// Get all active deals
$deals = $db->fetchAll("
    SELECT d.*, b.name as business_name, b.image_url as business_image, 
           b.address as business_address, c.name as category_name
    FROM deals d 
    LEFT JOIN businesses b ON d.business_id = b.id 
    LEFT JOIN categories c ON b.category_id = c.id
    WHERE d.is_active = 1 AND d.expires_at > NOW() 
    ORDER BY d.created_at DESC
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

<main class="min-h-screen">
    <!-- Hero Section -->
    <section class="text-white pt-36 pb-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">
                Exclusive <span class="text-accent">Deals</span>
            </h1>
            <p class="text-xl text-white/90 max-w-2xl mx-auto">
                Discover amazing offers from local businesses in your community
            </p>
        </div>
    </section>

    <!-- Deals Grid -->
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <?php if (!empty($deals)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($deals as $deal): ?>
                <div class="group bg-card/40 backdrop-blur-sm hover:bg-card/60 backdrop-blur-sm rounded-xl transition-all duration-200 border border-border hover:border-primary/50 overflow-hidden">
                    <div class="relative">
                        <img src="<?php echo escapeHtml($deal['business_image'] ?: 'fbla-waves.png'); ?>" 
                             alt="<?php echo escapeHtml($deal['business_name']); ?>" 
                             class="w-full h-48 object-cover">
                        <div class="absolute top-3 left-3 bg-secondary text-secondary-foreground px-3 py-1 rounded-full text-sm font-semibold">
                            <?php
                                $value = $deal['discount_type'] === 'percentage' ? escapeHtml($deal['discount_value']) . '%' : '$' . escapeHtml($deal['discount_value']);
                                echo $value . ' OFF';
                            ?>
                        </div>
                        <div class="absolute top-3 right-3 bg-accent text-black px-2 py-1 rounded text-xs font-medium">
                            <?php echo strtoupper(escapeHtml($deal['discount_type'])); ?>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="font-semibold text-foreground mb-2 text-lg"><?php echo escapeHtml($deal['title']); ?></h3>
                        <p class="text-muted-foreground text-sm mb-3"><?php echo escapeHtml($deal['business_name']); ?></p>
                        <p class="text-sm text-foreground mb-4"><?php echo escapeHtml($deal['description']); ?></p>
                        
                        <div class="flex items-center justify-between text-sm mb-4">
                            <span class="text-muted-foreground">
                                <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>
                                Expires <?php echo date('M j, Y', strtotime($deal['expires_at'])); ?>
                            </span>
                            <?php if ($deal['category_name']): ?>
                            <span class="bg-muted text-muted-foreground px-2 py-1 rounded text-xs">
                                <?php echo escapeHtml($deal['category_name']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <a href="/business?id=<?php echo urlencode($deal['business_id']); ?>" 
                               class="text-primary hover:text-primary/80 font-medium text-sm">
                                View Business
                            </a>
                            <button onclick="showDealCode('<?php echo escapeHtml($deal['id']); ?>', '<?php echo escapeHtml($deal['discount_code'] ?? 'SAVE' . rand(10, 99)); ?>')" 
                                    class="px-4 py-2 bg-accent hover:bg-accent/90 text-black font-medium rounded-lg transition-colors text-sm">
                                Get Deal
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-16">
                <div class="w-20 h-20 bg-card/40 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="tag" class="w-8 h-8 text-muted-foreground"></i>
                </div>
                <h3 class="text-2xl font-semibold text-foreground mb-4">No deals available</h3>
                <p class="text-muted-foreground max-w-md mx-auto mb-6">
                    Check back soon as local businesses add new exclusive offers and discounts!
                </p>
                <a href="/discover" class="px-4 py-2 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors">
                    Discover Businesses
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Deal Code Modal -->
<div id="dealModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-lg flex items-center justify-center z-50" style="display: none;">
    <div class="bg-card rounded-xl border border-border p-6 w-full max-w-md mx-4">
        <div class="text-center">
            <div class="w-16 h-16 bg-accent rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="tag" class="w-8 h-8 text-black"></i>
            </div>
            <h3 class="text-xl font-semibold text-foreground mb-2">Your Deal Code</h3>
            <p class="text-muted-foreground mb-6">Show this code to the business to claim your discount</p>
            
            <div class="bg-muted rounded-lg p-4 mb-6">
                <div class="text-2xl font-bold text-foreground tracking-wider" id="promoCode">
                    SAVE20
                </div>
            </div>
            
            <div class="flex space-x-3">
                <button onclick="copyPromoCode()" 
                        class="flex-1 px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                    Copy Code
                </button>
                <button onclick="closeDealModal()" 
                        class="flex-1 px-4 py-2 bg-accent hover:bg-accent/90 text-black font-medium rounded-lg transition-colors">
                    Done
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showDealCode(e,t){document.getElementById("promoCode").textContent=t,document.getElementById("dealModal").style.display="flex"}function closeDealModal(){document.getElementById("dealModal").style.display="none"}function copyPromoCode(){let e=document.getElementById("promoCode").textContent;navigator.clipboard.writeText(e).then(function(){let e=event.target,t=e.textContent;e.textContent="Copied!",e.classList.add("bg-green-100","text-green-800"),setTimeout(()=>{e.textContent=t,e.classList.remove("bg-green-100","text-green-800")},2e3)})}lucide.createIcons();
</script>

<?php include 'includes/footer.php'; ?>
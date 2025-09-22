<?php
require_once 'functions.php';

requireAuth();
$user = getCurrentUser();

$pageTitle = 'Bookmarks';
$pageDescription = 'Your saved businesses';

$db = Database::getInstance();

// Get user's bookmarked businesses
$bookmarks = $db->fetchAll("
    SELECT b.*, c.name as category_name, c.icon as category_icon, bk.created_at as bookmarked_at
    FROM bookmarks bk
    LEFT JOIN businesses b ON bk.business_id = b.id 
    LEFT JOIN categories c ON b.category_id = c.id 
    WHERE bk.user_id = ?
    ORDER BY bk.created_at DESC
", [$user['id']]);

include 'includes/header.php';
?>

<main class="min-h-screen bg-background">
    <section class="hero-gradient text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">
                Your <span class="text-accent">Bookmarks</span>
            </h1>
            
            <p class="text-muted-foreground">Businesses you've saved for later</p>
        </div>
    </section>

    <section class="px-8 py-8">
        <?php if (!empty($bookmarks)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($bookmarks as $business): ?>
                <div class="bg-card rounded-xl shadow-sm border border-border overflow-hidden business-card cursor-pointer hover:shadow-md transition-shadow"
                     onclick="window.location.href='/business.php?id=<?php echo urlencode($business['id']); ?>'">
                    <div class="flex h-40">
                        <div class="relative flex-shrink-0">
                            <img src="<?php echo escapeHtml($business['image_url'] ?: 'fbla-waves.png'); ?>" 
                                 alt="<?php echo escapeHtml($business['name']); ?>" 
                                 class="w-48 h-full object-cover">
                            <button onclick="event.stopPropagation(); removeBookmark('<?php echo $business['id']; ?>')" 
                                    class="absolute top-2 right-2 w-7 h-7 bg-accent hover:bg-accent/90 rounded-full flex items-center justify-center">
                                <i data-lucide="bookmark" class="w-3 h-3 text-black fill-current"></i>
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
                            <p class="text-foreground mb-3 text-sm"><?php echo escapeHtml(substr($business['description'], 0, 70)) . (strlen($business['description']) > 70 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between text-sm text-muted-foreground">
                                <div class="flex items-center space-x-1">
                                    <i data-lucide="bookmark-check" class="w-3 h-3"></i>
                                    <span>Saved <?php echo timeAgo($business['bookmarked_at']); ?></span>
                                </div>
                                <span class="font-medium"><?php echo escapeHtml($business['price_range']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-16">
                <div class="w-20 h-20 bg-muted rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="bookmark" class="w-8 h-8 text-muted-foreground"></i>
                </div>
                <h3 class="text-2xl font-semibold text-foreground mb-4">No bookmarks yet</h3>
                <p class="text-muted-foreground max-w-md mx-auto mb-6">
                    Start exploring businesses and bookmark your favorites to save them here for quick access.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="/discover.php" class="px-6 py-3 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-lg transition-colors">
                        Discover Businesses
                    </a>
                    <a href="/search.php" class="px-6 py-3 border border-border hover:bg-muted text-foreground rounded-lg transition-colors">
                        Search Now
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <div id="confirm-modal" class="fixed inset-0 flex items-center backdrop-blur-lg justify-center bg-black/50 z-50 hidden">
        <div class="bg-card rounded-xl p-6 max-w-sm w-full text-center relative shadow-lg">
            <p id="confirm-text" class="text-foreground text-sm mb-6"></p>
            <div class="flex justify-center space-x-4">
                <button id="confirm-no" class="px-6 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                    No
                </button>
                <button id="confirm-yes" class="px-6 py-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg">
                    Yes
                </button>
            </div>
        </div>
    </div>
</main>

<script>
lucide.createIcons();

    function showConfirm(message) {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirm-modal');
            const textEl = document.getElementById('confirm-text');
            const yesBtn = document.getElementById('confirm-yes');
            const noBtn = document.getElementById('confirm-no');
    
            textEl.textContent = message;
            modal.classList.remove('hidden');
    
            const cleanup = () => {
                modal.classList.add('hidden');
                yesBtn.onclick = null;
                noBtn.onclick = null;
            };
    
            yesBtn.onclick = () => {
                cleanup();
                resolve(true);
            };
    
            noBtn.onclick = () => {
                cleanup();
                resolve(false);
            };
    
            modal.onclick = (e) => {
                if (e.target === modal) {
                    cleanup();
                    resolve(false);
                }
            };
        });
    }
    
    async function handleDeleteBookmark(url) {
        const confirmed = await showConfirm("Are you sure you want to delete this bookmark?");
        if (confirmed) {
            window.location.href = url;
        }
    }

    async function removeBookmark(businessId) {
        const confirmed = await showConfirm("Are you sure you want to delete this bookmark?");
        if (confirmed) {
            fetch('/api/bookmarks.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    business_id: businessId,
                    action: 'remove'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    }

</script>

<?php include 'includes/footer.php'; ?>
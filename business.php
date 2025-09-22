<?php
    // Debug: Parse query string from environment if $_GET is empty
    if (empty($_GET) && isset($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $_GET);
    }
    
    require_once 'functions.php';
    
    // Get business ID from URL
    $businessId = isset($_GET['id']) ? sanitizeInput($_GET['id']) : '';
    if (!$businessId) {
        header('Location: /discover.php');
        exit;
    }
    
    $db = Database::getInstance();
    
    // Get business details
    $business = $db->fetch("
        SELECT b.*, c.name as category_name, c.icon as category_icon,
               u.first_name as owner_first_name, u.last_name as owner_last_name
        FROM businesses b 
        LEFT JOIN categories c ON b.category_id = c.id 
        LEFT JOIN users u ON b.owner_id = u.id
        WHERE b.id = ?
    ", [$businessId]);
    
    if (!$business) {
        header('Location: /discover.php');
        exit;
    }
    
    $eventId = generateUUID();
    $sessionId = session_id();
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    $pageUrl = $_SERVER['REQUEST_URI'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    $deviceType = preg_match('/mobile/i', $_SERVER['HTTP_USER_AGENT']) ? 'mobile' : 'desktop';
    $browser = getBrowser();
    $os = getOS();
    $country = null;
    $city = null;  
    $metadata = null; 
    
    $db->query("
        INSERT INTO analytics (
            id, business_id, event_type, event_timestamp, session_id,
            user_id, page_url, referrer_url, device_type, browser, os,
            country, city, metadata
        ) VALUES (
            ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ", [
        $eventId, $businessId, 'page_view', $sessionId,
        $userId, $pageUrl, $referrer, $deviceType, $browser, $os,
        $country, $city, $metadata
    ]);
    
    // Recalculate and update business ratings from actual reviews
    $actualReviews = $db->fetchAll("
        SELECT rating FROM reviews 
        WHERE business_id = ? AND is_verified = 1
    ", [$businessId]);
    
    if (!empty($actualReviews)) {
        $totalRating = 0;
        $reviewCount = count($actualReviews);
        
        foreach ($actualReviews as $review) {
            $totalRating += $review['rating'];
        }
        
        $averageRating = round($totalRating / $reviewCount, 2);
        
        // Update the business record with correct values
        $db->query("
            UPDATE businesses 
            SET average_rating = ?, total_reviews = ?, updated_at = NOW()
            WHERE id = ?
        ", [$averageRating, $reviewCount, $businessId]);
        
        // Update the business array for display
        $business['average_rating'] = $averageRating;
        $business['total_reviews'] = $reviewCount;
        
        // Debug logging
        error_log("Business {$businessId}: Calculated rating {$averageRating} from {$reviewCount} reviews");
    } else {
        // No reviews, ensure values are 0
        $business['average_rating'] = 0;
        $business['total_reviews'] = 0;
    }
    
    // Get business reviews
    $sort = isset($_GET['sort']) && $_GET['sort'] === 'highest' ? 'highest' : 'newest';
    
    if ($sort === 'highest') {
        $reviews = $db->fetchAll("
            SELECT r.*, u.first_name, u.last_name, u.profile_image_url 
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.business_id = ?
            ORDER BY r.rating DESC, r.created_at DESC
        ", [$businessId]);
    } else { // newest
        $reviews = $db->fetchAll("
            SELECT r.*, u.first_name, u.last_name, u.profile_image_url 
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.business_id = ?
            ORDER BY r.created_at DESC
        ", [$businessId]);
    }
    // Get business deals
    $deals = $db->fetchAll("
        SELECT * FROM deals 
        WHERE business_id = ? AND is_active = 1 AND expires_at > NOW() 
        ORDER BY created_at DESC
    ", [$businessId]);
    
    // Check if user has bookmarked this business
    $isBookmarked = false;
    if (isLoggedIn()) {
        $bookmark = $db->fetch(
            "SELECT id FROM bookmarks WHERE user_id = ? AND business_id = ?",
            [$_SESSION['user_id'], $businessId]
        );
        $isBookmarked = (bool)$bookmark;
    }
    
    // Render stars with colored/filled Lucide icons
    // Major performance impact 
    // Use renderStars in functions.php for more efficient Unicode star render
    /*
    function renderStarsLucide($rating) {
        $output = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                // Filled star (yellow)
                $output .= '<i data-lucide="star" class="w-5 h-5 text-yellow-400 fill-yellow-400 inline-block"></i>';
            } else {
                // Empty star (gray)
                $output .= '<i data-lucide="star" class="w-5 h-5 text-gray-300 inline-block"></i>';
            }
        }
        return $output;
    }
    */
    $business['is_open'] = false;
    if (!empty($business['opening_hours']) && !empty($business['timezone'])) {
        $business['is_open'] = isBusinessOpen($business['opening_hours'], $business['timezone']);
    }
    
    $pageTitle = $business['name'];
    $pageDescription = $business['description'] ?: "Learn more about {$business['name']}";
    
    include 'includes/header.php';
?>

<script>
    const existingReviews = <?php echo json_encode($reviews); ?>;
    const currentUserId = <?php echo isLoggedIn() ? (int)$_SESSION['user_id'] : 'null'; ?>;
</script>

<main class="min-h-screen bg-background">
    <!-- Business Header -->
    <section class="relative">
        <div class="h-64 bg-gradient-to-r from-primary to-secondary overflow-hidden">
            <?php if ($business['banner_image_url']): ?>
            <img src="<?php echo escapeHtml($business['banner_image_url']); ?>" 
                 alt="<?php echo escapeHtml($business['name']); ?>" 
                 class="w-full h-full object-cover">
            <?php endif; ?>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative -mt-24 bg-card rounded-xl border border-border p-8 shadow-lg">
                <div class="flex flex-col md:flex-row items-start space-y-4 md:space-y-0 md:space-x-6">
                    <div class="flex-shrink-0">
                        <img src="<?php echo escapeHtml($business['image_url'] ?: 'fbla-waves.png'); ?>" 
                             alt="<?php echo escapeHtml($business['name']); ?>" 
                             class="w-24 h-24 rounded-xl object-cover border-2 border-border">
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between">
                            <div>
                                <h1 class="text-3xl font-bold text-foreground mb-2"><?php echo escapeHtml($business['name']); ?></h1>
                                <div class="flex items-center space-x-4 mb-3">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-secondary/20 text-secondary">
                                        <i data-lucide="<?php echo escapeHtml($business['category_icon']); ?>" class="w-4 h-4 mr-2"></i>
                                        <?php echo escapeHtml($business['category_name']); ?>
                                    </span>
                                    <span class="text-muted-foreground"><?php echo escapeHtml($business['price_range']); ?></span>
                                    <?php if ($business['is_open']): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                        Open
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <span class="w-2 h-2 bg-red-400 rounded-full mr-2"></span>
                                        Closed
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center space-x-4 text-sm text-muted-foreground">
                                    <div class="flex items-center space-x-1">
                                        <?php echo renderStars(round($business['average_rating'] ?: 0)); ?>
                                        <span class="font-semibold text-foreground"><?php echo number_format($business['average_rating'] ?: 0, 1); ?></span>
                                        <span>(<?php echo $business['total_reviews']; ?> reviews)</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-3">
                                <?php if (isLoggedIn()): ?>
                                <button onclick="toggleBookmark('<?php echo $businessId; ?>')" 
                                        class="p-3 rounded-lg border border-border hover:bg-muted transition-colors <?php echo $isBookmarked ? 'bg-accent text-black' : ''; ?>">
                                    <i data-lucide="bookmark" class="w-5 h-5 <?php echo $isBookmarked ? 'fill-current' : ''; ?>"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($business['phone']): ?>
                                <a href="tel:<?php echo escapeHtml($business['phone']); ?>" 
                                   class="flex items-center px-4 py-2 bg-primary hover:bg-primary/90 text-primary-foreground rounded-lg transition-colors">
                                    <i data-lucide="phone" class="w-4 h-4 mr-2"></i>
                                    Call
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($business['website']): ?>
                                <button onclick="trackWebsiteClick('<?php echo $businessId; ?>', '<?php echo escapeHtml($business['website']); ?>')" 
                                        class="flex items-center px-4 py-2 border border-border hover:bg-muted rounded-lg transition-colors">
                                    <i data-lucide="external-link" class="w-4 h-4 mr-2"></i>
                                    Website
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Description -->
                    <?php if ($business['description']): ?>
                    <div class="bg-card rounded-xl border border-border p-6">
                        <h2 class="text-xl font-semibold text-foreground mb-4">About</h2>
                        <p class="text-foreground leading-relaxed"><?php echo nl2br(escapeHtml($business['description'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Active Deals -->
                    <?php if (!empty($deals)): ?>
                    <div class="bg-card rounded-xl border border-border p-6">
                        <h2 class="text-xl font-semibold text-foreground mb-6">Current Deals</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($deals as $deal): ?>
                            <div class="bg-gradient-to-r from-secondary/20 to-accent/20 rounded-lg p-4 border border-secondary/30">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-semibold text-foreground"><?php echo escapeHtml($deal['title']); ?></h3>
                                    <span class="bg-secondary text-secondary-foreground px-2 py-1 rounded-full text-sm font-medium">
                                        <?php echo escapeHtml($deal['discount_value']); ?> OFF
                                    </span>
                                </div>
                                <p class="text-muted-foreground text-sm mb-3"><?php echo escapeHtml($deal['description']); ?></p>
                                <div class="flex items-center justify-between">
                                    <p class="text-xs text-muted-foreground">
                                        Expires <?php echo date('M j, Y', strtotime($deal['expires_at'])); ?>
                                    </p>
                                    <button onclick="showDealCode('<?php echo escapeHtml($deal['id']); ?>', '<?php echo escapeHtml($deal['discount_code'] ?? 'Error retrieving code'); ?>')" 
                                            class="px-3 py-1 bg-accent hover:bg-accent/90 text-black text-xs font-medium rounded transition-colors">
                                        Get Code
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Reviews -->
                    <div class="bg-card rounded-xl border border-border p-6">
                        
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-semibold text-foreground">Reviews (<?php echo count($reviews); ?>)</h2>
                            <div class="flex space-x-4">
                                <?php if (isLoggedIn()): ?>
                                <button onclick="showReviewModal()" class="px-4 py-2 bg-accent hover:bg-accent/90 text-black font-medium rounded-lg transition-colors">
                                    Write Review
                                </button>
                                <?php endif; ?>
                                <select id="reviewSort" onchange="onSortChange()" class="px-2 py-1 border border-border rounded-lg bg-card text-foreground">
                                    <option value="newest" <?php echo $sort==='newest'?'selected':''; ?>>Newest</option>
                                    <option value="highest" <?php echo $sort==='highest'?'selected':''; ?>>Highest</option>
                                </select>
                            </div>
                        </div>

                        <?php if (!empty($reviews)): ?>
                        <div class="space-y-6">
                            <?php foreach ($reviews as $review): ?>
                            <div class="border-b border-border pb-6 last:border-b-0 last:pb-0">
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0">
                                        <?php if ($review['profile_image_url']): ?>
                                        <img src="<?php echo escapeHtml($review['profile_image_url']); ?>" 
                                             alt="Profile" class="w-10 h-10 rounded-full object-cover">
                                        <?php else: ?>
                                        <div class="w-10 h-10 bg-muted rounded-full flex items-center justify-center">
                                            <i data-lucide="user" class="w-5 h-5 text-muted-foreground"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="font-medium text-foreground">
                                                <?php echo escapeHtml($review['first_name'] . ' ' . $review['last_name']); ?>
                                            </h4>
                                            <span class="text-sm text-muted-foreground"><?php echo timeAgo($review['created_at']); ?></span>
                                        </div>
                                        <div class="review-rating">
                                            <?php echo renderStars((int)$review['rating']); ?>
                                        </div>
                                        <?php if ($review['comment']): ?>
                                        <p class="text-foreground"><?php echo nl2br(substr(substr(json_encode($review['comment']),1), 0, -1)); ?></p>
                                        <?php endif; ?>
                                        
                                        
                                        <?php if (isLoggedIn() && (int)$review['user_id'] === (int)$_SESSION['user_id']): ?>
                                        <div class="flex space-x-2 mt-3">
                                            <button 
                                                class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded"
                                                onclick='openReviewModal(
                                                    <?= json_encode($review['id']) ?>,
                                                    <?= json_encode((int)$review['rating']) ?>,
                                                    <?= json_encode($review['comment']) ?>
                                                )'>
                                            Edit
                                            </button>


                                            <button onclick="deleteReview('<?php echo $review['id']; ?>')" 
                                                    class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded">
                                                Delete
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Upvote functionality is broken
                                        
                                        <div class="flex items-center mt-2 space-x-2">
                                            <button type="button" class="text-muted-foreground hover:text-primary" onclick="voteReview('<?php echo $review['id']; ?>', 1, this)">
                                                <i data-lucide="arrow-big-up" class="w-5 h-5"></i>
                                            </button>
                                            <span class="text-foreground font-medium" id="review-upvotes-<?php echo $review['id']; ?>">
                                                <?php echo intval($review['upvotes']); ?>
                                            </span>
                                            <button type="button" class="text-muted-foreground hover:text-primary" onclick="voteReview('<?php echo $review['id']; ?>', -1, this)">
                                                <i data-lucide="arrow-big-down" class="w-5 h-5"></i>
                                            </button>
                                        </div>
                                        -->
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-8">
                            <i data-lucide="message-circle" class="w-12 h-12 text-muted-foreground mx-auto mb-4"></i>
                            <h3 class="text-lg font-medium text-foreground mb-2">No reviews yet</h3>
                            <p class="text-muted-foreground">Be the first to leave a review for this business!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Contact Information -->
                    <div class="bg-card rounded-xl border border-border p-6">
                        <h3 class="text-lg font-semibold text-foreground mb-4">Contact Information</h3>
                        <div class="space-y-3">
                            <div class="flex items-start space-x-3">
                                <i data-lucide="map-pin" class="w-5 h-5 text-muted-foreground mt-0.5"></i>
                                <span class="text-foreground"><?php echo nl2br(escapeHtml($business['address'])); ?></span>
                            </div>
                            
                            <?php if ($business['phone']): ?>
                            <div class="flex items-center space-x-3">
                                <i data-lucide="phone" class="w-5 h-5 text-muted-foreground"></i>
                                <a href="tel:<?php echo escapeHtml($business['phone']); ?>" 
                                   class="text-primary hover:text-primary/80"><?php echo escapeHtml($business['phone']); ?></a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($business['email']): ?>
                            <div class="flex items-center space-x-3">
                                <i data-lucide="mail" class="w-5 h-5 text-muted-foreground"></i>
                                <a href="mailto:<?php echo escapeHtml($business['email']); ?>" 
                                   class="text-primary hover:text-primary/80"><?php echo escapeHtml($business['email']); ?></a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($business['website']): ?>
                            <div class="flex items-center space-x-3">
                                <i data-lucide="globe" class="w-5 h-5 text-muted-foreground"></i>
                                <a href="<?php echo escapeHtml($business['website']); ?>" target="_blank" 
                                   class="text-primary hover:text-primary/80">Visit Website</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Business Hours -->
                    <?php if ($business['opening_hours']): ?>
                    <div class="bg-card rounded-xl border border-border p-6">
                        <h3 class="text-lg font-semibold text-foreground mb-4">Business Hours</h3>
                        <div class="space-y-2">
                            <?php foreach (getBusinessHours($business['opening_hours']) as $dayHours): ?>
                            <div class="flex justify-between items-center py-1">
                                <span class="font-medium text-foreground"><?php echo $dayHours['day']; ?>:</span>
                                <span class="text-muted-foreground <?php echo $dayHours['hours'] === 'Closed' ? 'text-red-400' : ''; ?>">
                                    <?php echo $dayHours['hours']; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Review Modal -->
<div id="reviewModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-lg flex items-center justify-center z-50" style="display: none;">
    <div class="bg-card rounded-xl border border-border p-6 w-full max-w-md mx-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-foreground">Write a Review</h3>
            <button onclick="closeReviewModal()" class="text-muted-foreground hover:text-foreground">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <form id="review-form" method="POST" action="/api/reviews.php" onsubmit="return validateReviewForm()">
            <input type="hidden" name="business_id" value="<?php echo escapeHtml($businessId); ?>">
            <input type="hidden" name="_method" id="methodInput" value="POST">

            <div class="mb-6">
                <label class="block text-sm font-medium text-foreground mb-3">Rating *</label>
                <div class="flex space-x-1" id="starRating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="star-button text-gray-300 hover:text-yellow-400 cursor-pointer transition-colors" data-rating="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)">
                        <i data-lucide="star" class="w-6 h-6"></i>
                    </button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" required>
                <p class="text-xs text-muted-foreground mt-1">Click on a star to rate</p>
            </div>
            
            <div class="mb-6">
                <label for="comment" class="block text-sm font-medium text-foreground mb-2">Comment (optional)</label>
                <textarea id="comment" name="comment" rows="4" 
                          class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                          placeholder="Share your experience..."></textarea>
            </div>
            
            <div class="flex space-x-3">
                <button type="button" onclick="closeReviewModal()" 
                        class="flex-1 px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-4 py-2 bg-accent hover:bg-accent/90 text-black font-medium rounded-lg transition-colors">
                    Submit Review
                </button>
            </div>
        </form>
    </div>
</div>

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
                    Error fetching promo code
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

<!-- Message Modal -->

<div id="message-modal" class="fixed inset-0 flex items-center backdrop-blur-lg justify-center bg-black/50 z-50 hidden">
    <div class="bg-card rounded-xl p-6 max-w-sm w-full text-center relative shadow-lg">
        <button id="message-close" class="absolute top-2 right-2 text-muted-foreground hover:text-foreground text-xl font-bold">
            &times;
        </button>
        <div id="message-icon" class="mb-4 flex justify-center"></div>
        <p id="message-text" class="text-foreground text-sm"></p>
    </div>
</div>

<!-- Confirm Modal -->

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
    
<script>
    lucide.createIcons();
    
    function toggleBookmark(businessId) {
        // AJAX call to toggle bookmark
        fetch('/api/bookmarks.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                business_id: businessId,
                action: 'toggle'
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
    
    async function deleteReview(reviewId) {
        const confirmed = await showConfirm("Are you sure you want to delete this review?");
        if (confirmed) {
            fetch('/api/reviews.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ review_id: reviewId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage("Review deleted successfully!", "success");
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(data.message || "Failed to delete review", "error");
                }
            })
            .catch(err => {
                console.error(err);
                showMessage("An error occurred while deleting.", "error");
            });
        }
    }
    
    function showMessage(message, type = 'info') {
        const modal = document.getElementById('message-modal');
        const textEl = document.getElementById('message-text');
        const iconEl = document.getElementById('message-icon');
        const closeBtn = document.getElementById('message-close');
    
        // Set the text
        textEl.textContent = message;
    
        // Clear previous icon
        iconEl.innerHTML = '';
    
        // Determine icon based on type
        let iconName = '';
        let iconColor = '';
        switch (type) {
            case 'success':
                iconName = 'check-circle';
                iconColor = 'text-green-500';
                break;
            case 'error':
                iconName = 'alert-circle';
                iconColor = 'text-red-500';
                break;
            case 'warning':
                iconName = 'alert-triangle';
                iconColor = 'text-yellow-500';
                break;
            default:
                iconName = 'info';
                iconColor = 'text-blue-500';
        }
    
        // Create icon element
        const icon = document.createElement('i');
        icon.setAttribute('data-lucide', iconName);
        icon.className = `w-10 h-10 ${iconColor} mb-2`;
        iconEl.appendChild(icon);
    
        // Show modal
        modal.classList.remove('hidden');
    
        // Refresh Lucide icons
        lucide.createIcons();
    
        // Close modal on button click
        closeBtn.onclick = () => {
            modal.classList.add('hidden');
        };
    
        // Optional: Close modal when clicking outside the modal box
        modal.onclick = (e) => {
            if (e.target === modal) modal.classList.add('hidden');
        };
    }
    
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
    
    function showReviewModal() {
        const modal = document.getElementById('reviewModal');
        modal.style.display = 'flex';
        document.getElementById('ratingInput').value = '';
        document.getElementById('comment').value = '';
    
        // Clear reviewIdInput if exists
        const reviewIdInput = document.getElementById('reviewIdInput');
        if (reviewIdInput) reviewIdInput.value = '';
    
        const starButtons = document.querySelectorAll('.star-button');
        starButtons.forEach(button => {
            button.classList.add('text-gray-300');
            button.classList.remove('text-yellow-400');
        });
    
        // Only block new reviews if user already has one
        if (currentUserId) {
            const alreadyReviewed = existingReviews.some(r => parseInt(r.user_id) === currentUserId);
            if (alreadyReviewed) {
                closeReviewModal();
                showMessage("Already reviewed this business!", "error");
                return false;
            }
        }
    }
    
    function openReviewModal(reviewId, rating, comment) {
        const modal = document.getElementById('reviewModal');
        modal.style.display = 'flex';
    
        // Prefill fields
        document.getElementById('methodInput').value = reviewId ? 'PUT' : 'POST';
        document.getElementById('ratingInput').value = rating;
        document.getElementById('comment').value = comment;
        setRating(rating);
    
        // Hidden field for review ID
        let reviewIdInput = document.getElementById('reviewIdInput');
        if (!reviewIdInput) {
            reviewIdInput = document.createElement('input');
            reviewIdInput.type = 'hidden';
            reviewIdInput.name = 'review_id';
            reviewIdInput.id = 'reviewIdInput';
            modal.querySelector('form').appendChild(reviewIdInput);
        }
        reviewIdInput.value = reviewId;
    
        modal.querySelector('h3').textContent = 'Edit Review';
        modal.querySelector('form button[type="submit"]').textContent = 'Update Review';
    }


    function closeReviewModal() {
        document.getElementById('reviewModal').style.display = 'none';
    }
    
    function setRating(stars) {
        const starButtons = document.querySelectorAll('.star-button');
        const ratingInput = document.getElementById('ratingInput');
        
        ratingInput.value = stars;
        
        starButtons.forEach(button => {
            const rating = parseInt(button.getAttribute('data-rating'));
            if (rating <= stars) {
                button.classList.add('text-yellow-400');
                button.classList.remove('text-gray-300');
            } else {
                button.classList.add('text-gray-300');
                button.classList.remove('text-yellow-400');
            }
        });
        
        console.log('Rating set to:', stars);
    }
    
    function validateReviewForm() {
        const rating = document.getElementById('ratingInput').value;
        const comment = document.getElementById('comment').value.trim();
        const reviewIdInput = document.getElementById('reviewIdInput');
        const editingId = reviewIdInput ? reviewIdInput.value : null;
    
        if (!rating || rating < 1 || rating > 5) {
            showMessage('Please select a rating from 1 to 5 stars.', "error");
            return false;
        }
    
        if (currentUserId) {
            const alreadyReviewed = existingReviews.some(r => parseInt(r.user_id) === currentUserId && r.id !== editingId);
            if (alreadyReviewed) {
                showMessage("Already reviewed this business!", "error");
                return false;
            }
        }
    
        return true;
    }

    function showDealCode(dealId, promoCode) {
        document.getElementById('promoCode').textContent = promoCode;
        document.getElementById('dealModal').style.display = 'flex';
    }
    
    function closeDealModal() {
        document.getElementById('dealModal').style.display = 'none';
    }
    
    function copyPromoCode() {
        const promoCode = document.getElementById('promoCode').textContent;
        navigator.clipboard.writeText(promoCode).then(function() {
            // Show success feedback
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.classList.add('bg-green-100', 'text-green-800');
            
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('bg-green-100', 'text-green-800');
            }, 2000);
        });
    }
    
    function trackWebsiteClick(businessId, websiteUrl) {
        fetch('/api/track_event.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                event_type: 'website_click',
                business_id: businessId
            })
        }).then(() => {
            // Redirect after logging event (slight delay to ensure DB writes)
            setTimeout(() => {
                window.open(websiteUrl, '_blank');
            }, 100);
        }).catch((err) => {
            console.error('Tracking error:', err);
            window.open(websiteUrl, '_blank'); // Fallback
        });
    }
    
    function onSortChange() {
        const sel = document.getElementById('reviewSort');
        const val = sel.value;
        const params = new URLSearchParams(window.location.search);
        params.set('sort', val);
        window.location.search = params.toString();
    }
    
    function voteReview(reviewId, voteValue, btnElem) {
        fetch('/api/review_vote.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                review_id: reviewId,
                vote: voteValue
            })
        })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                const span = document.getElementById('review-upvotes-' + reviewId);
                span.textContent = data.upvotes;
                // Optional: highlight the button or change color to reflect user has voted
            } else {
                alert(data.message || 'Could not vote');
            }
        })
        .catch(err => {
            console.error('Vote error:', err);
            alert('Could not vote, try again');
        });
    }
    
    document.querySelectorAll('#edit-review-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const reviewId = btn.dataset.id;
            const rating   = btn.dataset.rating;
            const comment  = btn.dataset.comment;
    
            // Open the modal
            openReviewModal();
    
            // Prefill fields
            document.querySelector('#review-id').value = reviewId;
            document.querySelector('#rating-input').value = rating;
            document.querySelector('#comment-input').value = comment;
    
            // Switch form mode to "edit"
            document.querySelector('#review-form').dataset.mode = 'edit';
        });
    });
    
    document.addEventListener("DOMContentLoaded", () => {
        const reviewForm = document.getElementById("review-form");
        if (!reviewForm) return;
    
        reviewForm.addEventListener("submit", async (e) => {
            e.preventDefault(); // stop normal redirect to /api/reviews.php
    
            const formData = new FormData(reviewForm);
            const data = Object.fromEntries(formData.entries());
    
            try {
                const response = await fetch("/api/reviews.php", {
                    method: "POST", // your API will check _method for PUT/POST
                    body: formData,
                });
                const result = await response.json();
    
                if (result.success) {
                    closeReviewModal();
                    showMessage("Review saved successfully!", "success");
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(result.message || "Failed to save review", "error");
                }
            } catch (err) {
                console.error(err);
                if(err instanceof SyntaxError) {
                    closeReviewModal();
                    showMessage("Review processed.", "success");
                    setTimeout(() => location.reload(), 1000);
                }
                else {
                    showMessage("An error occurred while saving review.", "error");
                }
            }
        });
    });

</script>

<?php include 'includes/footer.php'; ?>
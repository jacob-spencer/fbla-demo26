<?php
require_once 'functions.php';

// Get username or ID from URL
if (!isset($_GET['u'])) {
    http_response_code(404);
    echo "Profile not found.";
    exit;
}

$username = sanitizeInput($_GET['u']);
$db = Database::getInstance();

// Fetch public user info
$userf = $db->fetch("SELECT id, username, first_name, last_name, profile_image_url, bio, location, is_business_owner, created_at FROM users WHERE username = ?", [$username]);

if (!$userf) {
    http_response_code(404);
    echo "Profile not found.";
    exit;
}

// Fetch user stats
$reviewCount = $db->fetch("SELECT COUNT(*) as count FROM reviews WHERE user_id = ?", [$userf['id']])['count'];
$bookmarkCount = $db->fetch("SELECT COUNT(*) as count FROM bookmarks WHERE user_id = ?", [$userf['id']])['count'];

$pageTitle = escapeHtml($userf['first_name'] . ' ' . $userf['last_name']);
$pageDescription = 'View ' . escapeHtml($userf['first_name']) . '\'s public profile';

include 'includes/header.php';
?>

<main class="min-h-screen py-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Header -->
        <div class="mb-8 text-center">
            
            <?php if ($userf['profile_image_url']): ?>
            <img src="<?php echo escapeHtml($userf['profile_image_url']); ?>" 
                 alt="Profile" class="w-20 h-20 rounded-full object-cover flex items-center justify-center mx-auto mb-4">
            <?php else: ?>
            <div class="w-20 h-20 bg-primary/20 rounded-full flex items-center flex items-center justify-center mx-auto mb-4">
                <i data-lucide="user" class="w-10 h-10 text-primary"></i>
            </div>
            <?php endif; ?>
            
            <h1 class="text-3xl font-bold text-foreground mb-1">
                <?php echo escapeHtml($userf['first_name'] . ' ' . $userf['last_name']); ?>
            </h1>
            <p class="text-muted-foreground">@<?php echo escapeHtml($userf['username']); ?></p>

            <?php if ($userf['is_business_owner']): ?>
                <div class="mt-3 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-accent/20 text-accent">
                    <i data-lucide="building-2" class="w-4 h-4 mr-1"></i>
                    Business Owner
                </div>
            <?php endif; ?>

            <?php if (!empty($userf['location'])): ?>
                <p class="mt-2 text-muted-foreground text-sm">
                    <i data-lucide="map-pin" class="inline w-4 h-4 mr-1 text-muted-foreground"></i>
                    <?php echo escapeHtml($userf['location']); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Bio -->
        <?php if (!empty($userf['bio'])): ?>
        <div class="bg-card/20 backdrop-blur-lg rounded-xl p-6 mb-8">
            <h2 class="text-xl font-semibold text-foreground mb-3">About</h2>
            <p class="text-muted-foreground whitespace-pre-line"><?php echo nl2br(escapeHtml($userf['bio'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-card/20 backdrop-blur-lg rounded-xl p-6 text-center">
                <i data-lucide="star" class="w-5 h-5 text-muted-foreground mx-auto mb-2"></i>
                <div class="text-lg font-semibold text-foreground"><?php echo $reviewCount; ?></div>
                <div class="text-sm text-muted-foreground">Reviews</div>
            </div>
            <div class="bg-card/20 backdrop-blur-lg rounded-xl p-6 text-center">
                <i data-lucide="bookmark" class="w-5 h-5 text-muted-foreground mx-auto mb-2"></i>
                <div class="text-lg font-semibold text-foreground"><?php echo $bookmarkCount; ?></div>
                <div class="text-sm text-muted-foreground">Bookmarks</div>
            </div>
            <div class="bg-card/20 backdrop-blur-lg rounded-xl p-6 text-center">
                <i data-lucide="calendar" class="w-5 h-5 text-muted-foreground mx-auto mb-2"></i>
                <div class="text-lg font-semibold text-foreground"><?php echo date('M Y', strtotime($userf['created_at'])); ?></div>
                <div class="text-sm text-muted-foreground">Joined</div>
            </div>
        </div>
    </div>
</main>

<script>
lucide.createIcons();
</script>

<?php include 'includes/footer.php'; ?>

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
<style>
/* Glowing wave effect */
html, body {
    overflow-x: hidden; /* prevent horizontal scrolling */
    overflow-y: auto hidden;   /* allow vertical scroll */
}
.wave-left-top {
    background: radial-gradient(ellipse, rgba(0, 120, 255, 0.6) 0%, transparent 70%);
}
.wave-left-bottom {
    background: radial-gradient(ellipse, rgba(0, 120, 255, 0.6) 0%, transparent 70%);
}
.wave-right-top {
    background: radial-gradient(ellipse, rgba(0, 120, 255, 0.6) 0%, transparent 70%);
}
.wave-right-bottom {
    background: radial-gradient(ellipse, rgba(0, 120, 255, 0.6) 0%, transparent 70%);
}
@keyframes waveLeft {
    0% { transform: translateX(-40%); }
    100% { transform: translateX(-40%); }
}
@keyframes waveRight {
    0% { transform: translateX(40%); }
    100% { transform: translateX(40%); }
}
#waves {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;          /* will be updated via JS */
    z-index: -10;
    overflow: hidden;      /* keep wave shapes from spilling outside */
}

.fade-bottom {
    -webkit-mask-image: linear-gradient(to top, transparent 0%, black 30%);
    -webkit-mask-repeat: no-repeat;
    -webkit-mask-size: 100% 100%;
    mask-image: linear-gradient(to top, transparent 0%, black 30%);
    mask-repeat: no-repeat;
    mask-size: 100% 100%;
} /* fade image out cleanly */
</style>

<section id="waves" class="absolute inset-0 -z-10 pointer-events-none"></section>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("waves");

    // Wave patterns: horizontal placement via Tailwind, vertical offset handled in JS
    const wavePatterns = [
        { classes: "left-1/2 -translate-x-1/2 w-[100vw] h-[70vh] wave-right-top", verticalOffset: -0.25 },
        { classes: "-left-1/2 w-[90vw] h-[90vh] wave-left-top", verticalOffset: 0.33 },
        { classes: "left-1/2 w-[100vw] h-[75vh] wave-left-bottom", verticalOffset: 0.66 },
        { classes: "-left-1/2 w-[80vw] h-[85vh] wave-right-bottom", verticalOffset: 0 },
        { classes: "left-1/2 w-[80vw] h-[85vh] wave-right-bottom", verticalOffset: 0 },
        { classes: "left-1/2 w-[100vw] h-[70vh] wave-left-bottom", verticalOffset: -0.2 },
    ];

    function spawnWaves() {
        container.innerHTML = "";
        const pageHeight = document.body.scrollHeight;
        const sectionHeight = window.innerHeight;
        const numSections = Math.ceil(pageHeight / sectionHeight);

        for (let i = 0; i < numSections; i++) {
            const offsetY = i * sectionHeight;

            wavePatterns.forEach(cfg => {
                const div = document.createElement("div");
                div.className = `absolute ${cfg.classes}`;

                // Calculate vertical position in pixels
                const waveOffset = cfg.verticalOffset ? cfg.verticalOffset * sectionHeight : 0;
                div.style.top = `${offsetY + waveOffset}px`;

                container.appendChild(div);
            });
        }
    }

    function resizeWavesContainer() {
        container.style.height = document.body.scrollHeight + "px";
    }

    // Spawn waves after everything (images + content) is loaded
    window.addEventListener("load", () => {
        resizeWavesContainer();
        spawnWaves();
    });

    // Recalculate on resize
    window.addEventListener("resize", () => {
        resizeWavesContainer();
        spawnWaves();
    });
});
</script>

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

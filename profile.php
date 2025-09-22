<?php
require_once 'functions.php';

requireAuth();
$user = getCurrentUser();

$pageTitle = 'Profile';
$pageDescription = 'Manage your profile settings';

$db = Database::getInstance();
$error = '';
$success = '';

// Handle profile update
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $bio = sanitizeInput($_POST['bio']);
    $location = sanitizeInput($_POST['location']);
    
    if ($firstName && $lastName) {
        try {
            $db->query("
                UPDATE users 
                SET first_name = ?, last_name = ?, bio = ?, location = ?, updated_at = NOW()
                WHERE id = ?
            ", [$firstName, $lastName, $bio, $location, $user['id']]);
            
            $success = 'Profile updated successfully!';
            
            // Refresh user data
            $user = getCurrentUser();
        } catch (Exception $e) {
            $error = 'Failed to update profile. Please try again.';
        }
    } else {
        $error = 'First name and last name are required.';
    }
}

// Get user stats
$reviewCount = $db->fetch("SELECT COUNT(*) as count FROM reviews WHERE user_id = ?", [$user['id']])['count'];
$bookmarkCount = $db->fetch("SELECT COUNT(*) as count FROM bookmarks WHERE user_id = ?", [$user['id']])['count'];

include 'includes/header.php';
?>

<main class="min-h-screen bg-background">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-foreground mb-2">Profile Settings</h1>
            <p class="text-muted-foreground">Manage your account information and preferences</p>
        </div>

        <?php if ($error): ?>
        <div class="bg-destructive/10 border border-destructive/20 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i data-lucide="alert-circle" class="w-5 h-5 text-destructive mr-2"></i>
                <span class="text-destructive"><?php echo escapeHtml($error); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-500/10 border border-green-500/20 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                <span class="text-green-500"><?php echo escapeHtml($success); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Profile Form -->
            <div class="lg:col-span-2">
                <div class="bg-card rounded-xl border border-border p-6">
                    <h2 class="text-xl font-semibold text-foreground mb-6">Personal Information</h2>
                    
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-foreground mb-2">First Name</label>
                                <input type="text" id="first_name" name="first_name" required 
                                       value="<?php echo escapeHtml($user['first_name']); ?>"
                                       class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-foreground mb-2">Last Name</label>
                                <input type="text" id="last_name" name="last_name" required 
                                       value="<?php echo escapeHtml($user['last_name']); ?>"
                                       class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-foreground mb-2">Email</label>
                            <input type="email" id="email" value="<?php echo escapeHtml($user['email']); ?>" disabled 
                                   class="w-full px-3 py-2 bg-muted border border-border rounded-lg text-muted-foreground">
                            <p class="text-xs text-muted-foreground mt-1">Email cannot be changed</p>
                        </div>

                        <div>
                            <label for="username" class="block text-sm font-medium text-foreground mb-2">Username</label>
                            <input type="text" id="username" value="<?php echo escapeHtml($user['username']); ?>" disabled 
                                   class="w-full px-3 py-2 bg-muted border border-border rounded-lg text-muted-foreground">
                            <p class="text-xs text-muted-foreground mt-1">Username cannot be changed</p>
                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-foreground mb-2">Location</label>
                            <input type="text" id="location" name="location" 
                                   value="<?php echo escapeHtml($user['location'] ?: ''); ?>"
                                   placeholder="City, State"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <div>
                            <label for="bio" class="block text-sm font-medium text-foreground mb-2">Bio</label>
                            <textarea id="bio" name="bio" rows="4" 
                                      placeholder="Tell us about yourself..."
                                      class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"><?php echo escapeHtml($user['bio'] ?: ''); ?></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-6 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Profile Summary -->
                <div class="bg-card rounded-xl border border-border p-6">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="user" class="w-8 h-8 text-primary"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-foreground mb-1">
                            <?php echo escapeHtml($user['first_name'] . ' ' . $user['last_name']); ?>
                        </h3>
                        <p class="text-muted-foreground text-sm mb-4">@<?php echo escapeHtml($user['username']); ?></p>
                        
                        <?php if ($user['is_business_owner']): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-accent/20 text-accent">
                            <i data-lucide="building-2" class="w-4 h-4 mr-1"></i>
                            Business Owner
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activity Stats -->
                <div class="bg-card rounded-xl border border-border p-6">
                    <h3 class="text-lg font-semibold text-foreground mb-4">Activity</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i data-lucide="star" class="w-4 h-4 text-muted-foreground"></i>
                                <span class="text-foreground">Reviews</span>
                            </div>
                            <span class="text-muted-foreground"><?php echo $reviewCount; ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i data-lucide="bookmark" class="w-4 h-4 text-muted-foreground"></i>
                                <span class="text-foreground">Bookmarks</span>
                            </div>
                            <span class="text-muted-foreground"><?php echo $bookmarkCount; ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i data-lucide="calendar" class="w-4 h-4 text-muted-foreground"></i>
                                <span class="text-foreground">Member </span>
                            </div>
                            <span class="text-muted-foreground"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-card rounded-xl border border-border p-6">
                    <h3 class="text-lg font-semibold text-foreground mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="/bookmarks.php" class="flex items-center space-x-2 text-foreground hover:text-primary transition-colors">
                            <i data-lucide="bookmark" class="w-4 h-4"></i>
                            <span>View Bookmarks</span>
                        </a>
                        <?php if ($user['is_business_owner']): ?>
                        <a href="/my-business.php" class="flex items-center space-x-2 text-foreground hover:text-primary transition-colors">
                            <i data-lucide="building-2" class="w-4 h-4"></i>
                            <span>Manage Business</span>
                        </a>
                        <?php endif; ?>
                        <a href="/logout.php" class="flex items-center space-x-2 text-destructive hover:text-destructive/80 transition-colors">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
lucide.createIcons();
</script>

<?php include 'includes/footer.php'; ?>
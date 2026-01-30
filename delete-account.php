<?php
require_once 'functions.php';

requireAuth();
$user = getCurrentUser();
$id = $_GET['u'];

if ($id !== $user['id']) {
        header('Location: /');
}

$pageTitle = 'Delete Account';
$pageDescription = 'Delete Your Mainstreet Account';

$db = Database::getInstance();
$error = '';
$success = '';

// Handle profile deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $db->query("DELETE FROM analytics WHERE user_id = ?", [$user['id']]);
        $db->query("DELETE FROM bookmarks WHERE user_id = ?", [$user['id']]);

        $db->query("
            DELETE d FROM deals d
            JOIN businesses b ON d.business_id = b.id
            WHERE b.owner_id = ?
        ", [$user['id']]);

        $db->query("
            DELETE r FROM reviews r
            JOIN businesses b ON r.business_id = b.id
            WHERE b.owner_id = ?
        ", [$user['id']]);

        $db->query("DELETE FROM reviews WHERE user_id = ?", [$user['id']]);
        $db->query("DELETE FROM businesses WHERE owner_id = ?", [$user['id']]);
        $db->query("DELETE FROM users WHERE id = ?", [$user['id']]);

        $db->commit();
        $success = 'Account deleted successfully.';
        
        header('Location: /logout.php');

    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Failed to delete account.';
    }
}


include 'includes/header.php';
?>

<main class="min-h-screen bg-background py-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-foreground mb-2">Are you sure you want to delete your account?</h1>
            <p class="text-muted-foreground">Deleting your account will remove all information and preferences, as well as any content generated on the site.</p>
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
            <div class="bg-card rounded-xl p-6">
                <div class="space-y-3">
                    <a href="javascript:openModal();" class="flex items-center space-x-2 text-destructive hover:text-destructive/80 transition-colors">
                        <i data-lucide="trash" class="w-4 h-4"></i>
                        <span>Confirm</span>
                    </a>
                    
                    <a href="/" class="flex items-center space-x-2 text-foreground hover:text-primary transition-colors">
                        <span>Cancel</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-lg flex items-center justify-center z-50" style="display: none;">
        <div class="bg-card/40 backdrop-blur-sm rounded-xl p-6 w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-foreground">Enter your password to continue</h3>
                <button onclick="closeModal()" class="text-muted-foreground hover:text-foreground">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <form id="review-form" method="POST">
                <div class="mb-6">
                    <label for="pwd" class="block text-sm font-medium text-foreground mb-2">Password</label>
                    <input id="pwd" name="pwd" type="password"
                              class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                              placeholder="Password">
                </div>
                
                <div class="flex space-x-3">
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-2 bg-accent hover:bg-accent/90 text-black font-medium rounded-lg transition-colors">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
lucide.createIcons();

function openModal() {
  document.getElementById("confirmModal").style.display = "flex";
}
function closeModal() {
  document.getElementById("confirmModal").style.display = "none";
}


</script>

<?php include 'includes/footer.php'; ?>
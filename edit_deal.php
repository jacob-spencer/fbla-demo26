<?php
require_once 'functions.php';
requireAuth();
$user = getCurrentUser();

$db = Database::getInstance();
$dealId = $_GET['id'] ?? null;

if (!$dealId) {
    header('Location: my-business.php');
    exit;
}

// Fetch deal and ensure it belongs to the current user's business
$deal = $db->fetch("
    SELECT d.*, b.owner_id
    FROM deals d
    JOIN businesses b ON d.business_id = b.id
    WHERE d.id = ?
", [$dealId]);

if (!$deal || $deal['owner_id'] !== $user['id']) {
    header('Location: my-business.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $discountType = sanitizeInput($_POST['discount_type'] ?? '');
    $discountValue = sanitizeInput($_POST['discount_value'] ?? '');
    $expiresAt = sanitizeInput($_POST['expires_at'] ?? '');

    if ($title && $discountType && $discountValue) {
        $db->query("
            UPDATE deals
            SET title = ?, description = ?, discount_type = ?, discount_value = ?, expires_at = ?, updated_at = NOW()
            WHERE id = ?
        ", [$title, $description, $discountType, $discountValue, $expiresAt, $dealId]);

        header('Location: my-business.php');
        exit;
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>

<?php include 'includes/header.php'; ?>

<main class="max-w-xl mx-auto py-12">
    <h1 class="text-2xl font-bold mb-4">Edit Deal</h1>

    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo escapeHtml($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="deal_title" class="block text-sm font-medium text-foreground mb-2">Deal Title *</label>
                <input type="text" id="title" name="title" value="<?php echo escapeHtml($deal['title']); ?>" required
                       class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
            </div>
    
            <div>
                <label for="discount_type" class="block text-sm font-medium text-foreground mb-2">Discount Type *</label>
                <select name="discount_type" id="discount_type" required
                        class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
                    <option value="percentage" <?php if ($deal['discount_type'] === 'percentage') echo 'selected'; ?>>Percentage</option>
                    <option value="fixed" <?php if ($deal['discount_type'] === 'fixed') echo 'selected'; ?>>Fixed</option>
                </select>
            </div>
        </div>
    
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="discount_value" class="block text-sm font-medium text-foreground mb-2">Discount Value *</label>
                <input type="number" name="discount_value" id="discount_value" value="<?php echo escapeHtml($deal['discount_value']); ?>" required step="0.01" min="0"
                       class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
            </div>
    
            <div>
                <label for="discount_code" class="block text-sm font-medium text-foreground mb-2">Discount Code *</label>
                <input type="text" name="discount_code" id="discount_code"
                       class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="expires_at" class="block text-sm font-medium text-foreground mb-2">Expires At *</label>
                <input type="date" name="expires_at" id="expires_at" required value="<?php echo escapeHtml($deal['expires_at']); ?>"
                       class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
            </div>
        </div>
    
        <div>
            <label for="description" class="block text-sm font-medium text-foreground mb-2">Description</label>
            <textarea name="description" id="description" rows="3"
                      class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground"><?php echo escapeHtml($deal['description']); ?></textarea>
        </div>
    
        <div class="flex justify-end space-x-4">
            <a href="my-business.php" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg">
                Create Deal
            </button>
        </div>
    </form>
</main>

<?php include 'includes/footer.php'; ?>

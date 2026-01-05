<?php
require_once 'functions.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

requireAuth();
$user = getCurrentUser();

if (!$user['is_business_owner']) {
    header('Location: /');
    exit;
}

$db = Database::getInstance();
$error = '';
$success = '';

// Get user's business
$business = $db->fetch("
    SELECT b.*, c.name as category_name 
    FROM businesses b 
    LEFT JOIN categories c ON b.category_id = c.id 
    WHERE b.owner_id = ?
", [$user['id']]);

// Get categories for form
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY name ASC");

// Get deals
$deals = [];
if ($business) {
    $deals = $db->fetchAll("SELECT * FROM deals WHERE business_id = ? ORDER BY created_at DESC", [$business['id']]);
}

$street = $city = $state = $zip = $country = '';

// Break address for edit
if (!empty($business['address'])) {
    $parts = array_map('trim', explode(',', $business['address']));
    $street  = $parts[0] ?? '';
    $city    = $parts[1] ?? '';
    $state   = $parts[2] ?? '';
    $zip     = $parts[3] ?? '';
    $country = $parts[4] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'business') {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $categoryId = sanitizeInput($_POST['category_id']);
        $phone = sanitizeInput($_POST['phone']);
        $email = sanitizeInput($_POST['email']);
        $website = sanitizeInput($_POST['website']);
        $priceRange = sanitizeInput($_POST['price_range']);
        $street  = sanitizeInput($_POST['street']);
        $city    = sanitizeInput($_POST['city']);
        $state   = sanitizeInput($_POST['state']);
        $zip     = sanitizeInput($_POST['zip']);
        $country = sanitizeInput($_POST['country']);
        $lat     = sanitizeInput($_POST['lat']);
        $long    = sanitizeInput($_POST['long']);
        $address = implode(', ', array_filter([$street, $city, $state, $zip, $country]));
        $timezone = lookupTimezoneFromLatLong($lat, $long);
        
        // Image upload component
        $imageUrl = $business['image_url'] ?? null;
        $bannerUrl = $business['banner_image_url'] ?? null;
        
        $uploadDir = __DIR__ . '/uploads/businesses/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('thumb_') . '.' . $ext;
            move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $filename);
            $imageUrl = '/uploads/businesses/' . $filename;
        }
        
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('banner_') . '.' . $ext;
            move_uploaded_file($_FILES['banner']['tmp_name'], $uploadDir . $filename);
            $bannerUrl = '/uploads/businesses/' . $filename;
        }
        
        if ($name && $categoryId && $address) {
            try {
                if ($business) {
                    $db->query("
                        UPDATE businesses 
                        SET name = ?, description = ?, category_id = ?, address = ?, phone = ?, 
                            email = ?, website = ?, latitude = ?, longitude = ?, price_range = ?, 
                            image_url = ?, banner_image_url = ?, updated_at = NOW(), timezone = ?
                        WHERE id = ? AND owner_id = ?
                    ", [
                        $name, $description, $categoryId, $address, $phone,
                        $email, $website, $lat, $long, $priceRange,
                        $imageUrl, $bannerUrl, $timezone, $business['id'], $user['id']
                    ]);
                    
                    $success = 'Business updated successfully!';
                } else {
                    $businessId = generateUuid();
                    $db->query("
                        INSERT INTO businesses (id, name, description, category_id, owner_id, address, phone, email, website, latitude, longitude, price_range, image_url, banner_image_url, created_at, updated_at, timezone) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
                    ", [
                        $businessId, $name, $description, $categoryId, $user['id'], $address, $phone, $email, $website, $lat, $long, $priceRange, $imageUrl, $bannerUrl, $timezone
                    ]);

                    $success = 'Business created successfully!';
                    
                    $business = $db->fetch("
                        SELECT b.*, c.name as category_name 
                        FROM businesses b 
                        LEFT JOIN categories c ON b.category_id = c.id 
                        WHERE b.owner_id = ?
                    ", [$user['id']]);
                }
            } catch (Exception $e) {
                $error = 'Failed to save business. Please try again.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    }

    if ($formType === 'deal') {
        $title = sanitizeInput($_POST['deal_title']);
        $description = sanitizeInput($_POST['deal_description']);
        $discountType = sanitizeInput($_POST['discount_type']);
        $discountCode = sanitizeInput($_POST['discount_code']);
        $discountValue = floatval($_POST['discount_value']);
        $expiresAt = $_POST['expires_at'] ?? null;
    
        if ($title && $discountType && $discountValue && $business) {
            try {
                $dealId = generateUuid();
                $db->query(
                    "INSERT INTO deals (id, business_id, title, description, discount_type, discount_value, discount_code, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$dealId, $business['id'], $title, $description, $discountType, $discountValue, $discountCode, $expiresAt]
                );
                header('Location: my-business.php?tab=deals&success=deal_created');
                exit;
            } catch (Exception $e) {
                $error = 'Failed to create deal. Please try again.';
            }
        } else {
            $error = 'Please fill in all required deal fields.';
        }
    }
    
    if ($formType === 'hours' && $business) {
        $daysMap = [
            'm'  => 'Monday',
            'tu' => 'Tuesday',
            'w'  => 'Wednesday',
            'th' => 'Thursday',
            'f'  => 'Friday',
            'sa' => 'Saturday',
            'su' => 'Sunday',
        ];
    
        $hours = [];
        foreach ($daysMap as $prefix => $day) {
            $open  = sanitizeInput($_POST["{$prefix}-opening"]) ?: null;
            $close = sanitizeInput($_POST["{$prefix}-closing"]) ?: null;
            $shutdown = isset($_POST["{$prefix}-closed-checker"]);

            $hours[$day] = ['open' => $open, 'close' => $close, 'shutdown' => $shutdown];
        }
    
        try {
            $db->query("
                UPDATE businesses
                SET opening_hours = ?, updated_at = NOW()
                WHERE id = ? AND owner_id = ?
            ", [json_encode($hours), $business['id'], $user['id']]);
    
            $success = "Hours updated successfully!";
        } catch (Exception $e) {
            $error = "Failed to update hours.";
        }
    }
}

// Handle deal deletion
if (isset($_GET['delete_deal']) && $business) {
    $dealId = $_GET['delete_deal'];
    $deal = $db->fetch("SELECT * FROM deals WHERE id = ? AND business_id = ?", [$dealId, $business['id']]);
    
    if ($deal) {
        $db->query("DELETE FROM deals WHERE id = ?", [$dealId]);
        header('Location: my-business.php?tab=deals&success=deal_deleted');
        exit;
    }
}

// Get active tab from URL
$activeTab = $_GET['tab'] ?? 'overview';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'deal_created') {
        $success = 'Deal created successfully!';
    } elseif ($_GET['success'] === 'deal_deleted') {
        $success = 'Deal deleted successfully!';
    }
}

$pageTitle = 'My Business';
$pageDescription = 'Manage your business profile and information';

include 'includes/header.php';
?>

<style>
    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease-in;
    }
    .tab-content.active {
        display: block;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .nav-tab {
        position: relative;
        transition: all 0.3s ease;
    }
    .nav-tab.active {
        color: var(--accent);
        font-weight: 600;
    }
    .nav-tab.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--accent);
    }
</style>

<main class="min-h-screen bg-background py-20">
    <!-- Toast Notification -->
    <div id="toast" class="fixed top-24 right-4 z-50 hidden">
        <div class="bg-card border border-border rounded-lg shadow-lg p-4 min-w-[300px] max-w-md">
            <div class="flex items-start space-x-3">
                <div id="toast-icon" class="flex-shrink-0"></div>
                <div class="flex-1">
                    <p id="toast-message" class="text-sm text-foreground"></p>
                </div>
                <button onclick="hideToast()" class="flex-shrink-0 text-muted-foreground hover:text-foreground">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div id="confirm-modal" class="fixed inset-0 flex items-center backdrop-blur-lg justify-center bg-black/50 z-50 hidden">
        <div class="bg-card rounded-xl p-6 max-w-sm w-full shadow-xl border border-border">
            <div class="mb-4">
                <i data-lucide="alert-triangle" class="w-12 h-12 text-yellow-500 mx-auto mb-3"></i>
                <p id="confirm-text" class="text-foreground text-center"></p>
            </div>
            <div class="flex justify-center space-x-3">
                <button id="confirm-no" class="px-6 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                    Cancel
                </button>
                <button id="confirm-yes" class="px-6 py-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg">
                    Delete
                </button>
            </div>
        </div>
    </div>
    
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl geist text-foreground mb-2">My Business</h1>
            <p class="text-muted-foreground">Manage your business profile, deals, and settings</p>
        </div>

        <?php if ($error): ?>
        <div class="bg-destructive/10 border border-destructive/20 rounded-lg p-4 mb-6 flex items-start space-x-3">
            <i data-lucide="alert-circle" class="w-5 h-5 text-destructive flex-shrink-0 mt-0.5"></i>
            <span class="text-destructive"><?php echo escapeHtml($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-500/10 border border-green-500/20 rounded-lg p-4 mb-6 flex items-start space-x-3">
            <i data-lucide="check-circle" class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5"></i>
            <span class="text-green-500"><?php echo escapeHtml($success); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!$business): ?>
        <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-6 mb-6">
            <div class="flex items-start space-x-3">
                <i data-lucide="alert-triangle" class="w-6 h-6 text-yellow-500 flex-shrink-0"></i>
                <div>
                    <h3 class="text-lg font-semibold text-foreground mb-1">No Business Profile Found</h3>
                    <p class="text-muted-foreground">Create your business profile to get started with managing deals, reviews, and more.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <div class="bg-card rounded-xl border border-border mb-6 overflow-hidden">
            <div class="flex">
                <button onclick="switchTab('overview')" class="nav-tab flex geist items-center space-x-2 px-6 py-4 text-muted-foreground hover:bg-muted/50 transition-colors whitespace-nowrap <?php echo $activeTab === 'overview' ? 'active' : ''; ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span>Overview</span>
                </button>
                <button onclick="switchTab('settings')" class="nav-tab flex geist items-center space-x-2 px-6 py-4 text-muted-foreground hover:bg-muted/50 transition-colors whitespace-nowrap <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    <span>Settings</span>
                </button>
                <button onclick="switchTab('deals')" class="nav-tab flex geist items-center space-x-2 px-6 py-4 text-muted-foreground hover:bg-muted/50 transition-colors whitespace-nowrap <?php echo $activeTab === 'deals' ? 'active' : ''; ?>">
                    <i data-lucide="tag" class="w-4 h-4"></i>
                    <span>Deals</span>
                    <?php if (!empty($deals)): ?>
                    <span class="bg-accent text-black text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo count($deals); ?></span>
                    <?php endif; ?>
                </button>
                <button onclick="switchTab('hours')" class="nav-tab flex geist items-center space-x-2 px-6 py-4 text-muted-foreground hover:bg-muted/50 transition-colors whitespace-nowrap <?php echo $activeTab === 'hours' ? 'active' : ''; ?>">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                    <span>Hours</span>
                </button>
                <button onclick="switchTab('reviews')" class="nav-tab flex geist items-center space-x-2 px-6 py-4 text-muted-foreground hover:bg-muted/50 transition-colors whitespace-nowrap <?php echo $activeTab === 'reviews' ? 'active' : ''; ?>">
                    <i data-lucide="star" class="w-4 h-4"></i>
                    <span>Reviews</span>
                </button>
            </div>
        </div>

        <!-- Overview Tab -->
        <div id="tab-overview" class="tab-content <?php echo $activeTab === 'overview' ? 'active' : ''; ?>">
            <?php if ($business): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Stats Cards -->
                <div class="bg-card rounded-xl border border-border p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-muted-foreground text-sm">Average Rating</span>
                        <i data-lucide="star" class="w-5 h-5 text-yellow-500"></i>
                    </div>
                    <p class="text-3xl font-bold text-foreground"><?php echo number_format($business['average_rating'] ?: 0, 1); ?></p>
                    <p class="text-xs text-muted-foreground mt-1"><?php echo $business['total_reviews']; ?> reviews</p>
                </div>
                
                <div class="bg-card rounded-xl border border-border p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-muted-foreground text-sm">Active Deals</span>
                        <i data-lucide="tag" class="w-5 h-5 text-green-500"></i>
                    </div>
                    <p class="text-3xl font-bold text-foreground"><?php echo count($deals); ?></p>
                    <p class="text-xs text-muted-foreground mt-1">Currently active</p>
                </div>
                
                <div class="bg-card rounded-xl border border-border p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-muted-foreground text-sm">Price Range</span>
                        <i data-lucide="dollar-sign" class="w-5 h-5 text-blue-500"></i>
                    </div>
                    <p class="text-3xl font-bold text-foreground"><?php echo escapeHtml($business['price_range']); ?></p>
                    <p class="text-xs text-muted-foreground mt-1">Average cost</p>
                </div>
            </div>

            <!-- Business Preview -->
            <div class="bg-card rounded-xl border border-border overflow-hidden">
                <?php if (!empty($business['banner_image_url'])): ?>
                <div class="h-48 bg-muted overflow-hidden">
                    <img src="<?php echo escapeHtml($business['banner_image_url']); ?>" alt="Banner" class="w-full h-full object-cover">
                </div>
                <?php endif; ?>
                
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-start space-x-4">
                            <div class="w-20 h-20 rounded-lg overflow-hidden bg-muted flex items-center justify-center flex-shrink-0">
                                <?php if (!empty($business['image_url'])): ?>
                                    <img src="<?php echo escapeHtml($business['image_url']); ?>" alt="Logo" class="object-cover w-full h-full">
                                <?php else: ?>
                                    <i data-lucide="building-2" class="w-10 h-10 text-muted-foreground"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <h3 class="text-2xl geist text-foreground mb-1"><?php echo escapeHtml($business['name']); ?></h3>
                                <p class="text-muted-foreground mb-2"><?php echo escapeHtml($business['category_name']); ?></p>
                                <div class="flex items-center space-x-4 text-sm">
                                    <div class="flex items-center space-x-1">
                                        <?php echo renderStars(round($business['average_rating'] ?: 0)); ?>
                                        <span class="text-foreground font-medium"><?php echo number_format($business['average_rating'] ?: 0, 1); ?></span>
                                    </div>
                                    <span class="text-muted-foreground"><?php echo $business['total_reviews']; ?> reviews</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex space-x-2">
                            <a href="/analytics?id=<?php echo urlencode($business['id']); ?>" target="_blank" 
                               class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors flex items-center space-x-2">
                                <i data-lucide="bar-chart-2" class="w-4 h-4"></i>
                                <span>Analytics</span>
                            </a>
                            <a href="/business?id=<?php echo urlencode($business['id']); ?>" target="_blank" 
                               class="px-4 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors flex items-center space-x-2">
                                <i data-lucide="external-link" class="w-4 h-4"></i>
                                <span>View Public Page</span>
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($business['description'])): ?>
                    <p class="text-muted-foreground mb-4"><?php echo escapeHtml($business['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-border">
                        <?php if (!empty($business['address'])): ?>
                        <div class="flex items-start space-x-2">
                            <i data-lucide="map-pin" class="w-4 h-4 text-muted-foreground mt-0.5"></i>
                            <span class="text-sm text-foreground"><?php echo escapeHtml($business['address']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($business['phone'])): ?>
                        <div class="flex items-center space-x-2">
                            <i data-lucide="phone" class="w-4 h-4 text-muted-foreground"></i>
                            <span class="text-sm text-foreground"><?php echo escapeHtml($business['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($business['email'])): ?>
                        <div class="flex items-center space-x-2">
                            <i data-lucide="mail" class="w-4 h-4 text-muted-foreground"></i>
                            <span class="text-sm text-foreground"><?php echo escapeHtml($business['email']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($business['website'])): ?>
                        <div class="flex items-center space-x-2">
                            <i data-lucide="globe" class="w-4 h-4 text-muted-foreground"></i>
                            <a href="<?php echo escapeHtml($business['website']); ?>" target="_blank" class="text-sm text-accent hover:underline"><?php echo escapeHtml($business['website']); ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-card rounded-xl border border-border p-12 text-center">
                <i data-lucide="building-2" class="w-16 h-16 text-muted-foreground mx-auto mb-4"></i>
                <h3 class="text-xl font-semibold text-foreground mb-2">Create Your Business Profile</h3>
                <p class="text-muted-foreground mb-6">Get started by setting up your business information</p>
                <button onclick="switchTab('settings')" class="px-6 py-3 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors">
                    Create Business Profile
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Settings Tab -->
        <div id="tab-settings" class="tab-content <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
            <div class="bg-card rounded-xl border border-border p-6">
                <h2 class="text-2xl geist text-foreground mb-6">
                    <?php echo $business ? 'Business Settings' : 'Create Your Business'; ?>
                </h2>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-8">
                    <input type="hidden" name="form_type" value="business">
                    
                    <!-- Basic Information -->
                    <div>
                        <h3 class="text-lg font-semibold text-foreground mb-4 flex items-center">
                            <i data-lucide="info" class="w-5 h-5 mr-2"></i>
                            Basic Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-foreground mb-2">Business Name *</label>
                                <input type="text" id="name" name="name" required 
                                       value="<?php echo escapeHtml($business['name'] ?? ''); ?>"
                                       class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="Enter business name">
                            </div>
                            
                            <div>
                                <label for="category_id" class="block text-sm font-medium text-foreground mb-2">Category *</label>
                                <select id="category_id" name="category_id" required 
                                        class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo escapeHtml($category['id']); ?>" 
                                            <?php echo ($business['category_id'] ?? '') === $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo escapeHtml($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="description" class="block text-sm font-medium text-foreground mb-2">Description</label>
                            <textarea id="description" name="description" rows="4" 
                                      placeholder="Describe your business..."
                                      class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"><?php echo escapeHtml($business['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Images -->
                    <div>
                        <h3 class="text-lg font-semibold text-foreground mb-4 flex items-center">
                            <i data-lucide="image" class="w-5 h-5 mr-2"></i>
                            Images
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-2">Business Logo</label>
                                <div id="thumbnail-dropzone" 
                                     class="border-2 border-dashed border-border rounded-lg p-6 flex flex-col items-center justify-center cursor-pointer bg-input hover:border-primary transition-colors relative min-h-[150px]">
                                    <i data-lucide="upload" class="w-8 h-8 text-muted-foreground mb-2"></i>
                                    <span id="thumbnail-label" class="text-sm text-muted-foreground text-center">Drop image or click to upload</span>
                                    <input type="file" id="thumbnail" name="thumbnail" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                </div>
                                <?php if (!empty($business['image_url'])): ?>
                                <div class="mt-3">
                                    <img id="thumbnail-preview" src="<?php echo escapeHtml($business['image_url']); ?>" alt="Current Logo" class="w-32 h-32 object-cover rounded-lg border border-border">
                                </div>
                                <?php else: ?>
                                <div class="mt-3 hidden">
                                    <img id="thumbnail-preview" src="" alt="Preview" class="w-32 h-32 object-cover rounded-lg border border-border">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-2">Business Banner</label>
                                <div id="banner-dropzone" 
                                     class="border-2 border-dashed border-border rounded-lg p-6 flex flex-col items-center justify-center cursor-pointer bg-input hover:border-primary transition-colors relative min-h-[150px]">
                                    <i data-lucide="upload" class="w-8 h-8 text-muted-foreground mb-2"></i>
                                    <span id="banner-label" class="text-sm text-muted-foreground text-center">Drop image or click to upload</span>
                                    <input type="file" id="banner" name="banner" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                </div>
                                <?php if (!empty($business['banner_image_url'])): ?>
                                <div class="mt-3">
                                    <img id="banner-preview" src="<?php echo escapeHtml($business['banner_image_url']); ?>" alt="Current Banner" class="w-full h-32 object-cover rounded-lg border border-border">
                                </div>
                                <?php else: ?>
                                <div class="mt-3 hidden">
                                    <img id="banner-preview" src="" alt="Preview" class="w-full h-32 object-cover rounded-lg border border-border">
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div>
                        <h3 class="text-lg font-semibold text-foreground mb-4 flex items-center">
                            <i data-lucide="map-pin" class="w-5 h-5 mr-2"></i>
                            Location
                        </h3>
                        
                        <div class="mb-4">
                            <label for="street" class="block text-sm font-medium text-foreground mb-2">Street Address *</label>
                            <input type="text" id="street" name="street" required
                                   placeholder="123 Main Street"
                                   value="<?php echo escapeHtml($street); ?>"
                                   class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="city" class="block text-sm font-medium text-foreground mb-2">City *</label>
                                <input type="text" id="city" name="city" required
                                       value="<?php echo escapeHtml($city); ?>"
                                       placeholder="City"
                                       class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label for="state" class="block text-sm font-medium text-foreground mb-2">State *</label>
                                <input type="text" id="state" name="state" required
                                       value="<?php echo escapeHtml($state); ?>"
                                       placeholder="State"
                                       class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label for="zip" class="block text-sm font-medium text-foreground mb-2">ZIP Code *</label>
                                <input type="text" id="zip" name="zip" required
                                       value="<?php echo escapeHtml($zip); ?>"
                                       placeholder="12345"
                                       class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="country" class="block text-sm font-medium text-foreground mb-2">Country *</label>
                                <input type="text" id="country" name="country" required
                                       value="<?php echo escapeHtml($country); ?>"
                                       placeholder="Country"
                                       class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div class="flex items-end">
                                <button type="button" onclick="fillLocation()" 
                                        class="w-full px-4 py-2.5 border border-border rounded-lg hover:bg-muted transition-colors flex items-center justify-center space-x-2">
                                    <i data-lucide="navigation" class="w-4 h-4"></i>
                                    <span>Auto-detect Location</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Hidden lat/long fields -->
                        <input type="hidden" id="lat" name="lat" value="<?php echo escapeHtml($business['latitude'] ?? '0'); ?>">
                        <input type="hidden" id="long" name="long" value="<?php echo escapeHtml($business['longitude'] ?? '0'); ?>">
                    </div>
                    
                    <!-- Contact Information -->
                    <div>
                        <h3 class="text-lg font-semibold text-foreground mb-4 flex items-center">
                            <i data-lucide="phone" class="w-5 h-5 mr-2"></i>
                            Contact Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="phone" class="block text-sm font-medium text-foreground mb-2">Phone</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo escapeHtml($business['phone'] ?? ''); ?>"
                                       placeholder="(555) 123-4567"
                                       class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-foreground mb-2">Email</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo escapeHtml($business['email'] ?? ''); ?>"
                                       placeholder="contact@business.com"
                                       class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label for="website" class="block text-sm font-medium text-foreground mb-2">Website</label>
                                <input type="url" id="website" name="website" 
                                       value="<?php echo escapeHtml($business['website'] ?? ''); ?>"
                                       placeholder="https://www.business.com"
                                       class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            
                            <div>
                                <label for="price_range" class="block text-sm font-medium text-foreground mb-2">Price Range</label>
                                <select id="price_range" name="price_range"
                                    class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                
                                    <option value="$" <?php echo (($business['price_range'] ?? '') === '$') ? 'selected' : ''; ?>>
                                        $ - Budget
                                    </option>
                                
                                    <option value="$$" <?php echo (($business['price_range'] ?? '') === '$$') ? 'selected' : ''; ?>>
                                        $$ - Moderate
                                    </option>
                                
                                    <option value="$$$" <?php echo (($business['price_range'] ?? '') === '$$$') ? 'selected' : ''; ?>>
                                        $$$ - Expensive
                                    </option>
                                
                                    <option value="$$$$" <?php echo (($business['price_range'] ?? '') === '$$$$') ? 'selected' : ''; ?>>
                                        $$$$ - Very Expensive
                                    </option>
                                
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 pt-4 border-t border-border">
                        <a href="/" class="px-6 py-2.5 border border-border rounded-lg hover:bg-muted transition-colors">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2.5 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors flex items-center space-x-2">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span><?php echo $business ? 'Save Changes' : 'Create Business'; ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Deals Tab -->
        <div id="tab-deals" class="tab-content <?php echo $activeTab === 'deals' ? 'active' : ''; ?>">
            <?php if ($business): ?>
            <!-- Create Deal Form -->
            <div class="bg-card rounded-xl border border-border p-6 mb-6">
                <h2 class="text-xl geist text-foreground mb-6 flex items-center">
                    <i data-lucide="plus-circle" class="w-5 h-5 mr-2"></i>
                    Create New Deal
                </h2>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="form_type" value="deal">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="deal_title" class="block text-sm font-medium text-foreground mb-2">Deal Title *</label>
                            <input type="text" id="deal_title" name="deal_title" required
                                   placeholder="e.g., Summer Sale"
                                   class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label for="discount_type" class="block text-sm font-medium text-foreground mb-2">Discount Type *</label>
                            <select name="discount_type" id="discount_type" required
                                    class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="discount_value" class="block text-sm font-medium text-foreground mb-2">Discount Value *</label>
                            <input type="number" name="discount_value" id="discount_value" required step="0.01" min="0"
                                   placeholder="10"
                                   class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label for="discount_code" class="block text-sm font-medium text-foreground mb-2">Discount Code *</label>
                            <input type="text" name="discount_code" id="discount_code" required
                                   placeholder="SUMMER2024"
                                   class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label for="expires_at" class="block text-sm font-medium text-foreground mb-2">Expires At *</label>
                            <input type="date" name="expires_at" id="expires_at" required
                                   class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <div>
                        <label for="deal_description" class="block text-sm font-medium text-foreground mb-2">Description</label>
                        <textarea name="deal_description" id="deal_description" rows="3"
                                  placeholder="Describe your deal..."
                                  class="w-full px-4 py-2.5 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-2.5 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors flex items-center space-x-2">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span>Create Deal</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Existing Deals -->
            <div class="bg-card rounded-xl border border-border p-6">
                <h2 class="text-xl geist text-foreground mb-6">Active Deals</h2>
                
                <?php if (empty($deals)): ?>
                <div class="text-center py-12">
                    <i data-lucide="tag" class="w-16 h-16 text-muted-foreground mx-auto mb-4"></i>
                    <h3 class="text-lg geist text-foreground mb-2">No deals yet</h3>
                    <p class="text-muted-foreground">Create your first deal to attract more customers</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($deals as $deal): ?>
                    <div class="p-5 border border-border rounded-lg bg-muted/30 hover:bg-muted/50 transition-colors">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="text-lg font-semibold text-foreground"><?php echo escapeHtml($deal['title']); ?></h4>
                                    <div class="flex items-center space-x-2 ml-4">
                                        <span class="px-3 py-1 bg-accent/20 text-accent text-xs font-semibold rounded-full">
                                            <?php 
                                                if ($deal['discount_type'] === 'percentage') {
                                                    echo escapeHtml($deal['discount_value']) . '% OFF';
                                                } else {
                                                    echo '$' . escapeHtml($deal['discount_value']) . ' OFF';
                                                }
                                                ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($deal['description'])): ?>
                                <p class="text-muted-foreground text-sm mb-3"><?php echo escapeHtml($deal['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <div class="flex items-center space-x-1.5">
                                        <i data-lucide="ticket" class="w-4 h-4 text-muted-foreground"></i>
                                        <span class="text-foreground font-mono"><?php echo escapeHtml($deal['discount_code']); ?></span>
                                    </div>
                                    <div class="flex items-center space-x-1.5">
                                        <i data-lucide="calendar" class="w-4 h-4 text-muted-foreground"></i>
                                        <span class="text-muted-foreground">Expires: <?php echo date('M d, Y', strtotime($deal['expires_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex space-x-2 ml-4">
                                <a href="/edit_deal?id=<?php echo urlencode($deal['id']); ?>"
                                   class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors flex items-center space-x-1.5">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                    <span>Edit</span>
                                </a>
                                <button onclick="handleDeleteDeal('<?php echo urlencode($deal['id']); ?>')"
                                        class="px-4 py-2 bg-red-500/10 border border-red-500/20 text-red-500 hover:bg-red-500/20 rounded-lg transition-colors flex items-center space-x-1.5">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="bg-card rounded-xl border border-border p-12 text-center">
                <i data-lucide="alert-circle" class="w-16 h-16 text-yellow-500 mx-auto mb-4"></i>
                <h3 class="text-xl font-semibold text-foreground mb-2">Business Profile Required</h3>
                <p class="text-muted-foreground mb-6">Create your business profile before adding deals</p>
                <button onclick="switchTab('settings')" class="px-6 py-3 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors">
                    Go to Settings
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Hours Tab -->
        <div id="tab-hours" class="tab-content <?php echo $activeTab === 'hours' ? 'active' : ''; ?>">
            <?php if ($business): ?>
            <div class="bg-card rounded-xl border border-border p-6">
                <h2 class="text-xl geist text-foreground mb-6 flex items-center">
                    <i data-lucide="clock" class="w-5 h-5 mr-2"></i>
                    Business Hours
                </h2>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="form_type" value="hours">
                    
                    <?php
                    $days = ['Monday'=>'m','Tuesday'=>'tu','Wednesday'=>'w','Thursday'=>'th','Friday'=>'f','Saturday'=>'sa','Sunday'=>'su'];
                    $times = [];
                    for ($h=0;$h<24;$h++) {
                        for ($m=0;$m<60;$m+=30) {
                            $times[] = sprintf('%02d:%02d', $h, $m);
                        }
                    }
                    
                    foreach ($days as $day => $prefix): ?>
                    <div class="p-4 bg-muted/30 rounded-lg">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-3 md:space-y-0">
                            <div class="flex items-center justify-between md:w-48">
                                <label class="text-sm font-medium text-foreground"><?php echo $day; ?></label>
                                <div class="flex items-center space-x-2">
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" id="<?php echo $prefix; ?>-closed-checker" name="<?php echo $prefix; ?>-closed-checker"
                                               class="w-4 h-4 border-2 border-border rounded checked:bg-accent checked:border-accent transition">
                                        <span class="text-sm text-muted-foreground">Closed</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2 flex-1 md:max-w-md">
                                <select id="<?php echo $prefix; ?>-opening" name="<?php echo $prefix; ?>-opening"
                                        class="flex-1 px-3 py-2 bg-input border border-border rounded-lg text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">Open</option>
                                    <?php foreach($times as $t): ?>
                                        <option value="<?php echo $t; ?>"><?php echo date('g:i A', strtotime($t)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <span class="text-muted-foreground">to</span>
                                
                                <select id="<?php echo $prefix; ?>-closing" name="<?php echo $prefix; ?>-closing"
                                        class="flex-1 px-3 py-2 bg-input border border-border rounded-lg text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">Close</option>
                                    <?php foreach($times as $t): ?>
                                        <option value="<?php echo $t; ?>"><?php echo date('g:i A', strtotime($t)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button type="button" onclick="copyHours('<?php echo $prefix; ?>')" 
                                        class="px-3 py-2 border border-border rounded-lg hover:bg-muted transition-colors" 
                                        title="Copy to all days">
                                    <i data-lucide="copy" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="flex justify-end pt-4 border-t border-border">
                        <button type="submit" class="px-6 py-2.5 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors flex items-center space-x-2">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span>Save Hours</span>
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="bg-card rounded-xl border border-border p-12 text-center">
                <i data-lucide="alert-circle" class="w-16 h-16 text-yellow-500 mx-auto mb-4"></i>
                <h3 class="text-xl font-semibold text-foreground mb-2">Business Profile Required</h3>
                <p class="text-muted-foreground mb-6">Create your business profile before setting hours</p>
                <button onclick="switchTab('settings')" class="px-6 py-3 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors">
                    Go to Settings
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Reviews Tab -->
        <div id="tab-reviews" class="tab-content <?php echo $activeTab === 'reviews' ? 'active' : ''; ?>">
            <div class="bg-card rounded-xl border border-border p-12 text-center">
                <i data-lucide="star" class="w-16 h-16 text-muted-foreground mx-auto mb-4"></i>
                <h3 class="text-xl geist text-foreground mb-2">Reviews Coming Soon</h3>
                <p class="text-muted-foreground">You will be able to view and reply to reviews here.</p>
            </div>
        </div>
    </div>
</main>

<script>
// Tab Management
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all nav tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    const selectedTab = document.getElementById('tab-' + tabName);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Add active class to clicked nav tab
    event.target.closest('.nav-tab').classList.add('active');
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
    
    // Reinitialize icons
    lucide.createIcons();
}

// Toast Notifications
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const toastIcon = document.getElementById('toast-icon');
    
    toastMessage.textContent = message;
    toastIcon.innerHTML = '';
    
    let iconName = 'info';
    let iconColor = 'text-blue-500';
    
    switch(type) {
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
    }
    
    const icon = document.createElement('i');
    icon.setAttribute('data-lucide', iconName);
    icon.className = `w-5 h-5 ${iconColor}`;
    toastIcon.appendChild(icon);
    
    toast.classList.remove('hidden');
    lucide.createIcons();
    
    setTimeout(() => hideToast(), 5000);
}

function hideToast() {
    document.getElementById('toast').classList.add('hidden');
}

// Confirm Dialog
function showConfirm(message) {
    return new Promise(resolve => {
        const modal = document.getElementById('confirm-modal');
        const text = document.getElementById('confirm-text');
        const yesBtn = document.getElementById('confirm-yes');
        const noBtn = document.getElementById('confirm-no');
        
        text.textContent = message;
        modal.classList.remove('hidden');
        lucide.createIcons();
        
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

// Deal Deletion
async function handleDeleteDeal(dealId) {
    const confirmed = await showConfirm('Are you sure you want to delete this deal? This action cannot be undone.');
    if (confirmed) {
        window.location.href = '?delete_deal=' + dealId;
    }
}

// Image Upload
function setupDragDrop(inputId, labelId, previewId, dropzoneId) {
    const input = document.getElementById(inputId);
    const label = document.getElementById(labelId);
    const preview = document.getElementById(previewId);
    const dropzone = document.getElementById(dropzoneId);
    
    if (!input || !dropzone) return;
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropzone.classList.add('border-primary', 'bg-muted/20');
        });
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropzone.classList.remove('border-primary', 'bg-muted/20');
        });
    });
    
    dropzone.addEventListener('drop', (e) => {
        const file = e.dataTransfer.files[0];
        if (file) {
            input.files = e.dataTransfer.files;
            updatePreview(file);
        }
    });

    input.addEventListener('change', () => {
        if (input.files && input.files[0]) {
            updatePreview(input.files[0]);
        }
    });

    function updatePreview(file) {
        if (!preview) return;
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.parentElement.classList.remove('hidden');
            if (label) label.textContent = file.name;
        };
        reader.readAsDataURL(file);
    }
}

// Auto-detect location
function fillLocation() {
    if (!navigator.geolocation) {
        showToast('Geolocation not supported', 'error');
        return;
    }

    navigator.geolocation.getCurrentPosition(async position => {
        const lat = position.coords.latitude;
        const lon = position.coords.longitude;

        document.getElementById('lat').value = lat;
        document.getElementById('long').value = lon;

        try {
            const res = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`
            );
            const data = await res.json();

            document.getElementById('street').value =
                `${data.address.house_number || ''} ${data.address.road || ''}`.trim();
            document.getElementById('city').value =
                data.address.city || data.address.town || data.address.village || '';
            document.getElementById('state').value = data.address.state || '';
            document.getElementById('zip').value = data.address.postcode || '';
            document.getElementById('country').value = data.address.country || '';

            showToast('Location detected successfully', 'success');
        } catch {
            showToast('Failed to detect address', 'error');
        }
    }, () => {
        showToast('Location permission denied', 'error');
    });
}

// Copy hours to all days
function copyHours(prefix) {
    const open = document.getElementById(`${prefix}-opening`).value;
    const close = document.getElementById(`${prefix}-closing`).value;
    const closed = document.getElementById(`${prefix}-closed-checker`).checked;

    ['m','tu','w','th','f','sa','su'].forEach(p => {
        if (p === prefix) return;
        document.getElementById(`${p}-opening`).value = open;
        document.getElementById(`${p}-closing`).value = close;
        document.getElementById(`${p}-closed-checker`).checked = closed;
    });

    showToast('Hours copied to all days', 'success');
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    setupDragDrop('thumbnail', 'thumbnail-label', 'thumbnail-preview', 'thumbnail-dropzone');
    setupDragDrop('banner', 'banner-label', 'banner-preview', 'banner-dropzone');

    lucide.createIcons();

    // Flash success from query params
    const params = new URLSearchParams(window.location.search);
    if (params.get('success') === 'deal_created') {
        showToast('Deal created successfully!', 'success');
    }
    if (params.get('success') === 'deal_deleted') {
        showToast('Deal deleted successfully!', 'success');
    }
});
</script>

<?php
require_once 'functions.php';

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
        // $address = sanitizeInput($_POST['address']);
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
        
        // Insert into database
        if ($name && $categoryId && $address) {
            try {
                if ($business) {
                    // Update existing business
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
                    // Create new business
                    $businessId = generateUuid();
                    $db->query("
                        INSERT INTO businesses (id, name, description, category_id, owner_id, address, phone, email, website, latitude, longitude, price_range, image_url, banner_image_url, created_at, updated_at, timezone) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
                    ", [
                        $businessId, $name, $description, $categoryId, $user['id'], $address, $phone, $email, $website, $lat, $long, $priceRange, $imageUrl, $bannerUrl, $timezone
                    ]);

                    $success = 'Business created successfully!';
                    
                    // Refresh business data
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
                header('Location: my-business.php');
                exit;
                
                // Shouldn't run this
                $success = 'Deal created successfully!';
                // Refresh deals list
                $deals = $db->fetchAll("SELECT * FROM deals WHERE business_id = ? ORDER BY created_at DESC", [$business['id']]);
                
            }catch (Exception $e) {
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
            $hours[$day] = ['open' => $open, 'close' => $close];
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

// Handle deal deletion (thanks GPT for this one :)
if (isset($_GET['delete_deal']) && $business) {
    $dealId = $_GET['delete_deal'];
    $deal = $db->fetch("SELECT * FROM deals WHERE id = ? AND business_id = ?", [$dealId, $business['id']]);
    
    if ($deal) {
        $db->query("DELETE FROM deals WHERE id = ?", [$dealId]);
        $success = "Deal deleted successfully.";
        
        header('Location: my-business.php');
        exit;
        
        // Shouldn't run this
        // Refresh deals list
        $deals = $db->fetchAll("SELECT * FROM deals WHERE business_id = ? ORDER BY created_at DESC", [$business['id']]);
    } else {
        // This is totally unnecessary, it adds an ugly warning if there are no associated deals in the DB
        // even if nothing is wrong. 
        // $error = "No deals found for this business.";
    }
}

// Initialize page
$pageTitle = 'My Business';
$pageDescription = 'Manage your business profile and information';

include 'includes/header.php';
?>

<!-- yucky style tags -->
<style>
.tab {
  display: none; 
}
.active {
  display: block;
}
</style>

<main class="min-h-screen bg-background">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-foreground mb-2">My Business</h1>
            <p class="text-muted-foreground">Manage your business profile and information</p>
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

        <?php if ($business): ?>
        <!-- Toolbar -->
        <div class="bg-card rounded-xl border border-border p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <a onclick="openTab('overview')" target="_blank" 
                       class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors mr-2">
                        <i data-lucide="menu" class="w-4 h-4 mr-2 inline"></i>
                        Overview
                    </a>
                    <a onclick="openTab('business-setting')" target="_blank" 
                       class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors mr-2">
                        <i data-lucide="settings" class="w-4 h-4 mr-2 inline"></i>
                        Business Settings
                    </a>
                    <a onclick="openTab('deals')" target="_blank" 
                       class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors mr-2">
                        <i data-lucide="circle-dollar-sign" class="w-4 h-4 mr-2 inline"></i>
                        Deals
                    </a>
                    <a onclick="openTab('reviews')" target="_blank" 
                       class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors mr-2">
                        <i data-lucide="star" class="w-4 h-4 mr-2 inline"></i>
                        Reviews
                    </a>
                    <a onclick="openTab('hours')" target="_blank" 
                       class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors mr-2">
                        <i data-lucide="Clock" class="w-4 h-4 mr-2 inline"></i>
                        Hours
                    </a>
                </div>
            </div>
        </div>
        <!-- Business Preview -->
        
        <div id="overview" class="tab">
            <div class="bg-card rounded-xl border border-border p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-foreground">Business Preview</h2>
                    <div>
                        <a href="/analytics.php?id=<?php echo urlencode($business['id']); ?>" target="_blank" 
                           class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="external-link" class="w-4 h-4 mr-2 inline"></i>
                            View Analytics
                        </a>
                        <a href="/business.php?id=<?php echo urlencode($business['id']); ?>" target="_blank" 
                           class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="external-link" class="w-4 h-4 mr-2 inline"></i>
                            View Public Page
                        </a>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="w-20 h-20 rounded-lg overflow-hidden bg-muted flex items-center justify-center">
                        <?php if (!empty($business['image_url'])): ?>
                            <img src="<?php echo escapeHtml($business['image_url']); ?>" alt="Thumbnail" class="object-cover w-full h-full">
                        <?php else: ?>
                            <i data-lucide="building-2" class="w-8 h-8 text-muted-foreground"></i>
                        <?php endif; ?>
                    </div>
    
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-foreground mb-1"><?php echo escapeHtml($business['name']); ?></h3>
                        <p class="text-muted-foreground mb-2"><?php echo escapeHtml($business['category_name']); ?></p>
                        <div class="flex items-center space-x-4 text-sm text-muted-foreground">
                            <div class="flex items-center space-x-1">
                                <?php echo renderStars(round($business['average_rating'] ?: 0)); ?>
                                <span><?php echo number_format($business['average_rating'] ?: 0, 1); ?></span>
                            </div>
                            <span><?php echo $business['total_reviews']; ?> reviews</span>
                            <span><?php echo escapeHtml($business['price_range']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>

        <div id="business-setting" class="tab">
            <!-- Business Form -->
            <div class="bg-card rounded-xl border border-border p-6">
                <h2 class="text-xl font-semibold text-foreground mb-6">
                    <?php echo $business ? 'Edit Business Information' : 'Create Your Business Profile'; ?>
                </h2>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="form_type" value="business">
    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-foreground mb-2">Business Name *</label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo escapeHtml($business['name'] ?? $_POST['name'] ?? ''); ?>"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-foreground mb-2">Category *</label>
                            <select id="category_id" name="category_id" required 
                                    class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo escapeHtml($category['id']); ?>" 
                                        <?php echo ($business['category_id'] ?? $_POST['category_id'] ?? '') === $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo escapeHtml($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
    
                    <div>
                        <label for="description" class="block text-sm font-medium text-foreground mb-2">Description</label>
                        <textarea id="description" name="description" rows="4" 
                                  placeholder="Tell customers about your business..."
                                  class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"><?php echo escapeHtml($business['description'] ?? $_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-2">Business Thumbnail</label>
                        <div id="thumbnail-dropzone" 
                             class="border-2 border-dashed border-border rounded-lg px-4 py-8 flex flex-col items-center justify-center cursor-pointer bg-input text-foreground hover:border-primary transition-colors relative">
                            <span id="thumbnail-label">Drag & drop an image here, or click to browse</span>
                            <input type="file" id="thumbnail" name="thumbnail" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        </div>
                        <div class="mt-2">
                            <img id="thumbnail-preview" src="<?php echo escapeHtml($business['image_url'] ?? ''); ?>" alt="Thumbnail Preview" class="hidden w-32 h-32 object-cover rounded-lg">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-2">Business Banner</label>
                        <div id="banner-dropzone" 
                             class="border-2 border-dashed border-border rounded-lg px-4 py-8 flex flex-col items-center justify-center cursor-pointer bg-input text-foreground hover:border-primary transition-colors relative">
                            <span id="banner-label">Drag & drop an image here, or click to browse</span>
                            <input type="file" id="banner" name="banner" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        </div>
                        <div class="mt-2">
                            <img id="banner-preview" src="<?php echo escapeHtml($business['banner_image_url'] ?? ''); ?>" alt="Banner Preview" class="hidden w-full h-40 object-cover rounded-lg">
                        </div>
                    </div>
    
                    <div>
                        <label for="street" class="block text-sm font-medium text-foreground mb-2">Street Address *</label>
                        <input type="text" id="street" name="street"  
                                  placeholder="Enter your business address"
                                  value="<?php echo escapeHtml($street); ?>"
                                  class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                    
                    <!-- hidden values -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5" style="display: none;">
                        <div>
                            <label for="lat" class="block text-sm font-medium text-foreground mb-2">Latitude *</label>
                            <input type="text" id="lat" name="lat" required
                                   value="<?php echo escapeHtml($business['latitude'] ?? $_POST['lat'] ?? ''); ?>"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label for="long" class="block text-sm font-medium text-foreground mb-2">Longitude *</label>
                            <input type="text" id="long" name="long" required
                                   value="<?php echo escapeHtml($business['longitude'] ?? $_POST['long'] ?? ''); ?>"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label for="city" class="block text-sm font-medium text-foreground mb-2">City *</label>
                            <input type="text" id="city" name="city" required
                                   value="<?php echo escapeHtml($city); ?>"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label for="state" class="block text-sm font-medium text-foreground mb-2">State *</label>
                            <input type="text" id="state" name="state" required
                                   value="<?php echo escapeHtml($state); ?>"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label for="zip" class="block text-sm font-medium text-foreground mb-2">ZIP *</label>
                            <input type="text" id="zip" name="zip" required
                                   value="<?php echo escapeHtml($zip); ?>"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="country" class="block text-sm font-medium text-foreground mb-2">Country *</label>
                            <input type="text" id="country" name="country" required
                                   value="<?php echo escapeHtml($country); ?>"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="country" class="block text-sm font-medium text-foreground mb-2">&nbsp;</label>
                            <button type="button" onclick="fillLocation()" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                                Autofill Location
                            </button>
                        </div>
                    </div>
                    </div>
    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-foreground mb-2">Phone</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo escapeHtml($business['phone'] ?? $_POST['phone'] ?? ''); ?>"
                                   placeholder="(555) 123-4567"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-foreground mb-2">Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo escapeHtml($business['email'] ?? $_POST['email'] ?? ''); ?>"
                                   placeholder="contact@business.com"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="website" class="block text-sm font-medium text-foreground mb-2">Website</label>
                            <input type="url" id="website" name="website" 
                                   value="<?php echo escapeHtml($business['website'] ?? $_POST['website'] ?? ''); ?>"
                                   placeholder="https://www.business.com"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label for="price_range" class="block text-sm font-medium text-foreground mb-2">Price Range</label>
                            <select id="price_range" name="price_range" 
                                    class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="$" <?php echo ($business['price_range'] ?? $_POST['price_range'] ?? '') === '$' ? 'selected' : ''; ?>>$ - Budget</option>
                                <option value="$$" <?php echo ($business['price_range'] ?? $_POST['price_range'] ?? '$$') === '$$' ? 'selected' : ''; ?>>$$ - Moderate</option>
                                <option value="$$$" <?php echo ($business['price_range'] ?? $_POST['price_range'] ?? '') === '$$$' ? 'selected' : ''; ?>>$$$ - Expensive</option>
                                <option value="$$$$" <?php echo ($business['price_range'] ?? $_POST['price_range'] ?? '') === '$$$$' ? 'selected' : ''; ?>>$$$$ - Very Expensive</option>
                            </select>
                        </div>
                    </div>
    
                    <div class="flex justify-end space-x-4">
                        <a href="/" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors">
                            <?php echo $business ? 'Update Business' : 'Create Business'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="deals" class="tab">
            <!-- Deal Form -->
            <div class="bg-card rounded-xl border border-border p-6">
                <h2 class="text-xl font-semibold text-foreground mb-6">
                    <?php echo $business ? 'Add a Deal' : 'Add a Deal'; ?>
                </h2>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="form_type" value="deal">
                
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="deal_title" class="block text-sm font-medium text-foreground mb-2">Deal Title *</label>
                            <input type="text" id="deal_title" name="deal_title" required
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
                        </div>
                
                        <div>
                            <label for="discount_type" class="block text-sm font-medium text-foreground mb-2">Discount Type *</label>
                            <select name="discount_type" id="discount_type" required
                                    class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                    </div>
                
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="discount_value" class="block text-sm font-medium text-foreground mb-2">Discount Value *</label>
                            <input type="number" name="discount_value" id="discount_value" required step="0.01" min="0"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
                        </div>
                
                        <div>
                            <label for="discount_code" class="block text-sm font-medium text-foreground mb-2">Discount Code *</label>
                            <input type="text" name="discount_code" id="discount_code" required
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="expires_at" class="block text-sm font-medium text-foreground mb-2">Expires At *</label>
                            <input type="date" name="expires_at" id="expires_at" required
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground">
                        </div>
                    </div>
                
                    <div>
                        <label for="deal_description" class="block text-sm font-medium text-foreground mb-2">Description</label>
                        <textarea name="deal_description" id="deal_description" rows="3"
                                  class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground"></textarea>
                    </div>
                
                    <div class="flex justify-end space-x-4">
                        <button type="submit" class="px-6 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg">
                            Create Deal
                        </button>
                    </div>
                </form>
            </div> 
            
            <div class="p-3 mb-3">
                <!--spacer :)-->
            </div>
            
            <div class="bg-card rounded-xl border border-border p-6">
                <h2 class="text-xl font-semibold text-foreground mb-6">
                    Existing Deals
                </h2>
                <?php if (empty($deals)): ?>
                    Create a deal to get started.
                <?php endif; ?>
                
                <?php if (!empty($deals)): ?>
                    <div class="mt-8">
                        <div class="space-y-4">
                            <?php foreach ($deals as $deal): ?>
                                <div class="p-4 border border-border rounded-lg bg-muted flex justify-between items-center">
                                    <div>
                                        <h4 class="text-md font-semibold text-foreground"><?php echo escapeHtml($deal['title']); ?></h4>
                                        <p class="text-muted-foreground text-sm">
                                            <?php echo escapeHtml($deal['description']); ?>
                                        </p>
                                        <p class="text-sm mt-1">
                                            <strong>Type:</strong> <?php echo escapeHtml($deal['discount_type']); ?> |
                                            <strong>Value:</strong> <?php echo escapeHtml($deal['discount_value']); ?> |
                                            <strong>Expires:</strong> <?php echo escapeHtml($deal['expires_at']); ?>
                                        </p>
                                    </div>
                                    <div class="space-x-2">
                                        <a href="/edit_deal.php?id=<?php echo urlencode($deal['id']); ?>"
                                           class="px-6 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg">Edit</a>
                                        <a href="?delete_deal=<?php echo urlencode($deal['id']); ?>"
                                           onclick="event.preventDefault(); handleDeleteDeal(this.href);"
                                           class="px-6 py-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-lg">
                                           Delete
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <div id="reviews" class="tab">
            <!-- Deal Form -->
            <div class="bg-card rounded-xl border border-border p-6">
                <h2 class="text-xl font-semibold text-foreground mb-6">
                    <?php echo $business ? 'Reviews' : 'Reviews'; ?>
                </h2>

            </div>
        </div>
        
        <div id="hours" class="tab">
            <!-- Hours Form -->
            <div class="bg-card rounded-xl border border-border p-6">
                <h2 class="text-xl font-semibold text-foreground mb-6">
                    <?php echo $business ? 'Set Hours' : 'Set Hours'; ?>
                </h2>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="form_type" value="hours">
                
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php
                        $days = ['Monday'=>'m','Tuesday'=>'tu','Wednesday'=>'w','Thursday'=>'th','Friday'=>'f','Saturday'=>'sa','Sunday'=>'su'];
                        $times = [];
                        for ($h=0;$h<24;$h++) {
                            for ($m=0;$m<60;$m+=30) {
                                $times[] = sprintf('%02d:%02d', $h, $m);
                            }
                        }
                        foreach ($days as $day => $prefix): ?>
                        <div class="flex flex-col space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-foreground"><?php echo $day; ?> *</label>
                                <button type="button" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors mr-2" onclick="setAllHours('<?php echo $prefix; ?>')">&raquo;</button>
                            </div>
                            <div class="flex space-x-2">
                                <select id="<?php echo $prefix; ?>-opening" name="<?php echo $prefix; ?>-opening" required
                                        class="w-1/2 px-3 py-2 bg-input border border-border rounded-lg text-foreground">
                                    <option value="">Open</option>
                                    <?php foreach($times as $t): ?>
                                        <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="<?php echo $prefix; ?>-closing" name="<?php echo $prefix; ?>-closing" required
                                        class="w-1/2 px-3 py-2 bg-input border border-border rounded-lg text-foreground">
                                    <option value="">Close</option>
                                    <?php foreach($times as $t): ?>
                                        <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-4">
                        <button type="submit" class="px-6 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg">
                            Set Hours
                        </button>
                    </div>
                    
                    <script>
                    function setAllHours(prefix) {
                        const openVal = document.getElementById(prefix + '-opening').value;
                        const closeVal = document.getElementById(prefix + '-closing').value;
                        const dayIds = ['m','tu','w','th','f','sa','su'];
                        dayIds.forEach(d => {
                            document.getElementById(d + '-opening').value = openVal;
                            document.getElementById(d + '-closing').value = closeVal;
                        });
                    }
                    </script>

                </form>
            </div> 
        </div>
        
    </div>    
    
    <!-- Modals -->
    
    <div id="message-modal" class="fixed inset-0 flex items-center backdrop-blur-lg justify-center bg-black/50 z-50 hidden">
        <div class="bg-card rounded-xl p-6 max-w-sm w-full text-center relative shadow-lg">
            <button id="message-close" class="absolute top-2 right-2 text-muted-foreground hover:text-foreground text-xl font-bold">
                &times;
            </button>
            <div id="message-icon" class="mb-4 flex justify-center"></div>
            <p id="message-text" class="text-foreground text-sm"></p>
        </div>
    </div>

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
    openTab('overview');
    
    lucide.createIcons(); // for context this has to run for every section with Lucide icons
                          // it's run in triplicate on most pages (header, body, footer)
                          // the performance hit is massive
                          // you know what else is still massive?
                          // THE LOW TAPER FADE MEME
    
    // To make different "tabs" visible
    function openTab(tabId) {
      const tabs = document.querySelectorAll('.tab');
      tabs.forEach(tab => tab.classList.remove('active'));
        
      const activeTab = document.getElementById(tabId);
      if (activeTab) {
        activeTab.classList.add('active');
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
    
    async function handleDeleteDeal(url) {
        const confirmed = await showConfirm("Are you sure you want to delete this deal?");
        if (confirmed) {
            window.location.href = url;
        }
    }

    function setupDragDrop(inputId, labelId, previewId, dropzoneId) {
        const input = document.getElementById(inputId);
        const label = document.getElementById(labelId);
        const preview = document.getElementById(previewId);
        const dropzone = document.getElementById(dropzoneId);
    
        dropzone.addEventListener('click', () => input.click());
    
        ['dragenter', 'dragover'].forEach(event => {
            dropzone.addEventListener(event, (e) => {
                e.preventDefault();
                dropzone.classList.add('border-primary', 'bg-muted/20');
            });
        });
    
        ['dragleave', 'drop'].forEach(event => {
            dropzone.addEventListener(event, (e) => {
                e.preventDefault();
                dropzone.classList.remove('border-primary', 'bg-muted/20');
            });
        });
        
        dropzone.addEventListener('drop', (e) => {
            const file = e.dataTransfer.files[0];
            if (file) {
                input.files = e.dataTransfer.files;
                label.textContent = file.name;
    
                if (preview) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        preview.src = ev.target.result;
                        preview.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            }
        });
        
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (file) {
                label.textContent = file.name;
    
                if (preview) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        preview.src = ev.target.result;
                        preview.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            } else {
                label.textContent = 'Drag & drop an image here, or click to browse';
                if (preview) preview.classList.add('hidden');
            }
        });
    }
    
    setupDragDrop('thumbnail', 'thumbnail-label', 'thumbnail-preview', 'thumbnail-dropzone');
    setupDragDrop('banner', 'banner-label', 'banner-preview', 'banner-dropzone');
    
    async function fillLocation() {
        if (!navigator.geolocation) {
            showMessage("Geolocation is not supported by your browser.", "error");
            return;
        }
    
        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const lat = position.coords.latitude;
                const long = position.coords.longitude;
    
                document.getElementById('lat').value = lat.toFixed(6);
                document.getElementById('long').value = long.toFixed(6);
    
                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${long}&addressdetails=1`);
                    if (!response.ok) throw new Error("Failed to fetch address");
    
                    const data = await response.json();
                    const a = data.address;
    
                    const street = [a.house_number, a.road].filter(Boolean).join(' ');
                    const city = a.city || a.town || a.village || '';
                    const state = a.state || '';
                    const zip = a.postcode || '';
                    const country = a.country || '';
    
                    document.getElementById('street').value = street;
                    document.getElementById('city').value = city;
                    document.getElementById('state').value = state;
                    document.getElementById('zip').value = zip;
                    document.getElementById('country').value = country;
    
                } catch (error) {
                    console.error(error);
                    showMessage("Could not get address.", "error");
                }
            },
            (error) => {
                showMessage("Geolocation failed: " + error.message, "error");
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }
</script>

<?php include 'includes/footer.php'; ?>
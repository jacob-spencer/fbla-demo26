<?php
require_once 'functions.php';

requireAuth();

$db = Database::getInstance();
$error = '';
$success = '';
$user = getCurrentUser();

// Get user's business
$business = $db->fetch("
    SELECT b.*, c.name as category_name 
    FROM businesses b 
    LEFT JOIN categories c ON b.category_id = c.id 
    WHERE b.owner_id = ?", [$user['id']]);

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
        $name = ($_POST['name']);
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
                        WHERE b.owner_id = ?", [$user['id']]
                    );
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
        header('Location: my-business.php?success=' . urlencode('Deal deleted successfully.'));
        exit;
    }
}

// Get reviews for the business
$reviews = [];
if ($business) {
    $reviews = $db->fetchAll("
        SELECT r.*, u.first_name, u.last_name, u.profile_image_url, u.username 
        FROM reviews r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.business_id = ?
        ORDER BY r.created_at DESC
    ", [$business['id']]);
}

// Handle success messages from redirects
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Initialize page
$pageTitle = 'My Business';
$pageDescription = 'Manage your business profile and information';

include 'includes/header.php';
?>
<style>
/* Glowing wave effect */
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
</style>

<section id="waves" class="absolute inset-0 -z-10 pointer-events-none"></section>

<script>
document.addEventListener("DOMContentLoaded", () => {const container = document.getElementById("waves");const wavePatterns = [{ classes: "left-1/2 -translate-x-1/2 w-[100vw] h-[70vh] wave-right-top", verticalOffset: -0.25 },{ classes: "-left-1/2 w-[90vw] h-[90vh] wave-left-top", verticalOffset: 0.33 },{ classes: "left-1/2 w-[100vw] h-[75vh] wave-left-bottom", verticalOffset: 0.66 },{ classes: "-left-1/2 w-[80vw] h-[85vh] wave-right-bottom", verticalOffset: 0 },{ classes: "left-1/2 w-[80vw] h-[85vh] wave-right-bottom", verticalOffset: 0 },{ classes: "left-1/2 w-[100vw] h-[70vh] wave-left-bottom", verticalOffset: -0.2 },];function spawnWaves() {container.innerHTML = "";const pageHeight = document.body.scrollHeight;const sectionHeight = window.innerHeight;const numSections = Math.ceil(pageHeight / sectionHeight);for (let i = 0; i < numSections; i++) {const offsetY = i * sectionHeight;wavePatterns.forEach(cfg => {const div = document.createElement("div");div.className = `absolute ${cfg.classes}`;const waveOffset = cfg.verticalOffset ? cfg.verticalOffset * sectionHeight : 0;div.style.top = `${offsetY + waveOffset}px`;container.appendChild(div);});}}function resizeWavesContainer() {container.style.height = document.body.scrollHeight + "px";}window.addEventListener("load", () => {resizeWavesContainer();spawnWaves();});window.addEventListener("resize", () => {resizeWavesContainer();spawnWaves();});});
</script>

<style>
    .sidebar-section {
        display: none;
    }
    .sidebar-section.active {
        display: block;
    }
    .sidebar-nav-item.active {
        background-color: hsl(var(--accent));
        color: black;
        font-weight: 600;
    }
</style>

<script>
    function showMessage(e,t="info"){let a=document.getElementById("message-modal"),l=document.getElementById("message-text"),n=document.getElementById("message-icon"),s=document.getElementById("message-close");l.innerHTML=e,n.innerHTML="";let c="",i="";switch(t){case"success":c="check-circle",i="text-green-500";break;case"error":c="alert-circle",i="text-red-500";break;case"warning":c="alert-triangle",i="text-yellow-500";break;default:c="info",i="text-blue-500"}let d=document.createElement("i");d.setAttribute("data-lucide",c),d.className=`w-10 h-10 ${i} mb-2`,n.appendChild(d),a.classList.remove("hidden"),lucide.createIcons(),s.onclick=()=>{a.classList.add("hidden")},a.onclick=e=>{e.target===a&&a.classList.add("hidden")}}document.addEventListener("DOMContentLoaded",function(e){console.log("DOM fully loaded, getting lat/long"),console.log("Prompting user location"),fillLatLong()});
</script>

<main class="min-h-screen bg-background pt-16">
    <div id="message-modal" class="fixed inset-0 flex items-center backdrop-blur-lg justify-center bg-black/50 z-50 hidden">
        <div class="bg-card rounded-xl p-6 max-w-sm w-full text-center relative shadow-lg">
            <button id="message-close" class="absolute top-2 right-2 text-muted-foreground hover:text-foreground text-xl font-bold">
                &times; <!-- <<< this is dumb -->
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
    
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-card/40 top-16 sticky h-[calc(100vh-5rem)] overflow-y-auto hidden lg:block">
            <div class="p-6">
                <h2 class="text-xl font-bold text-foreground mb-6">My Business</h2>
                <nav class="space-y-2">
                    <a href="#" onclick="showSection('overview'); return false;" data-section="overview" class="sidebar-nav-item text-accent flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Overview</span>
                    </a>
                    <a href="#" onclick="showSection('business-setting'); return false;" data-section="business-setting" class="sidebar-nav-item flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                        <i data-lucide="settings" class="w-5 h-5"></i>
                        <span>Business Settings</span>
                    </a>
                    <a href="#" onclick="showSection('deals'); return false;" data-section="deals" class="sidebar-nav-item flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                        <i data-lucide="circle-dollar-sign" class="w-5 h-5"></i>
                        <span>Deals</span>
                    </a>
                    <a href="#" onclick="showSection('reviews'); return false;" data-section="reviews" class="sidebar-nav-item flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                        <i data-lucide="star" class="w-5 h-5"></i>
                        <span>Reviews</span>
                    </a>
                    <a href="#" onclick="showSection('hours'); return false;" data-section="hours" class="sidebar-nav-item flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                        <span>Hours</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Mobile menu toggle -->
        <button id="mobile-menu-toggle" class="lg:hidden fixed bottom-4 right-4 z-50 bg-accent text-black p-4 rounded-full shadow-lg">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>

        <!-- Mobile sidebar overlay -->
        <div id="mobile-sidebar" class="lg:hidden fixed inset-0 bg-black/50 z-40 hidden">
            <aside class="w-64 bg-card/40 h-full overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-foreground">My Business</h2>
                        <button id="mobile-menu-close" class="text-foreground">
                            <i data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>
                    <nav class="space-y-2">
                        <a href="#" onclick="showSection('overview'); closeMobileMenu(); return false;" data-section="overview" class="sidebar-nav-item text-accent flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                            <span>Overview</span>
                        </a>
                        <a href="#" onclick="showSection('business-setting'); closeMobileMenu(); return false;" data-section="business-setting" class="sidebar-nav-item flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="settings" class="w-5 h-5"></i>
                            <span>Business Settings</span>
                        </a>
                        <a href="#" onclick="showSection('deals'); closeMobileMenu(); return false;" data-section="deals" class="sidebar-nav-item flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="circle-dollar-sign" class="w-5 h-5"></i>
                            <span>Deals</span>
                        </a>
                        <a href="#" onclick="showSection('reviews'); closeMobileMenu(); return false;" data-section="reviews" class="sidebar-nav-item flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="star" class="w-5 h-5"></i>
                            <span>Reviews</span>
                        </a>
                        <a href="#" onclick="showSection('hours'); closeMobileMenu(); return false;" data-section="hours" class="sidebar-nav-item flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="clock" class="w-5 h-5"></i>
                            <span>Hours</span>
                        </a>
                    </nav>
                </div>
            </aside>
        </div>

        <!-- Main Content -->
        <div class="flex-1 px-4 sm:px-6 lg:px-8 py-12">
            
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
        <!-- Business Preview -->
        <div id="overview" class="sidebar-section active">
            <div class="bg-card rounded-xl border border-border p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-foreground">Business Preview</h2>
                    <div>
                        <a href="/analytics?id=<?php echo urlencode($business['id']); ?>" target="_blank" 
                           class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="external-link" class="w-4 h-4 mr-2 inline"></i>
                            View Analytics
                        </a>
                        <a href="/business?id=<?php echo urlencode($business['id']); ?>" target="_blank" 
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
        <?php else: ?>
        <script>
            showMessage("No business found. <br>Try creating one in the <strong>'Business Settings'</strong> menu.", "warning");
            showSection('business-setting');
        </script>
        <?php endif; ?>

        <div id="business-setting" class="sidebar-section">
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
                            <input type="text" id="lat" name="lat"
                                   value="<?php echo escapeHtml($business['latitude'] ?? $_POST['lat'] ?? '0'); ?>"
                                   class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label for="long" class="block text-sm font-medium text-foreground mb-2">Longitude *</label>
                            <input type="text" id="long" name="long"
                                   value="<?php echo escapeHtml($business['longitude'] ?? $_POST['long'] ?? '0'); ?>"
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
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-2">&nbsp;</label>
                            <button type="button" onclick="fillLocation()" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors w-full">
                                Autofill Location
                            </button>
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
        
        <?php if($business): ?>
        <div id="deals" class="sidebar-section">
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
                                        <a href="/edit_deal?id=<?php echo urlencode($deal['id']); ?>"
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
        <?php else: ?>
        <script>
            showMessage("No business found. <br>You need to create one before setting deals.", "warning");
            showSection('business-setting');
        </script>
        <?php endif; ?>
        
        <?php if($business): ?>
        <div id="reviews" class="sidebar-section">
            <div class="bg-card rounded-xl border border-border p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-foreground">
                        Reviews (<?php echo count($reviews); ?>)
                    </h2>
                </div>
                
                <?php if (empty($reviews)): ?>
                    <div class="text-center py-12">
                        <i data-lucide="star" class="w-16 h-16 text-muted-foreground mx-auto mb-4"></i>
                        <p class="text-muted-foreground text-lg">No reviews yet</p>
                        <p class="text-muted-foreground text-sm mt-2">Your business hasn't received any reviews yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($reviews as $review): ?>
                        <div class="border-b border-border pb-6 last:border-b-0 last:pb-0">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <?php if ($review['profile_image_url']): ?>
                                    <img src="<?php echo escapeHtml($review['profile_image_url']); ?>" 
                                         alt="Profile" class="w-12 h-12 rounded-full object-cover">
                                    <?php else: ?>
                                    <div class="w-12 h-12 bg-primary/20 rounded-full flex items-center justify-center">
                                        <i data-lucide="user" class="w-6 h-6 text-primary"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <a href="/profile?u=<?php echo escapeHtml($review['username']); ?>" 
                                               class="font-semibold text-foreground hover:text-primary">
                                                <?php echo escapeHtml($review['first_name'] . ' ' . $review['last_name']); ?>
                                            </a>
                                            <div class="flex items-center space-x-2 mt-1">
                                                <div class="flex items-center">
                                                    <?php echo renderStars($review['rating']); ?>
                                                </div>
                                                <span class="text-sm text-muted-foreground">
                                                    <?php echo timeAgo($review['created_at']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if (!empty($review['comment'])): ?>
                                    <p class="text-foreground mt-2"><?php echo escapeHtml($review['comment']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <script>
            showMessage("No business found. <br>You need to create one before viewing reviews.", "warning");
            showSection('business-setting');
        </script>
        <?php endif; ?>
        
        <div id="hours" class="sidebar-section">
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
                                <label class="block text-sm font-medium text-foreground"><?php echo $day; ?> * 
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" id="<?php echo $prefix; ?>-closed-checker" name="<?php echo $prefix; ?>-closed-checker"
                                          class="appearance-none h-5 w-5 border-2 border-border rounded-md checked:bg-primary checked:border-primary
                                                 transition duration-200 relative cursor-pointer
                                                 checked:before:content-['✓'] checked:before:absolute checked:before:text-white checked:before:text-sm checked:before:inset-0 checked:before:flex checked:before:items-center checked:before:justify-center" />
                                        <span class="text-foreground">Closed</span>
                                    </label>
                                
                                </label>
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
                        function setAllHours(e){let l=document.getElementById(e+"-opening").value,t=document.getElementById(e+"-closing").value;["m","tu","w","th","f","sa","su"].forEach(e=>{document.getElementById(e+"-opening").value=l,document.getElementById(e+"-closing").value=t})}
                    </script>

                </form>
            </div> 
        </div>
        
    </div>    
</main>

<script>
function showSection(sectionId) {
    const sections = document.querySelectorAll('.sidebar-section');
    sections.forEach(s => s.classList.remove('active'));
    
    const navItems = document.querySelectorAll('.sidebar-nav-item');
    navItems.forEach(item => item.classList.remove('text-accent'));
    
    const section = document.getElementById(sectionId);
    if (section) {
        section.classList.add('active');
    }
    
    const activeNavs = document.querySelectorAll(`[data-section="${sectionId}"]`);
    activeNavs.forEach(nav => nav.classList.add('text-accent'));
    
    lucide.createIcons();
}

function closeMobileMenu() {
    document.getElementById('mobile-sidebar').classList.add('hidden');
}

function showMessage(e,t="info"){let a=document.getElementById("message-modal"),n=document.getElementById("message-text"),o=document.getElementById("message-icon"),r=document.getElementById("message-close");n.textContent=e,o.innerHTML="";let l="",s="";switch(t){case"success":l="check-circle",s="text-green-500";break;case"error":l="alert-circle",s="text-red-500";break;case"warning":l="alert-triangle",s="text-yellow-500";break;default:l="info",s="text-blue-500"}let i=document.createElement("i");i.setAttribute("data-lucide",l),i.className=`w-10 h-10 ${s} mb-2`,o.appendChild(i),a.classList.remove("hidden"),lucide.createIcons(),r.onclick=()=>{a.classList.add("hidden")},a.onclick=e=>{e.target===a&&a.classList.add("hidden")}}

function showConfirm(e){return new Promise(t=>{let a=document.getElementById("confirm-modal"),n=document.getElementById("confirm-text"),o=document.getElementById("confirm-yes"),r=document.getElementById("confirm-no");n.textContent=e,a.classList.remove("hidden");let l=()=>{a.classList.add("hidden"),o.onclick=null,r.onclick=null};o.onclick=()=>{l(),t(!0)},r.onclick=()=>{l(),t(!1)},a.onclick=e=>{e.target===a&&(l(),t(!1))}})}

async function handleDeleteDeal(e){let t=await showConfirm("Are you sure you want to delete this deal?");t&&(window.location.href=e)}

function setupDragDrop(e,t,a,n){let o=document.getElementById(e),r=document.getElementById(t),l=document.getElementById(a),s=document.getElementById(n);s.addEventListener("click",()=>o.click()),["dragenter","dragover"].forEach(e=>{s.addEventListener(e,e=>{e.preventDefault(),s.classList.add("border-primary","bg-muted/20")})}),["dragleave","drop"].forEach(e=>{s.addEventListener(e,e=>{e.preventDefault(),s.classList.remove("border-primary","bg-muted/20")})}),s.addEventListener("drop",e=>{let t=e.dataTransfer.files[0];if(t&&(o.files=e.dataTransfer.files,r.textContent=t.name,l)){let a=new FileReader;a.onload=e=>{l.src=e.target.result,l.classList.remove("hidden")},a.readAsDataURL(t)}}),o.addEventListener("change",()=>{let e=o.files[0];if(e){if(r.textContent=e.name,l){let t=new FileReader;t.onload=e=>{l.src=e.target.result,l.classList.remove("hidden")},t.readAsDataURL(e)}}else r.textContent="Drag & drop an image here, or click to browse",l&&l.classList.add("hidden")})}

function fillLatLong(){if(!navigator.geolocation){showMessage("Geolocation is not supported by your browser.","error");return}navigator.geolocation.getCurrentPosition(async e=>{let t=e.coords.latitude,a=e.coords.longitude;document.getElementById("lat").value=t.toFixed(6),document.getElementById("long").value=a.toFixed(6)},e=>{showMessage("Geolocation failed: "+e.message,"error")},{enableHighAccuracy:!0,timeout:1e4,maximumAge:0})}

async function fillLocation(){if(!navigator.geolocation){showMessage("Geolocation is not supported by your browser.","error");return}navigator.geolocation.getCurrentPosition(async e=>{let t=e.coords.latitude,a=e.coords.longitude;document.getElementById("lat").value=t.toFixed(6),document.getElementById("long").value=a.toFixed(6);try{let n=await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${t}&lon=${a}&addressdetails=1`);if(!n.ok)throw Error("Failed to fetch address");let o=await n.json(),r=o.address,l=[r.house_number,r.road].filter(Boolean).join(" "),s=r.city||r.town||r.village||"",i=r.state||"",d=r.postcode||"",c=r.country||"";document.getElementById("street").value=l,document.getElementById("city").value=s,document.getElementById("state").value=i,document.getElementById("zip").value=d,document.getElementById("country").value=c}catch(g){console.error(g),showMessage("Could not get address.","error")}},e=>{showMessage("Geolocation failed: "+e.message,"error")},{enableHighAccuracy:!0,timeout:1e4,maximumAge:0})}

document.addEventListener('DOMContentLoaded', () => {
    const mobileToggle = document.getElementById('mobile-menu-toggle');
    const mobileSidebar = document.getElementById('mobile-sidebar');
    const mobileClose = document.getElementById('mobile-menu-close');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', () => {
            mobileSidebar.classList.remove('hidden');
            lucide.createIcons();
        });
    }
    
    if (mobileClose) {
        mobileClose.addEventListener('click', closeMobileMenu);
    }
    
    if (mobileSidebar) {
        mobileSidebar.addEventListener('click', (e) => {
            if (e.target === mobileSidebar) {
                closeMobileMenu();
            }
        });
    }
    
    lucide.createIcons();
    setupDragDrop("thumbnail","thumbnail-label","thumbnail-preview","thumbnail-dropzone");
    setupDragDrop("banner","banner-label","banner-preview","banner-dropzone");
});
</script>

<?php include 'includes/footer.php'; ?>
<?php
if (empty($_GET) && isset($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $_GET);
}

require_once 'functions.php';

$error = '';
$success = '';
$isLogin = !isset($_GET['register']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        parse_str($rawInput, $_POST);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['g-recaptcha-response'])) {
        $error = 'Please complete the reCAPTCHA verification.';
    } else {
        $recaptchaSecret = "6LdG888rAAAAAGA4TGA5kdurTQaIB7JdspERjmOL";
        $recaptchaResponse = $_POST['g-recaptcha-response'];
    
        $verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret="
            . $recaptchaSecret . "&response=" . $recaptchaResponse . "&remoteip=" . $_SERVER['REMOTE_ADDR']);
        $captchaSuccess = json_decode($verify);
    
        if ($captchaSuccess->success != true) {
            $error = $verify;
        }
        else {
            $db = Database::getInstance();
    
            if ($isLogin) {
                $email = sanitizeInput($_POST['email']);
                $password = $_POST['password'];
                
                if ($email && $password) {
                    $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
                    
                    if ($user && verifyPassword($password, $user['password'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        
                        $redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : '/';
                        header('Location: ' . $redirect_url);
                        exit;
                    } else {
                        $error = 'Invalid email or password';
                    }
                } else {
                    $error = 'Please fill in all fields';
                }
            } else {
                $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
                $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
                $password = isset($_POST['password']) ? trim($_POST['password']) : '';
                $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
                $firstName = isset($_POST['first_name']) ? sanitizeInput($_POST['first_name']) : '';
                $lastName = isset($_POST['last_name']) ? sanitizeInput($_POST['last_name']) : '';
                $location = isset($_POST['location']) ? sanitizeInput($_POST['location']) : '';
                $bio = isset($_POST['bio']) ? sanitizeInput($_POST['bio']) : '';
                $isBusinessOwner = isset($_POST['is_business_owner']);
                $agreeToTerms = isset($_POST['agree_to_terms']);
                $newsletterSignup = isset($_POST['newsletter_signup']);
                
                // Enhanced validation
                $validationErrors = [];
                
                if (empty($username)) {
                    $validationErrors[] = 'Username is required';
                } elseif (strlen($username) < 3) {
                    $validationErrors[] = 'Username must be at least 3 characters long';
                } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                    $validationErrors[] = 'Username can only contain letters, numbers, and underscores';
                }
                
                if (empty($email)) {
                    $validationErrors[] = 'Email is required';
                } elseif (!validateEmail($email)) {
                    $validationErrors[] = 'Please enter a valid email address';
                }
                
                if (empty($password)) {
                    $validationErrors[] = 'Password is required';
                } elseif (strlen($password) < 8) {
                    $validationErrors[] = 'Password must be at least 8 characters long';
                } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
                    $validationErrors[] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
                }
                
                if ($password !== $confirmPassword) {
                    $validationErrors[] = 'Passwords do not match';
                }
                
                if (empty($firstName)) {
                    $validationErrors[] = 'First name is required';
                }
                
                if (empty($lastName)) {
                    $validationErrors[] = 'Last name is required';
                }
                
                if (!$agreeToTerms) {
                    $validationErrors[] = 'You must agree to the Terms of Service and Privacy Policy';
                }
                
                if (empty($validationErrors)) {
                    // Check if user already exist
                    $existingUser = $db->fetch("SELECT id, username, email FROM users WHERE email = ? OR username = ?", [$email, $username]);
                    
                    if ($existingUser) {
                        if ($existingUser['email'] === $email) {
                            $error = 'An account with this email already exists. Please use a different email.';
                        } elseif ($existingUser['username'] === $username) {
                            $error = 'This username is already taken. Please choose a different username.';
                        }
                    } else {
                        try {
                            $hashedPassword = hashPassword($password);
                            $userId = generateUuid();
                    
                            $db->query("
                                INSERT INTO users (id, username, email, password, first_name, last_name, location, bio, is_business_owner, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ", [$userId, $username, $email, $hashedPassword, $firstName, $lastName, $location, $bio, $isBusinessOwner]);
                    
                            // Set session data
                            $_SESSION['user_id'] = $userId;
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                            $_SESSION['user_username'] = $username;
                            $_SESSION['is_business_owner'] = $isBusinessOwner;
                    
                            error_log("New user registered: " . $email . " (ID: " . $userId . ")");
                    
                            $success = 'Account created successfully! Redirecting to dashboard...';
                    
                            header('refresh:3;url=/');
                            exit;
                        } catch (Exception $e) {
                            error_log("Registration error: " . $e->getMessage());
                            $error = 'Registration failed due to a technical error. Please try again or contact support if the problem persists.';
                        }
                    }
        
                } else {
                    $error = implode('. ', $validationErrors) . '.';
                }
            }
        }
    }
    
    
}

$pageTitle = $isLogin ? 'Sign In' : 'Sign Up';
$pageDescription = $isLogin ? 'Sign in to your Mainstreet account' : 'Create your Mainstreet account';

include 'includes/header.php';
?>
<style>
body {
    background-image: url("/assets/purple.jpg");
    background-repeat: no-repeat;
    background-size: 200% 200%;
    background-position: center center;
    background-attachment: fixed; /* Fixes the background */
}
</style>
    
<div class="min-h-screen flex justify-center pt-32 pb-12 px-4 sm:px-6 lg:px-8">
    <div class="z-20 bg-card/40 backdrop-blur-sm rounded-lg shadow p-6 flex items-center space-x-2">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="map-pin" class="w-8 h-8 text-primary-foreground"></i>
                </div>
                <h2 class="text-3xl font-bold text-foreground">
                    <?php echo $isLogin ? 'Sign in to your account' : 'Create your account'; ?>
                </h2>
                <p class="mt-2 text-muted-foreground">
                    <?php if ($isLogin): ?>
                        Or <a href="/auth?register" class="text-primary hover:text-primary/80">create a new account</a>
                    <?php else: ?>
                        Or <a href="/auth" class="text-primary hover:text-primary/80">sign in to existing account</a>
                    <?php endif; ?>
                </p>
            </div>
    
            <?php if ($error): ?>
            <div class="bg-destructive/10 border border-destructive/20 rounded-lg p-4">
                <div class="flex items-center">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-destructive mr-2"></i>
                    <span class="text-destructive"><?php echo escapeHtml($error); ?></span>
                </div>
            </div>
            <?php endif; ?>
    
            <?php if ($success): ?>
            <div class="bg-green-500/10 border border-green-500/20 rounded-lg p-4">
                <div class="flex items-center">
                    <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                    <span class="text-green-500"><?php echo escapeHtml($success); ?></span>
                </div>
            </div>
            <?php endif; ?>
    
            <form method="POST" action="/auth<?php echo !$isLogin ? '?register=1' : ''; ?>" class="space-y-6" id="authForm">
                <?php if (!$isLogin): ?>
                <!-- Personal Information Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-foreground border-b border-border pb-2">Personal Information</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-foreground mb-2">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required 
                                   value="<?php echo escapeHtml($_POST['first_name'] ?? ''); ?>"
                                   class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-foreground mb-2">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required 
                                   value="<?php echo escapeHtml($_POST['last_name'] ?? ''); ?>"
                                   class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
    
                    <div>
                        <label for="username" class="block text-sm font-medium text-foreground mb-2">Username *</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo escapeHtml($_POST['username'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                               pattern="[a-zA-Z0-9_]+" title="Username can only contain letters, numbers, and underscores">
                        <p class="text-xs text-muted-foreground mt-1">3+ characters, letters, numbers, and underscores only</p>
                    </div>
    
                    <div>
                        <label for="location" class="block text-sm font-medium text-foreground mb-2">Location</label>
                        <input type="text" id="location" name="location" 
                               value="<?php echo escapeHtml($_POST['location'] ?? ''); ?>"
                               placeholder="City, State or Country"
                               class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
    
                    <div>
                        <label for="bio" class="block text-sm font-medium text-foreground mb-2">Bio</label>
                        <textarea id="bio" name="bio" rows="3" 
                                  placeholder="Tell us a bit about yourself..."
                                  class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary resize-none"><?php echo escapeHtml($_POST['bio'] ?? ''); ?></textarea>
                    </div>
                </div>
    
                <!-- Account Information Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-foreground border-b border-border pb-2">Account Information</h3>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-foreground mb-2">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo escapeHtml($_POST['email'] ?? ''); ?>"
                               class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
    
                    <div>
                        <label for="password" class="block text-sm font-medium text-foreground mb-2">Password *</label>
                        <input type="password" id="password" name="password" required 
                               class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                        <p class="text-xs text-muted-foreground mt-1">8+ characters with uppercase, lowercase, and number</p>
                    </div>
    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-foreground mb-2">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
    
                <!-- Preferences Section -->
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold text-foreground border-b border-border pb-2">Preferences</h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_business_owner" value="1" 
                                   class="w-4 h-4 text-primary border-border rounded focus:ring-2 focus:ring-primary">
                            <span class="ml-2 text-sm text-foreground">I am a business owner</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" name="newsletter_signup" value="1" 
                                   class="w-4 h-4 text-primary border-border rounded focus:ring-2 focus:ring-primary">
                            <span class="ml-2 text-sm text-foreground">Subscribe to our newsletter for updates and deals</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" name="agree_to_terms" value="1" required
                                   class="w-4 h-4 text-primary border-border rounded focus:ring-2 focus:ring-primary">
                            <span class="ml-2 text-sm text-foreground">I agree to the <a href="/terms" class="text-primary hover:text-primary/80">Terms of Service</a> and <a href="/privacy" class="text-primary hover:text-primary/80">Privacy Policy</a> *</span>
                        </label>
                    </div>
                </div>
                <?php else: ?>
                <!-- Login Form -->
                <div>
                    <label for="email" class="block text-sm font-medium text-foreground mb-2">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo escapeHtml($_POST['email'] ?? ''); ?>"
                           class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
    
                <div>
                    <label for="password" class="block text-sm font-medium text-foreground mb-2">Password</label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-3 py-2 bg-card/70 backdrop-blur-sm border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <?php endif; ?>
                
                <div class="g-recaptcha" data-sitekey="6LdG888rAAAAAHrqVDK9f18Usfu7LjYwP1w2fykM" data-theme="dark"></div>
    
                <button type="submit" class="w-full px-4 py-3 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors" id="submitBtn">
                    <?php echo $isLogin ? 'Sign In' : 'Create Account'; ?>
                </button>
            </form>
    
            <div class="text-center">
                <p class="text-sm text-muted-foreground">
                    By continuing, you agree to our 
                    <a href="/terms" class="text-primary hover:text-primary/80">Terms of Service</a> and 
                    <a href="/privacy" class="text-primary hover:text-primary/80">Privacy Policy</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<script>
lucide.createIcons();
document.addEventListener('DOMContentLoaded', function() {const form = document.getElementById('authForm');const submitBtn = document.getElementById('submitBtn');const passwordField = document.getElementById('password');const confirmPasswordField = document.getElementById('confirm_password');const usernameField = document.getElementById('username');if (form && !form.querySelector('input[name="email"]').closest('form').querySelector('input[name="first_name"]')) {form.addEventListener('submit', function(e) {const email = form.querySelector('input[name="email"]').value;const password = form.querySelector('input[name="password"]').value;if (!email || !password) {e.preventDefault();alert('Please fill in all fields');return false;}});} else if (form) {if (passwordField) {passwordField.addEventListener('input', function() {const password = this.value;const strength = checkPasswordStrength(password);updatePasswordStrengthIndicator(strength);});}if (confirmPasswordField && passwordField) {confirmPasswordField.addEventListener('input', function() {const password = passwordField.value;const confirmPassword = this.value;if (confirmPassword && password !== confirmPassword) {this.setCustomValidity('Passwords do not match');this.style.borderColor = '#ef4444';} else {this.setCustomValidity('');this.style.borderColor = '';}});}
        
        // Username availability check (simulated)
        // DYLAN SEND HELP I WONT MINIFY THIS YET
        if (usernameField) {
            let usernameTimeout;
            usernameField.addEventListener('input', function() {
                clearTimeout(usernameTimeout);
                const username = this.value;
                
                if (username.length >= 3) {
                    usernameTimeout = setTimeout(() => {
                        // Simulate username check
                        if (username === 'admin' || username === 'test') {
                            this.setCustomValidity('Username is already taken');
                            this.style.borderColor = '#ef4444';
                        } else {
                            this.setCustomValidity('');
                            this.style.borderColor = '';
                        }
                    }, 500);
                }
            });
        }
        
form.addEventListener('submit', function(e) {submitBtn.disabled = true;submitBtn.textContent = 'Creating Account...';submitBtn.style.opacity = '0.7';});}});function checkPasswordStrength(password) {let strength = 0;if(password.length>=8)strength++;if(/[a-z]/.test(password))strength++;if(/[A-Z]/.test(password))strength++;if(/[0-9]/.test(password))strength++;if(/[^A-Za-z0-9]/.test(password))strength++;return strength;}function updatePasswordStrengthIndicator(strength) {const passwordField = document.getElementById('password');if (!passwordField) return;const existingIndicator = passwordField.parentNode.querySelector('.password-strength');if (existingIndicator) {existingIndicator.remove();}const indicator = document.createElement('div');indicator.className = 'password-strength mt-1';const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];const strengthColors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];indicator.innerHTML = `<div class="flex items-center space-x-2"><div class="flex space-x-1">${[1,2,3,4,5].map(i => `<div class="w-2 h-2 rounded-full ${i <= strength ? '' : 'bg-gray-300'}" style="background-color: ${i <= strength ? strengthColors[strength - 1] : '#d1d5db'}"></div>`).join('')}</div><span class="text-xs" style="color: ${strengthColors[strength - 1] || '#6b7280'}">${strengthText[strength - 1] || 'Very Weak'}</span></div>`;passwordField.parentNode.appendChild(indicator);}
</script>

<?php include 'includes/footer.php'; ?>
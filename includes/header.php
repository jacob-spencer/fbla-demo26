<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- PWA Components -->
    <link rel="manifest" href="/manifest.json">
    
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : 'Mainstreet - Discover and connect with local businesses in your area'; ?>">
    
    <!-- Tailwind CSS config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        background: '#000000',
                        foreground: '#ffffff',
                        card: 'rgba(0, 0, 0, 0.05)',
                        'card-foreground': '#ffffff',
                        primary: '#8b5cf6',
                        'primary-foreground': '#ffffff',
                        secondary: '#6366f1',
                        'secondary-foreground': '#ffffff',
                        muted: 'rgba(255, 255, 255, 0.1)',
                        'muted-foreground': 'rgba(255, 255, 255, 0.6)',
                        accent: '#8b5cf6',
                        'accent-foreground': '#ffffff',
                        destructive: '#ef4444',
                        'destructive-foreground': '#ffffff',
                        border: 'rgba(255, 255, 255, 0.1)',
                        input: 'rgba(255, 255, 255, 0.05)',
                        ring: '#8b5cf6'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #000000;
            color: #ffffff;
            overflow-x: hidden;
        }
        
        @keyframes gradientShift {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }
        
        .business-card {
            transition: all 0.3s ease-in-out;
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .business-card:hover {
            transform: translateY(-4px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(139, 92, 246, 0.3);
            box-shadow: 0 20px 40px -12px rgba(139, 92, 246, 0.3);
        }

        /* Profile dropdown improvements */
        .profile-dropdown {
            pointer-events: none;
            position: fixed !important;
        }
        
        .profile-dropdown.show {
            pointer-events: auto;
        }
        
        /* Ensure hamburger is always visible */
        #menu-toggle {
            position: relative;
            z-index: 60;
        }
        
        /* Force header to not clip content */
        header, header * {
            overflow: visible !important;
        }
    </style>
</head>
<body class="text-foreground">
    <header class="sticky w-full top-0 z-50">
        
    <div class="liquid-glass backdrop-blur-sm w-full h-full">
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
          <!-- Logo -->
          <div class="flex items-center space-x-4">
            <a href="/" class="flex items-center space-x-2">
              <div class="w-8 h-8 bg-black rounded-full flex items-center justify-center border border-white/20">
                <i data-lucide="map-pin" class="text-primary w-4 h-4"></i>
              </div>
              <span class="text-xl font-bold text-white"><?php echo APP_NAME; ?></span>
            </a>
          </div>
    
          <!-- Desktop nav -->
          <nav class="hidden md:flex items-center space-x-8">
            <a href="/discover.php" class="transition-colors text-foreground hover:text-primary">Discover</a>
            <a href="/search.php" class="transition-colors text-foreground hover:text-primary">
              <i data-lucide="search" class="w-4 h-4 mr-2 inline"></i> Search
            </a>
            <a href="/deals.php" class="transition-colors text-foreground hover:text-primary">Deals</a>
            <a href="/map.php" class="transition-colors text-foreground hover:text-primary">
              <i data-lucide="map" class="w-4 h-4 mr-2 inline"></i> Map
            </a>
            <?php if (getCurrentUser() && getCurrentUser()['is_business_owner']): ?>
            <a href="/my-business.php" class="transition-colors text-foreground hover:text-primary">
              <i data-lucide="building-2" class="w-4 h-4 mr-2 inline"></i> My Business
            </a>
            <?php endif; ?>
          </nav>
    
          <!-- Right side -->
          <div class="flex items-center space-x-4">
            <!-- Desktop user links -->
            <div class="hidden md:flex items-center space-x-4">
            <?php if (isLoggedIn()): ?>
                <?php $user = getCurrentUser(); ?>
            
                <!-- Profile dropdown -->
                <div class="relative profile-container">
                  <button class="flex items-center space-x-2 focus:outline-none profile-trigger">
                    <?php if ($user['profile_image_url']): ?>
                      <img src="<?php echo escapeHtml($user['profile_image_url']); ?>" 
                           alt="Profile" 
                           class="w-8 h-8 rounded-full object-cover border border-border">
                    <?php else: ?>
                      <div class="w-8 h-8 bg-primary/20 rounded-full flex items-center justify-center">
                        <i data-lucide="user" class="w-4 h-4 text-primary"></i>
                      </div>
                    <?php endif; ?>
                  </button>
                
                  <!-- Dropdown menu -->
                  <div class="profile-dropdown w-48 bg-card backdrop-blur-sm border border-border rounded-lg shadow-lg opacity-0 scale-95 invisible transition-all duration-150 origin-top-right z-[100]">
                    <ul class="py-2 text-sm text-foreground">
                      <li>
                        <a href="/my-profile" class="block px-4 py-2 hover:bg-muted hover:text-primary transition-colors">
                          <i data-lucide="user" class="w-4 h-4 mr-2 inline"></i> My Profile
                        </a>
                      </li>
                      <li>
                        <a href="/bookmarks" class="block px-4 py-2 text-accent hover:bg-muted hover:text-primary transition-colors">
                          <i data-lucide="bookmark" class="w-4 h-4 mr-2 inline"></i> Bookmarks
                        </a>
                      </li>
                      <li><hr class="border-border my-1"></li>
                      <li>
                        <a href="/logout" class="block px-4 py-2 text-destructive hover:bg-muted hover:text-destructive transition-colors">
                          <i data-lucide="log-out" class="w-4 h-4 mr-2 inline"></i> Sign Out
                        </a>
                      </li>
                    </ul>
                  </div>
                </div>
        
             <?php else: ?>
                <div class="relative profile-container">
                  <button class="flex items-center space-x-2 focus:outline-none profile-trigger">
                      <div class="w-8 h-8 bg-primary/20 rounded-full flex items-center justify-center">
                        <i data-lucide="user" class="w-4 h-4 text-primary"></i>
                      </div>
                  </button>
                
                  <!-- Dropdown menu -->
                  <div class="profile-dropdown w-48 bg-card backdrop-blur-sm border border-border rounded-lg shadow-lg opacity-0 scale-95 invisible transition-all duration-150 origin-top-right z-[100]">
                    <ul class="py-2 text-sm text-foreground">
                      <li>
                        <a href="/auth" class="block px-4 py-2 hover:bg-muted hover:text-primary transition-colors">
                          <i data-lucide="log-in" class="w-4 h-4 mr-2 inline"></i> Log In
                        </a>
                      </li>
                      <li>
                        <a href="/auth?register" class="block px-4 py-2 hover:bg-muted hover:text-primary transition-colors">
                          <i data-lucide="user-plus" class="w-4 h-4 mr-2 inline"></i> Sign Up
                        </a>
                      </li>
                    </ul>
                  </div>
                </div>
                
             <?php endif; ?>
            </div>

            <!-- Hamburger button (mobile only) -->
            <button id="menu-toggle" class="md:hidden w-10 h-10 flex flex-col justify-center items-center">
              <span class="bar w-6 h-0.5 bg-white rounded transition-all duration-300 mb-1.5"></span>
              <span class="bar w-6 h-0.5 bg-white rounded transition-all duration-300 mb-1.5"></span>
              <span class="bar w-6 h-0.5 bg-white rounded transition-all duration-300"></span>
            </button>
          </div>
        </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="md:hidden max-h-0 overflow-hidden transition-all duration-300 liquid-glass backdrop-blur-sm">
      <nav class="px-4 py-4 space-y-3">
        <a href="/discover.php" class="block py-2 text-foreground hover:text-primary transition-colors">Discover</a>
        <a href="/search.php" class="block py-2 text-foreground hover:text-primary transition-colors">
          <i data-lucide="search" class="w-4 h-4 mr-2 inline"></i> Search
        </a>
        <a href="/deals.php" class="block py-2 text-foreground hover:text-primary transition-colors">Deals</a>
        <a href="/map.php" class="block py-2 text-foreground hover:text-primary transition-colors">
          <i data-lucide="map" class="w-4 h-4 mr-2 inline"></i> Map
        </a>
        <?php if (getCurrentUser() && getCurrentUser()['is_business_owner']): ?>
        <a href="/my-business.php" class="block py-2 text-foreground hover:text-primary transition-colors">
          <i data-lucide="building-2" class="w-4 h-4 mr-2 inline"></i> My Business
        </a>
        <?php endif; ?>
        <hr class="border-border">
        <?php if (isLoggedIn()): ?>
          <a href="/my-profile" class="block py-2 text-foreground hover:text-primary transition-colors">
            <i data-lucide="user" class="w-4 h-4 mr-2 inline"></i> My Profile
          </a>
          <a href="/bookmarks" class="block py-2 text-accent hover:text-primary transition-colors">
            <i data-lucide="bookmark" class="w-4 h-4 mr-2 inline"></i> Bookmarks
          </a>
          <a href="/logout" class="block py-2 text-destructive hover:text-destructive transition-colors">
            <i data-lucide="log-out" class="w-4 h-4 mr-2 inline"></i> Sign Out
          </a>
        <?php else: ?>
          <a href="/auth" class="block py-2 text-foreground hover:text-primary transition-colors">
            <i data-lucide="log-in" class="w-4 h-4 mr-2 inline"></i> Log In
          </a>
          <a href="/auth?register" class="block py-2 text-foreground hover:text-primary transition-colors">
            <i data-lucide="user-plus" class="w-4 h-4 mr-2 inline"></i> Sign Up
          </a>
        <?php endif; ?>
      </nav>
    </div>
    </header>

<script>
  lucide.createIcons();

  // Mobile menu toggle
  const menuToggle = document.getElementById('menu-toggle');
  const bars = menuToggle.querySelectorAll('.bar');
  const mobileMenu = document.getElementById('mobile-menu');
  let isOpen = false;
  
  menuToggle.addEventListener('click', () => {
    isOpen = !isOpen;
    mobileMenu.classList.toggle('max-h-0');
    mobileMenu.classList.toggle('max-h-screen');
    
    // animate bars into X
    if (isOpen) {
      bars[0].classList.add('rotate-45', 'translate-y-2');
      bars[1].classList.add('opacity-0');
      bars[2].classList.add('-rotate-45', '-translate-y-2');
    } else {
      bars[0].classList.remove('rotate-45', 'translate-y-2');
      bars[1].classList.remove('opacity-0');
      bars[2].classList.remove('-rotate-45', '-translate-y-2');
    }
  });

  // Desktop profile dropdown (hover)
  document.querySelectorAll('.profile-container').forEach(container => {
    const dropdown = container.querySelector('.profile-dropdown');
    const trigger = container.querySelector('.profile-trigger');
    let hideTimeout;
    
    // Calculate and position dropdown
    function positionDropdown() {
      const rect = trigger.getBoundingClientRect();
      dropdown.style.position = 'fixed';
      dropdown.style.top = (rect.bottom + 8) + 'px';
      dropdown.style.right = (window.innerWidth - rect.right) + 'px';
      dropdown.style.left = 'auto';
    }
    
    function showDropdown() {
      clearTimeout(hideTimeout);
      positionDropdown();
      dropdown.classList.remove('opacity-0', 'scale-95', 'invisible');
      dropdown.classList.add('opacity-100', 'scale-100', 'show');
    }
    
    function hideDropdown() {
      hideTimeout = setTimeout(() => {
        dropdown.classList.add('opacity-0', 'scale-95', 'invisible');
        dropdown.classList.remove('opacity-100', 'scale-100', 'show');
      }, 150);
    }
    
    // Show on hover over container or dropdown
    container.addEventListener('mouseenter', showDropdown);
    dropdown.addEventListener('mouseenter', showDropdown);
    
    // Hide when leaving container or dropdown
    container.addEventListener('mouseleave', hideDropdown);
    dropdown.addEventListener('mouseleave', hideDropdown);
    
    // Reposition on scroll/resize
    window.addEventListener('scroll', positionDropdown);
    window.addEventListener('resize', positionDropdown);
  });
</script>
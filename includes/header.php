<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : 'Mainstreet - Discover and connect with local businesses in your area'; ?>">
    
    <!-- Tailwind CSS yuckyfuck -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        background: 'hsl(214, 31%, 8%)',
                        foreground: 'hsl(213, 31%, 91%)',
                        card: 'hsl(214, 30%, 12%)',
                        'card-foreground': 'hsl(213, 31%, 88%)',
                        primary: 'hsl(217, 91%, 60%)',
                        'primary-foreground': 'hsl(0, 0%, 100%)',
                        secondary: 'hsl(228, 94%, 67%)',
                        'secondary-foreground': 'hsl(0, 0%, 100%)',
                        muted: 'hsl(214, 30%, 15%)',
                        'muted-foreground': 'hsl(214, 15%, 65%)',
                        accent: 'hsl(45, 93%, 58%)',
                        'accent-foreground': 'hsl(0, 0%, 100%)',
                        destructive: 'hsl(0, 84%, 60%)',
                        'destructive-foreground': 'hsl(0, 0%, 100%)',
                        border: 'hsl(214, 20%, 20%)',
                        input: 'hsl(214, 25%, 18%)',
                        ring: 'hsl(217, 91%, 60%)'
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
            background-color: hsl(214, 31%, 8%);
            color: hsl(213, 31%, 91%);
        }
        .hero-gradient {
            background: url('fbla-waves.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .business-card {
            transition: all 0.2s ease-in-out;
        }
        .business-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-background text-foreground">
    <header class="bg-card border-b border-border sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="/" class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                            <i data-lucide="map-pin" class="text-primary-foreground w-4 h-4"></i>
                        </div>
                        <span class="text-xl font-bold text-primary"><?php echo APP_NAME; ?></span>
                    </a>
                </div>
                
                <nav class="hidden md:flex items-center space-x-8">
                    <a href="/discover.php" class="transition-colors text-foreground hover:text-primary">
                        Discover
                    </a>
                    <a href="/search.php" class="transition-colors text-foreground hover:text-primary">
                        <i data-lucide="search" class="w-4 h-4 mr-2 inline"></i>
                        Search
                    </a>
                    <a href="/deals.php" class="transition-colors text-foreground hover:text-primary">
                        Deals
                    </a>
                    <?php if (getCurrentUser() && getCurrentUser()['is_business_owner']): ?>
                    <a href="/my-business.php" class="transition-colors text-foreground hover:text-primary">
                        <i data-lucide="building-2" class="w-4 h-4 mr-2 inline"></i>
                        My Business
                    </a>
                    <?php endif; ?>
                </nav>
                
                <div class="flex items-center space-x-4">
                    <?php if (isLoggedIn()): ?>
                        <?php $user = getCurrentUser(); ?>
                        <a href="/bookmarks.php" class="relative p-2 rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="bookmark" class="w-4 h-4"></i>
                        </a>
                        
                        <a href="/profile.php" class="p-2 rounded-lg hover:bg-muted transition-colors">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </a>
                        
                        <div class="flex items-center space-x-3">
                            <?php if ($user['profile_image_url']): ?>
                                <img src="<?php echo escapeHtml($user['profile_image_url']); ?>" 
                                     alt="Profile" class="w-8 h-8 rounded-full object-cover">
                            <?php endif; ?>
                            <a href="/logout.php" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                                Sign Out
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="/auth.php" class="px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors mr-2">
                            Sign In
                        </a>
                        <a href="/auth.php?register" class="px-4 py-2 bg-accent hover:bg-accent/90 text-black font-semibold rounded-lg transition-colors">
                            Sign Up
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <script>
        lucide.createIcons();
    </script>
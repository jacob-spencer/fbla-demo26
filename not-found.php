<?php
$pageTitle = '404 - Page Not Found';
$pageDescription = 'The page you are looking for could not be found';

include 'includes/header.php';
?>

<main class="min-h-screen bg-background flex items-center justify-center">
    <div class="text-center px-4 sm:px-6 lg:px-8">
        <div class="w-32 h-32 bg-muted rounded-full flex items-center justify-center mx-auto mb-8">
            <i data-lucide="search-x" class="w-16 h-16 text-muted-foreground"></i>
        </div>
        
        <h1 class="text-6xl font-bold text-foreground mb-4">404</h1>
        <h2 class="text-2xl font-semibold text-foreground mb-6">Page Not Found</h2>
        <p class="text-muted-foreground max-w-md mx-auto mb-8">
            The page you're looking for doesn't exist or has been moved to a different location.
        </p>
        
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/" class="px-6 py-3 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold rounded-lg transition-colors">
                Go Home
            </a>
            <a href="/search.php" class="px-6 py-3 border border-border hover:bg-muted text-foreground rounded-lg transition-colors">
                Search Businesses
            </a>
        </div>
    </div>
</main>

<script>
lucide.createIcons();
</script>

<?php include 'includes/footer.php'; ?>
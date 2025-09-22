    <footer class="bg-card border-t border-border text-foreground py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="space-y-4">
                     <div class="flex items-center space-x-4">
                        <a href="/" class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                                <i data-lucide="map-pin" class="text-primary-foreground w-4 h-4"></i>
                            </div>
                            <span class="text-xl font-bold text-primary"><?php echo APP_NAME; ?></span>
                        </a>
                    </div>
                    <p class="text-muted-foreground">
                        Connecting communities with local businesses. Discover, support, and grow together.
                    </p>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-4">Discover</h4>
                    <ul class="space-y-2 text-muted-foreground">
                        <li><a href="/search.php?category=restaurants" class="hover:text-primary transition-colors">Restaurants</a></li>
                        <li><a href="/search.php?category=services" class="hover:text-primary transition-colors">Services</a></li>
                        <li><a href="/search.php?category=pet-care" class="hover:text-primary transition-colors">Pet Care</a></li>
                        <li><a href="/search.php?category=hotel" class="hover:text-primary transition-colors">Hotel</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-4">For Businesses</h4>
                    <ul class="space-y-2 text-muted-foreground">
                        <li><a href="/my-business.php" class="hover:text-primary transition-colors">List Your Business</a></li>
                        <li><a href="/my-business.php" class="hover:text-primary transition-colors">Business Dashboard</a></li>
                        <li><a href="/deals.php" class="hover:text-primary transition-colors">Promote Deals</a></li>
                        <li><a href="/analytics.php" class="hover:text-primary transition-colors">Analytics</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold mb-4">Support</h4>
                    <ul class="space-y-2 text-muted-foreground">
                        <li><a href="/help.php" class="hover:text-primary transition-colors">Help Center</a></li>
                        <li><a href="/contact.php" class="hover:text-primary transition-colors">Contact Us</a></li>
                        <li><a href="/privacy.php" class="hover:text-primary transition-colors">Privacy Policy</a></li>
                        <li><a href="/terms.php" class="hover:text-primary transition-colors">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
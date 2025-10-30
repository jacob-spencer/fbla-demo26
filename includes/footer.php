<style>
    .fade-top {
        -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 20%);
        -webkit-mask-repeat: no-repeat;
        -webkit-mask-size: 100% 100%;
        mask-image: linear-gradient(to bottom, transparent 0%, black 20%);
        mask-repeat: no-repeat;
        mask-size: 100% 100%;
    } /* fade in cleanly */
</style>

<footer id="footer" class="relative border-t border-border text-foreground overflow-hidden fade-top">
    
    <div class="absolute bottom-0 left-0 w-full h-64 pointer-events-none wave"></div>
    <div class="absolute top-0 right-0 w-full h-48 pointer-events-none wave-top"></div>
    
    <!-- Footer content -->
    <div class="bg-card/70 backdrop-blur-sm w-full h-full py-12">
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl px-4 sm:px-6 lg:px-8">
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
                            <li><a href="/search?category=restaurants" class="hover:text-primary transition-colors">Restaurants</a></li>
                            <li><a href="/search?category=services" class="hover:text-primary transition-colors">Services</a></li>
                            <li><a href="/search?category=pet-care" class="hover:text-primary transition-colors">Pet Care</a></li>
                            <li><a href="/search?category=hotel" class="hover:text-primary transition-colors">Hotel</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold mb-4">For Businesses</h4>
                        <ul class="space-y-2 text-muted-foreground">
                            <li><a href="/my-business" class="hover:text-primary transition-colors">List Your Business</a></li>
                            <li><a href="/my-business" class="hover:text-primary transition-colors">Business Dashboard</a></li>
                            <li><a href="/deals" class="hover:text-primary transition-colors">Promote Deals</a></li>
                            <li><a href="/analytics" class="hover:text-primary transition-colors">Analytics</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold mb-4">Support</h4>
                        <ul class="space-y-2 text-muted-foreground">
                            <li><a href="/help" class="hover:text-primary transition-colors">Help Center</a></li>
                            <li><a href="/contact" class="hover:text-primary transition-colors">Contact Us</a></li>
                            <li><a href="/privacy" class="hover:text-primary transition-colors">Privacy Policy</a></li>
                            <li><a href="/terms" class="hover:text-primary transition-colors">Terms of Service</a></li>
                        </ul>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
</footer>

<style>
/* Glowing wave effect */
.wave {
    background: radial-gradient(ellipse at bottom, rgba(0, 120, 255, 0.6) 0%, transparent 70%);
    animation: waveMove 20s linear infinite;
}
.wave-top {
    background: radial-gradient(ellipse at top, rgba(0, 120, 255, 0.6) 0%, transparent 70%);
    animation: waveMoveTop 20s linear infinite;
}
/* Slight horizontal animation for movement */
@keyframes waveMove {
    0% { transform: translateX(-40%); }
    100% { transform: translateX(-40%); }
}
@keyframes waveMoveTop {
    0% { transform: translateX(40%); }
    100% { transform: translateX(40%); }
}
</style>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>
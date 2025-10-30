<?php
require_once 'functions.php';

$pageTitle = 'Terms of Service';
$pageDescription = 'Terms of Service';

include 'includes/header.php';
?>
<style>
/* Glowing wave effect */
html, body {
    overflow-x: hidden; /* prevent horizontal scrolling */
    overflow-y: auto hidden;   /* allow vertical scroll */
}
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
document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("waves");

    const wavePatterns = [
        { classes: "left-1/2 -translate-x-1/2 w-[100vw] h-[70vh] wave-right-top", verticalOffset: -0.25 },
        { classes: "-left-1/2 w-[90vw] h-[90vh] wave-left-top", verticalOffset: 0.33 },
        { classes: "left-1/2 w-[100vw] h-[75vh] wave-left-bottom", verticalOffset: 0.66 },
        { classes: "-left-1/2 w-[80vw] h-[85vh] wave-right-bottom", verticalOffset: 0 },
        { classes: "left-1/2 w-[80vw] h-[85vh] wave-right-bottom", verticalOffset: 0 },
        { classes: "left-1/2 w-[100vw] h-[70vh] wave-left-bottom", verticalOffset: -0.2 },
    ];

    function spawnWaves() {
        container.innerHTML = "";
        const pageHeight = document.body.scrollHeight;
        const sectionHeight = window.innerHeight;
        const numSections = Math.ceil(pageHeight / sectionHeight);

        for (let i = 0; i < numSections; i++) {
            const offsetY = i * sectionHeight;

            wavePatterns.forEach(cfg => {
                const div = document.createElement("div");
                div.className = `absolute ${cfg.classes}`;

                // Calculate vertical position in pixels
                const waveOffset = cfg.verticalOffset ? cfg.verticalOffset * sectionHeight : 0;
                div.style.top = `${offsetY + waveOffset}px`;

                container.appendChild(div);
            });
        }
    }

    function resizeWavesContainer() {
        container.style.height = document.body.scrollHeight + "px";
    }

    // Spawn waves after everything (images + content) is loaded
    window.addEventListener("load", () => {
        resizeWavesContainer();
        spawnWaves();
    });

    // Recalculate on resize
    window.addEventListener("resize", () => {
        resizeWavesContainer();
        spawnWaves();
    });
});
</script>

<!-- Hero Section -->
<section class="text-white pt-40 pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-5xl md:text-6xl font-bold mb-6">
            Terms of
            <span class="text-accent">Service</span>
        </h1>
        <p class="text-xl md:text-2xl text-white/90 mb-8 max-w-3xl mx-auto">
            At Mainstreet.lt, we ask you to use our platform responsibly. Take a moment to review our Terms of Service.
        </p>
    </div>
</section>

<!-- Categories Section -->
<section class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-foreground mb-4">Terms of Service</h2>
            
            <p class="text-muted-foreground mb-6">Last updated: 2 September, 2025</p>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">1. Agreement to Terms</h2>
            <p class="text-foreground mb-4">
                By accessing and using Mainstreet, you accept and agree to be bound by the terms and provision of this agreement.
            </p>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">2. Use License</h2>
            <p class="text-foreground mb-4">
                Permission is granted to temporarily access and use Mainstreet for personal, non-commercial transitory viewing only. 
                This is the grant of a license, not a transfer of title, and under this license you may not:
            </p>
            <ul class="list-disc pl-6 text-foreground mb-4">
                <li>modify or copy the materials</li>
                <li>use the materials for any commercial purpose or for any public display</li>
                <li>attempt to reverse engineer any software contained on the website</li>
                <li>remove any copyright or other proprietary notations from the materials</li>
            </ul>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">3. User Accounts</h2>
            <p class="text-foreground mb-4">
                When you create an account with us, you must provide information that is accurate, complete, and current at all times. 
                You are responsible for safeguarding the password and for all activities that occur under your account.
            </p>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">4. Business Listings</h2>
            <p class="text-foreground mb-4">
                Business owners may list their businesses on Mainstreet. By listing your business, you warrant that all information 
                provided is accurate and that you have the right to represent the business.
            </p>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">5. Reviews and Content</h2>
            <p class="text-foreground mb-4">
                Users may submit reviews and other content. You retain ownership of any content you submit, but grant Mainstreet 
                a license to use, display, and distribute your content on the platform.
            </p>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">6. Prohibited Uses</h2>
            <p class="text-foreground mb-4">You may not use Mainstreet:</p>
            <ul class="list-disc pl-6 text-foreground mb-4">
                <li>For any unlawful purpose or to solicit others to commit unlawful acts</li>
                <li>To violate any international, federal, provincial, or state regulations, rules, laws, or local ordinances</li>
                <li>To infringe upon or violate our intellectual property rights or the intellectual property rights of others</li>
                <li>To harass, abuse, insult, harm, defame, slander, disparage, intimidate, or discriminate</li>
                <li>To submit false or misleading information</li>
            </ul>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">7. Disclaimer</h2>
            <p class="text-foreground mb-4">
                The information on Mainstreet is provided on an 'as is' basis. To the fullest extent permitted by law, 
                Mainstreet excludes all representations, warranties, conditions, and terms.
            </p>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">8. Limitations</h2>
            <p class="text-foreground mb-4">
                In no event shall Mainstreet or its suppliers be liable for any damages (including, without limitation, 
                damages for loss of data or profit, or due to business interruption) arising out of the use or inability 
                to use Mainstreet.
            </p>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">9. Changes to Terms</h2>
            <p class="text-foreground mb-4">
                Mainstreet reserves the right to revise these terms of service at any time without notice. 
                By using this platform, you are agreeing to be bound by the then current version of these terms of service.
            </p>

            <h2 class="text-xl font-semibold text-foreground mt-8 mb-4">10. Contact Information</h2>
            <p class="text-foreground mb-4">
                If you have any questions about these Terms of Service, please contact us.
            </p>
        </div>
        
</section>


<script>
lucide.createIcons();
</script>

<?php include 'includes/footer.php'; ?>

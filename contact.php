<?php
require_once 'functions.php';

$pageTitle = 'Contact';
$pageDescription = 'Contact us';

include 'includes/header.php';
?>

<style>
html, body {
    overflow-x: hidden;
    overflow-y: auto hidden;
}
.wave-left-top,
.wave-left-bottom,
.wave-right-top,
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
    height: 100%;
    z-index: -10;
    overflow: hidden;
}
</style>

<section id="waves" class="absolute inset-0 -z-10 pointer-events-none"></section>

<script>
document.addEventListener("DOMContentLoaded",()=>{let e=document.getElementById("waves"),t=[{classes:"left-1/2 -translate-x-1/2 w-[100vw] h-[70vh] wave-right-top",verticalOffset:-.25},{classes:"-left-1/2 w-[90vw] h-[90vh] wave-left-top",verticalOffset:.33},{classes:"left-1/2 w-[100vw] h-[75vh] wave-left-bottom",verticalOffset:.66},{classes:"-left-1/2 w-[80vw] h-[85vh] wave-right-bottom",verticalOffset:0},{classes:"left-1/2 w-[80vw] h-[85vh] wave-right-bottom",verticalOffset:0},{classes:"left-1/2 w-[100vw] h-[70vh] wave-left-bottom",verticalOffset:-.2},];function l(){e.innerHTML="";let l=document.body.scrollHeight,s=window.innerHeight,a=Math.ceil(l/s);for(let v=0;v<a;v++){let f=v*s;t.forEach(t=>{let l=document.createElement("div");l.className=`absolute ${t.classes}`;let a=t.verticalOffset?t.verticalOffset*s:0;l.style.top=`${f+a}px`,e.appendChild(l)})}}function s(){e.style.height=document.body.scrollHeight+"px"}window.addEventListener("load",()=>{s(),l()}),window.addEventListener("resize",()=>{s(),l()})});
</script>

<!-- Hero Section -->
<section class="text-white pt-40 pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-5xl md:text-6xl font-bold mb-6">
            Contact
            <span class="text-accent">Us</span>
        </h1>
        <p class="text-xl md:text-2xl text-white/90 mb-8 max-w-3xl mx-auto">
            Get in touch with Mainstreet.lt. We'd love to hear from you.
        </p>
    </div>
</section>

<!-- Contact Form Section -->
<section class="py-16">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-foreground mb-4">Send a Message</h2>
            <p class="text-muted-foreground mb-6">We'll respond to your inquiry as soon as possible.</p>

            <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $name = htmlspecialchars(trim($_POST["name"] ?? ''));
                $email = htmlspecialchars(trim($_POST["email"] ?? ''));
                $message = htmlspecialchars(trim($_POST["message"] ?? ''));
                $honeypot = $_POST["website"] ?? '';

                if (!empty($honeypot)) {
                    die(); // bot detected
                }

                if ($name && $email && $message) {
                    $to = "contact@mainstreet.lt"; // ← Replace with your email
                    $subject = "New Contact Form Submission";
                    $body = "Name: $name\nEmail: $email\n\nMessage:\n$message";
                    $headers = "From: $email";

                    if (mail($to, $subject, $body, $headers)) {
                        echo '<p class="text-green-600 mb-4">Thank you! Your message has been sent.</p>';
                    } else {
                        echo '<p class="text-red-600 mb-4">Something went wrong. Please try again later.</p>';
                    }
                } else {
                    echo '<p class="text-red-600 mb-4">All fields are required.</p>';
                }
            }
            ?>

            <form method="POST" action="" class="space-y-6">
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                <div>
                    <label for="name" class="block text-sm font-medium text-foreground mb-2">Name *</label>
                    <input type="text" id="name" name="name" required
                           class="w-full px-3 py-2 bg-card/40 backdrop-blur-sm rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-foreground mb-2">Email *</label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-3 py-2 bg-card/40 backdrop-blur-sm rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-foreground mb-2">Message *</label>
                    <textarea id="message" name="message" rows="5" required
                              class="w-full px-3 py-2 bg-card/40 backdrop-blur-sm rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary resize-none"
                              placeholder="Your message..."></textarea>
                </div>

                <button type="submit"
                        class="px-6 py-3 rounded-lg bg-primary text-white font-semibold hover:bg-primary/80 transition-all">
                    Send Message
                </button>
            </form>
        </div>
    </div>
</section>

<script>
lucide.createIcons();
</script>

<?php include 'includes/footer.php'; ?>
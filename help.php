<?php
require_once 'functions.php';

$pageTitle = 'Help & FAQ';
$pageDescription = 'Frequently Asked Questions about Mainstreet';

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
document.addEventListener("DOMContentLoaded",()=>{let e=document.getElementById("waves"),t=[{classes:"left-1/2 -translate-x-1/2 w-[100vw] h-[70vh] wave-right-top",verticalOffset:-.25},{classes:"-left-1/2 w-[90vw] h-[90vh] wave-left-top",verticalOffset:.33},{classes:"left-1/2 w-[100vw] h-[75vh] wave-left-bottom",verticalOffset:.66},{classes:"-left-1/2 w-[80vw] h-[85vh] wave-right-bottom",verticalOffset:0},{classes:"left-1/2 w-[80vw] h-[85vh] wave-right-bottom",verticalOffset:0},{classes:"left-1/2 w-[100vw] h-[70vh] wave-left-bottom",verticalOffset:-.2},];function l(){e.innerHTML="";let l=document.body.scrollHeight,s=window.innerHeight,a=Math.ceil(l/s);for(let v=0;v<a;v++){let f=v*s;t.forEach(t=>{let l=document.createElement("div");l.className=`absolute ${t.classes}`;let a=t.verticalOffset?t.verticalOffset*s:0;l.style.top=`${f+a}px`,e.appendChild(l)})}}function s(){e.style.height=document.body.scrollHeight+"px"}window.addEventListener("load",()=>{s(),l()}),window.addEventListener("resize",()=>{s(),l()})});
</script>

<!-- Hero Section -->
<section class="text-white pt-40 pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-5xl md:text-6xl font-bold mb-6">
            Need Help?
            <span class="text-accent">We've Got You</span>
        </h1>
        <p class="text-xl md:text-2xl text-white/90 mb-8 max-w-3xl mx-auto">
            Find answers to the most common questions about Mainstreet and our services.
        </p>
    </div>
</section>

<!-- FAQ Section -->
<section class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-12 text-center">
            <h2 class="text-3xl font-bold text-foreground mb-4">FAQ</h2>
            <p class="text-muted-foreground text-lg">
                Here are answers to some common questions about Mainstreet and how we protect your privacy.
            </p>
        </div>

        <div class="space-y-6" id="faq-list">

            <?php
            $faqs = [
                [
                    'question' => 'What is Mainstreet?',
                    'answer' => 'Mainstreet is a [brief description of your business — e.g., platform/service/tool] designed to help [target users] with [main benefit or service offered].'
                ],
                [
                    'question' => 'Is my personal data secure?',
                    'answer' => 'Yes. We prioritize your privacy and security. Mainstreet uses industry-standard security measures to protect your data. Please review our <a href="/privacy" class="text-accent hover:underline">Privacy Policy</a> for more details.'
                ],
                [
                    'question' => 'How can I update or delete my account information?',
                    'answer' => 'You can update or request deletion of your account information by contacting us at <a href="mailto:contact@mainstreet.lt" class="text-accent hover:underline">contact@mainstreet.lt</a>. We’ll process your request in accordance with applicable privacy laws.'
                ],
                [
                    'question' => 'Do you use cookies?',
                    'answer' => 'Yes, we use cookies and similar tracking technologies to improve your experience and analyze site traffic. You can manage your cookie preferences through your browser settings.'
                ],
                [
                    'question' => 'How do I contact Mainstreet for support?',
                    'answer' => 'You can reach our support team by emailing <a href="mailto:contact@mainstreet.lt" class="text-accent hover:underline">contact@mainstreet.lt</a>. We aim to respond within 24–48 business hours.'
                ],
                [
                    'question' => 'Can I opt out of marketing emails?',
                    'answer' => 'Absolutely. Every marketing email includes an unsubscribe link. You can also email us directly at <a href="mailto:contact@mainstreet.lt" class="text-accent hover:underline">contact@mainstreet.lt</a> to opt out.'
                ],
            ];

            foreach ($faqs as $index => $faq): ?>
                <div class="bg-card/20 backdrop-blur-sm rounded-xl">
                    <button
                        class="w-full rounded-xl text-left px-6 py-4 flex justify-between items-center bg-card/20 backdrop-blur-sm hover:bg-card/40 backdrop-blur-sm focus:outline-none"
                        onclick="toggleFAQ(<?= $index ?>)"
                        aria-expanded="false"
                        aria-controls="faq-content-<?= $index ?>"
                    >
                        <span class="text-lg font-medium text-foreground"><?= $faq['question'] ?></span>
                        <svg id="icon-<?= $index ?>" class="w-5 h-5 text-muted-foreground transition-transform duration-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="faq-content-<?= $index ?>" class="hidden px-6 pb-4 text-muted-foreground">
                        <p class="mt-2"><?= $faq['answer'] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script>
    function toggleFAQ(index) {
        const content = document.getElementById(`faq-content-${index}`);
        const icon = document.getElementById(`icon-${index}`);
        const isOpen = content.classList.contains('hidden') === false;

        if (isOpen) {
            content.classList.add('hidden');
            icon.classList.remove('rotate-180');
        } else {
            content.classList.remove('hidden');
            icon.classList.add('rotate-180');
        }
    }

    lucide.createIcons?.();
</script>

<?php include 'includes/footer.php'; ?>
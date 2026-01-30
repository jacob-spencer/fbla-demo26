<?php
require_once 'functions.php';

$pageTitle = 'Help & FAQ';
$pageDescription = 'Frequently Asked Questions about Mainstreet';

include 'includes/header.php';
?>

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
                    'answer' => 'Mainstreet is a platform that helps users discover and support small, local businesses in their community. At Mainstreet, our mission is to strengthen local connections by spotlighting the small, local businesses that make our community unique. We aim to inspire residents to shop locally, engage deeply, and take pride in supporting the people and places that keep our neighborhoods thriving.'
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

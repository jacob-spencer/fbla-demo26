<?php
require_once 'functions.php';

$pageTitle = 'Business Health';
$pageDescription = 'Discover exclusive deals and offers from local businesses';

$db = Database::getInstance();

// Get all active deals
$deals = $db->fetchAll("
    SELECT d.*, b.name as business_name, b.image_url as business_image, 
           b.address as business_address, c.name as category_name
    FROM deals d 
    LEFT JOIN businesses b ON d.business_id = b.id 
    LEFT JOIN categories c ON b.category_id = c.id
    WHERE d.is_active = 1 AND d.expires_at > NOW() 
    ORDER BY d.created_at DESC
");

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

<main class="min-h-screen">
    <!-- Hero Section -->
    <section class="text-white pt-36 pb-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">
                Business <span class="text-accent">Health</span>
            </h1>
            <p class="text-xl text-white/90 max-w-2xl mx-auto">
                Check out the health of local businesses in your community.
            </p>
        </div>
    </section>

    <!-- Body -->
    <section class="py-16">
      <div class="max-w-5xl mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-6 text-white">Generate a Business Health Report</h2>
        <p class="text-lg text-white/70 mb-10">Enter a ZIP Code to analyze nearby businesses and see their overall health score.</p>
    
        <button onclick="openModal()" 
                class="px-6 py-3 bg-accent hover:bg-accent/90 text-black font-medium rounded-lg transition">
          Generate Report
        </button>
    
        <div id="pdf-status" class="mt-8 text-white/70 hidden">Generating your report...</div>
      </div>
    </section>
</main>

<!-- Modal -->
<div id="pdfGenerateModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-lg flex items-center justify-center z-50" style="display: none;">
    <div class="bg-card/40 backdrop-blur-sm rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-foreground">Generate Report</h3>
            <button onclick="closeModal()" class="text-muted-foreground hover:text-foreground">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <form id="review-form">
            <div class="mb-6">
                <label for="zip" class="block text-sm font-medium text-foreground mb-2">ZIP Code</label>
                <input id="zip" name="zip"
                          class="w-full px-3 py-2 bg-input border border-border rounded-lg text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                          placeholder="ZIP Code"></textarea>
            </div>
            
            <div class="flex space-x-3">
                <button type="button" onclick="closeModal()" 
                        class="flex-1 px-4 py-2 border border-border rounded-lg hover:bg-muted transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 px-4 py-2 bg-accent hover:bg-accent/90 text-black font-medium rounded-lg transition-colors">
                    Generate
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function openModal() {
  document.getElementById("pdfGenerateModal").style.display = "flex";
}
function closeModal() {
  document.getElementById("pdfGenerateModal").style.display = "none";
}

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("review-form");
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const zip = document.getElementById("zip").value.trim();
    if (!zip) return alert("Please enter a ZIP code");

    closeModal();
    document.getElementById("pdf-status").classList.remove("hidden");

    try {
      const response = await fetch(`bh-pdf.php?zip=${encodeURIComponent(zip)}`);
      const html = await response.text();

      // ✅ Create a visible iframe that can render full styles
      const iframe = document.createElement("iframe");
      iframe.style.position = "fixed";
      iframe.style.top = "0";
      iframe.style.left = "0";
      iframe.style.width = "100vw";
      iframe.style.height = "100vh";
      iframe.style.zIndex = "-1";
      iframe.style.opacity = "0";
      document.body.appendChild(iframe);

      // ✅ Write HTML directly to iframe
      iframe.contentDocument.open();
      iframe.contentDocument.write(html);
      iframe.contentDocument.close();

      // ✅ Wait for iframe DOM and styles to be ready
    await new Promise((resolve) => {
      const doc = iframe.contentDocument;
      const checkReady = () => {
        const stylesLoaded = Array.from(doc.styleSheets).every((sheet) => {
          try {
            return sheet.cssRules; // Accessing cssRules will throw if not loaded
          } catch (e) {
            return false;
          }
        });
        if (doc.readyState === "complete" && stylesLoaded) {
          resolve();
        } else {
          setTimeout(checkReady, 200);
        }
      };
      checkReady();
    });


      // ✅ Convert iframe’s body to PDF
      const opt = {
        margin: 0.5,
        filename: `business-health-${zip}.pdf`,
        image: { type: "jpeg", quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
        jsPDF: { unit: "in", format: "letter", orientation: "portrait" }
      };

      await html2pdf().from(iframe.contentDocument.body).set(opt).save();

      // Cleanup
      iframe.remove();
      document.getElementById("pdf-status").classList.add("hidden");

    } catch (err) {
      console.error(err);
      alert("Error generating report. Please try again.");
      document.getElementById("pdf-status").classList.add("hidden");
    }
  });
});

</script>

<?php include 'includes/footer.php'; ?>
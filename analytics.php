<?php
require_once 'functions.php';
requireAuth();
$user = getCurrentUser();
if (!$user['is_business_owner']) {
    header('Location: /');
    exit;
}

$db = Database::getInstance();

// Get business
$business = $db->fetch("
    SELECT b.*, c.name as category_name 
    FROM businesses b 
    LEFT JOIN categories c ON b.category_id = c.id 
    WHERE b.owner_id = ?
", [$user['id']]);

if (!$business) die("Business not found.");

// Time filter
$timeOptions = [
    '1d'=>['interval'=>1,'unit'=>'DAY','label'=>'1 Day'],
    '3d'=>['interval'=>3,'unit'=>'DAY','label'=>'3 Days'],
    '1w'=>['interval'=>1,'unit'=>'WEEK','label'=>'1 Week'],
    '1m'=>['interval'=>1,'unit'=>'MONTH','label'=>'1 Month']
];
$selectedTime = $_GET['range'] ?? '1w';
$interval = $timeOptions[$selectedTime]['interval'];
$unit = $timeOptions[$selectedTime]['unit'];
$rangeLabel = $timeOptions[$selectedTime]['label'];

// Current stats
$aggregatedStats = $db->fetch("
    SELECT COUNT(*) AS page_views,
           SUM(CASE WHEN event_type='website_click' THEN 1 ELSE 0 END) AS conversions,
           COUNT(DISTINCT session_id) AS sessions
    FROM analytics
    WHERE business_id=? AND event_timestamp >= NOW() - INTERVAL ? $unit
", [$business['id'], $interval]);

// Previous stats for % change
$prevStats = $db->fetch("
    SELECT COUNT(*) AS page_views,
           SUM(CASE WHEN event_type='website_click' THEN 1 ELSE 0 END) AS conversions,
           COUNT(DISTINCT session_id) AS sessions
    FROM analytics
    WHERE business_id=? AND event_timestamp BETWEEN NOW() - INTERVAL (?*2) $unit AND NOW() - INTERVAL ? $unit
", [$business['id'], $interval, $interval]);

function percentChange($curr, $prev) {
    if ($prev==0) return $curr>0 ? 100 : 0;
    return round((($curr-$prev)/$prev)*100,1);
}
$pageViewsChange = percentChange($aggregatedStats['page_views'],$prevStats['page_views']);
$conversionsChange = percentChange($aggregatedStats['conversions'],$prevStats['conversions']);
$sessionsChange = percentChange($aggregatedStats['sessions'],$prevStats['sessions']);

// Chart data (daily)
$chartData = $db->fetchAll("
    SELECT DATE(event_timestamp) AS date,
           COUNT(*) AS page_views,
           SUM(CASE WHEN event_type='website_click' THEN 1 ELSE 0 END) AS conversions,
           COUNT(DISTINCT session_id) AS sessions
    FROM analytics
    WHERE business_id=? AND event_timestamp >= NOW() - INTERVAL ? $unit
    GROUP BY DATE(event_timestamp)
    ORDER BY DATE(event_timestamp)
", [$business['id'], $interval]);

// Hourly averages
$hourlyData = $db->fetchAll("
    SELECT HOUR(event_timestamp) AS hour,
           COUNT(*)/COUNT(DISTINCT DATE(event_timestamp)) AS avg_page_views,
           SUM(CASE WHEN event_type='website_click' THEN 1 ELSE 0 END)/COUNT(DISTINCT DATE(event_timestamp)) AS avg_conversions,
           COUNT(DISTINCT session_id)/COUNT(DISTINCT DATE(event_timestamp)) AS avg_sessions
    FROM analytics
    WHERE business_id=? AND event_timestamp >= NOW() - INTERVAL ? $unit
    GROUP BY HOUR(event_timestamp)
", [$business['id'], $interval]);

// Ensure all 24 hours
$hourlyFull = array_fill(0,24,['hour'=>0,'avg_page_views'=>0,'avg_conversions'=>0,'avg_sessions'=>0]);
foreach($hourlyFull as $h=>&$row) $row['hour']=$h; unset($row);
foreach($hourlyData as $row) $hourlyFull[(int)$row['hour']]=$row;
$hourlyData = array_values($hourlyFull);

// Recent events
$recentEvents = $db->fetchAll("
    SELECT event_type,event_timestamp,page_url,referrer_url,device_type,user_id,session_id,browser,os
    FROM analytics
    WHERE business_id=?
    ORDER BY event_timestamp DESC
    LIMIT 20
", [$business['id']]) ?: [];

// Performance summary
$summaryRanges=['1d'=>'1 DAY','3d'=>'3 DAY','1w'=>'1 WEEK','1m'=>'1 MONTH'];
$performanceSummary=[];
foreach($summaryRanges as $key=>$intervalStr){
    $row = $db->fetch("
        SELECT COUNT(*) AS page_views,
               SUM(CASE WHEN event_type='website_click' THEN 1 ELSE 0 END) AS conversions,
               COUNT(DISTINCT session_id) AS sessions
        FROM analytics
        WHERE business_id=? AND event_timestamp >= NOW() - INTERVAL $intervalStr
    ", [$business['id']]);
    $row['conversion_rate'] = $row['sessions']>0 ? round(($row['conversions']/$row['sessions'])*100,1):0;
    $performanceSummary[$key] = array_merge(['label'=>$timeOptions[$key]['label']],$row);
}

$pageTitle='Analytics';
$pageDescription='View your business analytics';

include 'includes/header.php';
?>

<main class="min-h-screen bg-background py-20">
    <div id="geo-loading-overlay" class="fixed inset-0 bg-background/70 backdrop-blur-lg flex items-center justify-center z-50 hidden">
        <div class="bg-card px-6 py-4 rounded-lg shadow-lg text-center border border-border">
            <svg class="animate-spin h-6 w-6 text-primary mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <p class="text-sm text-foreground">Preparing report...</p>
        </div>
    </div>
    
    <div id="report-options-modal" class="fixed inset-0 bg-background/70 backdrop-blur-lg flex items-center justify-center z-50 hidden">
        <div class="bg-card px-6 py-4 rounded-lg shadow-lg text-center border border-border">
            <h2 class="text-sm text-foreground mb-3">Report Options</h2>
    
            <div class="space-y-2 text-left">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="statistics-table-checkbox" checked
                      class="appearance-none h-5 w-5 border-2 border-border rounded-md checked:bg-primary checked:border-primary
                             transition duration-200 relative cursor-pointer
                             checked:before:content-['✓'] checked:before:absolute checked:before:text-white checked:before:text-sm checked:before:inset-0 checked:before:flex checked:before:items-center checked:before:justify-center" />
                    <span class="text-foreground">Statistics Table</span>
                </label>
                
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="traffic-chart-checkbox" checked
                      class="appearance-none h-5 w-5 border-2 border-border rounded-md checked:bg-primary checked:border-primary
                             transition duration-200 relative cursor-pointer
                             checked:before:content-['✓'] checked:before:absolute checked:before:text-white checked:before:text-sm checked:before:inset-0 checked:before:flex checked:before:items-center checked:before:justify-center" />
                    <span class="text-foreground">Traffic Chart</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="hourly-chart-checkbox" checked
                      class="appearance-none h-5 w-5 border-2 border-border rounded-md checked:bg-primary checked:border-primary
                             transition duration-200 relative cursor-pointer
                             checked:before:content-['✓'] checked:before:absolute checked:before:text-white checked:before:text-sm checked:before:inset-0 checked:before:flex checked:before:items-center checked:before:justify-center" />
                    <span class="text-foreground">Hourly Trends Chart</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="summary-table-checkbox" checked
                      class="appearance-none h-5 w-5 border-2 border-border rounded-md checked:bg-primary checked:border-primary
                             transition duration-200 relative cursor-pointer
                             checked:before:content-['✓'] checked:before:absolute checked:before:text-white checked:before:text-sm checked:before:inset-0 checked:before:flex checked:before:items-center checked:before:justify-center" />
                    <span class="text-foreground">Summary</span>
                </label>
                
            </div>
    
            <div class="mt-4 flex justify-end gap-2">
                <button id="cancelReport" class="px-3 py-1 border border-border rounded-lg">Cancel</button>
                <button id="confirmReport" class="px-3 py-1 bg-primary text-white rounded-lg">Generate PDF</button>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <!-- Dashboard Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="geist text-3xl">Analytics</h1>
            <p class="text-muted-foreground">Monitor your business performance</p>
        </div>
        <button id="downloadPdf" class="px-4 py-2 bg-primary text-white rounded-lg">Download PDF</button>
    </div>
    
    <!-- Filter Dropdown -->
    <form method="GET" class="mb-6">
    <label for="range" class="mr-2 font-medium text-foreground">Time Range:</label>
    <select id="range" name="range" onchange="this.form.submit()" class="px-3 py-2 bg-input border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary">
        <option value="1d" <?= $selectedTime==='1d'?'selected':'' ?>>1 day</option>
        <option value="3d" <?= $selectedTime==='3d'?'selected':'' ?>>3 days</option>
        <option value="1w" <?= $selectedTime==='1w'?'selected':'' ?>>1 week</option>
        <option value="1m" <?= $selectedTime==='1m'?'selected':'' ?>>1 month</option>
    </select>
    </form>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
        <div class="bg-card rounded-xl border border-border p-6 mb-8">
            <h3 class="text-lg font-semibold text-foreground">Page Views</h3>
            <p class="text-3xl font-bold mt-2"><?= $aggregatedStats['page_views'] ?></p>
        </div>
        <div class="bg-card rounded-xl border border-border p-6 mb-8">
            <h3 class="text-lg font-semibold text-foreground">Conversions</h3>
            <p class="text-3xl font-bold mt-2"><?= $aggregatedStats['conversions'] ?></p>
        </div>
        <div class="bg-card rounded-xl border border-border p-6 mb-8">
            <h3 class="text-lg font-semibold text-foreground">Sessions</h3>
            <p class="text-3xl font-bold mt-2"><?= $aggregatedStats['sessions'] ?></p>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="bg-card rounded-xl border border-border p-6 mb-8">
        <h3 class="text-lg font-semibold text-foreground mb-4">Traffic Trends</h3>
        <div style="height:300px;">
            <canvas id="analyticsChart"></canvas>
        </div>
    </div>
    
    <div class="bg-card rounded-xl border border-border p-6 mb-8">
        <h3 class="text-lg font-semibold text-foreground mb-4">Hourly Trends</h3>
        <div style="height:300px;">
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>

<!-- Recent Events Table -->
    <div class="bg-card rounded-xl border border-border p-6 mb-8">
        <h3 class="text-lg font-semibold text-foreground mb-4">Recent Events</h3>
        <div class="overflow-auto">
            <table class="min-w-full text-sm text-left text-muted-foreground">
                <thead>
                    <tr>
                        <th class="px-4 py-2">Event</th>
                        <th class="px-4 py-2">Time</th>
                        <th class="px-4 py-2">Page</th>
                        <th class="px-4 py-2">Referrer</th>
                        <th class="px-4 py-2">Device</th>
                        <th class="px-4 py-2">OS</th>
                        <th class="px-4 py-2">Browser</th>
                        <th class="px-4 py-2">User ID</th>
                        <th class="px-4 py-2">Session ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recentEvents as $event): ?>
                        <tr class="border-t">
                            <td class="px-4 py-2"><?=htmlspecialchars($event['event_type'])?></td>
                            <td class="px-4 py-2"><?=date("M d, Y H:i",strtotime($event['event_timestamp']))?></td>
                            <td class="px-4 py-2 truncate"><?=htmlspecialchars($event['page_url'])?></td>
                            <td class="px-4 py-2 truncate"><?= $event['referrer_url'] !== null ? htmlspecialchars($event['referrer_url']) : 'none' ?></td>
                            <td class="px-4 py-2 truncate"><?=htmlspecialchars($event['device_type'])?></td>
                            <td class="px-4 py-2 truncate"><?=htmlspecialchars($event['os'])?></td>
                            <td class="px-4 py-2 truncate"><?=htmlspecialchars($event['browser'])?></td>
                            <td class="px-4 py-2 truncate"><?= $event['user_id'] !== null ? htmlspecialchars($event['user_id']) : 'none' ?></td>
                            <td class="px-4 py-2 truncate"><?=htmlspecialchars($event['session_id'])?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Hidden PDF Container -->
<div id="pdfContainer" style="display:none; width:100%; max-width:760px; background:white; padding:20px; transform: translateX(-24pt); box-sizing:border-box;">
    <?php include 'pdf_template.php'; ?>
</div>

<!-- Scripts, now minified for your viewing pleasure -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
<script>
    const chartData = <?=json_encode($chartData)?>;
const hourlyData = <?=json_encode($hourlyData)?>;
const ctxMain = document.getElementById('analyticsChart').getContext('2d');
new Chart(ctxMain, {
    type: 'line',
    data: {
        labels: chartData.map(r => r.date),
        datasets: [{
            label: 'Page Views',
            data: chartData.map(r => r.page_views),
            borderColor: '#007bff',
            fill: false
        }, {
            label: 'Conversions',
            data: chartData.map(r => r.conversions),
            borderColor: '#ffc107',
            fill: false
        }, {
            label: 'Sessions',
            data: chartData.map(r => r.sessions),
            borderColor: '#28a745',
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
const ctxHourly = document.getElementById('hourlyChart').getContext('2d');
new Chart(ctxHourly, {
    type: 'line',
    data: {
        labels: hourlyData.map(r => String(r.hour).padStart(2, '0') + ":00"),
        datasets: [{
            label: 'Page Views',
            data: hourlyData.map(r => r.avg_page_views),
            borderColor: '#007bff',
            fill: false
        }, {
            label: 'Conversions',
            data: hourlyData.map(r => r.avg_conversions),
            borderColor: '#ffc107',
            fill: false
        }, {
            label: 'Sessions',
            data: hourlyData.map(r => r.avg_sessions),
            borderColor: '#28a745',
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
let pdfTrafficChartInstance = null;
let pdfHourlyChartInstance = null;
const overlay = document.getElementById('geo-loading-overlay');
async function buildPdfCharts() {
    const statisticsTable = document.getElementById("statistics-table-checkbox").checked;
    const trafficChart = document.getElementById("traffic-chart-checkbox").checked;
    const hourlyChart = document.getElementById("hourly-chart-checkbox").checked;
    const summaryTable = document.getElementById("summary-table-checkbox").checked;
    if (overlay) overlay.classList.remove('hidden');
    const pdfContainer = document.getElementById('pdfContainer');
    pdfContainer.style.display = 'block';
    if (pdfTrafficChartInstance) pdfTrafficChartInstance.destroy();
    if (pdfHourlyChartInstance) pdfHourlyChartInstance.destroy();
    const trafficCtx = document.getElementById('pdfTrafficChart').getContext('2d');
    pdfTrafficChartInstance = new Chart(trafficCtx, {
        type: 'line',
        data: {
            labels: chartData.map(r => r.date),
            datasets: [{
                label: 'Page Views',
                data: chartData.map(r => r.page_views),
                borderColor: '#007bff',
                fill: false
            }, {
                label: 'Conversions',
                data: chartData.map(r => r.conversions),
                borderColor: '#ffc107',
                fill: false
            }, {
                label: 'Sessions',
                data: chartData.map(r => r.sessions),
                borderColor: '#28a745',
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    const hourlyCtx = document.getElementById('pdfHourlyChart').getContext('2d');
    pdfHourlyChartInstance = new Chart(hourlyCtx, {
        type: 'line',
        data: {
            labels: hourlyData.map(r => String(r.hour).padStart(2, '0') + ":00"),
            datasets: [{
                label: 'Page Views',
                data: hourlyData.map(r => r.avg_page_views),
                borderColor: '#007bff',
                fill: false
            }, {
                label: 'Conversions',
                data: hourlyData.map(r => r.avg_conversions),
                borderColor: '#ffc107',
                fill: false
            }, {
                label: 'Sessions',
                data: hourlyData.map(r => r.avg_sessions),
                borderColor: '#28a745',
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    await new Promise(r => setTimeout(r, 300));
    if (overlay) overlay.classList.add('hidden');
    return pdfContainer;
}
const modal = document.getElementById('report-options-modal');
const downloadBtn = document.getElementById('downloadPdf');
const confirmBtn = document.getElementById('confirmReport');
const cancelBtn = document.getElementById('cancelReport');
downloadBtn.addEventListener('click', () => {
    modal.classList.remove('hidden');
});
cancelBtn.addEventListener('click', () => {
    modal.classList.add('hidden');
});
confirmBtn.addEventListener('click', async () => {
    modal.classList.add('hidden');
    const container = await buildPdfCharts();
    html2pdf().set({
        margin: 10,
        filename: 'analytics_report.pdf',
        image: {
            type: 'jpeg',
            quality: 0.98
        },
        html2canvas: {
            scale: 1.8,
            useCORS: true
        },
        jsPDF: {
            unit: 'pt',
            format: 'a4',
            orientation: 'portrait'
        }
    }).from(container).save().finally(() => {
        container.style.display = 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>

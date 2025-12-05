<?php
// pdf_template.php
// Include this inside a hidden #pdfContainer in your dashboard PHP
?>

<div style="width:800px; background:white; padding:20px; font-family: Arial, sans-serif; color:#333;">
    <!-- Header -->
    <div class="header" style="text-align:center; margin-bottom:30px; border-bottom:3px solid #007bff; padding-bottom:20px;">
        <h1 style="margin:0; color:#333; font-size:22pt; font-weight:bold;"><?= htmlspecialchars($business['name']) ?> - Analytics</h1>
        <p style="margin:5px 0 0 0; color:#666; font-size:14px;">Generated on <?= date("F j, Y") ?> at <?= date("H:i:s") ?> | Dashboard Overview</p>
    </div>

    <!-- Metrics Grid -->
    <div id="metrics-grid" class="metrics-grid" style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:40px;">
        <div class="metric-card" style="background:#f8f9fa; padding:20px; border-radius:8px; text-align:center; border-left:4px solid #007bff;">
            <div class="metric-title" style="font-size:14px; color:#666; margin-bottom:10px; font-weight:bold;">Page Views</div>
            <div class="metric-value" style="font-size:32px; font-weight:bold; color:#007bff; margin-bottom:5px;"><?= number_format($aggregatedStats['page_views']) ?></div>
            <div class="metric-change" style="font-size:12px; color:<?= $pageViewsChange>=0 ? '#28a745':'#dc3545' ?>;">
                <?= ($pageViewsChange>=0?'↗ +':'↘ ').$pageViewsChange ?>% from last period
            </div>
        </div>

        <div class="metric-card" style="background:#f8f9fa; padding:20px; border-radius:8px; text-align:center; border-left:4px solid #ffc107;">
            <div class="metric-title" style="font-size:14px; color:#666; margin-bottom:10px; font-weight:bold;">Conversions</div>
            <div class="metric-value" style="font-size:32px; font-weight:bold; color:#ffc107; margin-bottom:5px;"><?= number_format($aggregatedStats['conversions']) ?></div>
            <div class="metric-change" style="font-size:12px; color:<?= $conversionsChange>=0 ? '#28a745':'#dc3545' ?>;">
                <?= ($conversionsChange>=0?'↗ +':'↘ ').$conversionsChange ?>% from last period
            </div>
        </div>

        <div class="metric-card" style="background:#f8f9fa; padding:20px; border-radius:8px; text-align:center; border-left:4px solid #28a745;">
            <div class="metric-title" style="font-size:14px; color:#666; margin-bottom:10px; font-weight:bold;">Sessions</div>
            <div class="metric-value" style="font-size:32px; font-weight:bold; color:#28a745; margin-bottom:5px;"><?= number_format($aggregatedStats['sessions']) ?></div>
            <div class="metric-change" style="font-size:12px; color:<?= $sessionsChange>=0 ? '#28a745':'#dc3545' ?>;">
                <?= ($sessionsChange>=0?'↗ +':'↘ ').$sessionsChange ?>% from last period
            </div>
        </div>
    </div>

    <!-- Traffic Trends Chart -->
    <div id="t-section" class="chart-section" style="margin-bottom:40px;">
        <h2 class="chart-title" style="font-size:18px; font-weight:bold; margin-bottom:15px; color:#333;">Traffic Trends Over Time</h2>
        <div class="chart-container" style="position:relative; height:300px; margin-bottom:30px;">
            <canvas id="pdfTrafficChart" style="width:100%; height:300px;"></canvas>
        </div>
    </div>

    <!-- Hourly Traffic Chart -->
    <div id="ht-section" class="chart-section" style="margin-bottom:40px;">
        <h2 class="chart-title" style="font-size:18px; font-weight:bold; margin-bottom:15px; color:#333;">Average Traffic per Hour (24-hour cycle, UTC)</h2>
        <div class="chart-container" style="position:relative; height:300px; margin-bottom:30px;">
            <canvas id="pdfHourlyChart" style="width:100%; height:300px;"></canvas>
        </div>
    </div>

    <!-- Performance Summary Table -->
    <div id="perf-section" class="chart-section" style="hidden margin-bottom:40px;">
        <h2 class="chart-title" style="font-size:18px; font-weight:bold; margin-bottom:15px; color:#333;">Performance Summary</h2>
        <table id="performance-summary-table" class="summary-table" style="width:100%; border-collapse:collapse; margin-top:20px;">
            <thead>
                <tr>
                    <th style="padding:12px; text-align:left; border-bottom:1px solid #ddd; background:#f8f9fa; font-weight:bold;">Time Range</th>
                    <th style="padding:12px; text-align:left; border-bottom:1px solid #ddd;">Page Views</th>
                    <th style="padding:12px; text-align:left; border-bottom:1px solid #ddd;">Sessions</th>
                    <th style="padding:12px; text-align:left; border-bottom:1px solid #ddd;">Conversions</th>
                    <th style="padding:12px; text-align:left; border-bottom:1px solid #ddd;">Conversion Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($performanceSummary as $row): ?>
                <tr>
                    <td style="padding:12px; border-bottom:1px solid #ddd;"><?= $row['label'] ?></td>
                    <td style="padding:12px; border-bottom:1px solid #ddd;"><?= number_format($row['page_views']) ?></td>
                    <td style="padding:12px; border-bottom:1px solid #ddd;"><?= number_format($row['sessions']) ?></td>
                    <td style="padding:12px; border-bottom:1px solid #ddd;"><?= $row['conversions'] ?></td>
                    <td style="padding:12px; border-bottom:1px solid #ddd;"><?= $row['conversion_rate'] ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer" style="text-align:center; margin-top:40px; padding-top:20px; border-top:1px solid #ddd; color:#666; font-size:12px;">
        <p>Mainstreet Analytics Dashboard | Confidential</p>
        <p>For questions about this report, contact analytics@mainstreet.lt</p>
    </div>
</div>

<script>
    

</script>

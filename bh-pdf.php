<?php
require_once 'functions.php';
ob_start();
require_once 'search-ajax.php';
ob_end_clean();
header('Content-Type: text/html; charset=utf-8');

$db = Database::getInstance();

ini_set('display_errors', 'Off');
error_reporting(0);

$zip = $_GET['zip'] ?? '';
if (!$zip) {
  echo "<p>ZIP code required.</p>";
  exit;
}

// Fetch businesses near this ZIP
$searchService = new SearchService(); // from your existing code
$businesses = $searchService->searchBusinesses([]);
$result = $searchService->processBusinessesWithLocation($businesses, $zip, 10); // 10 mile radius

$nearby = $result['businesses'];
$totalBusinesses = count($nearby);

// Calculate business “health metrics”
$avgRating = 0;
$reviewCount = 0;
foreach ($nearby as $b) {
  $avgRating += (float)$b['average_rating'];
  $reviewCount += (int)$b['total_reviews'];
}
$avgRating = $totalBusinesses ? round($avgRating / $totalBusinesses, 2) : 0;
$avgReviews = $totalBusinesses ? round($reviewCount / $totalBusinesses, 1) : 0;

// Determine grade
if ($avgRating >= 4.5) $grade = "A+";
elseif ($avgRating >= 4.0) $grade = "A";
elseif ($avgRating >= 3.5) $grade = "B";
elseif ($avgRating >= 3.0) $grade = "C";
else $grade = "D";

// Output report HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Business Health Report - <?= htmlspecialchars($zip) ?></title>
<style>
  body { font-family: Arial, sans-serif; color: #222; margin: 0; padding: 20px; }
  h1 { text-align: center; color: #0366d6; }
  .summary { margin: 30px 0; text-align: center; }
  .metric { font-size: 1.2rem; margin-bottom: 10px; color: #000000; }
  .grade { font-size: 3rem; font-weight: bold; color: #28a745; }
  table { width: 100%; border-collapse: collapse; margin-top: 20px; }
  th, td { padding: 8px 12px; border-bottom: 1px solid #ddd; color: #000000; }
  th { background: #f5f5f5; text-align: left; }
  tr:nth-child(even) { background: #fafafa; }
</style>
</head>
<body style="font-family: Arial, sans-serif; color: #222; margin: 0; padding: 20px; background: #ffffff;">
  <h1 style="text-align: center; color: #0366d6;">Business Health Report</h1>
  <p style="text-align:center; color: #000000;">ZIP Code: <strong><?= htmlspecialchars($zip) ?></strong></p>

  <div class="summary" style="margin: 30px 0; text-align: center;">
    <div class="metric" style="font-size: 1.2rem; margin-bottom: 10px; color: #000000;">Nearby Businesses: <strong><?= $totalBusinesses ?></strong></div>
    <div class="metric" style="font-size: 1.2rem; margin-bottom: 10px; color: #000000;">Average Rating: <strong><?= $avgRating ?></strong></div>
    <div class="metric" style="font-size: 1.2rem; margin-bottom: 10px; color: #000000;">Average Reviews per Business: <strong><?= $avgReviews ?></strong></div>
    <div class="metric" style="font-size: 1.2rem; margin-bottom: 10px; color: #000000;">Overall Business Health Grade:</div>
    <div class="grade" style="font-size: 3rem; font-weight: bold; color: #28a745;"><?= $grade ?></div>
  </div>

  <?php if ($totalBusinesses > 0): ?>
  <table style=" width: 100%; border-collapse: collapse; margin-top: 20px; ">
    <thead>
      <tr>
        <th style="padding: 8px 12px; border-bottom: 1px solid #ddd; color: #000000; background: #f5f5f5; text-align: left;">Business Name</th>
        <th style="padding: 8px 12px; border-bottom: 1px solid #ddd; color: #000000; background: #f5f5f5; text-align: left;">Category</th>
        <th style="padding: 8px 12px; border-bottom: 1px solid #ddd; color: #000000; background: #f5f5f5; text-align: left;">Rating</th>
        <th style="padding: 8px 12px; border-bottom: 1px solid #ddd; color: #000000; background: #f5f5f5; text-align: left;">Distance (mi)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($nearby as $b): ?>
      <tr style="background: #fafafa;">
        <td style="padding: 8px 12px; border-bottom: 1px solid #ddd; color: #000000;"><?= htmlspecialchars($b['name']) ?></td>
        <td style="padding: 8px 12px; border-bottom: 1px solid #ddd; color: #000000;"><?= htmlspecialchars($b['category_name']) ?></td>
        <td style="padding: 8px 12px; border-bottom: 1px solid #ddd; color: #000000;"><?= htmlspecialchars($b['average_rating'] ?? 'N/A') ?></td>
        <td style="padding: 8px 12px; border-bottom: 1px solid #ddd; color: #000000;"><?= isset($b['distance']) ? round($b['distance'], 2) : 'N/A' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p>No businesses found within 10 miles of this ZIP code.</p>
  <?php endif; ?>
</body>
</html>

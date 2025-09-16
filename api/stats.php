<?php
require_once '../config/database.php'; // database connection

$pdo = getDatabase(); // connect to DB

// overall totals
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_activities,
        COALESCE(SUM(distance), 0) as total_distance,
        COALESCE(SUM(duration), 0) as total_duration,
        COALESCE(SUM(nights), 0) as total_nights
    FROM activities
");
$stats = $stmt->fetch();

// convert totals into correct types
$stats['total_distance'] = (float)$stats['total_distance'];
$stats['total_duration'] = (float)$stats['total_duration'];
$stats['total_nights']   = (int)$stats['total_nights'];

// totals per month (last 12 months)
$monthlyStmt = $pdo->query("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        COUNT(*) as count,
        COALESCE(SUM(distance), 0) as distance
    FROM activities
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month
");
$monthlyStats = $monthlyStmt->fetchAll();

// convert monthly numbers
foreach ($monthlyStats as &$month) {
    $month['distance'] = (float)$month['distance'];
    $month['count']    = (int)$month['count'];
}

$recentActivities = $recentStmt->fetchAll();

// convert distance + duration
foreach ($recentActivities as &$activity) {
    $activity['distance'] = (float)$activity['distance'];
    $activity['duration'] = (float)$activity['duration'];
}

// return combined stats
echo json_encode([
    'stats'   => $stats,
    'monthly' => $monthlyStats,
    'recent'  => $recentActivities
]);

<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDatabase();

if ($method === 'GET') {
    // Get all awarded achievements for the user
    $stmt = $pdo->query("
        SELECT 
            a.award_id,
            a.name,
            a.description,
            a.icon,
            a.type,
            a.value,
            aw.date_earned
        FROM awarded aw
        JOIN awards a ON aw.award_id = a.award_id
        WHERE aw.user_id = 1
        ORDER BY a.type, a.value DESC, aw.date_earned DESC
    ");
    
    $awards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the highest awards by type
    $highestAwards = [];
    $seenTypes = [];
    
    foreach ($awards as $award) {
        if (!in_array($award['type'], $seenTypes)) {
            $highestAwards[] = $award;
            $seenTypes[] = $award['type'];
        }
    }
    
    // Get current totals for progress tracking
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(distance), 0) as total_distance,
            COALESCE(SUM(nights), 0) as total_nights
        FROM activities
    ");
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get next available awards (not yet earned)
    $stmt = $pdo->query("
        SELECT 
            a.award_id,
            a.name,
            a.description,
            a.icon,
            a.type,
            a.value
        FROM awards a
        WHERE a.award_id NOT IN (
            SELECT award_id FROM awarded WHERE user_id = 1
        )
        ORDER BY a.type, a.value ASC
    ");
    $nextAwards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find the next award for each type
    $nextByType = [];
    $nextSeenTypes = [];
    
    foreach ($nextAwards as $award) {
        if (!in_array($award['type'], $nextSeenTypes)) {
            $nextByType[] = $award;
            $nextSeenTypes[] = $award['type'];
        }
    }
    
    echo json_encode([
        'highest_awards' => $highestAwards,
        'next_awards' => $nextByType,
        'totals' => [
            'distance' => (float)$totals['total_distance'],
            'nights' => (int)$totals['total_nights']
        ],
        'all_awarded' => $awards
    ]);
    
} else {
    echo json_encode(['error' => 'Method not supported']);
}
?>
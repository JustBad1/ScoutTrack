<?php
require_once 'config/database.php';


function processAwards($userId = 1) {
    $pdo = getDatabase();
    
    // Get current user totals
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(distance), 0) as total_distance,
            COALESCE(SUM(nights), 0) as total_nights
        FROM activities
    ");
    
    $totals = $stmt->fetch();
    
    $totalDistance = (float)$totals['total_distance'];
    $totalNights = (int)$totals['total_nights'];
    
    echo "User totals: {$totalDistance}km distance, {$totalNights} nights\n";
    
    // Get all awards and check which ones user should have
    $stmt = $pdo->query("SELECT * FROM awards ORDER BY type, value");
    $allAwards = $stmt->fetchAll();
    
    // Get awards user already has
    $stmt = $pdo->prepare("SELECT award_id FROM awarded WHERE user_id = ?");
    $stmt->execute([$userId]);
    $existingAwards = array_column($stmt->fetchAll(), 'award_id');
    
    $newAwards = 0;
    
    foreach ($allAwards as $award) {
        // Skip if user already has this award
        if (in_array($award['award_id'], $existingAwards)) {
            continue;
        }
        
        $qualified = false;
        
        // Check qualification based on award type
        
        if ($award['type'] === 'camping' && $totalNights >= $award['value']) {
            $qualified = true;
        } elseif ($award['type'] === 'walkabout' && $totalDistance >= $award['value']) {
            $qualified = true;
        }
        
        // Award if qualified
        if ($qualified) {
            $stmt = $pdo->prepare("
                INSERT INTO awarded (user_id, award_id, date_earned) 
                VALUES (?, ?, CURDATE())
            ");
            $stmt->execute([$userId, $award['award_id']]);
            
            echo "✓ Awarded: {$award['name']}\n";
            $newAwards++;
        }
    }
    
    if ($newAwards === 0) {
        echo "No new awards to grant.\n";
    } else {
        echo "Granted {$newAwards} new award(s).\n";
    }
    
    return $newAwards;
}

// Run the processor
echo "Processing awards...\n";
processAwards();
echo "Awards processing complete.\n";
?>
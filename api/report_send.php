<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer/src/SMTP.php';
require __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get database connection
    $pdo = getDatabase();
    
    // Query to get total distance and nights from activities
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(distance), 0) as total_distance,
            COALESCE(SUM(nights), 0) as total_nights,
            COUNT(*) as total_activities
        FROM activities
    ");
    $stats = $stmt->fetch();
    
    $totalDistance = (float)$stats['total_distance'];
    $totalNights   = (int)$stats['total_nights'];
    $totalActivities = (int)$stats['total_activities'];
    
    // Query to get all awards already awarded
    $stmtAwards = $pdo->query("
        SELECT a.name, a.description, a.icon, aw.date_earned
        FROM awarded aw
        JOIN awards a ON aw.award_id = a.award_id
        ORDER BY aw.date_earned DESC
    ");
    $awards = $stmtAwards->fetchAll();
    
    // Format awards for HTML
    $awardsHtml = "";
    if ($awards) {
        $awardsHtml .= "<h3 style='color:#2c6e49; margin-top:20px;'>Awards Earned</h3>";
        foreach ($awards as $award) {
            $dateEarned = date("j M Y", strtotime($award['date_earned']));
            $awardsHtml .= "
                <div style='padding:10px; background:#fff; border-radius:6px; 
                            border:1px solid #eee; margin-bottom:8px;'>
                    <b>{$award['name']}</b><br>
                    <span style='font-size:13px; color:#555;'>{$award['description']}</span><br>
                    <span style='font-size:12px; color:#999;'>Earned on {$dateEarned}</span>
                </div>
            ";
        }
    } else {
        $awardsHtml .= "<p style='font-size:14px; color:#555;'>No awards earned yet - keep exploring to start earning achievements!</p>";
    }
    
    // Initialise PHPMailer
    $mail = new PHPMailer(true);
    
    // SMTP config for Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'scouttrack.logbook@gmail.com';
    $mail->Password   = 'fevf bwek ogvw dulf';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    // From/To
    $mail->setFrom('scouttrack.logbook@gmail.com', 'ScoutTrack Logbook');
    $mail->addAddress('lrieger@concordia.sa.edu.au', 'Lincoln');
    
    // Message
    $currentDate = date('j F Y');
    $mail->isHTML(true);
    $mail->Subject = "Activity Summary Report - {$currentDate}";
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width:600px; margin:auto; padding:20px; 
                    background:#f9f9f9; border-radius:10px; border:1px solid #ddd; color:#333;'>
            
            <h2 style='color:#2c6e49; margin-bottom:10px;'>Adventure Summary Report</h2>
            <p style='font-size:15px; line-height:1.6; color:#666;'>
                Generated on {$currentDate} from your <b>ScoutTrack Logbook</b>
            </p>
            
            <h3 style='color:#2c6e49; margin-top:25px;'>Overall Statistics</h3>
            <div style='margin:15px 0;'>
                <div style='display:inline-block; width:48%; padding:15px; background:#fff; 
                            border-radius:8px; border:1px solid #eee; margin-right:2%; margin-bottom:10px;'>
                    <span style='font-size:16px;'><b>Total Activities:</b> {$totalActivities}</span>
                </div>
                <div style='display:inline-block; width:48%; padding:15px; background:#fff; 
                            border-radius:8px; border:1px solid #eee; margin-bottom:10px;'>
                    <span style='font-size:16px;'><b>Total Distance:</b> " . number_format($totalDistance, 1) . " km</span>
                </div>
                <div style='display:inline-block; width:48%; padding:15px; background:#fff; 
                            border-radius:8px; border:1px solid #eee; margin-right:2%;'>
                    <span style='font-size:16px;'><b>Nights Camped:</b> {$totalNights}</span>
                </div>
                <div style='display:inline-block; width:48%; padding:15px; background:#fff; 
                            border-radius:8px; border:1px solid #eee;'>
                    <span style='font-size:16px;'><b>Avg per Activity:</b> " . ($totalActivities > 0 ? number_format($totalDistance / $totalActivities, 1) : '0') . " km</span>
                </div>
            </div>
            
            {$awardsHtml}
            
            <div style='margin-top:30px; padding:15px; background:#e8f5e8; border-radius:8px; border:1px solid #c3e6c3;'>
                <p style='font-size:14px; color:#2c6e49; line-height:1.6; margin:0;'>
                    <b>Keep exploring!</b> Every adventure adds to your logbook and brings you closer to new achievements.
                </p>
            </div>
            
            <hr style='margin:25px 0; border:none; border-top:1px solid #ddd;'>
            <p style='font-size:13px; color:#888; text-align:center; margin:0;'>
                Automatically generated by <b>ScoutTrack Logbook</b><br>
                <span style='font-size:11px;'>This report contains your latest activity data as of {$currentDate}</span>
            </p>
        </div>
    ";
    
    // Send the email
    $mail->send();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Activity report sent successfully',
        'stats' => [
            'total_activities' => $totalActivities,
            'total_distance' => $totalDistance,
            'total_nights' => $totalNights
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Email send failed'
    ]);
}
?>
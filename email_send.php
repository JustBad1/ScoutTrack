<?php
require_once 'config/database.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    // Get database connection
    $pdo = getDatabase();
    
    // Query to get total distance and nights from activities
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(distance), 0) as total_distance,
            COALESCE(SUM(nights), 0) as total_nights
        FROM activities
    ");
    $stats = $stmt->fetch();
    
    $totalDistance = (float)$stats['total_distance'];
    $totalNights   = (int)$stats['total_nights'];
    
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
        $awardsHtml .= "<h3 style='color:#2c6e49;'>Awards Earned</h3>";
        foreach ($awards as $award) {
            $dateEarned = date("j M Y", strtotime($award['date_earned']));
            $awardsHtml .= "
                <div style='padding:10px; background:#fff; border-radius:6px; 
                            border:1px solid #eee; margin-bottom:8px;'>
                    <i class='{$award['icon']}' style='margin-right:6px; color:#2c6e49;'></i>
                    <b>{$award['name']}</b><br>
                    <span style='font-size:13px; color:#555;'>{$award['description']}</span><br>
                    <span style='font-size:12px; color:#999;'>Earned on {$dateEarned}</span>
                </div>
            ";
        }
    } else {
        $awardsHtml .= "<p style='font-size:14px; color:#555;'>No awards earned yet — keep going!</p>";
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
    $mail->isHTML(true);
    $mail->Subject = 'Your Activity Summary';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width:600px; margin:auto; padding:20px; 
                    background:#f9f9f9; border-radius:10px; border:1px solid #ddd; color:#333;'>
            
            <h2 style='color:#2c6e49; margin-bottom:10px;'>Hey there,</h2>
            <p style='font-size:15px; line-height:1.6;'>
                Here is your latest adventure summary from <b>ScoutTrack Logbook</b>:
            </p>
            
            <div style='margin:20px 0;'>
                <div style='padding:15px; background:#fff; border-radius:8px; border:1px solid #eee; 
                            margin-bottom:10px;'>
                    <span style='font-size:16px;'><b>Total Distance:</b> {$totalDistance} km</span>
                </div>
                <div style='padding:15px; background:#fff; border-radius:8px; border:1px solid #eee;'>
                    <span style='font-size:16px;'><b>Nights Camped:</b> {$totalNights}</span>
                </div>
            </div>
            
            {$awardsHtml}
            
            <p style='font-size:14px; color:#555; line-height:1.6;'>
                Keep up the great work and keep exploring - every step and every night adds to your journey
            </p>
            
            <hr style='margin:25px 0; border:none; border-top:1px solid #ddd;'>
            <p style='font-size:13px; color:#888; text-align:center;'>
                Sent with care by <b>ScoutTrack Logbook</b>
            </p>
        </div>
    ";
    
    $mail->send();
    echo "Message sent";
    
} catch (Exception $e) {
    echo "Send failed. Error: {$mail->ErrorInfo}";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>

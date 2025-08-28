<?php

// Get totals and email them
$totals = getActivityTotals();

if ($totals) {
    $message = "Scout Track Activity Summary:\n\n";
    $message .= "Total Activities: " . $totals['total_activities'] . "\n";
    $message .= "Total Distance: " . $totals['total_distance'] . " km\n";
    $message .= "Total Duration: " . $totals['total_duration'] . " hours\n";
    $message .= "Total Nights: " . $totals['total_nights'] . "\n";
    
    if (sendEmail('Activity Totals Report', $message)) {
        echo 'Activity totals emailed successfully!';
    } else {
        echo 'Failed to send email.';
    }
} else {
    echo 'Failed to get activity totals.';
}
?>
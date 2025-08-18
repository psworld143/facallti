<?php
/**
 * Auto-disable Expired Evaluations Script
 *
 * This script should be run periodically (e.g., via cron job) to automatically
 * disable evaluations that have reached their end time.
 *
 * Usage:
 * - Run manually: php auto-disable-expired-evaluations.php
 * - Set up cron job: 0/5 * * * * php /path/to/auto-disable-expired-evaluations.php
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

echo "=== Auto-disable Expired Evaluations Script ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Find all active evaluation schedules that have passed their end date
    $expired_schedules_query = "SELECT es.*, mec.name as category_name
                                FROM evaluation_schedules es
                                JOIN main_evaluation_categories mec ON es.evaluation_type = mec.evaluation_type
                                WHERE es.status = 'active'
                                AND es.end_date < NOW()";

    $expired_schedules_result = mysqli_query($conn, $expired_schedules_query);

    if (!$expired_schedules_result) {
        throw new Exception("Database error: " . mysqli_error($conn));
    }

    $expired_count = mysqli_num_rows($expired_schedules_result);

    if ($expired_count == 0) {
        echo "No expired evaluations found.\n";
    } else {
        echo "Found {$expired_count} expired evaluation(s) to disable:\n\n";

        // Start transaction
        mysqli_begin_transaction($conn);

        $disabled_count = 0;
        $errors = [];

        while ($schedule = mysqli_fetch_assoc($expired_schedules_result)) {
            echo "- {$schedule['category_name']} (ID: {$schedule['id']}) - Expired at: {$schedule['end_date']}\n";

            // Update schedule status to completed
            $update_schedule = "UPDATE evaluation_schedules
                               SET status = 'completed', updated_at = NOW()
                               WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_schedule);
            mysqli_stmt_bind_param($update_stmt, "i", $schedule['id']);

            if (mysqli_stmt_execute($update_stmt)) {
                $disabled_count++;
                echo "  ✓ Disabled successfully\n";
            } else {
                $errors[] = "Failed to disable schedule ID {$schedule['id']}: " . mysqli_error($conn);
                echo "  ✗ Failed to disable\n";
            }
        }

        // Commit transaction
        mysqli_commit($conn);

        echo "\n=== Summary ===\n";
        echo "Total expired evaluations found: {$expired_count}\n";
        echo "Successfully disabled: {$disabled_count}\n";

        if (!empty($errors)) {
            echo "Errors encountered:\n";
            foreach ($errors as $error) {
                echo "- {$error}\n";
            }
        }

        // Log the action
        $log_message = "Auto-disabled {$disabled_count} expired evaluation(s) at " . date('Y-m-d H:i:s');
        error_log("[EVALUATION_SYSTEM] {$log_message}");
    }

    // Also check for evaluations that are about to expire (within 1 hour) and log them
    $expiring_soon_query = "SELECT es.*, mec.name as category_name
                           FROM evaluation_schedules es
                           JOIN main_evaluation_categories mec ON es.evaluation_type = mec.evaluation_type
                           WHERE es.status = 'active'
                           AND es.end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)";

    $expiring_soon_result = mysqli_query($conn, $expiring_soon_query);

    if ($expiring_soon_result && mysqli_num_rows($expiring_soon_result) > 0) {
        echo "\n=== Evaluations Expiring Soon (within 1 hour) ===\n";
        while ($schedule = mysqli_fetch_assoc($expiring_soon_result)) {
            $time_until_expiry = strtotime($schedule['end_date']) - time();
            $hours = floor($time_until_expiry / 3600);
            $minutes = floor(($time_until_expiry % 3600) / 60);

            echo "- {$schedule['category_name']} expires in {$hours}h {$minutes}m\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";

    // Rollback transaction if it was started
    if (mysqli_ping($conn)) {
        mysqli_rollback($conn);
    }

    // Log the error
    error_log("[EVALUATION_SYSTEM] Error in auto-disable script: " . $e->getMessage());
}

echo "\nScript completed at: " . date('Y-m-d H:i:s') . "\n";
echo "==========================================\n";
?>
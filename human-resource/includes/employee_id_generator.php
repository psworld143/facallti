<?php
/**
 * Employee ID Generator
 * Generates employee IDs in format: YYYY-XXXX
 * Where YYYY is the current year and XXXX is a 4-digit series number
 * Checks both faculty and employees tables to ensure uniqueness
 */

function generateEmployeeID($conn) {
    $current_year = date('Y');
    
    // Get all employee IDs from both tables for the current year
    // Handle NULL values and ensure proper filtering
    // Fix collation issue by explicitly specifying collation
    $query = "SELECT qrcode COLLATE utf8mb4_unicode_ci as id FROM faculty 
              WHERE qrcode IS NOT NULL AND qrcode LIKE ? 
              UNION ALL 
              SELECT employee_id COLLATE utf8mb4_unicode_ci as id FROM employees 
              WHERE employee_id IS NOT NULL AND employee_id LIKE ?";
    
    $year_pattern = $current_year . '-%';
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $year_pattern, $year_pattern);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Database execute error: ' . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception('Database result error: ' . mysqli_error($conn));
    }
    
    $max_series = 0;
    
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $employee_id = $row['id'];
            
            // Validate the format of the employee ID
            if (preg_match('/^\d{4}-\d{4}$/', $employee_id)) {
                // Extract the series number and increment it
                $parts = explode('-', $employee_id);
                $series = (int)$parts[1];
                
                if ($series > $max_series) {
                    $max_series = $series;
                }
            }
        }
    }
    
    $new_series = $max_series + 1;
    
    // Format the series number to 4 digits
    $formatted_series = str_pad($new_series, 4, '0', STR_PAD_LEFT);
    
    // Check if we've reached the maximum (9999)
    if ($new_series > 9999) {
        throw new Exception("Maximum employee ID series reached for year $current_year. Please contact administrator.");
    }
    
    $employee_id = $current_year . '-' . $formatted_series;
    
    // Final validation of the generated ID
    if (!preg_match('/^\d{4}-\d{4}$/', $employee_id)) {
        throw new Exception('Generated employee ID has invalid format: ' . $employee_id);
    }
    
    return $employee_id;
}

function validateEmployeeID($employee_id) {
    // Check if format is correct: YYYY-XXXX
    if (!preg_match('/^\d{4}-\d{4}$/', $employee_id)) {
        return false;
    }
    
    $parts = explode('-', $employee_id);
    $year = (int)$parts[0];
    $series = (int)$parts[1];
    
    // Validate year (reasonable range: 2000-2030)
    if ($year < 2000 || $year > 2030) {
        return false;
    }
    
    // Validate series (1-9999)
    if ($series < 1 || $series > 9999) {
        return false;
    }
    
    return true;
}

function isEmployeeIDUnique($conn, $employee_id, $exclude_faculty_id = null) {
    // Check both faculty and employees tables for uniqueness
    $query = "SELECT COUNT(*) as count FROM (
                SELECT qrcode COLLATE utf8mb4_unicode_ci as id FROM faculty WHERE qrcode = ?
                UNION ALL
                SELECT employee_id COLLATE utf8mb4_unicode_ci as id FROM employees WHERE employee_id = ?
              ) as combined";
    $params = [$employee_id, $employee_id];
    $param_types = "ss";
    
    if ($exclude_faculty_id) {
        // If excluding a faculty ID, we need to modify the query
        $query = "SELECT COUNT(*) as count FROM (
                    SELECT qrcode COLLATE utf8mb4_unicode_ci as id FROM faculty WHERE qrcode = ? AND id != ?
                    UNION ALL
                    SELECT employee_id COLLATE utf8mb4_unicode_ci as id FROM employees WHERE employee_id = ?
                  ) as combined";
        $params = [$employee_id, $exclude_faculty_id, $employee_id];
        $param_types = "sis";
    }
    
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Database execute error: ' . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception('Database result error: ' . mysqli_error($conn));
    }
    
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'] == 0;
}

function getNextEmployeeID($conn) {
    try {
        return generateEmployeeID($conn);
    } catch (Exception $e) {
        error_log("Error in getNextEmployeeID: " . $e->getMessage());
        return null;
    }
}
?>

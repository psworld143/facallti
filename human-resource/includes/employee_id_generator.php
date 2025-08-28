<?php
/**
 * Employee ID Generator
 * Generates employee IDs in format: YYYY-XXXX
 * Where YYYY is the current year and XXXX is a 4-digit series number
 */

function generateEmployeeID($conn) {
    $current_year = date('Y');
    
    // Get the highest series number for the current year
    $query = "SELECT employee_id FROM faculty_details 
              WHERE employee_id LIKE ? 
              ORDER BY CAST(SUBSTRING_INDEX(employee_id, '-', -1) AS UNSIGNED) DESC 
              LIMIT 1";
    
    $year_pattern = $current_year . '-%';
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $year_pattern);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_employee_id = $row['employee_id'];
        
        // Extract the series number and increment it
        $parts = explode('-', $last_employee_id);
        $last_series = (int)$parts[1];
        $new_series = $last_series + 1;
    } else {
        // No employee IDs for this year yet, start with 0001
        $new_series = 1;
    }
    
    // Format the series number to 4 digits
    $formatted_series = str_pad($new_series, 4, '0', STR_PAD_LEFT);
    
    // Check if we've reached the maximum (9999)
    if ($new_series > 9999) {
        throw new Exception("Maximum employee ID series reached for year $current_year. Please contact administrator.");
    }
    
    return $current_year . '-' . $formatted_series;
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
    $query = "SELECT COUNT(*) as count FROM faculty_details WHERE employee_id = ?";
    $params = [$employee_id];
    $param_types = "s";
    
    if ($exclude_faculty_id) {
        $query .= " AND faculty_id != ?";
        $params[] = $exclude_faculty_id;
        $param_types .= "i";
    }
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'] == 0;
}

function getNextEmployeeID($conn) {
    try {
        return generateEmployeeID($conn);
    } catch (Exception $e) {
        return null;
    }
}
?>

<?php
// Services CRUD Operations
// This file contains all database operations for services, separated from UI

require_once '../config/database.php';

// ==================== SERVICE CATEGORIES CRUD ====================

/**
 * Get all active service categories
 */
function get_service_categories($conn) {
    $query = "SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        error_log("Error fetching service categories: " . mysqli_error($conn));
        return [];
    }

    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }

    return $categories;
}

/**
 * Get service category by ID
 */
function get_service_category_by_id($conn, $category_id) {
    $query = "SELECT * FROM service_categories WHERE id = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

/**
 * Add new service category
 */
function add_service_category($conn, $name, $description, $icon, $color_theme, $sort_order = 0) {
    $query = "INSERT INTO service_categories (name, description, icon, color_theme, sort_order) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssi", $name, $description, $icon, $color_theme, $sort_order);

    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($conn);
    } else {
        error_log("Error adding service category: " . mysqli_error($conn));
        return false;
    }
}

/**
 * Update service category
 */
function update_service_category($conn, $category_id, $name, $description, $icon, $color_theme, $sort_order = 0) {
    $query = "UPDATE service_categories SET name = ?, description = ?, icon = ?, color_theme = ?, sort_order = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssii", $name, $description, $icon, $color_theme, $sort_order, $category_id);

    return mysqli_stmt_execute($stmt);
}

/**
 * Delete service category (soft delete)
 */
function delete_service_category($conn, $category_id) {
    $query = "UPDATE service_categories SET is_active = 0 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $category_id);

    return mysqli_stmt_execute($stmt);
}

// ==================== SERVICES CRUD ====================

/**
 * Get all active services with category information
 */
function get_services($conn) {
    $query = "SELECT s.*, sc.name as category_name, sc.color_theme as category_color
              FROM services s
              LEFT JOIN service_categories sc ON s.category_id = sc.id
              WHERE s.is_active = 1
              ORDER BY s.sort_order ASC, s.name ASC";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        error_log("Error fetching services: " . mysqli_error($conn));
        return [];
    }

    $services = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $services[] = $row;
    }

    return $services;
}

/**
 * Get services by category
 */
function get_services_by_category($conn, $category_id) {
    $query = "SELECT s.*, sc.name as category_name, sc.color_theme as category_color
              FROM services s
              LEFT JOIN service_categories sc ON s.category_id = sc.id
              WHERE s.is_active = 1 AND s.category_id = ?
              ORDER BY s.sort_order ASC, s.name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $services = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $services[] = $row;
    }

    return $services;
}

/**
 * Get service by ID with category information
 */
function get_service_by_id($conn, $service_id) {
    $query = "SELECT s.*, sc.name as category_name, sc.color_theme as category_color
              FROM services s
              LEFT JOIN service_categories sc ON s.category_id = sc.id
              WHERE s.id = ? AND s.is_active = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $service_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

/**
 * Add new service
 */
function add_service($conn, $name, $description, $icon, $color_theme, $category_id, $sort_order = 0) {
    $query = "INSERT INTO services (name, description, icon, color_theme, category_id, sort_order) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssii", $name, $description, $icon, $color_theme, $category_id, $sort_order);

    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($conn);
    } else {
        error_log("Error adding service: " . mysqli_error($conn));
        return false;
    }
}

/**
 * Update service
 */
function update_service($conn, $service_id, $name, $description, $icon, $color_theme, $category_id, $sort_order = 0) {
    $query = "UPDATE services SET name = ?, description = ?, icon = ?, color_theme = ?, category_id = ?, sort_order = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ssssiii", $name, $description, $icon, $color_theme, $category_id, $sort_order, $service_id);

    return mysqli_stmt_execute($stmt);
}

/**
 * Delete service (soft delete)
 */
function delete_service($conn, $service_id) {
    $query = "UPDATE services SET is_active = 0 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $service_id);

    return mysqli_stmt_execute($stmt);
}

// ==================== SERVICE DETAILS CRUD ====================

/**
 * Get service details by service ID
 */
function get_service_details($conn, $service_id) {
    $query = "SELECT * FROM service_details WHERE service_id = ? AND is_active = 1 ORDER BY sort_order ASC, title ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $service_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $details = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $details[] = $row;
    }

    return $details;
}

/**
 * Get service detail by ID
 */
function get_service_detail_by_id($conn, $detail_id) {
    $query = "SELECT * FROM service_details WHERE id = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $detail_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}

/**
 * Add new service detail
 */
function add_service_detail($conn, $service_id, $title, $content, $icon, $sort_order = 0) {
    $query = "INSERT INTO service_details (service_id, title, content, icon, sort_order) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isssi", $service_id, $title, $content, $icon, $sort_order);

    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($conn);
    } else {
        error_log("Error adding service detail: " . mysqli_error($conn));
        return false;
    }
}

/**
 * Update service detail
 */
function update_service_detail($conn, $detail_id, $title, $content, $icon, $sort_order = 0) {
    $query = "UPDATE service_details SET title = ?, content = ?, icon = ?, sort_order = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssii", $title, $content, $icon, $sort_order, $detail_id);

    return mysqli_stmt_execute($stmt);
}

/**
 * Delete service detail (soft delete)
 */
function delete_service_detail($conn, $detail_id) {
    $query = "UPDATE service_details SET is_active = 0 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $detail_id);

    return mysqli_stmt_execute($stmt);
}

// ==================== UTILITY FUNCTIONS ====================

/**
 * Get services statistics
 */
function get_services_statistics($conn) {
    $stats = [];

    // Total services
    $query = "SELECT COUNT(*) as total FROM services WHERE is_active = 1";
    $result = mysqli_query($conn, $query);
    $stats['total_services'] = mysqli_fetch_assoc($result)['total'];

    // Total categories
    $query = "SELECT COUNT(*) as total FROM service_categories WHERE is_active = 1";
    $result = mysqli_query($conn, $query);
    $stats['total_categories'] = mysqli_fetch_assoc($result)['total'];

    // Services by category
    $query = "SELECT sc.name, COUNT(s.id) as service_count
              FROM service_categories sc
              LEFT JOIN services s ON sc.id = s.category_id AND s.is_active = 1
              WHERE sc.is_active = 1
              GROUP BY sc.id, sc.name
              ORDER BY sc.sort_order ASC";
    $result = mysqli_query($conn, $query);

    $stats['services_by_category'] = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $stats['services_by_category'][] = $row;
    }

    return $stats;
}

/**
 * Search services
 */
function search_services($conn, $search_term) {
    $search_term = "%$search_term%";
    $query = "SELECT s.*, sc.name as category_name, sc.color_theme as category_color
              FROM services s
              LEFT JOIN service_categories sc ON s.category_id = sc.id
              WHERE s.is_active = 1 AND (s.name LIKE ? OR s.description LIKE ? OR sc.name LIKE ?)
              ORDER BY s.sort_order ASC, s.name ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sss", $search_term, $search_term, $search_term);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $services = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $services[] = $row;
    }

    return $services;
}

/**
 * Validate service data
 */
function validate_service_data($name, $description, $category_id) {
    $errors = [];

    if (empty($name)) {
        $errors[] = "Service name is required";
    }

    if (empty($description)) {
        $errors[] = "Service description is required";
    }

    if (empty($category_id)) {
        $errors[] = "Service category is required";
    }

    return $errors;
}

/**
 * Validate service category data
 */
function validate_service_category_data($name, $description) {
    $errors = [];

    if (empty($name)) {
        $errors[] = "Category name is required";
    }

    if (empty($description)) {
        $errors[] = "Category description is required";
    }

    return $errors;
}
?>
<?php
/**
 * IntelliEVal System - Text Questionnaire Seeding Script
 *
 * This script seeds comprehensive text type questionnaires for each evaluation sub-category
 * in the Student to Teacher evaluation system.
 *
 * Usage: Run this script from the command line or web browser
 */

// Include database configuration
require_once '../config/database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to execute SQL file
function executeSqlFile($pdo, $filename) {
    echo "Executing: $filename\n";

    if (!file_exists($filename)) {
        echo "ERROR: File not found: $filename\n";
        return false;
    }

    $sql = file_get_contents($filename);

    try {
        // Split SQL by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(--|\/\*|#)/', trim($statement))) {
                $pdo->exec($statement);
            }
        }

        echo "SUCCESS: $filename executed successfully\n";
        return true;

    } catch (PDOException $e) {
        echo "ERROR executing $filename: " . $e->getMessage() . "\n";
        return false;
    }
}

// Function to verify seeding results
function verifySeeding($pdo) {
    echo "\n=== VERIFYING SEEDING RESULTS ===\n";

    try {
        // Count questions by type for each sub-category
        $query = "
            SELECT
                esc.name as sub_category_name,
                COUNT(CASE WHEN eq.question_type = 'text' THEN 1 END) as text_questions,
                COUNT(*) as total_questions,
                ROUND((COUNT(CASE WHEN eq.question_type = 'text' THEN 1 END) / COUNT(*)) * 100, 1) as text_percentage
            FROM evaluation_sub_categories esc
            LEFT JOIN evaluation_questionnaires eq ON esc.id = eq.sub_category_id
            WHERE esc.main_category_id = 1
            GROUP BY esc.id, esc.name
            ORDER BY esc.order_number
        ";

        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "\nQuestion Count by Sub-Category:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-25s %-15s %-15s %-15s\n", "Sub-Category", "Text Questions", "Total Questions", "Text %");
        echo str_repeat("-", 80) . "\n";

        $totalTextQuestions = 0;
        $totalQuestions = 0;

        foreach ($results as $row) {
            printf("%-25s %-15s %-15s %-15s\n",
                $row['sub_category_name'],
                $row['text_questions'],
                $row['total_questions'],
                $row['text_percentage'] . '%'
            );
            $totalTextQuestions += $row['text_questions'];
            $totalQuestions += $row['total_questions'];
        }

        echo str_repeat("-", 80) . "\n";
        printf("%-25s %-15s %-15s %-15s\n",
            "TOTAL",
            $totalTextQuestions,
            $totalQuestions,
            round(($totalTextQuestions / $totalQuestions) * 100, 1) . '%'
        );

        // Check for duplicates
        $duplicateQuery = "
            SELECT
                sub_category_id,
                question_text,
                COUNT(*) as duplicate_count
            FROM evaluation_questionnaires
            WHERE question_type = 'text'
            GROUP BY sub_category_id, question_text
            HAVING COUNT(*) > 1
        ";

        $duplicateStmt = $pdo->query($duplicateQuery);
        $duplicates = $duplicateStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($duplicates)) {
            echo "\n✓ No duplicate text questions found\n";
        } else {
            echo "\n⚠ WARNING: Found " . count($duplicates) . " duplicate text questions:\n";
            foreach ($duplicates as $duplicate) {
                echo "  - Sub-category ID: " . $duplicate['sub_category_id'] . "\n";
                echo "    Question: " . substr($duplicate['question_text'], 0, 50) . "...\n";
            }
        }

        return true;

    } catch (PDOException $e) {
        echo "ERROR during verification: " . $e->getMessage() . "\n";
        return false;
    }
}

// Main execution
echo "IntelliEVal System - Text Questionnaire Seeding\n";
echo "================================================\n\n";

try {
    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    echo "✓ Database connection established\n\n";

    // Check if required tables exist
    $requiredTables = ['main_evaluation_categories', 'evaluation_sub_categories', 'evaluation_questionnaires'];
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            echo "ERROR: Required table '$table' does not exist\n";
            echo "Please run the hierarchical schema setup first\n";
            exit(1);
        }
    }
    echo "✓ All required tables exist\n\n";

    // Execute seeding scripts
    $scripts = [
        'database/complete_text_questionnaires.sql'
    ];

    $success = true;
    foreach ($scripts as $script) {
        if (!executeSqlFile($pdo, $script)) {
            $success = false;
        }
    }

    if ($success) {
        echo "\n✓ All seeding scripts executed successfully\n";

        // Verify results
        verifySeeding($pdo);

        echo "\n🎉 Text questionnaire seeding completed successfully!\n";
        echo "\nSummary:\n";
        echo "- Added comprehensive text questions to all 5 sub-categories\n";
        echo "- Questions are designed for detailed student feedback\n";
        echo "- All questions are optional to encourage honest responses\n";
        echo "- Duplicate prevention ensures clean data\n";

    } else {
        echo "\n❌ Some seeding scripts failed. Please check the errors above.\n";
        exit(1);
    }

} catch (PDOException $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone.\n";
?>
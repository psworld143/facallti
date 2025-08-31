<?php
require_once '../config/database.php';

echo "<h1>Populating Faculty Details</h1>";

// Get faculty members without details
$query = "SELECT f.id, f.first_name, f.last_name FROM faculty f 
          LEFT JOIN faculty_details fd ON f.id = fd.faculty_id 
          WHERE fd.faculty_id IS NULL";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo "Error: " . mysqli_error($conn);
    exit();
}

$faculty_members = [];
while ($row = mysqli_fetch_assoc($result)) {
    $faculty_members[] = $row;
}

echo "<p>Found " . count($faculty_members) . " faculty members without details.</p>";

// Sample data for different faculty members
$sample_data = [
    [
        'middle_name' => 'Paul',
        'date_of_birth' => '1985-03-15',
        'gender' => 'Male',
        'civil_status' => 'Married',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'phone' => '+63 912 345 6789',
        'emergency_contact_name' => 'Maria Sebando',
        'emergency_contact_number' => '+63 923 456 7890',
        'address' => '123 Main Street, Quezon City, Metro Manila',
        'employee_id' => '2024-0001',
        'date_of_hire' => '2020-01-15',
        'employment_type' => 'Full-time',
        'basic_salary' => 45000.00,
        'salary_grade' => 'SG-15',
        'allowances' => 5000.00,
        'pay_schedule' => 'Monthly',
        'highest_education' => 'Master\'s Degree',
        'field_of_study' => 'Information Technology',
        'school_university' => 'University of the Philippines',
        'year_graduated' => 2015,
        'tin_number' => '123-456-789-000',
        'sss_number' => '34-5678901-2',
        'philhealth_number' => '1234-5678-9012',
        'pagibig_number' => '1234-5678-9012'
    ],
    [
        'middle_name' => 'William',
        'date_of_birth' => '1980-07-22',
        'gender' => 'Male',
        'civil_status' => 'Married',
        'nationality' => 'Filipino',
        'religion' => 'Protestant',
        'phone' => '+63 934 567 8901',
        'emergency_contact_name' => 'Jennifer Johnson',
        'emergency_contact_number' => '+63 945 678 9012',
        'address' => '456 Oak Avenue, Makati City, Metro Manila',
        'employee_id' => '2024-0002',
        'date_of_hire' => '2019-06-01',
        'employment_type' => 'Full-time',
        'basic_salary' => 52000.00,
        'salary_grade' => 'SG-16',
        'allowances' => 6000.00,
        'pay_schedule' => 'Monthly',
        'highest_education' => 'Doctorate',
        'field_of_study' => 'Computer Science',
        'school_university' => 'Ateneo de Manila University',
        'year_graduated' => 2018,
        'tin_number' => '234-567-890-000',
        'sss_number' => '45-6789012-3',
        'philhealth_number' => '2345-6789-0123',
        'pagibig_number' => '2345-6789-0123'
    ],
    [
        'middle_name' => 'Maria',
        'date_of_birth' => '1988-11-08',
        'gender' => 'Female',
        'civil_status' => 'Single',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'phone' => '+63 956 789 0123',
        'emergency_contact_name' => 'Carlos Martinez',
        'emergency_contact_number' => '+63 967 890 1234',
        'address' => '789 Pine Street, Taguig City, Metro Manila',
        'employee_id' => '2024-0003',
        'date_of_hire' => '2021-03-10',
        'employment_type' => 'Full-time',
        'basic_salary' => 38000.00,
        'salary_grade' => 'SG-14',
        'allowances' => 4000.00,
        'pay_schedule' => 'Monthly',
        'highest_education' => 'Bachelor\'s Degree',
        'field_of_study' => 'Business Administration',
        'school_university' => 'De La Salle University',
        'year_graduated' => 2012,
        'tin_number' => '345-678-901-000',
        'sss_number' => '56-7890123-4',
        'philhealth_number' => '3456-7890-1234',
        'pagibig_number' => '3456-7890-1234'
    ],
    [
        'middle_name' => 'James',
        'date_of_birth' => '1982-04-30',
        'gender' => 'Male',
        'civil_status' => 'Married',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'phone' => '+63 978 901 2345',
        'emergency_contact_name' => 'Sarah Brown',
        'emergency_contact_number' => '+63 989 012 3456',
        'address' => '321 Elm Street, Pasig City, Metro Manila',
        'employee_id' => '2024-0004',
        'date_of_hire' => '2018-09-15',
        'employment_type' => 'Full-time',
        'basic_salary' => 48000.00,
        'salary_grade' => 'SG-15',
        'allowances' => 5500.00,
        'pay_schedule' => 'Monthly',
        'highest_education' => 'Master\'s Degree',
        'field_of_study' => 'Engineering',
        'school_university' => 'University of Santo Tomas',
        'year_graduated' => 2010,
        'tin_number' => '456-789-012-000',
        'sss_number' => '67-8901234-5',
        'philhealth_number' => '4567-8901-2345',
        'pagibig_number' => '4567-8901-2345'
    ],
    [
        'middle_name' => 'Ann',
        'date_of_birth' => '1987-12-14',
        'gender' => 'Female',
        'civil_status' => 'Married',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'phone' => '+63 990 123 4567',
        'emergency_contact_name' => 'John Davis',
        'emergency_contact_number' => '+63 901 234 5678',
        'address' => '654 Maple Drive, Mandaluyong City, Metro Manila',
        'employee_id' => '2024-0005',
        'date_of_hire' => '2020-08-20',
        'employment_type' => 'Full-time',
        'basic_salary' => 42000.00,
        'salary_grade' => 'SG-14',
        'allowances' => 4500.00,
        'pay_schedule' => 'Monthly',
        'highest_education' => 'Bachelor\'s Degree',
        'field_of_study' => 'Education',
        'school_university' => 'Philippine Normal University',
        'year_graduated' => 2011,
        'tin_number' => '567-890-123-000',
        'sss_number' => '78-9012345-6',
        'philhealth_number' => '5678-9012-3456',
        'pagibig_number' => '5678-9012-3456'
    ],
    [
        'middle_name' => 'Grace',
        'date_of_birth' => '1986-05-18',
        'gender' => 'Female',
        'civil_status' => 'Single',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'phone' => '+63 912 345 6789',
        'emergency_contact_name' => 'Robert Anderson',
        'emergency_contact_number' => '+63 923 456 7890',
        'address' => '987 Cedar Lane, San Juan City, Metro Manila',
        'employee_id' => '2024-0006',
        'date_of_hire' => '2021-01-10',
        'employment_type' => 'Full-time',
        'basic_salary' => 40000.00,
        'salary_grade' => 'SG-14',
        'allowances' => 4200.00,
        'pay_schedule' => 'Monthly',
        'highest_education' => 'Master\'s Degree',
        'field_of_study' => 'Information Technology',
        'school_university' => 'Mapua University',
        'year_graduated' => 2013,
        'tin_number' => '678-901-234-000',
        'sss_number' => '89-0123456-7',
        'philhealth_number' => '6789-0123-4567',
        'pagibig_number' => '6789-0123-4567'
    ],
    [
        'middle_name' => 'Joy',
        'date_of_birth' => '1989-08-25',
        'gender' => 'Female',
        'civil_status' => 'Single',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'phone' => '+63 934 567 8901',
        'emergency_contact_name' => 'Manuel Fernandez',
        'emergency_contact_number' => '+63 945 678 9012',
        'address' => '147 Birch Road, Marikina City, Metro Manila',
        'employee_id' => '2024-0007',
        'date_of_hire' => '2022-02-15',
        'employment_type' => 'Full-time',
        'basic_salary' => 35000.00,
        'salary_grade' => 'SG-13',
        'allowances' => 3800.00,
        'pay_schedule' => 'Monthly',
        'highest_education' => 'Bachelor\'s Degree',
        'field_of_study' => 'Computer Science',
        'school_university' => 'FEU Institute of Technology',
        'year_graduated' => 2014,
        'tin_number' => '789-012-345-000',
        'sss_number' => '90-1234567-8',
        'philhealth_number' => '7890-1234-5678',
        'pagibig_number' => '7890-1234-5678'
    ],
    [
        'middle_name' => 'Rose',
        'date_of_birth' => '1984-01-12',
        'gender' => 'Female',
        'civil_status' => 'Married',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'phone' => '+63 956 789 0123',
        'emergency_contact_name' => 'Michael Thomas',
        'emergency_contact_number' => '+63 967 890 1234',
        'address' => '258 Spruce Street, Caloocan City, Metro Manila',
        'employee_id' => '2024-0008',
        'date_of_hire' => '2019-11-01',
        'employment_type' => 'Full-time',
        'basic_salary' => 46000.00,
        'salary_grade' => 'SG-15',
        'allowances' => 5200.00,
        'pay_schedule' => 'Monthly',
        'highest_education' => 'Master\'s Degree',
        'field_of_study' => 'Business Administration',
        'school_university' => 'University of Asia and the Pacific',
        'year_graduated' => 2016,
        'tin_number' => '890-123-456-000',
        'sss_number' => '01-2345678-9',
        'philhealth_number' => '8901-2345-6789',
        'pagibig_number' => '8901-2345-6789'
    ]
];

// Insert details for each faculty member
$success_count = 0;
$error_count = 0;

foreach ($faculty_members as $index => $faculty) {
    if ($index >= count($sample_data)) {
        break; // Stop if we run out of sample data
    }
    
    $data = $sample_data[$index];
    
    $insert_query = "INSERT INTO faculty_details (
        faculty_id, middle_name, date_of_birth, gender, civil_status, nationality, religion,
        phone, emergency_contact_name, emergency_contact_number, address,
        employee_id, date_of_hire, employment_type, basic_salary, salary_grade, allowances, pay_schedule,
        highest_education, field_of_study, school_university, year_graduated,
        tin_number, sss_number, philhealth_number, pagibig_number, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "isssssssssssssdsisssssssssssssss", 
        $faculty['id'],
        $data['middle_name'],
        $data['date_of_birth'],
        $data['gender'],
        $data['civil_status'],
        $data['nationality'],
        $data['religion'],
        $data['phone'],
        $data['emergency_contact_name'],
        $data['emergency_contact_number'],
        $data['address'],
        $data['employee_id'],
        $data['date_of_hire'],
        $data['employment_type'],
        $data['basic_salary'],
        $data['salary_grade'],
        $data['allowances'],
        $data['pay_schedule'],
        $data['highest_education'],
        $data['field_of_study'],
        $data['school_university'],
        $data['year_graduated'],
        $data['tin_number'],
        $data['sss_number'],
        $data['philhealth_number'],
        $data['pagibig_number']
    );
    
    if (mysqli_stmt_execute($stmt)) {
        echo "<p style='color: green;'>✅ Added details for {$faculty['first_name']} {$faculty['last_name']} (ID: {$faculty['id']})</p>";
        $success_count++;
    } else {
        echo "<p style='color: red;'>❌ Error adding details for {$faculty['first_name']} {$faculty['last_name']}: " . mysqli_error($conn) . "</p>";
        $error_count++;
    }
}

echo "<h2>Summary</h2>";
echo "<p>Successfully added details for: <strong>{$success_count}</strong> faculty members</p>";
echo "<p>Errors encountered: <strong>{$error_count}</strong></p>";

// Verify the results
$verify_query = "SELECT COUNT(*) as total_faculty FROM faculty";
$verify_result = mysqli_query($conn, $verify_query);
$total_faculty = mysqli_fetch_assoc($verify_result)['total_faculty'];

$details_query = "SELECT COUNT(*) as total_details FROM faculty_details";
$details_result = mysqli_query($conn, $details_query);
$total_details = mysqli_fetch_assoc($details_result)['total_details'];

echo "<h2>Database Status</h2>";
echo "<p>Total faculty members: <strong>{$total_faculty}</strong></p>";
echo "<p>Total faculty with details: <strong>{$total_details}</strong></p>";
echo "<p>Faculty without details: <strong>" . ($total_faculty - $total_details) . "</strong></p>";

if ($total_faculty - $total_details == 0) {
    echo "<p style='color: green; font-weight: bold;'>🎉 All faculty members now have complete details!</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Some faculty members still need details.</p>";
}
?>

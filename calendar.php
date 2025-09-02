<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/id_encryption.php';

// Get events
$events_query = "SELECT * FROM posts WHERE status = 'approved' AND type = 'event' ORDER BY created_at ASC";
$events_result = mysqli_query($conn, $events_query);

// Get current month/year
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month_name = date('F', mktime(0, 0, 0, $current_month, 1));
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$first_day_of_month = date('w', mktime(0, 0, 0, $current_month, 1, $current_year));

// Check if viewing whole year
$view_whole_year = isset($_GET['view']) && $_GET['view'] === 'yearly';

// Get events for current month
$monthly_events = [];
while ($event = mysqli_fetch_assoc($events_result)) {
    $event_day = date('j', strtotime($event['created_at']));
    if (!isset($monthly_events[$event_day])) {
        $monthly_events[$event_day] = [];
    }
    $monthly_events[$event_day][] = $event;
}

// Get all events for yearly view
$yearly_events_query = "SELECT * FROM posts WHERE status = 'approved' AND type = 'event' AND YEAR(created_at) = ? ORDER BY created_at ASC";
$stmt = mysqli_prepare($conn, $yearly_events_query);
mysqli_stmt_bind_param($stmt, "i", $current_year);
mysqli_stmt_execute($stmt);
$yearly_events_result = mysqli_stmt_get_result($stmt);

// Group events by month for yearly view
$yearly_events_by_month = [];
while ($event = mysqli_fetch_assoc($yearly_events_result)) {
    $event_month = date('n', strtotime($event['created_at']));
    if (!isset($yearly_events_by_month[$event_month])) {
        $yearly_events_by_month[$event_month] = [];
    }
    $yearly_events_by_month[$event_month][] = $event;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Calendar - SEAIT</title>
    <link rel="icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/seait-logo.png">
    <link rel="apple-touch-icon" type="image/png" href="assets/images/seait-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navbar.php'; ?>

    <section class="bg-gradient-to-r from-orange-500 to-orange-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">School Calendar</h1>
            <p class="text-xl md:text-2xl opacity-90">Stay updated with all events and activities</p>
        </div>
    </section>

    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center mb-8">
                <div class="flex items-center space-x-4">
                    <a href="?year=<?php echo $current_year; ?>&month=<?php echo $current_month - 1; ?>"
                       class="px-4 py-2 rounded-lg border border-gray-300 hover:border-orange-500 transition">
                        <i class="fas fa-chevron-left mr-2"></i>Previous
                    </a>
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">
                        <?php echo $month_name . ' ' . $current_year; ?>
                    </h2>
                    <a href="?year=<?php echo $current_year; ?>&month=<?php echo $current_month + 1; ?>"
                       class="px-4 py-2 rounded-lg border border-gray-300 hover:border-orange-500 transition">
                        Next<i class="fas fa-chevron-right ml-2"></i>
                    </a>
                </div>

                <div class="flex space-x-2">
                    <a href="calendar.php?year=<?php echo $current_year; ?>"
                       class="px-4 py-2 rounded-lg border border-gray-300 hover:border-orange-500 transition <?php echo !$view_whole_year ? 'bg-orange-500 text-white' : ''; ?>">
                        <i class="fas fa-calendar-alt mr-2"></i>Monthly View
                    </a>
                    <a href="calendar.php?view=yearly&year=<?php echo $current_year; ?>"
                       class="px-4 py-2 rounded-lg border border-gray-300 hover:border-orange-500 transition <?php echo $view_whole_year ? 'bg-orange-500 text-white' : ''; ?>">
                        <i class="fas fa-calendar mr-2"></i>Whole Year Events
                    </a>
                </div>
            </div>

            <?php if (!$view_whole_year): ?>
            <!-- Monthly Calendar View -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="grid grid-cols-7 bg-orange-500 text-white">
                    <div class="p-4 text-center font-semibold">Sun</div>
                    <div class="p-4 text-center font-semibold">Mon</div>
                    <div class="p-4 text-center font-semibold">Tue</div>
                    <div class="p-4 text-center font-semibold">Wed</div>
                    <div class="p-4 text-center font-semibold">Thu</div>
                    <div class="p-4 text-center font-semibold">Fri</div>
                    <div class="p-4 text-center font-semibold">Sat</div>
                </div>

                <div class="grid grid-cols-7">
                    <?php
                    $current_date = date('Y-m-d');

                    // Empty cells before first day
                    for ($i = 0; $i < $first_day_of_month; $i++) {
                        echo '<div class="border border-gray-200 p-2 min-h-[120px] bg-gray-50"></div>';
                    }

                    // Days of the month
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                        $is_today = $date === $current_date;
                        $has_events = isset($monthly_events[$day]);
                        $day_class = "border border-gray-200 p-2 min-h-[120px]";

                        if ($is_today) {
                            $day_class .= " bg-orange-500 text-white";
                        } elseif ($has_events) {
                            $day_class .= " bg-orange-50";
                        }

                        echo '<div class="' . $day_class . '">';
                        echo '<div class="text-sm font-semibold mb-2">' . $day . '</div>';

                        if ($has_events) {
                            foreach ($monthly_events[$day] as $event) {
                                echo '<div class="text-xs bg-orange-500 text-white p-1 rounded mb-1">';
                                echo htmlspecialchars(substr($event['title'], 0, 15)) . (strlen($event['title']) > 15 ? '...' : '');
                                echo '</div>';
                            }
                        }

                        echo '</div>';
                    }

                    // Empty cells after last day
                    $remaining_cells = 7 - (($first_day_of_month + $days_in_month) % 7);
                    if ($remaining_cells < 7) {
                        for ($i = 0; $i < $remaining_cells; $i++) {
                            echo '<div class="border border-gray-200 p-2 min-h-[120px] bg-gray-50"></div>';
                        }
                    }
                    ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Whole Year Events View -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">All Events for <?php echo $current_year; ?></h3>

                <?php if (empty($yearly_events_by_month)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600">No events found for <?php echo $current_year; ?></p>
                </div>
                <?php else: ?>
                <div class="space-y-8">
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                        <?php if (isset($yearly_events_by_month[$month])): ?>
                        <div class="border-b border-gray-200 pb-6 last:border-b-0">
                            <h4 class="text-xl font-semibold text-gray-800 mb-4">
                                <?php echo date('F', mktime(0, 0, 0, $month, 1)); ?>
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($yearly_events_by_month[$month] as $event): ?>
                                <div class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">
                                            Event
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            <?php echo date('M d, Y', strtotime($event['created_at'])); ?>
                                        </span>
                                    </div>
                                    <h5 class="font-semibold text-gray-800 mb-2">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </h5>
                                    <p class="text-gray-600 text-sm mb-3">
                                        <?php echo htmlspecialchars(substr(strip_tags($event['content']), 0, 80)) . '...'; ?>
                                    </p>
                                    <a href="news-detail.php?id=<?php echo encrypt_id($event['id']); ?>"
                                       class="text-orange-500 hover:underline text-sm font-medium">
                                        View Details →
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="mt-12">
                <h3 class="text-2xl font-bold text-gray-800 mb-6">Upcoming Events</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php
                    $upcoming_query = "SELECT * FROM posts WHERE status = 'approved' AND type = 'event' ORDER BY created_at ASC LIMIT 6";
                    $upcoming_result = mysqli_query($conn, $upcoming_query);

                    while($event = mysqli_fetch_assoc($upcoming_result)):
                        $event_date = new DateTime($event['created_at']);
                    ?>
                    <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition">
                        <div class="flex justify-between items-start mb-3">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Event</span>
                            <span class="text-xs text-gray-500"><?php echo $event_date->format('M d, Y'); ?></span>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($event['title']); ?></h4>
                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars(substr(strip_tags($event['content']), 0, 100)) . '...'; ?></p>
                        <a href="news-detail.php?id=<?php echo encrypt_id($event['id']); ?>" class="text-orange-500 hover:underline text-sm">View Details</a>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

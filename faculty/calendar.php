<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and has teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Set page title
$page_title = 'Calendar';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_event':
                $title = sanitize_input($_POST['title']);
                $description = $_POST['description']; // Don't sanitize HTML content
                $event_date = sanitize_input($_POST['event_date']);
                $event_type = sanitize_input($_POST['event_type']);
                $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : null;

                $insert_query = "INSERT INTO faculty_events (teacher_id, title, description, event_date, event_type, class_id, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($insert_stmt, "issssi", $_SESSION['user_id'], $title, $description, $event_date, $event_type, $class_id);

                if (mysqli_stmt_execute($insert_stmt)) {
                    $message = "Event added successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error adding event: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;

            case 'delete_event':
                $event_id = (int)$_POST['event_id'];

                $delete_query = "DELETE FROM faculty_events WHERE id = ? AND teacher_id = ?";
                $delete_stmt = mysqli_prepare($conn, $delete_query);
                mysqli_stmt_bind_param($delete_stmt, "ii", $event_id, $_SESSION['user_id']);

                if (mysqli_stmt_execute($delete_stmt)) {
                    $message = "Event deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting event: " . mysqli_error($conn);
                    $message_type = "error";
                }
                break;
        }
    }
}

// Get current month/year
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month_name = date('F', mktime(0, 0, 0, $current_month, 1));
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$first_day_of_month = date('w', mktime(0, 0, 0, $current_month, 1, $current_year));

// Get events for current month
$events_query = "SELECT fe.*, tc.section, cc.subject_title
                FROM faculty_events fe
                LEFT JOIN teacher_classes tc ON fe.class_id = tc.id
                LEFT JOIN course_curriculum cc ON tc.subject_id = cc.id
                WHERE fe.teacher_id = ? AND MONTH(fe.event_date) = ? AND YEAR(fe.event_date) = ?
                ORDER BY fe.event_date ASC";
$events_stmt = mysqli_prepare($conn, $events_query);
mysqli_stmt_bind_param($events_stmt, "iii", $_SESSION['user_id'], $current_month, $current_year);
mysqli_stmt_execute($events_stmt);
$events_result = mysqli_stmt_get_result($events_stmt);

// Group events by day
$monthly_events = [];
while ($event = mysqli_fetch_assoc($events_result)) {
    $event_day = date('j', strtotime($event['event_date']));
    if (!isset($monthly_events[$event_day])) {
        $monthly_events[$event_day] = [];
    }
    $monthly_events[$event_day][] = $event;
}

// Get teacher's classes for event form
$classes_query = "SELECT tc.*, cc.subject_title, cc.subject_code
                 FROM teacher_classes tc
                 JOIN course_curriculum cc ON tc.subject_id = cc.id
                 WHERE tc.teacher_id = ? AND tc.status = 'active'
                 ORDER BY cc.subject_title, tc.section";
$classes_stmt = mysqli_prepare($conn, $classes_query);
mysqli_stmt_bind_param($classes_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($classes_stmt);
$classes_result = mysqli_stmt_get_result($classes_stmt);

// Include the shared header
$sidebar_context = 'main';
include 'includes/unified-header.php';
?>

<div class="mb-6 sm:mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-seait-dark">Calendar</h1>
            <p class="text-gray-600 mt-1">Manage your class schedules and important events</p>
        </div>
        <button onclick="showAddEventModal()" class="mt-4 sm:mt-0 bg-seait-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition flex items-center">
            <i class="fas fa-plus mr-2"></i>Add Event
        </button>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Calendar Navigation -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="?month=<?php echo $current_month - 1; ?>&year=<?php echo $current_month == 1 ? $current_year - 1 : $current_year; ?>"
               class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h2 class="text-xl font-semibold text-gray-900"><?php echo $month_name . ' ' . $current_year; ?></h2>
            <a href="?month=<?php echo $current_month + 1; ?>&year=<?php echo $current_month == 12 ? $current_year + 1 : $current_year; ?>"
               class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>"
           class="text-seait-orange hover:text-orange-600 font-medium">
            Today
        </a>
    </div>
</div>

<!-- Calendar Grid -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <!-- Calendar Header -->
    <div class="grid grid-cols-7 gap-px bg-gray-200">
        <div class="bg-gray-50 p-3 text-center">
            <span class="text-sm font-medium text-gray-900">Sun</span>
        </div>
        <div class="bg-gray-50 p-3 text-center">
            <span class="text-sm font-medium text-gray-900">Mon</span>
        </div>
        <div class="bg-gray-50 p-3 text-center">
            <span class="text-sm font-medium text-gray-900">Tue</span>
        </div>
        <div class="bg-gray-50 p-3 text-center">
            <span class="text-sm font-medium text-gray-900">Wed</span>
        </div>
        <div class="bg-gray-50 p-3 text-center">
            <span class="text-sm font-medium text-gray-900">Thu</span>
        </div>
        <div class="bg-gray-50 p-3 text-center">
            <span class="text-sm font-medium text-gray-900">Fri</span>
        </div>
        <div class="bg-gray-50 p-3 text-center">
            <span class="text-sm font-medium text-gray-900">Sat</span>
        </div>
    </div>

    <!-- Calendar Days -->
    <div class="grid grid-cols-7 gap-px bg-gray-200">
        <?php
        // Empty cells for days before the first day of the month
        for ($i = 0; $i < $first_day_of_month; $i++) {
            echo '<div class="bg-white min-h-32 p-2"></div>';
        }

        // Days of the month
        for ($day = 1; $day <= $days_in_month; $day++) {
            $is_today = ($day == date('j') && $current_month == date('n') && $current_year == date('Y'));
            $has_events = isset($monthly_events[$day]);

            echo '<div class="bg-white min-h-32 p-2 relative ' . ($is_today ? 'bg-blue-50' : '') . '">';
            echo '<div class="flex items-center justify-between mb-1">';
            echo '<span class="text-sm font-medium ' . ($is_today ? 'text-blue-600' : 'text-gray-900') . '">' . $day . '</span>';
            if ($has_events) {
                echo '<span class="w-2 h-2 bg-seait-orange rounded-full"></span>';
            }
            echo '</div>';

            // Display events for this day
            if ($has_events) {
                echo '<div class="space-y-1">';
                foreach ($monthly_events[$day] as $event) {
                    $event_colors = [
                        'class' => 'bg-blue-100 text-blue-800',
                        'exam' => 'bg-red-100 text-red-800',
                        'assignment' => 'bg-yellow-100 text-yellow-800',
                        'meeting' => 'bg-green-100 text-green-800',
                        'other' => 'bg-gray-100 text-gray-800'
                    ];
                    $color_class = $event_colors[$event['event_type']] ?? 'bg-gray-100 text-gray-800';

                    echo '<div class="text-xs p-1 rounded cursor-pointer hover:bg-gray-100 transition ' . $color_class . '" onclick="viewEvent(' . $event['id'] . ')">';
                    echo '<div class="font-medium truncate">' . htmlspecialchars($event['title']) . '</div>';
                    if ($event['class_id']) {
                        echo '<div class="text-xs opacity-75">' . htmlspecialchars($event['subject_title'] . ' - ' . $event['section']) . '</div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        ?>
    </div>
</div>

<!-- Event Legend -->
<div class="bg-white rounded-lg shadow-md p-6 mt-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Event Types</h3>
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="flex items-center">
            <div class="w-3 h-3 bg-blue-100 border border-blue-300 rounded mr-2"></div>
            <span class="text-sm text-gray-700">Class</span>
        </div>
        <div class="flex items-center">
            <div class="w-3 h-3 bg-red-100 border border-red-300 rounded mr-2"></div>
            <span class="text-sm text-gray-700">Exam</span>
        </div>
        <div class="flex items-center">
            <div class="w-3 h-3 bg-yellow-100 border border-yellow-300 rounded mr-2"></div>
            <span class="text-sm text-gray-700">Assignment</span>
        </div>
        <div class="flex items-center">
            <div class="w-3 h-3 bg-green-100 border border-green-300 rounded mr-2"></div>
            <span class="text-sm text-gray-700">Meeting</span>
        </div>
        <div class="flex items-center">
            <div class="w-3 h-3 bg-gray-100 border border-gray-300 rounded mr-2"></div>
            <span class="text-sm text-gray-700">Other</span>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div id="addEventModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Event</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_event">

                <div class="mb-4">
                    <label for="event_title" class="block text-sm font-medium text-gray-700 mb-1">Event Title *</label>
                    <input type="text" id="event_title" name="title" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                           placeholder="Enter event title">
                </div>

                <div class="mb-4">
                    <label for="event_description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="event_description" name="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent"
                              placeholder="Enter event description"></textarea>
                </div>

                <div class="mb-4">
                    <label for="event_date" class="block text-sm font-medium text-gray-700 mb-1">Event Date *</label>
                    <input type="date" id="event_date" name="event_date" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                </div>

                <div class="mb-4">
                    <label for="event_type" class="block text-sm font-medium text-gray-700 mb-1">Event Type *</label>
                    <select id="event_type" name="event_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">Select event type</option>
                        <option value="class">Class</option>
                        <option value="exam">Exam</option>
                        <option value="assignment">Assignment</option>
                        <option value="meeting">Meeting</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="event_class_id" class="block text-sm font-medium text-gray-700 mb-1">Related Class (Optional)</label>
                    <select id="event_class_id" name="class_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-seait-orange focus:border-transparent">
                        <option value="">No specific class</option>
                        <?php
                        mysqli_data_seek($classes_result, 0);
                        while ($class = mysqli_fetch_assoc($classes_result)):
                        ?>
                        <option value="<?php echo $class['id']; ?>">
                            <?php echo htmlspecialchars($class['subject_title'] . ' - ' . $class['section']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideAddEventModal()"
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-seait-orange text-white rounded-lg hover:bg-orange-600 transition">
                        Add Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Event Modal -->
<div id="viewEventModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Event Details</h3>
            <div id="eventDetails"></div>
            <div class="flex justify-end space-x-3 mt-4">
                <button onclick="deleteEvent()"
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    Delete
                </button>
                <button onclick="hideViewEventModal()"
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentEventId = null;

function showAddEventModal() {
    document.getElementById('addEventModal').classList.remove('hidden');
    // Set default date to today
    document.getElementById('event_date').value = new Date().toISOString().split('T')[0];
}

function hideAddEventModal() {
    document.getElementById('addEventModal').classList.add('hidden');
}

function viewEvent(eventId) {
    currentEventId = eventId;
    // Show loading state
    document.getElementById('eventDetails').innerHTML = '<p class="text-gray-500">Loading...</p>';
    document.getElementById('viewEventModal').classList.remove('hidden');

    // Fetch event details via AJAX
    fetch(`../api/get-faculty-event-details.php?id=${eventId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const event = data.event;
                const eventTypeColors = {
                    'class': 'bg-blue-100 text-blue-800',
                    'meeting': 'bg-green-100 text-green-800',
                    'deadline': 'bg-red-100 text-red-800',
                    'personal': 'bg-purple-100 text-purple-800',
                    'other': 'bg-gray-100 text-gray-800'
                };

                document.getElementById('eventDetails').innerHTML = `
                    <div class="space-y-4">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900">${event.title}</h4>
                            <span class="inline-block px-2 py-1 text-xs rounded-full ${eventTypeColors[event.event_type] || eventTypeColors['other']} mt-1">
                                ${event.event_type.charAt(0).toUpperCase() + event.event_type.slice(1)} Event
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-600">Date:</p>
                                <p class="font-medium">${event.event_date}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Time:</p>
                                <p class="font-medium">${event.start_time} - ${event.end_time}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-gray-600">Location:</p>
                                <p class="font-medium">${event.location}</p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-gray-600">Class:</p>
                                <p class="font-medium">${event.class_name}</p>
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Description:</h5>
                            <div class="text-gray-900 text-sm leading-relaxed">
                                ${event.description}
                            </div>
                        </div>

                        <div class="text-xs text-gray-500">
                            Created: ${event.created_at}
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('eventDetails').innerHTML = `
                    <div class="text-red-600">
                        <p>Error: ${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('eventDetails').innerHTML = `
                <div class="text-red-600">
                    <p>Error loading event details. Please try again.</p>
                </div>
            `;
            console.error('Error:', error);
        });
}

function hideViewEventModal() {
    document.getElementById('viewEventModal').classList.add('hidden');
    currentEventId = null;
}

function deleteEvent() {
    if (currentEventId && confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_event">
            <input type="hidden" name="event_id" value="${currentEventId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const addEventModal = document.getElementById('addEventModal');
    const viewEventModal = document.getElementById('viewEventModal');

    if (event.target === addEventModal) {
        hideAddEventModal();
    }
    if (event.target === viewEventModal) {
        hideViewEventModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
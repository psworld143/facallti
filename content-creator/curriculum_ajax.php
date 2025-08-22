<?php
session_start();
require_once '../config/database.php';

$items_per_page = 10;
$curriculum_page = isset($_GET['curriculum_page']) ? (int)$_GET['curriculum_page'] : 1;
$curriculum_search = isset($_GET['curriculum_search']) ? trim($_GET['curriculum_search']) : '';
$requirements_page = isset($_GET['requirements_page']) ? (int)$_GET['requirements_page'] : 1;
$requirements_search = isset($_GET['requirements_search']) ? trim($_GET['requirements_search']) : '';

$curriculum_offset = ($curriculum_page - 1) * $items_per_page;
$curriculum_where = '';
$curriculum_params = [];
if ($curriculum_search !== '') {
    $curriculum_where = "WHERE (c.name LIKE ? OR cc.year_level LIKE ? OR cc.semester LIKE ? OR cc.subject_code LIKE ? OR cc.subject_title LIKE ? OR cc.units LIKE ? OR cc.description LIKE ? OR prereq.subject_code LIKE ? OR prereq.subject_title LIKE ?)";
    $search_term = "%$curriculum_search%";
    $curriculum_params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term];
}
$curriculum_query = "SELECT cc.*, c.name as course_name,
                     prereq.subject_code as prerequisite_code, prereq.subject_title as prerequisite_title
                     FROM course_curriculum cc
                     JOIN courses c ON cc.course_id = c.id
                     LEFT JOIN course_curriculum prereq ON cc.prerequisite_id = prereq.id
                     $curriculum_where
                     ORDER BY c.name ASC, cc.year_level ASC, cc.semester ASC, cc.sort_order ASC
                     LIMIT $items_per_page OFFSET $curriculum_offset";
$curriculum_stmt = mysqli_prepare($conn, $curriculum_query);
if ($curriculum_where) {
    mysqli_stmt_bind_param($curriculum_stmt, str_repeat('s', count($curriculum_params)), ...$curriculum_params);
}
mysqli_stmt_execute($curriculum_stmt);
$curriculum_result = mysqli_stmt_get_result($curriculum_stmt);
// Get total count for curriculum search
$curriculum_count_query = "SELECT COUNT(*) as total FROM course_curriculum cc JOIN courses c ON cc.course_id = c.id LEFT JOIN course_curriculum prereq ON cc.prerequisite_id = prereq.id $curriculum_where";
$curriculum_count_stmt = mysqli_prepare($conn, $curriculum_count_query);
if ($curriculum_where) {
    mysqli_stmt_bind_param($curriculum_count_stmt, str_repeat('s', count($curriculum_params)), ...$curriculum_params);
}
mysqli_stmt_execute($curriculum_count_stmt);
$curriculum_count_result = mysqli_stmt_get_result($curriculum_count_stmt);
$curriculum_total = mysqli_fetch_assoc($curriculum_count_result)['total'];
$curriculum_total_pages = ceil($curriculum_total / $items_per_page);
?>
<tbody id="curriculumTableBody">
<?php while($subject = mysqli_fetch_assoc($curriculum_result)): ?>
<tr>
    <td data-label="Course"><?php echo htmlspecialchars($subject['course_name']); ?></td>
    <td data-label="Year"><?php echo ucfirst(str_replace('_', ' ', $subject['year_level'])); ?></td>
    <td data-label="Semester"><?php echo ucfirst(str_replace('_', ' ', $subject['semester'])); ?></td>
    <td data-label="Subject Code"><?php echo htmlspecialchars($subject['subject_code']); ?></td>
    <td data-label="Subject Title" class="hidden lg:table-cell"><?php echo htmlspecialchars($subject['subject_title']); ?></td>
    <td data-label="Prerequisite" class="hidden md:table-cell">
        <?php if ($subject['prerequisite_code'] && $subject['prerequisite_title']): ?>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                <?php echo htmlspecialchars($subject['prerequisite_code']); ?>
            </span>
            <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($subject['prerequisite_title']); ?></div>
        <?php else: ?>
            <span class="text-gray-400 text-xs">None</span>
        <?php endif; ?>
    </td>
    <td data-label="Units"><?php echo htmlspecialchars($subject['units']); ?></td>
    <td data-label="Actions" class="actions-cell">
        <div class="flex space-x-2">
            <button onclick="editCurriculum(<?php echo $subject['id']; ?>)" class="text-seait-orange hover:text-orange-600 transition text-sm">
                <i class="fas fa-edit mr-1"></i>Edit
            </button>
            <button onclick="deleteCurriculum(<?php echo $subject['id']; ?>, '<?php echo addslashes($subject['subject_code']); ?>', '<?php echo addslashes($subject['subject_title']); ?>')" class="text-red-500 hover:text-red-700 transition text-sm">
                <i class="fas fa-trash mr-1"></i>Delete
            </button>
        </div>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
<div id="curriculumPagination">
    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-700" id="curriculumShowing">
            Showing <?php echo (($curriculum_page - 1) * $items_per_page) + 1; ?> to <?php echo min($curriculum_page * $items_per_page, $curriculum_total); ?> of <?php echo $curriculum_total; ?> curriculum subjects
        </div>
        <div class="flex space-x-2">
            <?php if ($curriculum_page > 1): ?>
            <a href="#" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition curriculum-page-link" data-page="<?php echo $curriculum_page - 1; ?>">Previous</a>
            <?php endif; ?>
            <?php for ($i = max(1, $curriculum_page - 2); $i <= min($curriculum_total_pages, $curriculum_page + 2); $i++): ?>
            <a href="#" class="px-3 py-2 text-sm rounded transition curriculum-page-link <?php echo $i == $curriculum_page ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>" data-page="<?php echo $i; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <?php if ($curriculum_page < $curriculum_total_pages): ?>
            <a href="#" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition curriculum-page-link" data-page="<?php echo $curriculum_page + 1; ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
</div>

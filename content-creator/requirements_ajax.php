<?php
session_start();
require_once '../config/database.php';

$items_per_page = 10;
$requirements_page = isset($_GET['requirements_page']) ? (int)$_GET['requirements_page'] : 1;
$requirements_search = isset($_GET['requirements_search']) ? trim($_GET['requirements_search']) : '';
$curriculum_page = isset($_GET['curriculum_page']) ? (int)$_GET['curriculum_page'] : 1;
$curriculum_search = isset($_GET['curriculum_search']) ? trim($_GET['curriculum_search']) : '';

$requirements_offset = ($requirements_page - 1) * $items_per_page;
$requirements_where = '';
$requirements_params = [];
if ($requirements_search !== '') {
    $requirements_where = "WHERE (c.name LIKE ? OR cr.requirement_type LIKE ? OR cr.title LIKE ? OR cr.description LIKE ?)";
    $search_term = "%$requirements_search%";
    $requirements_params = [$search_term, $search_term, $search_term, $search_term];
}
$requirements_query = "SELECT cr.*, c.name as course_name
                      FROM course_requirements cr
                      JOIN courses c ON cr.course_id = c.id
                      $requirements_where
                      ORDER BY c.name ASC, cr.requirement_type ASC, cr.sort_order ASC
                      LIMIT $items_per_page OFFSET $requirements_offset";
$requirements_stmt = mysqli_prepare($conn, $requirements_query);
if ($requirements_where) {
    mysqli_stmt_bind_param($requirements_stmt, str_repeat('s', count($requirements_params)), ...$requirements_params);
}
mysqli_stmt_execute($requirements_stmt);
$requirements_result = mysqli_stmt_get_result($requirements_stmt);
// Get total count for requirements search
$requirements_count_query = "SELECT COUNT(*) as total FROM course_requirements cr JOIN courses c ON cr.course_id = c.id $requirements_where";
$requirements_count_stmt = mysqli_prepare($conn, $requirements_count_query);
if ($requirements_where) {
    mysqli_stmt_bind_param($requirements_count_stmt, str_repeat('s', count($requirements_params)), ...$requirements_params);
}
mysqli_stmt_execute($requirements_count_stmt);
$requirements_count_result = mysqli_stmt_get_result($requirements_count_stmt);
$requirements_total = mysqli_fetch_assoc($requirements_count_result)['total'];
$requirements_total_pages = ceil($requirements_total / $items_per_page);
?>
<tbody id="requirementsTableBody">
<?php while($requirement = mysqli_fetch_assoc($requirements_result)): ?>
<tr>
    <td data-label="Course"><?php echo htmlspecialchars($requirement['course_name']); ?></td>
    <td data-label="Type"><?php echo ucfirst(htmlspecialchars($requirement['requirement_type'])); ?></td>
    <td data-label="Title"><?php echo htmlspecialchars($requirement['title']); ?></td>
    <td data-label="Description" class="hidden md:table-cell"><?php echo htmlspecialchars($requirement['description']); ?></td>
    <td data-label="Actions" class="actions-cell">
        <div class="flex space-x-2">
            <button onclick="editRequirement(<?php echo $requirement['id']; ?>)" class="text-seait-orange hover:text-orange-600 transition text-sm">
                <i class="fas fa-edit mr-1"></i>Edit
            </button>
            <button onclick="deleteRequirement(<?php echo $requirement['id']; ?>, '<?php echo addslashes($requirement['title']); ?>')" class="text-red-500 hover:text-red-700 transition text-sm">
                <i class="fas fa-trash mr-1"></i>Delete
            </button>
        </div>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
<div id="requirementsPagination">
    <div class="mt-6 flex items-center justify-between">
        <div class="text-sm text-gray-700" id="requirementsShowing">
            Showing <?php echo (($requirements_page - 1) * $items_per_page) + 1; ?> to <?php echo min($requirements_page * $items_per_page, $requirements_total); ?> of <?php echo $requirements_total; ?> requirements
        </div>
        <div class="flex space-x-2">
            <?php if ($requirements_page > 1): ?>
            <a href="#" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition requirements-page-link" data-page="<?php echo $requirements_page - 1; ?>">Previous</a>
            <?php endif; ?>
            <?php for ($i = max(1, $requirements_page - 2); $i <= min($requirements_total_pages, $requirements_page + 2); $i++): ?>
            <a href="#" class="px-3 py-2 text-sm rounded transition requirements-page-link <?php echo $i == $requirements_page ? 'bg-seait-orange text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>" data-page="<?php echo $i; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <?php if ($requirements_page < $requirements_total_pages): ?>
            <a href="#" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition requirements-page-link" data-page="<?php echo $requirements_page + 1; ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
</div>

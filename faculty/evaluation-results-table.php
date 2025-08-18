<!-- Evaluation Results Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden" id="evaluation-results-container">
    <div class="px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">
            <?php if ($current_category): ?>
                <?php echo htmlspecialchars($current_category['name']); ?> Results (<?php echo $total_records; ?>)
            <?php else: ?>
                Evaluation Results (<?php echo $total_records; ?>)
            <?php endif; ?>
        </h2>
    </div>

    <?php if (mysqli_num_rows($results_result) == 0): ?>
        <div class="p-6 text-center">
            <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-4"></i>
            <p class="text-gray-500">
                <?php if ($current_category): ?>
                    No evaluation results found for <?php echo htmlspecialchars($current_category['name']); ?>.
                <?php else: ?>
                    No evaluation results found. You haven't been evaluated yet.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($result = mysqli_fetch_assoc($results_result)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($result['category_name']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                <?php
                                switch($result['evaluation_type']) {
                                    case 'student_to_teacher':
                                        echo 'bg-orange-100 text-orange-800';
                                        break;
                                    case 'peer_to_peer':
                                        echo 'bg-purple-100 text-purple-800';
                                        break;
                                    case 'head_to_teacher':
                                        echo 'bg-indigo-100 text-indigo-800';
                                        break;
                                }
                                ?>">
                                <i class="fas
                                    <?php
                                    switch($result['evaluation_type']) {
                                        case 'student_to_teacher':
                                            echo 'fa-user-graduate';
                                            break;
                                        case 'peer_to_peer':
                                            echo 'fa-users';
                                            break;
                                        case 'head_to_teacher':
                                            echo 'fa-user-tie';
                                            break;
                                    }
                                    ?> mr-1"></i>
                                <?php echo ucwords(str_replace('_', ' ', $result['evaluation_type'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full <?php
                                echo $result['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                    ($result['status'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800');
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $result['status'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M d, Y', strtotime($result['evaluation_date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <?php if ($result['status'] === 'completed'): ?>
                                    <?php if ($result['response_count'] > 0): ?>
                                    <a href="view-evaluation.php?id=<?php echo $result['id']; ?>"
                                       class="text-blue-600 hover:text-blue-900" title="View Results">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-orange-400" title="Evaluation completed but no responses found">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                <span class="text-gray-400" title="Evaluation not completed yet">
                                    <i class="fas fa-clock"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> results
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&category=<?php echo urlencode($selected_category); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&semester=<?php echo urlencode($semester_filter); ?>"
                       class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&category=<?php echo urlencode($selected_category); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&semester=<?php echo urlencode($semester_filter); ?>"
                       class="px-3 py-2 text-sm <?php echo $i === $page ? 'bg-seait-orange text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded-md">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&category=<?php echo urlencode($selected_category); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&semester=<?php echo urlencode($semester_filter); ?>"
                       class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
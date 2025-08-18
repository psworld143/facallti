<?php
// Information Section Component for Social Media Manager
// This component provides helpful information and tips for users

// Get current page name for context-specific information
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Information Section -->
<div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 sm:p-6 mb-6">
    <div class="flex items-start space-x-3">
        <div class="flex-shrink-0">
            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-info-circle text-blue-600 text-sm"></i>
            </div>
        </div>
        <div class="flex-1">
            <h3 class="text-sm font-semibold text-blue-900 mb-2">Information & Tips</h3>

            <?php if ($current_page === 'dashboard.php'): ?>
                <div class="text-sm text-blue-800 space-y-2">
                    <p><strong>Dashboard Overview:</strong> This dashboard provides a quick overview of your content management activities. Monitor pending approvals, recent activity, and key metrics at a glance.</p>
                    <p><strong>Quick Actions:</strong> Use the action buttons to approve, reject, or manage posts directly from the dashboard.</p>
                    <p><strong>Statistics:</strong> Track content performance and approval rates to optimize your workflow.</p>
                </div>

            <?php elseif ($current_page === 'pending-post.php'): ?>
                <div class="text-sm text-blue-800 space-y-2">
                    <p><strong>Pending Posts:</strong> Review content that requires approval before publication. Each post shows author details, content preview, and action options.</p>
                    <p><strong>Approval Process:</strong> Click "Approve" to publish content, "Reject" to return with feedback, or "View" for detailed review.</p>
                    <p><strong>Content Guidelines:</strong> Ensure posts meet quality standards, are factually accurate, and align with SEAIT's communication policies.</p>
                </div>

            <?php elseif ($current_page === 'approved-posts.php'): ?>
                <div class="text-sm text-blue-800 space-y-2">
                    <p><strong>Approved Content:</strong> Manage posts that have been approved and published. You can unapprove posts to send them back for revision if needed.</p>
                    <p><strong>Content Management:</strong> Use "Unapprove" to move posts back to pending status, "View" for detailed content, or "Delete" to remove content.</p>
                    <p><strong>Performance Tracking:</strong> Monitor approval times and content statistics to improve workflow efficiency.</p>
                </div>

            <?php elseif ($current_page === 'rejected-posts.php'): ?>
                <div class="text-sm text-blue-800 space-y-2">
                    <p><strong>Rejected Posts:</strong> Review content that was not approved for publication. Each post includes the rejection reason and can be reconsidered.</p>
                    <p><strong>Reconsideration:</strong> Posts can be moved back to pending status for revision and resubmission.</p>
                    <p><strong>Feedback:</strong> Rejection reasons help content creators understand what needs to be improved.</p>
                </div>

            <?php elseif ($current_page === 'analytics.php'): ?>
                <div class="text-sm text-blue-800 space-y-2">
                    <p><strong>Analytics Dashboard:</strong> Track content performance, approval rates, and engagement metrics to optimize your content strategy.</p>
                    <p><strong>Key Metrics:</strong> Monitor posts by status, type, and creator performance to identify trends and opportunities.</p>
                    <p><strong>Data Insights:</strong> Use analytics to improve content quality, approval processes, and overall communication effectiveness.</p>
                </div>

            <?php elseif ($current_page === 'pending-carousel.php'): ?>
                <div class="text-sm text-blue-800 space-y-2">
                    <p><strong>Carousel Management:</strong> Review and approve carousel slides for the homepage. Carousel content appears prominently on the main website.</p>
                    <p><strong>Visual Content:</strong> Ensure images are high-quality, properly sized, and relevant to SEAIT's messaging.</p>
                    <p><strong>Content Guidelines:</strong> Carousel slides should be engaging, informative, and represent SEAIT's brand effectively.</p>
                </div>

            <?php else: ?>
                <div class="text-sm text-blue-800 space-y-2">
                    <p><strong>Social Media Manager:</strong> This platform helps you manage and approve content for SEAIT's website and social media presence.</p>
                    <p><strong>Content Workflow:</strong> Content creators submit posts for review, which you can approve, reject, or request revisions for.</p>
                    <p><strong>Quality Control:</strong> Ensure all published content meets SEAIT's standards for accuracy, professionalism, and brand consistency.</p>
                </div>
            <?php endif; ?>

            <div class="mt-4 pt-3 border-t border-blue-200">
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                        <i class="fas fa-clock mr-1"></i>Real-time Updates
                    </span>
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                        <i class="fas fa-shield-alt mr-1"></i>Secure Approval
                    </span>
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                        <i class="fas fa-chart-line mr-1"></i>Performance Tracking
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
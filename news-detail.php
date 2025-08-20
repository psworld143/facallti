<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$post_id = (int)$_GET['id'];
$query = "SELECT p.*, u.first_name, u.last_name FROM posts p
          JOIN users u ON p.author_id = u.id
          WHERE p.id = ? AND p.status = 'approved'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$post = mysqli_fetch_assoc($result)) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - SEAIT</title>

    <!-- Open Graph Meta Tags for Social Media Sharing -->
    <meta property="og:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 200)) . '...'; ?>">
    <meta property="og:url" content="https://<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="SEAIT - South East Asian Institute of Technology, Inc.">
    <meta property="og:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo !empty($post['image_url']) ? htmlspecialchars($post['image_url']) : 'assets/images/seait-logo.png'; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="SEAIT Logo">
    <meta property="og:locale" content="en_US">
    <meta property="fb:app_id" content="YOUR_FACEBOOK_APP_ID_HERE">

    <!-- Facebook App ID (optional but recommended) -->
    <!-- <meta property="fb:app_id" content="YOUR_FACEBOOK_APP_ID"> -->

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($post['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 200)) . '...'; ?>">
    <meta name="twitter:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo !empty($post['image_url']) ? htmlspecialchars($post['image_url']) : 'assets/images/seait-logo.png'; ?>">

    <!-- Article Meta Tags -->
    <meta property="article:published_time" content="<?php echo $post['created_at']; ?>">
    <meta property="article:author" content="<?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>">
    <meta property="article:section" content="<?php echo ucfirst($post['type']); ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'seait-orange': '#FF6B35',
                        'seait-dark': '#2C3E50',
                        'seait-light': '#FFF8F0'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
    <script src="assets/js/dark-mode.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .prose {
            max-width: none;
        }
        .prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
            color: #2C3E50;
            font-weight: 600;
            margin-top: 1.5em;
            margin-bottom: 0.5em;
        }
        .prose h1 { font-size: 2.5rem; }
        .prose h2 { font-size: 2rem; }
        .prose h3 { font-size: 1.75rem; }
        .prose h4 { font-size: 1.5rem; }
        .prose p {
            margin-bottom: 1.5em;
            line-height: 1.8;
            font-size: 1.1rem;
        }
        .prose ul, .prose ol {
            margin-bottom: 1.5em;
            padding-left: 1.5em;
        }
        .prose li {
            margin-bottom: 0.75em;
            line-height: 1.7;
        }
        .prose blockquote {
            border-left: 4px solid #FF6B35;
            padding-left: 1.5rem;
            margin: 2rem 0;
            font-style: italic;
            color: #6B7280;
            font-size: 1.1rem;
        }
        .prose table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
        }
        .prose table th, .prose table td {
            border: 1px solid #E5E7EB;
            padding: 1rem;
            text-align: left;
        }
        .prose table th {
            background-color: #F9FAFB;
            font-weight: 600;
        }
        .prose a {
            color: #FF6B35;
            text-decoration: underline;
        }
        .prose a:hover {
            color: #EA580C;
        }
        .prose img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 2rem 0;
        }
        .prose code {
            background-color: #F3F4F6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .prose pre {
            background-color: #1F2937;
            color: #F9FAFB;
            padding: 1.5rem;
            border-radius: 8px;
            overflow-x: auto;
            margin: 2rem 0;
        }

        /* Active navbar link styles */
        .navbar-link-active {
            color: #FF6B35 !important;
            font-weight: 600;
        }
        .navbar-link-active:hover {
            color: #FF6B35 !important;
        }
    </style>
</head>
<body class="bg-gray-50 dark-mode" data-theme="light">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Article Header -->
            <?php
                $hasImage = !empty($post['image_url']);
                $imagePath =  htmlspecialchars($post['image_url']);
                $headerStyle = $hasImage
                    ? "background: linear-gradient(rgba(44,62,80,0.3), rgba(44,62,80,0.3)), url('{$imagePath}') center center / cover no-repeat;"
                    : "";
            ?>
            <!-- DEBUG: image_url = <?php echo $post['image_url']; ?>, hasImage = <?php echo $hasImage ? 'true' : 'false'; ?>, imagePath = <?php echo $imagePath; ?> -->
            <div class="<?php echo $hasImage ? '' : 'bg-gradient-to-r from-seait-orange to-orange-600'; ?> text-white relative" style="<?php echo $headerStyle; ?> min-height:350px; min-height:40vh;">
                <div class="absolute left-0 bottom-0 z-10 max-w-3xl w-full p-4 md:p-6">
                    <div class="flex items-center space-x-4 mb-2">
                        <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                            <?php echo ucfirst($post['type']); ?>
                        </span>
                        <span class="text-xs opacity-90">
                            <?php echo date('F d, Y', strtotime($post['created_at'])); ?>
                        </span>
                    </div>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-bold mb-1"><?php echo htmlspecialchars($post['title']); ?></h1>
                    <p class="text-sm md:text-base opacity-90 mb-0">
                        By <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                    </p>
                </div>
            </div>

            <!-- Article Content -->
            <div class="p-8 md:p-12">
                <div class="prose prose-lg md:prose-xl max-w-none">
                    <?php echo $post['content']; ?>
                </div>
            </div>

            <!-- Article Footer -->
            <div class="border-t border-gray-200 p-8 md:p-12 bg-gray-50">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between space-y-4 md:space-y-0">
                    <div class="flex flex-col md:flex-row items-start md:items-center space-y-2 md:space-y-0 md:space-x-6">
                        <div class="flex items-center space-x-2 text-gray-600">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Published on <?php echo date('F d, Y', strtotime($post['created_at'])); ?></span>
                        </div>
                        <div class="flex items-center space-x-2 text-gray-600">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></span>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <a href="javascript:void(0);"
                           onclick="shareToFacebook('<?php echo htmlspecialchars($post['title']); ?>', '<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>')"
                           class="text-gray-600 hover:text-seait-orange transition p-2 rounded-full hover:bg-gray-100"
                           title="Share on Facebook">
                            <i class="fab fa-facebook text-lg"></i>
                        </a>
                        <a href="javascript:void(0);"
                           onclick="copyToClipboard('<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>')"
                           class="text-gray-600 hover:text-seait-orange transition p-2 rounded-full hover:bg-gray-100"
                           title="Copy URL">
                            <i class="fas fa-link text-lg"></i>
                        </a>
                        <a href="javascript:void(0);"
                           onclick="showShareOptions('<?php echo htmlspecialchars($post['title']); ?>', '<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>')"
                           class="text-gray-600 hover:text-seait-orange transition p-2 rounded-full hover:bg-gray-100"
                           title="More sharing options">
                            <i class="fas fa-share-alt text-lg"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode(htmlspecialchars($post['title'])); ?>"
                           target="_blank"
                           class="text-gray-600 hover:text-seait-orange transition p-2 rounded-full hover:bg-gray-100"
                           onclick="window.open(this.href, 'twitter-share', 'width=580,height=296'); return false;">
                            <i class="fab fa-twitter text-lg"></i>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>"
                           target="_blank"
                           class="text-gray-600 hover:text-seait-orange transition p-2 rounded-full hover:bg-gray-100"
                           onclick="window.open(this.href, 'linkedin-share', 'width=580,height=296'); return false;">
                            <i class="fab fa-linkedin text-lg"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Posts -->
        <div class="mt-12">
            <h2 class="text-2xl md:text-3xl font-bold text-seait-dark mb-6">Related Posts</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php
                $related_query = "SELECT * FROM posts WHERE status = 'approved' AND type = ? AND id != ? ORDER BY created_at DESC LIMIT 4";
                $stmt = mysqli_prepare($conn, $related_query);
                mysqli_stmt_bind_param($stmt, "si", $post['type'], $post_id);
                mysqli_stmt_execute($stmt);
                $related_result = mysqli_stmt_get_result($stmt);

                while($related = mysqli_fetch_assoc($related_result)):
                ?>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-2 text-seait-dark">
                            <a href="news-detail.php?id=<?php echo $related['id']; ?>" class="hover:text-seait-orange transition">
                                <?php echo htmlspecialchars($related['title']); ?>
                            </a>
                        </h3>
                        <div class="text-gray-600 mb-4 text-sm">
                            <?php
                            $content = strip_tags($related['content']);
                            echo htmlspecialchars(substr($content, 0, 120)) . '...';
                            ?>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-gray-500">
                                <?php echo date('M d, Y', strtotime($related['created_at'])); ?>
                            </span>
                            <span class="px-2 py-1 text-xs bg-seait-orange text-white rounded">
                                <?php echo ucfirst($related['type']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Include FAB Inquiry System -->
    <?php include 'includes/fab-inquiry.php'; ?>

    <script>
        // Active navbar link functionality for news detail page
        function updateActiveNavLink() {
            const navLinks = document.querySelectorAll('a[href^="index.php#"]');

            // Remove active class from all links
            navLinks.forEach(link => {
                link.classList.remove('navbar-link-active');
            });

            // Highlight News link for news detail page
            const newsLink = document.querySelector('a[href="index.php#news"]');
            if (newsLink) {
                newsLink.classList.add('navbar-link-active');
            }
        }

        // Update active link on page load
        document.addEventListener('DOMContentLoaded', updateActiveNavLink);

        // Facebook sharing function
        function shareToFacebook(title, url) {
            try {
                // Encode the URL and title properly
                const encodedUrl = encodeURIComponent(url);
                const encodedTitle = encodeURIComponent(title);

                // Create Facebook share URL
                const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;

                // Open Facebook share dialog
                const width = 580;
                const height = 296;
                const left = (screen.width - width) / 2;
                const top = (screen.height - height) / 2;

                const popup = window.open(
                    facebookUrl,
                    'facebook-share',
                    `width=${width},height=${height},left=${left},top=${top},location=0,menubar=0,toolbar=0,status=0,scrollbars=0,resizable=0`
                );

                // Check if popup was blocked
                if (!popup || popup.closed || typeof popup.closed === 'undefined') {
                    // Popup was blocked, show alternative options
                    showShareOptions(title, url);
                }
            } catch (error) {
                console.error('Facebook sharing error:', error);
                // Show alternative sharing options
                showShareOptions(title, url);
            }
        }

        // Function to copy URL to clipboard
        function copyToClipboard(url) {
            navigator.clipboard.writeText(url).then(() => {
                alert('URL copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy URL: ', err);
                alert('Failed to copy URL. Please copy it manually: ' + url);
            });
        }

        // Function to show more sharing options (e.g., copy URL, share on Twitter, LinkedIn)
        function showShareOptions(title, url) {
            const options = [
                { name: 'Copy URL', action: () => copyToClipboard(url) },
                { name: 'Share on Twitter', action: () => {
                    window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`, '_blank');
                }},
                { name: 'Share on LinkedIn', action: () => {
                    window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`, '_blank');
                }}
            ];

            const optionsHtml = options.map(option => `
                <button onclick="option.action();" class="w-full text-left px-4 py-2 hover:bg-gray-100">
                    ${option.name}
                </button>
            `).join('');

            const optionsModal = `
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
                        <h3 class="text-lg font-bold mb-4 text-seait-dark">Share This Post</h3>
                        <div class="space-y-2">
                            ${optionsHtml}
                        </div>
                        <button onclick="closeShareOptions()" class="mt-4 w-full text-seait-orange bg-white border border-seait-orange hover:bg-seait-orange hover:text-white transition py-2 px-4 rounded-md">
                            Close
                        </button>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', optionsModal);
            document.querySelector('.fixed.inset-0.bg-black.bg-opacity-50').addEventListener('click', closeShareOptions);
        }

        // Function to close the share options modal
        function closeShareOptions() {
            document.querySelector('.fixed.inset-0.bg-black.bg-opacity-50').remove();
        }
    </script>
</body>
</html>

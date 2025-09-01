<?php
include('../configuration.php');

$upload_dir = '../big-dump';
$files = array();

if (is_dir($upload_dir)) {
    $dirhandle = opendir($upload_dir);
    if ($dirhandle) {
        while (false !== ($file = readdir($dirhandle))) {
            if ($file != "." && $file != ".." && $file != "bigdump.php" && 
                (preg_match("/\.sql$/i", $file) || preg_match("/\.gz$/i", $file))) {
                $files[] = array(
                    'name' => $file,
                    'size' => filesize($upload_dir . '/' . $file),
                    'date' => date("Y-m-d H:i:s", filemtime($upload_dir . '/' . $file)),
                    'type' => preg_match("/\.sql$/i", $file) ? 'SQL' : 'GZip'
                );
            }
        }
        closedir($dirhandle);
    }
}

if (empty($files)) {
    echo '<div class="text-center py-12">';
    echo '<div class="bg-gray-700 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">';
    echo '<i class="fas fa-folder-open text-gray-400 text-2xl"></i>';
    echo '</div>';
    echo '<h3 class="text-lg font-medium text-white mb-2">No files found</h3>';
    echo '<p class="text-gray-400">Upload your first SQL file to get started</p>';
    echo '</div>';
} else {
    echo '<div class="grid gap-4">';
    
    foreach ($files as $file) {
        $size_mb = round($file['size'] / 1024 / 1024, 2);
        $icon_class = $file['type'] === 'SQL' ? 'fas fa-database text-blue-400' : 'fas fa-archive text-purple-400';
        
        echo '<div class="bg-gray-800 rounded-lg border border-gray-700 p-6 hover:shadow-lg transition-shadow duration-200 hover:border-gray-600">';
        echo '<div class="flex items-center justify-between">';
        echo '<div class="flex items-center space-x-4">';
        echo '<div class="bg-gray-700 p-3 rounded-full">';
        echo '<i class="' . $icon_class . ' text-xl"></i>';
        echo '</div>';
        echo '<div>';
        echo '<h3 class="text-lg font-semibold text-white">' . htmlspecialchars($file['name']) . '</h3>';
        echo '<div class="flex items-center space-x-4 text-sm text-gray-400">';
        echo '<span><i class="fas fa-weight-hanging mr-1"></i>' . $size_mb . ' MB</span>';
        echo '<span><i class="fas fa-calendar mr-1"></i>' . $file['date'] . '</span>';
        echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-900 text-blue-300">' . $file['type'] . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="flex items-center space-x-2">';
        echo '<button onclick="startImport(\'' . htmlspecialchars($file['name']) . '\')" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">';
        echo '<i class="fas fa-play mr-2"></i>Import';
        echo '</button>';
        echo '<button onclick="deleteFile(\'' . htmlspecialchars($file['name']) . '\')" class="inline-flex items-center px-4 py-2 text-sm font-medium text-red-300 bg-red-900 border border-red-700 rounded-lg hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">';
        echo '<i class="fas fa-trash mr-2"></i>Delete';
        echo '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
}
?>

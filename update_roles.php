<?php
$dir = new RecursiveDirectoryIterator('admin');
$iterator = new RecursiveIteratorIterator($dir);
$count = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'dashboard.php') {
        $content = file_get_contents($file->getPathname());
        $modified = preg_replace("/requireRole\(\s*'admin'\s*\);/", "requireRole(['admin', 'kepsek']);", $content);
        $modified = preg_replace("/requireLogin\(\s*\['admin'\]\s*\);/", "requireRole(['admin', 'kepsek']);", $modified);
        if ($modified !== $content) {
            file_put_contents($file->getPathname(), $modified);
            echo "Updated " . $file->getPathname() . "\n";
            $count++;
        }
    }
}
echo "Total updated: $count\n";
?>

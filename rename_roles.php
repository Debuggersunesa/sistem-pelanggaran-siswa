<?php
$dir = new RecursiveDirectoryIterator("c:/xampp/htdocs/sistem_pelanggaran");
$iterator = new RecursiveIteratorIterator($dir);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'rename_roles.php') {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        
        $modified = $content;
        
        // Match specific string literals or words first to avoid messing up variables that shouldn't be touched.
        // Actually, replacing 'guru_bk' to 'kepsek' and 'Guru BK' to 'Kepsek' is safe globally in this application because we are fully replacing the role
        $modified = str_replace(['\'guru_bk\'', '\"guru_bk\"'], ['\'kepsek\'', '\"kepsek\"'], $modified);
        $modified = str_replace(['Guru BK', 'guru_bk'], ['Kepsek', 'kepsek'], $modified);
        // Maybe some files used 'Guru_bk' or 'guru bk' 
        // We will just do the two above which cover 99% of occurrences.
        
        if ($modified !== $content) {
            file_put_contents($path, $modified);
            echo "Updated " . $path . "\n";
        }
    }
}
if (is_dir('c:/xampp/htdocs/sistem_pelanggaran/guru_bk')) {
    rename('c:/xampp/htdocs/sistem_pelanggaran/guru_bk', 'c:/xampp/htdocs/sistem_pelanggaran/kepsek');
    echo "Renamed directory guru_bk to kepsek\n";
}
?>

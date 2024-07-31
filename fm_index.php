<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>File Manager</title>
    <link rel="stylesheet" href="fm_style.css">
</head>
<body>
    <h1>File Manager</h1>

    <?php
    // Корневая папка для файлов
    $rootDir = './uploads/';
    $currentDir = isset($_GET['dir']) ? trim($_GET['dir'], '/') : '';
    $uploadDir = $rootDir . $currentDir;

    // Убедимся, что папка существует
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Функция для записи логов
    function logAction($action) {
        $logFile = 'log.txt';
        $clientIp = $_SERVER['REMOTE_ADDR'];
        $logEntry = date('Y-m-d H:i:s') . " - IP: $clientIp - Action: $action\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    // Обработка загрузки файлов
    function uploadFiles($uploadDir) {
        $allowedTypes = ['xml', 'json', 'txt', 'cfg', 'conf'];
        $files = $_FILES["filesToUpload"];

        for ($i = 0; $i < count($files["name"]); $i++) {
            $target_file = $uploadDir . '/' . basename($files["name"][$i]);
            $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($files["tmp_name"][$i], $target_file)) {
                    echo "The file ". basename($files["name"][$i]). " has been uploaded.<br>";
                    logAction("Uploaded file: " . basename($files["name"][$i]));
                } else {
                    echo "Sorry, there was an error uploading your file: " . basename($files["name"][$i]) . ".<br>";
                    logAction("Failed to upload file: " . basename($files["name"][$i]));
                }
            } else {
                echo "Sorry, only XML, JSON, TXT & CFG files are allowed: " . basename($files["name"][$i]) . ".<br>";
                logAction("Attempted to upload disallowed file type: " . basename($files["name"][$i]));
            }
        }
    }

    // Обработка создания папок
    function createFolder($uploadDir) {
        $folderName = $_POST['folderName'];
        $target_folder = $uploadDir . '/' . $folderName;
        if (!file_exists($target_folder)) {
            mkdir($target_folder, 0777, true);
            logAction("Created folder: $target_folder");
        } else {
            logAction("Failed to create folder (already exists): $target_folder");
        }
    }

    // Рекурсивная функция для удаления папки и её содержимого
    function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            logAction("Directory does not exist: $dir");
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                deleteDirectory($filePath); // Удаление содержимого директории
            } else {
                if (unlink($filePath)) {
                    logAction("Deleted file: $filePath");
                } else {
                    logAction("Failed to delete file: $filePath");
                }
            }
        }
        if (rmdir($dir)) {
            logAction("Deleted folder: $dir");
        } else {
            logAction("Failed to delete folder: $dir");
        }
    }

    // Обработка удаления файлов и папок
    function deleteItem($uploadDir) {
        $name = isset($_GET['name']) ? trim($_GET['name'], '/') : '';
        $target_path = $uploadDir . '/' . $name;

        // Проверка и удаление файла или папки
        if (is_file($target_path)) {
            if (unlink($target_path)) {
                logAction("Deleted file: $target_path");
            } else {
                logAction("Failed to delete file: $target_path");
            }
        } elseif (is_dir($target_path)) {
            deleteDirectory($target_path);
        } else {
            logAction("Attempted to delete non-existent file or directory: $target_path");
        }
    }

    // Обработка скачивания файлов
    function downloadFile($file) {
        $filePath = './uploads/' . trim($file, '/');

        if (file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            
            logAction("Downloaded file: $filePath");
            exit;
        } else {
            echo "File does not exist.";
        }
    }

    // Функция для скачивания выбранных файлов и папок в формате ZIP
    function downloadSelection($files) {
        $zip = new ZipArchive();
        $zipName = 'download_' . date('YmdHis') . '.zip';
        if ($zip->open($zipName, ZipArchive::CREATE) !== TRUE) {
            exit("Unable to create ZIP file.");
        }

        foreach ($files as $file) {
            $filePath = './uploads/' . trim($file, '/');
            if (is_file($filePath)) {
                $zip->addFile($filePath, basename($filePath));
            } elseif (is_dir($filePath)) {
                $dir = $filePath;
                $filesInDir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($filesInDir as $fileInDir) {
                    if ($fileInDir->isFile()) {
                        $relativePath = substr($fileInDir->getPathname(), strlen('./uploads/') + 1);
                        $zip->addFile($fileInDir->getPathname(), $relativePath);
                    }
                }
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipName));
        readfile($zipName);
        unlink($zipName);

        logAction("Downloaded selection as ZIP: $zipName");
        exit;
    }

    // Обработка POST-запросов
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['submit']) && $_POST['submit'] === 'Upload Files') {
            uploadFiles($uploadDir);
        } elseif (isset($_POST['submit']) && $_POST['submit'] === 'Create Folder') {
            createFolder($uploadDir);
        } elseif (isset($_POST['submit']) && $_POST['submit'] === 'Download Selected') {
            if (isset($_POST['files'])) {
                downloadSelection($_POST['files']);
            }
        }
    } elseif (isset($_GET['action']) && $_GET['action'] === 'delete') {
        deleteItem($uploadDir);
    } elseif (isset($_GET['file'])) {
        // Обработка скачивания файла
        downloadFile($_GET['file']);
    }
    ?>

    <!-- Button to Back to root -->
    <div class="back-button">
		<input type="submit" value="Back to root" name="submit" onclick="location.href='?dir='">
    </div>

    <!-- Forms for creating folders and uploading files in two columns -->
    <div class="form-container">
        <!-- Form for uploading files -->
        <div class="form-column">
            <h2>Upload Files</h2>
            <form action="?dir=<?php echo htmlspecialchars($currentDir); ?>" method="post" enctype="multipart/form-data">
                <input type="file" name="filesToUpload[]" id="filesToUpload" multiple>
                <input type="submit" value="Upload Files" name="submit">
            </form>
        </div>

        <!-- Form for creating new folder -->
        <div class="form-column">
            <h2>Create New Folder</h2>
            <form action="?dir=<?php echo htmlspecialchars($currentDir); ?>" method="post">
                <input type="text" name="folderName" placeholder="New Folder Name">
                <input type="submit" value="Create Folder" name="submit">
            </form>
        </div>
    </div>

    <!-- Form for managing files -->
    <form action="?dir=<?php echo htmlspecialchars($currentDir); ?>" method="post">
        <h2>Files and Folders:</h2>
        <ul>
            <?php
            if ($handle = opendir($uploadDir)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {
                        $entryPath = $currentDir ? $currentDir . '/' . $entry : $entry;
                        $entryFullPath = $uploadDir . '/' . $entry;
                        if (is_dir($entryFullPath)) {
                            echo "<li><a href='?dir=" . urlencode($entryPath) . "'>$entry/</a> <input type='checkbox' name='files[]' value='" . urlencode($entryPath) . "'> <a href='?action=delete&name=" . urlencode($entryPath) . "'>Delete</a></li>";
                        } else {
                            echo "<li><a href='?file=" . urlencode($entryPath) . "'>$entry</a> <input type='checkbox' name='files[]' value='" . urlencode($entryPath) . "'> <a href='?action=delete&name=" . urlencode($entryPath) . "'>Delete</a></li>";
                        }
                    }
                }
                closedir($handle);
            }
            ?>
        </ul>
        <input type="submit" value="Download Selected" name="submit">
    </form>
</body>
</html>

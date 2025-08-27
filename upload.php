<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$folders = $conn->query("SELECT * FROM folders WHERE user_id = $user_id");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a valid file.";
    } else {
        $folder_id = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
        $original = $_FILES['file']['name'];
        $tmp = $_FILES['file']['tmp_name'];
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $newName = uniqid() . '.' . $ext;

        // Check for duplicate
        $stmt = $conn->prepare("SELECT id FROM files WHERE user_id = ? AND original_name = ? AND folder_id <=> ?");
        $stmt->bind_param("isi", $user_id, $original, $folder_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "A file with this name already exists in this folder.";
        } else {
            if (!move_uploaded_file($tmp, 'uploads/' . $newName)) {
                $error = "Failed to save the uploaded file.";
            } else {
                $stmt = $conn->prepare("INSERT INTO files (user_id, filename, original_name, folder_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $user_id, $newName, $original, $folder_id);
                if ($stmt->execute()) {
                    $success = "File uploaded successfully!";
                } else {
                    $error = "Failed to store file info.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload File | CloudDrive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #1a1a2e;
            --gray: #6c757d;
            --border-radius: 12px;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f5f7ff 0%, #f0f4ff 100%);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .upload-container {
            width: 100%;
            max-width: 600px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .upload-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 24px;
            text-align: center;
        }
        
        .upload-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .upload-header p {
            opacity: 0.9;
            margin-top: 8px;
            font-size: 0.95rem;
        }
        
        .upload-body {
            padding: 30px;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
        }
        
        .alert.error {
            background-color: #fff0f3;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .alert.success {
            background-color: #f0fdf4;
            color: #166534;
            border-left: 4px solid #166534;
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 500;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group label i {
            color: var(--primary);
        }
        
        select, .file-input-container {
            width: 100%;
            padding: 14px 16px;
            border: 2px dashed #d1d5db;
            border-radius: var(--border-radius);
            background-color: #f9fafb;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        select:focus {
            outline: none;
            border-color: var(--primary);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .file-input-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            min-height: 150px;
            position: relative;
        }
        
        .file-input-container:hover {
            border-color: var(--primary-light);
            background-color: #f0f4ff;
        }
        
        .file-input-container.active {
            border-color: var(--primary);
            background-color: #e6eeff;
        }
        
        .file-input-container i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 12px;
        }
        
        .file-input-container p {
            color: var(--gray);
            margin-bottom: 8px;
        }
        
        .file-input-container .browse-text {
            color: var(--primary);
            font-weight: 500;
            text-decoration: underline;
        }
        
        .file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            top: 0;
            left: 0;
        }
        
        .file-preview {
            margin-top: 12px;
            padding: 12px;
            background-color: #f0f4ff;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 12px;
            display: none;
        }
        
        .file-preview i {
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .file-info {
            flex: 1;
            overflow: hidden;
        }
        
        .file-name {
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .file-size {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .remove-file {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 16px;
            font-size: 1.1rem;
            font-weight: 500;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.2);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .back-link {
            text-align: center;
            margin-top: 24px;
        }
        
        .back-link a {
            color: var(--gray);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        
        .back-link a:hover {
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .upload-container {
                max-width: 100%;
            }
            
            .upload-header {
                padding: 20px;
            }
            
            .upload-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <div class="upload-header">
            <h2><i class="fas fa-cloud-arrow-up"></i> Upload Files</h2>
            <p>Store your files securely in the cloud</p>
        </div>
        
        <div class="upload-body">
            <?php if ($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
                <div class="form-group">
                    <label for="folder_id"><i class="fas fa-folder"></i> Destination Folder</label>
                    <select name="folder_id" id="folder_id">
                        <option value="">-- Root Folder (Main Storage) --</option>
                        <?php while ($folder = $folders->fetch_assoc()): ?>
                            <option value="<?= $folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-file"></i> Select File</label>
                    <div class="file-input-container" id="fileDropArea">
                        <i class="fas fa-cloud-arrow-up"></i>
                        <p>Drag & drop your file here or</p>
                        <span class="browse-text">Browse files</span>
                        <input type="file" name="file" id="file" class="file-input" required>
                    </div>
                    <div class="file-preview" id="filePreview">
                        <i class="fas fa-file-alt"></i>
                        <div class="file-info">
                            <div class="file-name" id="fileName">No file selected</div>
                            <div class="file-size" id="fileSize">-</div>
                        </div>
                        <button type="button" class="remove-file" id="removeFile">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-upload"></i> Upload File
                </button>
            </form>
            
            <div class="back-link">
                <a href="files.php"><i class="fas fa-arrow-left"></i> Back to My Files</a>
            </div>
        </div>
    </div>

    <script>
        // File input handling
        const fileInput = document.getElementById('file');
        const fileDropArea = document.getElementById('fileDropArea');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeFileBtn = document.getElementById('removeFile');
        const uploadForm = document.getElementById('uploadForm');
        
        // Handle file selection
        fileInput.addEventListener('change', function(e) {
            if (this.files.length) {
                updateFileInfo(this.files[0]);
                filePreview.style.display = 'flex';
                fileDropArea.classList.add('active');
            }
        });
        
        // Handle drag and drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileDropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            fileDropArea.classList.add('active');
        }
        
        function unhighlight() {
            fileDropArea.classList.remove('active');
        }
        
        fileDropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length) {
                fileInput.files = files;
                updateFileInfo(files[0]);
                filePreview.style.display = 'flex';
            }
        }
        
        // Update file info display
        function updateFileInfo(file) {
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
        }
        
        // Remove file
        removeFileBtn.addEventListener('click', function() {
            fileInput.value = '';
            filePreview.style.display = 'none';
            fileDropArea.classList.remove('active');
        });
        
        // Form validation
        uploadForm.addEventListener('submit', function(e) {
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a file to upload');
                fileDropArea.classList.add('active');
                setTimeout(() => fileDropArea.classList.remove('active'), 1000);
            }
        });
    </script>
</body>
</html>
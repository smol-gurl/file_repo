<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Please <a href='login.php'>login</a> first.");
}

$user_id = $_SESSION['user_id'];
$current_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Get all folders for the dropdown
$folders = $conn->query("SELECT * FROM folders WHERE user_id = $user_id ORDER BY name");

// Get files for the current folder or root
if ($current_folder) {
    $result = $conn->query("SELECT * FROM files WHERE user_id = $user_id AND folder_id = $current_folder");
} else {
    $result = $conn->query("SELECT * FROM files WHERE user_id = $user_id AND folder_id IS NULL");
}

// Handle messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Files | Cloud Storage</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #560bad;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7ff;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .logo i {
            font-size: 1.8rem;
        }
        
        .logout-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--danger);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .logout-link:hover {
            color: #d0006f;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }
        
        .page-title i {
            color: var(--primary);
        }
        
        .message {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .message.error {
            background-color: #fdecea;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .folder-nav {
            background-color: white;
            padding: 1.25rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .folder-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .folder-select {
            flex: 1;
            min-width: 200px;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            font-family: inherit;
            background-color: var(--light);
            transition: var(--transition);
        }
        
        .folder-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .file-list {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .file-list-header {
            display: grid;
            grid-template-columns: 3fr 1fr;
            padding: 1rem 1.5rem;
            background-color: var(--light);
            font-weight: 600;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .file-item {
            display: grid;
            grid-template-columns: 3fr 1fr;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            transition: var(--transition);
        }
        
        .file-item:hover {
            background-color: #f9f9f9;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .file-icon {
            font-size: 1.5rem;
            color: var(--primary-light);
        }
        
        .file-name {
            font-weight: 500;
        }
        
        .file-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--dark);
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: 50%;
            width: 2.25rem;
            height: 2.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-btn:hover {
            background-color: #f0f0f0;
            color: var(--primary);
        }
        
        .action-btn.view {
            color: var(--success);
        }
        
        .action-btn.rename {
            color: var(--warning);
        }
        
        .action-btn.delete {
            color: var(--danger);
        }
        
        .rename-form {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            width: 100%;
        }
        
        .rename-form input {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
        }
        
        .rename-form input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .rename-form .btn {
            padding: 0.5rem 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .upload-section {
            margin-top: 2rem;
            text-align: center;
        }
        
        .upload-link {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2rem;
            background-color: var(--primary);
            color: white;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .upload-link:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .file-item {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .file-actions {
                justify-content: flex-start;
            }
            
            .folder-controls {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-cloud"></i>
                <span>CloudDrive</span>
            </div>
            <a href="logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </header>
        
        <h1 class="page-title">
            <i class="fas fa-folder-open"></i> 
            <?= $current_folder ? "Folder Contents" : "My Files" ?>
        </h1>
        
        <?php if (!empty($error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        
        <div class="folder-nav">
            <div class="folder-controls">
                <select name="folder" class="folder-select" onchange="window.location.href='?folder='+this.value">
                    <option value="">-- All Files (Root) --</option>
                    <?php while ($folder = $folders->fetch_assoc()): ?>
                        <option value="<?= $folder['id'] ?>" <?= $current_folder == $folder['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($folder['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <a href="folders.php" class="btn btn-outline">
                    <i class="fas fa-folder-plus"></i> Manage Folders
                </a>
            </div>
        </div>
        
        <div class="file-list">
            <div class="file-list-header">
                <div>File Name</div>
                <div>Actions</div>
            </div>
            
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="file-item">
                        <div class="file-info">
                            <i class="fas fa-file-alt file-icon"></i>
                            <div>
                                <div class="file-name"><?= htmlspecialchars($row['original_name']) ?></div>
                                <div id="rename-form-<?= $row['id'] ?>" style="display: none;">
                                    <form method="POST" action="rename.php" class="rename-form">
                                        <input type="hidden" name="type" value="file">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="text" name="new_name" value="<?= htmlspecialchars($row['original_name']) ?>" required>
                                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                        <button type="button" onclick="hideRenameForm(<?= $row['id'] ?>)" class="btn btn-outline" style="padding: 0.5rem 1rem;">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="file-actions">
                            <a href="uploads/<?= htmlspecialchars($row['filename']) ?>" class="action-btn view" target="_blank" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button onclick="showRenameForm(<?= $row['id'] ?>)" class="action-btn rename" title="Rename">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="delete.php?file=<?= urlencode($row['filename']) ?>" class="action-btn delete" title="Delete" onclick="return confirm('Are you sure you want to delete this file?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No files found in this folder</h3>
                    <p>Upload your first file to get started</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="upload-section">
            <a href="upload.php<?= $current_folder ? '?folder='.$current_folder : '' ?>" class="upload-link">
                <i class="fas fa-cloud-upload-alt"></i> Upload New File
            </a>
        </div>
    </div>

    <script>
        function showRenameForm(fileId) {
            // Hide all rename forms first
            document.querySelectorAll('[id^="rename-form-"]').forEach(form => {
                form.style.display = 'none';
            });
            
            // Show the selected rename form
            const renameForm = document.getElementById('rename-form-' + fileId);
            renameForm.style.display = 'block';
            
            // Focus the input field
            const inputField = renameForm.querySelector('input[type="text"]');
            inputField.focus();
            inputField.select();
        }
        
        function hideRenameForm(fileId) {
            document.getElementById('rename-form-' + fileId).style.display = 'none';
        }
    </script>
</body>
</html>
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

// Create folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    $folder_name = trim($_POST['folder_name']);
    
    if (empty($folder_name)) {
        $error = "Folder name cannot be empty";
    } else {
        // Check if folder already exists for this user
        $stmt = $conn->prepare("SELECT id FROM folders WHERE user_id = ? AND name = ?");
        $stmt->bind_param("is", $user_id, $folder_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "A folder with this name already exists";
        } else {
            $stmt = $conn->prepare("INSERT INTO folders (user_id, name) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $folder_name);
            
            if ($stmt->execute()) {
                $success = "Folder created successfully";
            } else {
                $error = "Failed to create folder";
            }
        }
    }
}

// Rename folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_folder'])) {
    $folder_id = $_POST['folder_id'];
    $new_name = trim($_POST['new_name']);
    
    if (empty($new_name)) {
        $error = "Folder name cannot be empty";
    } else {
        // Check if new name already exists
        $stmt = $conn->prepare("SELECT id FROM folders WHERE user_id = ? AND name = ? AND id != ?");
        $stmt->bind_param("isi", $user_id, $new_name, $folder_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "A folder with this name already exists";
        } else {
            $stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $new_name, $folder_id, $user_id);
            
            if ($stmt->execute()) {
                $success = "Folder renamed successfully";
            } else {
                $error = "Failed to rename folder";
            }
        }
    }
}

// Get all folders for the user
$folders = $conn->query("SELECT * FROM folders WHERE user_id = $user_id ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Folders | Cloud Storage</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --danger: #f72585;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 20px;
            color: var(--dark);
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        h2 {
            color: var(--primary);
            text-align: center;
        }
        
        .folder-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }
        
        .folder-actions a, .folder-actions button {
            margin-left: 10px;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .view-btn {
            background-color: var(--accent);
            color: white;
            border: none;
        }
        
        .rename-btn {
            background-color: var(--success);
            color: white;
            border: none;
        }
        
        .delete-btn {
            background-color: var(--danger);
            color: white;
            border: none;
        }
        
        .create-folder {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .submit-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .submit-btn:hover {
            background-color: var(--secondary);
        }
        
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            text-align: center;
        }
        
        .error {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
        }
        
        .success {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }
        
        .navigation-links {
            margin-top: 20px;
            text-align: center;
        }
        
        .navigation-links a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-folder"></i> Manage Folders</h2>
        
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
        
        <div class="create-folder">
            <h3><i class="fas fa-plus-circle"></i> Create New Folder</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="folder_name">Folder Name</label>
                    <input type="text" id="folder_name" name="folder_name" required>
                </div>
                <button type="submit" name="create_folder" class="submit-btn">
                    <i class="fas fa-folder-plus"></i> Create Folder
                </button>
            </form>
        </div>
        
        <h3><i class="fas fa-folder-open"></i> Your Folders</h3>
        
        <?php if ($folders->num_rows > 0): ?>
            <?php while ($folder = $folders->fetch_assoc()): ?>
                <div class="folder-item">
                    <span>
                        <i class="fas fa-folder"></i> <?= htmlspecialchars($folder['name']) ?>
                    </span>
                    <div class="folder-actions">
                        <a href="files.php?folder=<?= $folder['id'] ?>" class="view-btn">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <button onclick="showRenameForm(<?= $folder['id'] ?>, '<?= htmlspecialchars($folder['name']) ?>')" class="rename-btn">
                            <i class="fas fa-edit"></i> Rename
                        </button>
                        <a href="delete.php?type=folder&id=<?= $folder['id'] ?>" class="delete-btn" onclick="return confirm('Are you sure? All files in this folder will be moved to root.')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                
                <div id="rename-form-<?= $folder['id'] ?>" style="display: none; margin-bottom: 20px;">
                    <form method="POST">
                        <input type="hidden" name="folder_id" value="<?= $folder['id'] ?>">
                        <div class="form-group">
                            <label for="new_name">New Name</label>
                            <input type="text" id="new_name_<?= $folder['id'] ?>" name="new_name" value="<?= htmlspecialchars($folder['name']) ?>" required>
                        </div>
                        <button type="submit" name="rename_folder" class="submit-btn">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button type="button" onclick="hideRenameForm(<?= $folder['id'] ?>)" class="submit-btn" style="background-color: #6c757d;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align: center;">No folders created yet.</p>
        <?php endif; ?>
        
        <div class="navigation-links">
            <a href="files.php"><i class="fas fa-file-alt"></i> View All Files</a>
            <a href="upload.php"><i class="fas fa-cloud-upload-alt"></i> Upload File</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <script>
        function showRenameForm(folderId, currentName) {
            // Hide all rename forms first
            document.querySelectorAll('[id^="rename-form-"]').forEach(form => {
                form.style.display = 'none';
            });
            
            // Show the selected rename form
            const form = document.getElementById('rename-form-' + folderId);
            form.style.display = 'block';
            
            // Focus on the input field
            document.getElementById('new_name_' + folderId).focus();
        }
        
        function hideRenameForm(folderId) {
            document.getElementById('rename-form-' + folderId).style.display = 'none';
        }
    </script>
</body>
</html>
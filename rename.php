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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $id = $_POST['id'];
    $new_name = trim($_POST['new_name']);
    
    if (empty($new_name)) {
        $error = "Name cannot be empty";
    } else {
        if ($type === 'file') {
            // Rename file
            $stmt = $conn->prepare("SELECT filename FROM files WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $file = $result->fetch_assoc();
                $old_path = "uploads/" . $file['filename'];
                
                // Check if new name already exists
                $stmt = $conn->prepare("SELECT id FROM files WHERE original_name = ? AND user_id = ? AND id != ?");
                $stmt->bind_param("sii", $new_name, $user_id, $id);
                $stmt->execute();
                $check_result = $stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "A file with this name already exists";
                } else {
                    // Update database
                    $stmt = $conn->prepare("UPDATE files SET original_name = ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("sii", $new_name, $id, $user_id);
                    
                    if ($stmt->execute()) {
                        $success = "File renamed successfully";
                    } else {
                        $error = "Failed to rename file";
                    }
                }
            } else {
                $error = "File not found";
            }
        } elseif ($type === 'folder') {
            // Rename folder
            $stmt = $conn->prepare("SELECT name FROM folders WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                // Check if new name already exists
                $stmt = $conn->prepare("SELECT id FROM folders WHERE name = ? AND user_id = ? AND id != ?");
                $stmt->bind_param("sii", $new_name, $user_id, $id);
                $stmt->execute();
                $check_result = $stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = "A folder with this name already exists";
                } else {
                    // Update database
                    $stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("sii", $new_name, $id, $user_id);
                    
                    if ($stmt->execute()) {
                        $success = "Folder renamed successfully";
                    } else {
                        $error = "Failed to rename folder";
                    }
                }
            } else {
                $error = "Folder not found";
            }
        }
    }
    
    if (!empty($error)) {
        $_SESSION['error'] = $error;
    } elseif (!empty($success)) {
        $_SESSION['success'] = $success;
    }
    
    header("Location: " . ($type === 'file' ? 'files.php' : 'folders.php'));
    exit();
}
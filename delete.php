<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['file'])) {
    // Delete file
    $file = basename($_GET['file']);
    
    // Fetch file and check ownership
    $stmt = $conn->prepare("SELECT * FROM files WHERE filename = ? AND user_id = ?");
    $stmt->bind_param("si", $file, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $filePath = "uploads/" . $file;

        if (file_exists($filePath)) {
            unlink($filePath); // delete file from folder
        }

        // delete from DB
        $stmt = $conn->prepare("DELETE FROM files WHERE filename = ? AND user_id = ?");
        $stmt->bind_param("si", $file, $user_id);
        $stmt->execute();
        
        $_SESSION['success'] = "File deleted successfully";
    } else {
        $_SESSION['error'] = "File not found or you don't have permission";
    }
    
    header("Location: files.php");
    exit();
} elseif (isset($_GET['type']) && $_GET['type'] === 'folder' && isset($_GET['id'])) {
    // Delete folder (actually just move files to root and delete folder)
    $folder_id = (int)$_GET['id'];
    
    // Verify folder ownership
    $stmt = $conn->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $folder_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Move all files to root (set folder_id to NULL)
        $stmt = $conn->prepare("UPDATE files SET folder_id = NULL WHERE folder_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $folder_id, $user_id);
        $stmt->execute();
        
        // Delete the folder
        $stmt = $conn->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $folder_id, $user_id);
        $stmt->execute();
        
        $_SESSION['success'] = "Folder deleted. Files moved to root folder.";
    } else {
        $_SESSION['error'] = "Folder not found or you don't have permission";
    }
    
    header("Location: folders.php");
    exit();
} else {
    header("Location: files.php");
    exit();
}
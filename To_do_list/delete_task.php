<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get task ID from the URL
if (isset($_GET['task_id'])) {
    $task_id = $_GET['task_id'];

    // Delete the task query
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $task_id);

    if ($stmt->execute()) {
        // Task deleted successfully, redirect back to the project page
        header("Location: index.php?project_id=" . $_GET['project_id']);
    } else {
        // Handle failure
        echo "Failed to delete task.";
    }
}
?>

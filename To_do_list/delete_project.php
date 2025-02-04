<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");  // Redirect to login page if not logged in
    exit();
}

// Check if project_id is set in the GET request
if (isset($_GET['project_id'])) {
    $project_id = $_GET['project_id'];
    $user_id = $_SESSION['user_id'];

    // Prepare the delete statement
    $stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $project_id, $user_id);

    if ($stmt->execute()) {
        // Redirect back to the index page after deletion
        header("Location: index.php");
        exit();
    } else {
        // Handle error
        echo "Error deleting project: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>

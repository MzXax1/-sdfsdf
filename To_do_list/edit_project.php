<?php
session_start();
require 'db.php'; // Include your database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectId = $_POST['project_id'];
    $projectName = $_POST['project_name'];

    // Validate input
    if (!empty($projectId) && !empty($projectName)) {
        // Prepare and execute the update query
        $stmt = $conn->prepare("UPDATE projects SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $projectName, $projectId);

        if ($stmt->execute()) {
            // Redirect or return success message
            header("Location: index.php?project_id=$projectId");
            exit();
        } else {
            // Handle error
            echo "Error updating project: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Invalid input.";
    }
}

$conn->close();
?>
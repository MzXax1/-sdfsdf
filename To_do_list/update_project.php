<?php
require 'db.php'; // Koneksi ke database

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'], $_POST['project_name'])) {
    $project_id = intval($_POST['project_id']);
    $project_name = trim($_POST['project_name']);

    if (!empty($project_name)) {
        $stmt = $conn->prepare("UPDATE projects SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $project_name, $project_id);

        if ($stmt->execute()) {
            header("Location: index.php");
            exit;
        } else {
            echo "Failed to update project.";
        }
    } else {
        echo "Project name cannot be empty.";
    }
} else {
    echo "Invalid request.";
}
?>

<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_id'])) {
    $taskId = $_POST['id'];
    $taskName = $_POST['name'];
    $taskStatus = $_POST['status'];

    // Database connection
    include('db_connect.php');

    // Update query
    $query = "UPDATE tasks SET name = ?, is_completed = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sii', $taskName, $taskStatus, $taskId);

    if ($stmt->execute()) {
        header("Location: index.php?project_id=$projectId"); // Redirect after update
        exit();
    } else {
        echo "Error updating task";
    }

    $stmt->close();
    $conn->close();
}
?>

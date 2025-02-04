<?php
include 'db.php';

// Ensure that required fields are present
if (isset($_POST['task_name']) && isset($_POST['status']) && isset($_POST['project_id'])) {
    $task_name = $conn->real_escape_string($_POST['task_name']);
    $status = $conn->real_escape_string($_POST['status']);
    $project_id = (int)$_POST['project_id'];

    // Insert new task into database
    $sql = "INSERT INTO tasks (task_name, status, project_id) VALUES ('$task_name', '$status', $project_id)";
    
    if ($conn->query($sql) === TRUE) {
        // Return success response
        echo json_encode(['success' => true, 'task_name' => $task_name, 'status' => $status]);
    } else {
        // Handle error
        echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
}
?>

<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get task data from the form
    $task_name = isset($_POST['task_name']) ? $_POST['task_name'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'Pending';
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

    if (!empty($task_name) && $project_id > 0) {
        // Sanitize inputs
        $task_name = $conn->real_escape_string($task_name);
        $status = $conn->real_escape_string($status);

        // Insert the task into the database
        $sql = "INSERT INTO tasks (task_name, status, project_id) VALUES ('$task_name', '$status', $project_id)";
        if ($conn->query($sql) === TRUE) {
            // Respond with success and task data
            $response = [
                'success' => true,
                'task_name' => $task_name,
                'status' => $status
            ];
        } else {
            // Respond with error
            $response = [
                'success' => false,
                'message' => 'Error: ' . $conn->error
            ];
        }
    } else {
        $response = [
            'success' => false,
            'message' => 'Task name and project ID are required.'
        ];
    }

    // Send JSON response
    echo json_encode($response);
}
?>

<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get project name from form
    $project_name = isset($_POST['project_name']) ? $_POST['project_name'] : '';

    if (!empty($project_name)) {
        // Sanitize input
        $project_name = $conn->real_escape_string($project_name);

        // Insert the project into the database
        $sql = "INSERT INTO projects (name) VALUES ('$project_name')";
        if ($conn->query($sql) === TRUE) {
            // Respond with success and new project ID
            $response = [
                'success' => true,
                'project_id' => $conn->insert_id, // Get the ID of the newly inserted project
                'project_name' => $project_name
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
            'message' => 'Project name is required.'
        ];
    }

    // Send JSON response
    echo json_encode($response);
}
?>

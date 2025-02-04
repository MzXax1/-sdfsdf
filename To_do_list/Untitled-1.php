<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");  // Redirect to login page if not logged in
    exit();
}

// Initialize variables
$project_id = isset($_GET['project_id']) ? $_GET['project_id'] : 0;
$projectName = "All Projects";
$resultTasks = null;
$resultProjects = $conn->query("SELECT * FROM projects");

// Handle adding a project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_name'])) {
    $project_name = $_POST['project_name'];

    // Get user_id from session
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO projects (name, user_id) VALUES (?, ?)");
    $stmt->bind_param("si", $project_name, $user_id);
    $stmt->execute();
    $project_id = $conn->insert_id;
    $response = [
        'success' => true,
        'project_id' => $project_id,
        'project_name' => $project_name
    ];
    echo json_encode($response);
    exit;
}

// Handle adding a task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_name']) && isset($_POST['project_id'])) {
    $task_name = $_POST['task_name'];
    $status = $_POST['status'];  // Status task (Pending, In Progress, Completed)
    $project_id = $_POST['project_id'];  // Project ID where the task will be added

    // Ensure the status value is one of the expected values (0, 1, 2)
    switch ($status) {
        case 'Pending':
            $status = 0;
            break;
        case 'In Progress':
            $status = 1;
            break;
        case 'Completed':
            $status = 2;
            break;
        default:
            $status = 0; // Default to Pending if invalid value is provided
    }

    // Insert task query
    $stmt = $conn->prepare("INSERT INTO tasks (name, is_completed, project_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $task_name, $status, $project_id);

    if ($stmt->execute()) {
        $task_id = $conn->insert_id;  // Get the newly created task ID
        $response = [
            'success' => true,
            'task_id' => $task_id,
            'task_name' => $task_name,
            'status' => $status
        ];
        echo json_encode($response);  // Return JSON response
    } else {
        // If task insertion fails
        $response = [
            'success' => false,
            'message' => 'Failed to add task'
        ];
        echo json_encode($response);
    }

    exit;
}



// Fetch tasks for a specific project or all projects
if ($project_id && is_numeric($project_id)) {
    $sqlTasks = "SELECT * FROM tasks WHERE project_id = $project_id";
    $resultTasks = $conn->query($sqlTasks);

    // Get project name for header
    $sqlProject = "SELECT name FROM projects WHERE id = $project_id";
    $resultProject = $conn->query($sqlProject);
    if ($resultProject && $resultProject->num_rows > 0) {
        $projectName = $resultProject->fetch_assoc()['name'];
    } else {
        $projectName = "Unknown Project";
    }
} else {
    // Fetch tasks for all projects when no specific project is selected
    $sqlTasks = "SELECT tasks.*, projects.name as project_name FROM tasks JOIN projects ON tasks.project_id = projects.id";
    $resultTasks = $conn->query($sqlTasks);
    $projectName = "All Projects";
}

// Handle editing a task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit-task_name']) && isset($_POST['task_id'])) {
    $task_name = $_POST['edit-task_name'];
    $status = $_POST['edit-status'];  // Status task (Pending, In Progress, Completed)
    $task_id = $_POST['task_id'];  // Task ID from the form

    // Ensure the status value is one of the expected values (0, 1, 2)
    switch ($status) {
        case 'Pending':
            $status = 0;
            break;
        case 'In Progress':
            $status = 1;
            break;
        case 'Completed':
            $status = 2;
            break;
        default:
            $status = 0; // Default to Pending if invalid value is provided
    }

    // Update task query
    $stmt = $conn->prepare("UPDATE tasks SET name = ?, is_completed = ? WHERE id = ?");
    $stmt->bind_param("sii", $task_name, $status, $task_id);

    if ($stmt->execute()) {
        // Task was updated successfully
        $response = [
            'success' => true,
            'task_id' => $task_id,
            'task_name' => $task_name,
            'status' => $status
        ];
        echo json_encode($response);  // Return JSON response
        header("Location: index.php?project_id=" . $project_id);
        exit;
    } else {
        // If task update fails
        $response = [
            'success' => false,
            'message' => 'Failed to update task'
        ];
        echo json_encode($response);
    }

    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks for <?= htmlspecialchars($projectName); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: #ffffff;
            margin: 8% auto;
            padding: 20px;
            border-radius: 12px;
            width: 400px;
            box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: #007bff;
            color: white;
            padding: 15px;
            font-size: 18px;
        }

        .modal-body input, .modal-body select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 6px;
            font-size: 14px;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }

        .logout-btn {
            width: 100%;
            padding: 12px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }

        .logout-btn:hover {
            background-color: #c82333;
        }

        /* Task List Styling */
        .task-list {
            margin-top: 20px;
        }

        .task-item {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            background-color: #f9f9f9;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            align-items: center;
        }

        .task-item .task-name {
            font-size: 16px;
            font-weight: bold;
        }

        .task-item .task-status {
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 6px;
            color: white;
        }

        .task-item .task-status.pending {
            background-color: #ffc107;
        }

        .task-item .task-status.in-progress {
            background-color: #007bff;
        }

        .task-item .task-status.completed {
            background-color: #28a745;
        }

        .task-item button {
            background-color: #007bff;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .task-item button:hover {
            background-color: #0056b3;
        }

        .task-actions {
            display: flex;
            gap: 8px;
        }

        /* Add Task Button */
        .add-task-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        .add-task-btn:hover {
            background-color: #45a049;
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Black with opacity */
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            width: 300px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close-btn {
            background-color: transparent;
            border: none;
            font-size: 18px;
            cursor: pointer;
        }

        .modal-body input,
        .modal-body textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .submit-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            width: 100%;
            font-size: 16px;
        }

        .submit-btn:hover {
            background-color: #45a049;
        }

        .modal-footer {
            text-align: center;
        }

        /* Close Modal when clicked outside */
        .modal {
            display: none;
        }
        .modal.open {
            display: flex;
        }

        /* Task Action Buttons (Edit and Delete) */
        .task-item button {
            padding: 8px 16px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin: 0 5px;
            transition: background-color 0.3s ease;
        }

        .task-item button:hover {
            background-color: #218838;
        }

        /* Delete Button */
        .task-item button.delete-btn {
            background-color: #dc3545;
        }

        .task-item button.delete-btn:hover {
            background-color: #c82333;
        }

    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <h2>Projects</h2>
            <ul class="menu" id="project-list">
                <li class="menu-item">
                    <a href="index.php">All Projects</a>
                </li>
                <?php while ($project = $resultProjects->fetch_assoc()) { ?>
                    <li class="menu-item">
                        <a href="index.php?project_id=<?= $project['id']; ?>"><?= htmlspecialchars($project['name']); ?></a>
                    </li>
                <?php } ?>
            </ul>
            <button class="add-project-btn" onclick="openProjectModal()">+ Add Project</button>

            <!-- Logout Button -->
            <?php if (isset($_SESSION['user_id'])) { ?>
                <form method="POST" action="logout.php">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            <?php } ?>
        </aside>

        <main class="content">
            <h1>Tasks for <?= htmlspecialchars($projectName ?? "Unknown Project"); ?></h1>

            <!-- Remove the 'Add Task' button from the 'All Projects' section -->
            <?php if ($project_id !== 0) { ?>
                <button class="add-task-btn" onclick="openTaskModal()">+ Add Task</button>
            <?php } ?>

            <div class="task-list">
                <?php if ($resultTasks && $resultTasks->num_rows > 0) { ?>
                    <?php while ($task = $resultTasks->fetch_assoc()) {
                        // Map status code to string and class
                        $status = '';
                        $statusClass = '';
                        switch ($task['is_completed']) {
                            case 0:
                                $status = 'Pending';
                                $statusClass = 'pending';
                                break;
                            case 1:
                                $status = 'In Progress';
                                $statusClass = 'in-progress';
                                break;
                            case 2:
                                $status = 'Completed';
                                $statusClass = 'completed';
                                break;
                        }

                        // Get the project name for the task
                        if ($project_id == 0) {
                            // When viewing All Projects, get the project name directly from the project data
                            $sqlProjectName = "SELECT name FROM projects WHERE id = " . $task['project_id'];
                            $resultProjectName = $conn->query($sqlProjectName);
                            $projectNameForTask = $resultProjectName && $resultProjectName->num_rows > 0 ? $resultProjectName->fetch_assoc()['name'] : "Unknown Project";
                        } else {
                            // When viewing a specific project, use the project name from the main project query
                            $projectNameForTask = $projectName;
                        }
                    ?>
                        <div class="task-item">
                            <div class="task-name"><?= htmlspecialchars($task['name']); ?></div>
                            <div class="task-status <?= $statusClass; ?>"><?= htmlspecialchars($status); ?></div>
                            <div class="task-project"><?= htmlspecialchars($projectNameForTask); ?></div> <!-- Display project name -->
                            <div class="task-actions">
                                <?php if ($project_id !== 0) { ?>
                                    <button onclick="openEditTaskModal(<?= $task['id']; ?>, '<?= htmlspecialchars($task['name']); ?>', '<?= htmlspecialchars($status); ?>')">Edit</button>
                                    <button onclick="deleteTask(<?= $task['id']; ?>)">Delete</button>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <p>No tasks available for this project.</p>
                <?php } ?>
            </div>
        </main>


    </div>

    <!-- Modal for Adding Project -->
    <div id="project-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Project</h3>
                <span class="close-btn" onclick="closeProjectModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="add-project-form" method="POST">
                    <label for="project_name">Project Name:</label>
                    <input type="text" name="project_name" id="project_name" placeholder="Enter project name" required>
                    <button type="submit" class="submit-btn">Add Project</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for adding task -->
    <div class="modal" id="task-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Task</h2>
                <button class="close-btn" onclick="closeTaskModal()">X</button>
            </div>
            <div class="modal-body">
                <input type="text" id="task-name" placeholder="Task Name" required>
                <select id="task-status" required>
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                </select>
                <div class="modal-footer">
                    <button class="submit-btn" onclick="addTask()">Save Task</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal for Editing Task -->
    <div id="edit-task-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Task</h3>
                <span class="close-btn" onclick="closeEditTaskModal()">&times;</span>
            </div>
            <div class="modal-body">
            <form id="edit-task-form" method="POST" onsubmit="event.preventDefault(); confirmEditTask();">
                <label for="edit-task_name">Task Name:</label>
                <input type="text" name="edit-task_name" id="edit-task_name" required>

                <label for="edit-status">Status:</label>
                <select name="edit-status" id="edit-status" required>
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                </select>

                <input type="hidden" name="task_id" id="edit-task_id">

                <button type="submit" class="submit-btn">Save Changes</button>
            </form>
            </div>
        </div>
    </div>

    <script>
        function openTaskModal() {
            document.getElementById('task-modal').style.display = 'block';
        }

        function closeTaskModal() {
            document.getElementById('task-modal').style.display = 'none';
        }

        // Open the Edit Task Modal and populate the fields with current task data
        function openEditTaskModal(taskId, taskName, taskStatus) {
            document.getElementById('edit-task_name').value = taskName;
            document.getElementById('edit-status').value = taskStatus;
            document.getElementById('edit-task_id').value = taskId;
            document.getElementById('edit-task-modal').style.display = 'block';
        }

        function closeEditTaskModal() {
            document.getElementById('edit-task-modal').style.display = 'none';
        }

        function closeEditTaskModal() {
            document.getElementById('edit-task-modal').style.display = 'none';
        }

        // Handle task deletion
        function deleteTask(taskId) {
            if (confirm('Are you sure you want to delete this task?')) {
                // Make a POST request to delete the task
                window.location.href = 'delete_task.php?task_id=' + taskId;
            }
        }

        function addTask() {
            const taskName = document.getElementById('task-name').value;
            const taskStatus = document.getElementById('task-status').value;
            const projectId = <?= $project_id ?>;  // Ensure this is dynamically passed from PHP

            // Perform a basic validation
            if (taskName.trim() === '') {
                alert('Please enter a task name');
                return;
            }

            // AJAX call to send the task data to the server
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'index.php', true); // Send the POST request to the current page (index.php)
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('Task added successfully');
                        location.reload();  // Reload to update task list
                    } else {
                        alert('Error adding task');
                    }
                }
            };
            xhr.send(`task_name=${taskName}&status=${taskStatus}&project_id=${projectId}`);
        }

        // Handle task deletion using SweetAlert2
        function deleteTask(taskId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to the PHP delete task logic
                    window.location.href = 'delete_task.php?task_id=' + taskId;
                }
            });
        }

        // Confirm before editing the task with SweetAlert2
        function confirmEditTask() {
            const taskId = document.getElementById('edit-task_id').value;
            const taskName = document.getElementById('edit-task_name').value;
            const taskStatus = document.getElementById('edit-status').value;

            Swal.fire({
                title: 'Are you sure?',
                text: "You want to edit this task?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, save changes!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the edit form if confirmed
                    document.getElementById('edit-task-form').submit();
                }
            });
        }

        // Open the Add Task Modal
        function openTaskModal() {
            document.getElementById('task-modal').style.display = 'block';
        }

        function closeTaskModal() {
            document.getElementById('task-modal').style.display = 'none';
        }

        // Open the Add Project Modal
        function openProjectModal() {
            console.log("openProjectModal triggered"); // Debugging
            document.getElementById('project-modal').style.display = 'block';
        }

        // Close the Add Project Modal
        function closeProjectModal() {
            document.getElementById('project-modal').style.display = 'none';
        }

        // Add project functionality
        function addProject() {
            const projectName = document.getElementById('project-name').value;
            
            if (!projectName) {
                alert("Project name cannot be empty.");
                return;
            }

            const data = new FormData();
            data.append('project_name', projectName);

            fetch('index.php', {
                method: 'POST',
                body: data
            }).then(response => response.json()).then(result => {
                if (result.success) {
                    window.location.href = 'index.php?project_id=' + result.project_id;
                } else {
                    alert('Failed to add project');
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>

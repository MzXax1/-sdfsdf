<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login page if not logged in
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
    header("Location: index.php?project_id=" . $project_id);
    exit;
}
// Handle editing a project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_project_id']) && isset($_POST['new_project_name'])) {
    $edit_project_id = $_POST['edit_project_id'];
    $new_project_name = $_POST['new_project_name'];

    $stmt = $conn->prepare("UPDATE projects SET name = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_project_name, $edit_project_id, $_SESSION['user_id']);
    $stmt->execute();

    header("Location: index.php?project_id=" . $edit_project_id);
    exit();
}

// Handle adding a task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_name']) && isset($_POST['project_id'])) {
    $task_name = $_POST['task_name'];
    $status = $_POST['status']; // Status task (Pending, In Progress, Completed)
    $project_id = $_POST['project_id']; // Project ID where the task will be added
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : NULL; // Default due_date to NULL if not provided

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
    $stmt = $conn->prepare("INSERT INTO tasks (name, is_completed, project_id, due_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siis", $task_name, $status, $project_id, $due_date);

    if ($stmt->execute()) {
        $task_id = $conn->insert_id; // Get the newly created task ID
        $response = [
            'success' => true,
            'task_id' => $task_id,
            'task_name' => $task_name,
            'status' => $status
        ];
        echo json_encode($response); // Return JSON response
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
    $status = $_POST['edit-status']; // Status task (Pending, In Progress, Completed)
    $task_id = $_POST['task_id']; // Task ID from the form
    $due_date = isset($_POST['edit-due_date']) ? $_POST['edit-due_date'] : NULL; // Default to NULL if not provided

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
    $stmt = $conn->prepare("UPDATE tasks SET name = ?, is_completed = ?, due_date = ? WHERE id = ?");
    $stmt->bind_param("sisi", $task_name, $status, $due_date, $task_id);

    if ($stmt->execute()) {
        // Task was updated successfully
        $response = [
            'success' => true,
            'task_id' => $task_id,
            'task_name' => $task_name,
            'status' => $status
        ];
        echo json_encode($response); // Return JSON response
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
    <title>Tasks for
        <?= htmlspecialchars($projectName); ?>
    </title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Modal Styling */
        .modal {
            display: none;
            /* Hidden by default */
            position: fixed;
            /* Stay in place */
            z-index: 1000;
            /* Sit on top */
            left: 0;
            top: 0;
            width: 100%;
            /* Full width */
            height: 100%;
            /* Full height */
            background-color: rgba(0, 0, 0, 0.6);
            /* Black with opacity */
            backdrop-filter: blur(4px);
            /* Blur effect */
        }

        .modal-content {
            background: #ffffff;
            /* White background */
            margin: 8% auto;
            /* Centered */
            padding: 20px;
            /* Padding inside the modal */
            border-radius: 12px;
            /* Rounded corners */
            width: 400px;
            /* Fixed width */
            box-shadow: 0px 4px 20px rgba(0, 0, 0, 0.2);
            /* Shadow effect */
        }

        .modal-header {
            background: #007bff;
            /* Blue background */
            color: white;
            /* White text */
            padding: 15px;
            /* Padding inside the header */
            font-size: 18px;
            /* Font size */
            border-top-left-radius: 12px;
            /* Rounded corners */
            border-top-right-radius: 12px;
            /* Rounded corners */
        }

        .modal-body input,
        .modal-body select {
            width: 100%;
            /* Full width */
            padding: 10px;
            /* Padding inside input */
            margin-bottom: 15px;
            /* Space below input */
            border-radius: 6px;
            /* Rounded corners */
            border: 1px solid #ccc;
            /* Light border */
            font-size: 14px;
            /* Font size */
        }

        .submit-btn {
            width: 100%;
            /* Full width */
            padding: 12px;
            /* Padding inside button */
            background: #28a745;
            /* Green background */
            color: white;
            /* White text */
            border: none;
            /* No border */
            border-radius: 6px;
            /* Rounded corners */
            font-size: 16px;
            /* Font size */
            cursor: pointer;
            /* Pointer cursor */
            transition: background-color 0.3s ease;
            /* Smooth transition */
        }

        .submit-btn:hover {
            background: #218838;
            /* Darker green on hover */
        }

        /* Logout Button */
        .logout-btn {
            width: 100%;
            /* Full width */
            padding: 12px;
            /* Padding inside button */
            background-color: #dc3545;
            /* Red background */
            color: white;
            /* White text */
            border: none;
            /* No border */
            border-radius: 6px;
            /* Rounded corners */
            font-size: 16px;
            /* Font size */
            cursor: pointer;
            /* Pointer cursor */
            transition: background-color 0.3s ease;
            /* Smooth transition */
            margin-top: 20px;
            /* Space above */
        }

        .logout-btn:hover {
            background-color: #c82333;
            /* Darker red on hover */
        }

        /* Task List Styling */
        .task-list {
            margin-top: 20px;
            /* Space above task list */
        }

        .task-item {
            display: flex;
            /* Flexbox for layout */
            justify-content: space-between;
            /* Space between items */
            padding: 12px;
            /* Padding inside item */
            margin-bottom: 10px;
            /* Space below item */
            border-radius: 8px;
            /* Rounded corners */
            background-color: #f9f9f9;
            /* Light background */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            /* Shadow effect */
            align-items: center;
            /* Center items vertically */
        }

        .task-item .task-name {
            font-size: 16px;
            /* Font size */
            font-weight: bold;
            /* Bold text */
        }

        .task-item .task-status {
            font-size: 14px;
            /* Font size */
            padding: 5px 10px;
            /* Padding inside status */
            border-radius: 6px;
            /* Rounded corners */
            color: white;
            /* White text */
        }

        .task-item .task-status.pending {
            background-color: #ffc107;
            /* Yellow for pending */
        }

        .task-item .task-status.in-progress {
            background-color: #007bff;
            /* Blue for in-progress */
        }

        .task-item .task-status.completed {
            background-color: #28a745;
            /* Green for completed */
        }

        .task-item button {
            background-color: #007bff;
            /* Blue background */
            color: white;
            /* White text */
            padding: 6px 12px;
            /* Padding inside button */
            border: none;
            /* No border */
            border-radius: 6px;
            /* Rounded corners */
            cursor: pointer;
            /* Pointer cursor */
            font-size: 14px;
            /* Font size */
            transition: background-color 0.3s ease;
            /* Smooth transition */
        }

        .task-item button:hover {
            background-color: #0056b3;
            /* Darker blue on hover */
        }

        .task-actions {
            display: flex;
            /* Flexbox for actions */
            gap: 8px;
            /* Space between buttons */
        }

        .logout-btn {
            width: auto;
            /* Set width to auto for a smaller button */
            padding: 6px 10px;
            /* Reduce padding */
            background-color: #dc3545;
            /* Red background */
            color: white;
            /* White text */
            border: none;
            /* No border */
            border-radius: 4px;
            /* Rounded corners */
            font-size: 14px;
            /* Smaller font size */
            cursor: pointer;
            /* Pointer cursor */
            transition: background-color 0.3s ease;
            /* Smooth transition */
            margin-top: 10px;
            /* Space above */
        }

        .logout-btn:hover {
            background-color: #c82333;
            /* Darker red on hover */
        }

        /* Add Task Button */
        .add-task-btn {
            background-color: #4CAF50;
            /* Green background */
            color: white;
            /* White text */
            padding: 10px 20px;
            /* Padding inside button */
            font-size: 16px;
            /* Font size */
            border: none;
            /* No border */
            cursor: pointer;
            /* Pointer cursor */
            border-radius: 5px;
            /* Rounded corners */
            transition: background-color 0.3s ease;
            /* Smooth transition */
        }

        .add-task-btn:hover {
            background-color: #45a049;
            /* Darker green on hover */
        }

        /* Dropdown Menu Styling */
        .menu-actions {
            position: relative;
            /* Position relative to the project item */
            display: inline-block;
            /* Inline block for layout */
        }

        .menu-toggle {
            background: none;
            /* No background */
            border: none;
            /* No border */
            font-size: 16px;
            /* Font size */
            cursor: pointer;
            /* Pointer cursor */
            color: #007bff;
            /* Color for the toggle button */
        }

        .dropdown-menu {
            display: none;
            /* Hidden by default */
            position: absolute;
            /* Absolute positioning */
            top: 0;
            /* Align to the top of the project item */
            right: -120px;
            /* Position to the right of the project item */
            background-color: #ffffff;
            /* White background */
            border: 1px solid #ccc;
            /* Light border */
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            /* Shadow effect */
            z-index: 10;
            /* Sit on top */
            min-width: 120px;
            /* Minimum width */
            border-radius: 4px;
            /* Rounded corners */
            padding: 10px 0;
            /* Padding around the menu */
        }

        .dropdown-menu button {
            width: 100%;
            /* Full width */
            padding: 8px 10px;
            /* Padding inside button */
            border: none;
            /* No border */
            background: none;
            /* No background */
            text-align: left;
            /* Align text to the left */
            cursor: pointer;
            /* Pointer cursor */
            color: #333;
            /* Text color */
            transition: background-color 0.3s;
            /* Smooth transition */
        }

        .dropdown-menu button:hover {
            background-color: #f0f0f0;
            /* Light gray on hover */
        }

        /* Optional: Add a transition effect for the dropdown */
        .dropdown-menu.show {
            display: block;
            /* Show the dropdown */
            animation: fadeIn 0.2s;
            /* Fade-in effect */
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <aside class="sidebar">
            <h2>Projects</h2>
            <ul class="menu" id="project-list">
                <li class="menu-item">
                    <div class="project-item">
                        <span class="project-button <?= $project_id === 0 ? 'active' : '' ?>"
                            onclick="loadAllProjects()">All Projects</span>
                    </div>
                </li>
                <?php while ($project = $resultProjects->fetch_assoc()) { ?>
                    <li class="menu-item">
                        <div class="project-item">
                            <span class="project-button <?= $project_id == $project['id'] ? 'active' : '' ?>"
                                onclick="loadProjectTasks(<?= $project['id']; ?>)">
                                <?= htmlspecialchars($project['name']); ?>
                            </span>
                            <div class="menu-actions">
                                <button class="menu-toggle" onclick="toggleDropdown(<?= $project['id']; ?>)">⋮</button>
                                <div class="dropdown-menu" id="dropdown-<?= $project['id']; ?>" style="display: none;">
                                    <button
                                        onclick="openEditProjectModal(<?= $project['id']; ?>, '<?= htmlspecialchars($project['name']); ?>')">Edit</button>
                                    <button onclick="confirmDeleteProject(<?= $project['id']; ?>)">Delete</button>
                                </div>
                            </div>
                        </div>
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
            <h1>Tasks for
                <?= htmlspecialchars($projectName ?? "Unknown Project"); ?>
            </h1>

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
                        ?>
                        <div class="task-item">
                            <div class="task-name">
                                <?= htmlspecialchars($task['name']); ?>
                            </div>
                            <div class="task-status <?= $statusClass; ?>">
                                <?= htmlspecialchars($status); ?>
                            </div>
                            <div class="task-priority">Priority:
                                <?= htmlspecialchars($task['priority']); ?>
                            </div>
                            <div class="task-due-date">Due Date:
                                <?= htmlspecialchars($task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : 'Not Set'); ?>
                            </div>
                            <div class="task-actions">
                                <?php if ($project_id !== 0) { ?>
                                    <button
                                        onclick="openEditTaskModal(<?= $task['id']; ?>, '<?= htmlspecialchars($task['name']); ?>', '<?= htmlspecialchars($status); ?>', '<?= htmlspecialchars($task['priority']); ?>', '<?= htmlspecialchars($task['due_date']); ?>')">Edit</button>
                                    <button onclick="deleteTask(<?= $task['id']; ?>)">Delete</button>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <p>No tasks available</p>
                <?php } ?>
            </div>
        </main>
    </div>

    <!-- Edit Project Modal -->
    <div id="editProjectModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditProjectModal()">&times;</span>
            <h2>Edit Project</h2>
            <form id="editProjectForm" method="POST" action="edit_project.php">
                <input type="hidden" name="project_id" id="editProjectId">
                <label for="projectName">Project Name:</label>
                <input type="text" name="project_name" id="editProjectName" required>
                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>
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
                <select id="task-priority" required>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
                <input type="date" id="task-due-date" required>
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

                    <label for="edit-priority">Priority:</label>
                    <select name="edit-priority" id="edit-priority" required>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>

                    <label for="edit-due_date">Due Date:</label>
                    <input type="date" name="edit-due_date" id="edit-due_date" required>

                    <input type="hidden" name="task_id" id="edit-task_id">

                    <button type="submit" class="submit-btn">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Editing Product -->
    <div id="edit-product-modal" class="modal">
        <div class="edit-product-modal-content">
            <div class="edit-product-modal-header">
                <h3>Edit Product</h3>
                <span class="close-btn" onclick="closeEditProductModal()">&times;</span>
            </div>
            <div class="edit-product-modal-body">
                <form id="edit-product-form" method="POST" onsubmit="event.preventDefault(); confirmEditProduct();">
                    <label for="edit-product_name">Product Name:</label>
                    <input type="text" name="edit-product_name" id="edit-product_name" required>

                    <label for="edit-product_price">Price:</label>
                    <input type="number" name="edit-product_price" id="edit-product_price" required>

                    <label for="edit-product_description">Description:</label>
                    <textarea name="edit-product_description" id="edit-product_description" required></textarea>

                    <input type="hidden" name="product_id" id="edit-product_id">

                    <div class="edit-product-modal-footer">
                        <button type="submit" class="edit-product-submit-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script>
        function openEditProductModal(productId, productName, productPrice, productDescription) {
            document.getElementById('edit-product_name').value = productName;
            document.getElementById('edit-product_price').value = productPrice;
            document.getElementById('edit-product_description').value = productDescription;
            document.getElementById('edit-product_id').value = productId;
            document.getElementById('edit-product-modal').style.display = 'block';
        }

        function closeEditProductModal() {
            document.getElementById('edit-product-modal').style.display = 'none';
        }

        function confirmEditProduct() {
            const productId = document.getElementById('edit-product_id').value;
            const productName = document.getElementById('edit-product_name').value;
            const productPrice = document.getElementById('edit-product_price').value;
            const productDescription = document.getElementById('edit-product_description').value;

            const formData = new FormData();
            formData.append('edit-product_name', productName);
            formData.append('edit-product_price', productPrice);
            formData.append('edit-product_description', productDescription);
            formData.append('product_id', productId);

            fetch('index.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json()).then(result => {
                if (result.success) {
                    alert('Product updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating product: ' + result.message);
                }
            });
        }
        function deleteProject(projectId) {
            if (confirm("Are you sure you want to delete this project?")) {
                window.location.href = `delete_project.php?project_id=${projectId}`;
            }
        }


        function openEditProjectModal(projectId, projectName) {
            document.getElementById('edit-project-id').value = projectId;
            document.getElementById('edit-project-name').value = projectName;
            document.getElementById('editProjectModal').style.display = 'block';
        }

        function closeEditProjectModal() {
            document.getElementById('editProjectModal').style.display = 'none';
        }

        function openTaskModal() {
            document.getElementById('task-modal').style.display = 'block';
        }

        function closeTaskModal() {
            document.getElementById('task-modal').style.display = 'none';
        }

        // Open the Edit Task Modal and populate the fields with current task data
        function openEditTaskModal(taskId, taskName, taskStatus, taskPriority, taskDueDate) {
            document.getElementById('edit-task_name').value = taskName;
            document.getElementById('edit-status').value = taskStatus;
            document.getElementById('edit-priority').value = taskPriority;
            document.getElementById('edit-due_date').value = taskDueDate;
            document.getElementById('edit-task_id').value = taskId;
            document.getElementById('edit-task-modal').style.display = 'block';
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
            const taskPriority = document.getElementById('task-priority').value;
            const taskDueDate = document.getElementById('task-due-date').value;
            const projectId = <?= $project_id ?>;  // Ensure this is dynamically passed from PHP

            // Perform a basic validation
            if (taskName.trim() === '') {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Please enter a task name!',
                });
                return;
            }

            // AJAX call to send the task data to the server
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'index.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Task added successfully!',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();  // Reload to update task list
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error adding task',
                            text: response.message || 'Something went wrong!',
                        });
                    }
                }
            };
            xhr.send(`task_name=${taskName}&status=${taskStatus}&priority=${taskPriority}&due_date=${taskDueDate}&project_id=${projectId}`);
        }

        // Confirm before editing the task with SweetAlert2
        function confirmEditTask() {
            const taskId = document.getElementById('edit-task_id').value;
            const taskName = document.getElementById('edit-task_name').value;
            const taskStatus = document.getElementById('edit-status').value;
            const taskPriority = document.getElementById('edit-priority').value;
            const taskDueDate = document.getElementById('edit-due_date').value;

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
                    const formData = new FormData();
                    formData.append('edit-task_name', taskName);
                    formData.append('edit-status', taskStatus);
                    formData.append('edit-priority', taskPriority);
                    formData.append('edit-due_date', taskDueDate);
                    formData.append('task_id', taskId);

                    fetch('index.php', {
                        method: 'POST',
                        body: formData
                    }).then(response => response.json()).then(result => {
                        if (result.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Task updated successfully!',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();  // Reload to update task list
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error updating task',
                                text: result.message || 'Something went wrong!',
                            });
                        }
                    });
                }
            });
        }

        // Open the Add Project Modal
        function openProjectModal() {
            document.getElementById('project-modal').style.display = 'block';
        }

        // Close the Add Project Modal
        function closeProjectModal() {
            document.getElementById('project-modal').style.display = 'none';
        }

        // Add project functionality
        function addProject() {
            const projectName = document.getElementById('project_name').value;

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

        function toggleDropdown(projectId) {
            const dropdown = document.getElementById(`dropdown-${projectId}`);
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
        }

        // Optional: Close dropdown when clicking outside
        document.addEventListener("click", function (event) {
            const dropdowns = document.querySelectorAll(".dropdown-menu");
            dropdowns.forEach((dropdown) => {
                if (!dropdown.contains(event.target) && !event.target.classList.contains("menu-toggle")) {
                    dropdown.style.display = "none";
                }
            });
        });

        function editProject(projectId, projectName) {
            document.getElementById('edit_project_id').value = projectId;
            document.getElementById('new_project_name').value = projectName;
            $('#editModal').modal('show');
        }

        function deleteProject(projectId) {
            if (confirm("Are you sure you want to delete this project?")) {
                document.getElementById('delete_project_id').value = projectId;
                document.getElementById('deleteForm').submit();
            }
        }

        function toggleDropdown(projectId) {
            var dropdown = document.getElementById('dropdown-' + projectId);
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
        function openEditProjectModal(projectId, projectName) {
            document.getElementById('editProjectId').value = projectId;
            document.getElementById('editProjectName').value = projectName;
            document.getElementById('editProjectModal').style.display = 'block';
        }

        function closeEditProjectModal() {
            document.getElementById('editProjectModal').style.display = 'none';
        }
        function confirmDeleteProject(projectId) {
            if (confirm("Are you sure you want to delete this project? This action cannot be undone.")) {
                // Redirect to the delete_project.php script with the project ID
                window.location.href = `delete_project.php?project_id=${projectId}`;
            }
        }

        function loadAllProjects() {
            window.location.href = 'index.php'; // Load all projects
        }

        function loadProjectTasks(projectId) {
            window.location.href = 'index.php?project_id=' + projectId; // Load specific project tasks
        }


    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>
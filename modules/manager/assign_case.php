<?php
require '../../includes/init.php';
redirectIfNotLoggedIn();
if (!isManager()) {
    logAction('access_denied', 'Unauthorized access to assign case page', 'error');
    die("Access denied!");
}

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
logAction('db_connection_success', 'Successfully connected to database', 'info');

$lang = $_GET['lang'] ?? 'en';

// Initialize messages
$success = null;
$error = null;

// Handle chart filter form submission
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$date_filter = '';
$bind_params = [];
$param_types = '';

if ($start_date && $end_date) {
    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);
    if ($start && $end && $start <= $end) {
        $date_filter = " AND c.created_at BETWEEN ? AND ?";
        $bind_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
        $param_types = 'ss';
    } else {
        logAction('invalid_date_filter', 'Invalid date range provided: ' . $start_date . ' to ' . $end_date, 'warning');
        $start_date = $end_date = null;
    }
}

// Fetch status counts for charts
$statuses = ['Reported', 'Approved'];
$status_counts = ['reported' => 0, 'approved' => 0];
try {
    $sql = "SELECT COUNT(*) as count FROM cases c LEFT JOIN users u ON c.reported_by = u.id WHERE c.status = ? AND u.role = 'record_officer'" . $date_filter;
    $stmt = $conn->prepare($sql);
    foreach ($statuses as $status) {
        $params = array_merge([$status], $bind_params);
        $types = 's' . $param_types;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $status_counts[strtolower($status)] = $stmt->get_result()->fetch_assoc()['count'];
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Status count query failed: ' . $e->getMessage(), 'error');
}

// Fetch title distribution for charts
$title_data = [];
try {
    $sql = "SELECT c.title, COUNT(*) as count FROM cases c LEFT JOIN users u ON c.reported_by = u.id WHERE c.status IN ('Reported', 'Approved') AND u.role = 'record_officer'" . $date_filter . " GROUP BY c.title ORDER BY count DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    if ($bind_params) {
        $stmt->bind_param($param_types, ...$bind_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $title_data[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    logAction('query_failed', 'Case title query failed: ' . $e->getMessage(), 'error');
}

$title_labels = array_column($title_data, 'title') ?: ['No Data'];
$title_counts = array_column($title_data, 'count') ?: [0];

// Debug logging for chart data
logAction('chart_data', 'Status Counts: ' . json_encode($status_counts), 'info');
logAction('chart_data', 'Title Labels: ' . json_encode($title_labels) . ', Counts: ' . json_encode($title_counts), 'info');

// Handle case assignment/confirmation form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['assign', 'confirm'])) {
    $case_id = filter_input(INPUT_POST, 'case_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    if ($case_id && $action === 'assign') {
        $assignee_id = filter_input(INPUT_POST, 'assignee_id', FILTER_VALIDATE_INT);
        $notes = trim(filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING));

        if (!$assignee_id) {
            $error = $translations[$lang]['select_assignee'] ?? "Please select an assignee.";
        } elseif (empty($notes)) {
            $error = $translations[$lang]['provide_notes'] ?? "Please provide assignment notes.";
        } else {
            // Verify assignee exists
            $stmt_assignee = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND role IN ('record_officer', 'surveyor') AND is_locked = 0");
            $stmt_assignee->bind_param("i", $assignee_id);
            $stmt_assignee->execute();
            $assignee_result = $stmt_assignee->get_result();
            if ($assignee_result->num_rows === 0) {
                $error = $translations[$lang]['invalid_assignee'] ?? "Invalid assignee selected.";
                $stmt_assignee->close();
            } else {
                $assignee = $assignee_result->fetch_assoc();
                $stmt_assignee->close();

                // Fetch existing description
                $stmt_case = $conn->prepare("SELECT description FROM cases WHERE id = ?");
                $stmt_case->bind_param("i", $case_id);
                if ($stmt_case->execute()) {
                    $result = $stmt_case->get_result();
                    if ($result->num_rows) {
                        $current_description = json_decode($result->fetch_assoc()['description'], true) ?: [];
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $error = $translations[$lang]['invalid_json'] ?? "Invalid JSON in case description: " . json_last_error_msg();
                            error_log("JSON Error for case $case_id: " . json_last_error_msg());
                        } else {
                            $current_description['notes'] = $notes;
                            $updated_description = json_encode($current_description);

                            // Begin transaction
                            $conn->begin_transaction();
                            try {
                                // Update case
                                $stmt_update = $conn->prepare("UPDATE cases SET assigned_to = ?, status = 'Assigned', description = ? WHERE id = ? AND status IN ('Reported', 'Approved')");
                                if (!$stmt_update) {
                                    throw new Exception("Prepare failed for case update: " . $conn->error);
                                }
                                $stmt_update->bind_param("isi", $assignee_id, $updated_description, $case_id);
                                if (!$stmt_update->execute()) {
                                    throw new Exception("Failed to assign case: " . $stmt_update->error);
                                }
                                if ($stmt_update->affected_rows === 0) {
                                    throw new Exception("Case not found or not in Reported/Approved status.");
                                }
                                $stmt_update->close();

                                // Insert notification for assignee
                                $message = sprintf(
                                    $translations[$lang]['case_assigned_notification'] ?? 'Case ID %s has been assigned to you.',
                                    $case_id
                                );
                                if (strlen($message) > 255) {
                                    $message = substr($message, 0, 252) . '...';
                                }
                                $stmt_notify = $conn->prepare("INSERT INTO notifications (user_id, case_id, message, is_read) VALUES (?, ?, ?, 0)");
                                if (!$stmt_notify) {
                                    throw new Exception("Prepare failed for notification: " . $conn->error);
                                }
                                $stmt_notify->bind_param("iis", $assignee_id, $case_id, $message);
                                if (!$stmt_notify->execute()) {
                                    throw new Exception("Failed to insert notification: " . $stmt_notify->error);
                                }
                                $stmt_notify->close();

                                // Commit transaction
                                $conn->commit();
                                $success = $translations[$lang]['case_assigned'] ?? "Case assigned successfully.";
                                logAction('case_assigned', "Case $case_id assigned to user $assignee_id (role: {$assignee['role']})", 'info');
                            } catch (Exception $e) {
                                $conn->rollback();
                                $error = $translations[$lang]['assign_failed'] ?? "Failed to assign case: " . $e->getMessage();
                                error_log("Assign failed for case $case_id: " . $e->getMessage());
                                // Ensure statements are closed if open
                                if (isset($stmt_update) && $stmt_update instanceof mysqli_stmt) {
                                    $stmt_update->close();
                                }
                                if (isset($stmt_notify) && $stmt_notify instanceof mysqli_stmt) {
                                    $stmt_notify->close();
                                }
                            }
                        }
                    } else {
                        $error = $translations[$lang]['case_not_found'] ?? "Case not found.";
                        error_log("Case $case_id not found");
                    }
                } else {
                    $error = $translations[$lang]['fetch_failed'] ?? "Failed to fetch case: " . $stmt_case->error;
                    error_log("Fetch failed for case $case_id: " . $stmt_case->error);
                }
                $stmt_case->close();
            }
        }
    } elseif ($case_id && $action === 'confirm') {
        $stmt = $conn->prepare("UPDATE cases SET status = 'Finalized', assigned_to = NULL WHERE id = ? AND status = 'Assigned'");
        if (!$stmt) {
            $error = $translations[$lang]['prepare_failed'] ?? "Prepare failed: " . $conn->error;
            error_log("Prepare failed for confirm case $case_id: " . $conn->error);
        } else {
            $stmt->bind_param("i", $case_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success = $translations[$lang]['case_confirmed'] ?? "Case confirmed as finalized.";
                } else {
                    $error = $translations[$lang]['case_not_found_assigned'] ?? "Case not found or not in Assigned status.";
                    error_log("No rows affected for confirm case $case_id");
                }
            } else {
                $error = $translations[$lang]['confirm_failed'] ?? "Failed to confirm case: " . $stmt->error;
                error_log("Confirm failed for case $case_id: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}

// Fetch approved or reported cases reported by record officer
$sql = "SELECT c.id, c.title, JSON_UNQUOTE(JSON_EXTRACT(c.description, '$.full_name')) AS full_name, 
        JSON_UNQUOTE(JSON_EXTRACT(c.description, '$.notes')) AS notes, 
        u.username AS reported_by, c.status, c.assigned_to 
        FROM cases c 
        LEFT JOIN users u ON c.reported_by = u.id 
        WHERE c.status IN ('Reported', 'Approved') AND u.role = 'record_officer'";
$stmt = $conn->prepare($sql);
$cases = [];
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ($row['status'] === 'Reported' && !is_null($row['assigned_to'])) {
                error_log("Case {$row['id']} has status Reported but assigned_to = {$row['assigned_to']}");
            }
            $cases[] = $row;
        }
    } else {
        error_log("Cases query execution failed: " . $stmt->error);
        $error = $translations[$lang]['fetch_cases_failed'] ?? "Failed to fetch cases: " . $stmt->error;
    }
    $stmt->close();
} else {
    error_log("Cases query prepare failed: " . $conn->error);
    $error = $translations[$lang]['prepare_cases_failed'] ?? "Failed to prepare cases query: " . $conn->error;
}

// Fetch record officers and surveyors
$sql = "SELECT id, username, role FROM users WHERE role IN ('record_officer', 'surveyor') AND is_locked = 0";
$stmt = $conn->prepare($sql);
$assignees = [];
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $assignees[] = $row;
        }
    } else {
        error_log("Assignees query execution failed: " . $stmt->error);
        $error = $translations[$lang]['fetch_assignees_failed'] ?? "Failed to fetch assignees: " . $stmt->error;
    }
    $stmt->close();
} else {
    error_log("Assignees query prepare failed: " . $conn->error);
    $error = $translations[$lang]['prepare_assignees_failed'] ?? "Failed to prepare assignees query: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['assign_cases'] ?? 'Assign Cases'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.5.0/Chart.min.js"></script>
    <style>
        h2.text-center {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1a3c6d;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .table {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
            max-width: 300px;
            white-space: normal;
            overflow: visible;
            text-overflow: initial;
        }
        .table td[title] {
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 6px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            border: none;
            border-radius: 6px;
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #1e7e34, #166429);
        }
        .btn-secondary.disabled {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 6px;
        }
        .btn-secondary {
            background: #6b7280;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .form-select, .form-control {
            font-size: 0.9rem;
        }
        .no-data-message {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            color: #6b7280;
            text-align: center;
        }
        .form-container {
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 5px;
            padding: 8px;
            width: 100%;
        }
        .form-label {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 5px;
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            h2.text-center {
                font-size: 1.6rem;
            }
            .table {
                font-size: 0.9rem;
            }
            .table td {
                max-width: 200px;
            }
            .btn-sm {
                font-size: 0.8rem;
                padding: 4px 8px;
            }
            .form-select, .form-control {
                font-size: 0.8rem;
            }
            canvas {
                max-width: 100%;
            }
        }
        @media (max-width: 576px) {
            .table td {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center"><?php echo $translations[$lang]['assign_cases'] ?? 'Assign or Confirm Cases'; ?></h2>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="card mt-4">
                <div class="card-body p-0">
                    <?php if (empty($cases)): ?>
                        <p class="text-center text-muted m-3"><?php echo $translations[$lang]['no_cases_found'] ?? 'No reported or approved cases from Record Officers found.'; ?></p>
                    <?php else: ?>
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo $translations[$lang]['id'] ?? 'ID'; ?></th>
                                    <th><?php echo $translations[$lang]['title'] ?? 'Title'; ?></th>
                                    <th><?php echo $translations[$lang]['full_name'] ?? 'Full Name'; ?></th>
                                    <th><?php echo $translations[$lang]['notes'] ?? 'Notes'; ?></th>
                                    <th><?php echo $translations[$lang]['reported_by'] ?? 'Reported By'; ?></th>
                                    <th><?php echo $translations[$lang]['status'] ?? 'Status'; ?></th>
                                    <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($case['id']); ?></td>
                                        <td title="<?php echo htmlspecialchars($case['title']); ?>">
                                            <?php echo htmlspecialchars($case['title']); ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($case['full_name'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($case['full_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($case['notes'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($case['notes'] ?? 'N/A'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($case['reported_by'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($translations[$lang][strtolower($case['status'])] ?? $case['status']); ?></td>
                                        <td>
                                            <?php if (!is_null($case['assigned_to']) && $case['status'] === 'Assigned'): ?>
                                                <button class="btn btn-secondary btn-sm disabled">
                                                    <i class="fas fa-check-circle"></i> <?php echo $translations[$lang]['assigned'] ?? 'Assigned'; ?>
                                                </button>
                                            <?php else: ?>
                                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('<?php echo $translations[$lang]['confirm_assign'] ?? 'Assign this case to the selected user?'; ?>');">
                                                    <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case['id']); ?>">
                                                    <input type="hidden" name="action" value="assign">
                                                    <select name="assignee_id" class="form-select form-select-sm d-inline-block w-auto mb-1" required>
                                                        <option value=""><?php echo $translations[$lang]['select_assignee'] ?? 'Select Assignee'; ?></option>
                                                        <?php foreach ($assignees as $assignee): ?>
                                                            <option value="<?php echo htmlspecialchars($assignee['id']); ?>">
                                                                <?php echo htmlspecialchars($assignee['username'] . ' (' . ucfirst(str_replace('_', ' ', $assignee['role'])) . ')'); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <textarea name="notes" class="form-control notes-input mb-1" placeholder="<?php echo $translations[$lang]['enter_notes'] ?? 'Enter assignment notes'; ?>" required></textarea>
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-user-plus"></i> <?php echo $translations[$lang]['assign'] ?? 'Assign'; ?>
                                                    </button>
                                                </form>
                                                <form action="" method="POST" class="d-inline" onsubmit="return confirm('<?php echo $translations[$lang]['confirm_finalize'] ?? 'Confirm this case as finalized?'; ?>');">
                                                    <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case['id']); ?>">
                                                    <input type="hidden" name="action" value="confirm">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i> <?php echo $translations[$lang]['confirm'] ?? 'Confirm'; ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
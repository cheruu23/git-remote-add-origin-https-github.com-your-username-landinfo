<?php
require '../../includes/init.php';

// Redirect if not a manager
redirectIfNotLoggedIn();
if (!isManager()) {
    die($translations[$lang]['access_denied'] ?? "Access denied!");
}

// Language handling
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Database connection (MySQLi)
$conn = getDBConnection();

// Handle approve/reject actions
$action_message = '';
$debug_log = __DIR__ . '/debug.log';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - POST request received: " . json_encode($_POST) . "\n", FILE_APPEND);
    
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $user_id = $_SESSION['user']['id'];
    
    // Verify request exists and is Pending/Reported
    $sql = "SELECT requester_name, status FROM support_letter_requests WHERE id = ? AND status IN ('Pending', 'Reported')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();
    $stmt->close();
    
    if (!$request) {
        $action_message = "<div class='alert alert-danger'>" . ($translations[$lang]['request_not_found'] ?? 'Request not found or not pending/reported.') . "</div>";
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Request ID $request_id not found or not Pending/Reported\n", FILE_APPEND);
    } else {
        if ($action === 'approve') {
            // Update status to Approved
            $sql = "UPDATE support_letter_requests SET status = 'Approved', manager_id = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $user_id, $request_id);
            if ($stmt->execute()) {
                $action_message = "<div class='alert alert-success'>" . ($translations[$lang]['approved_success'] ?? 'Request approved successfully.') . "</div>";
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Request ID $request_id approved by manager_id=$user_id\n", FILE_APPEND);
            } else {
                $action_message = "<div class='alert alert-danger'>" . ($translations[$lang]['approve_failed'] ?? 'Failed to approve request.') . "</div>";
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Failed to approve request_id=$request_id: " . $conn->error . "\n", FILE_APPEND);
            }
            $stmt->close();
        } elseif ($action === 'reject') {
            // Update status to Rejected
            $sql = "UPDATE support_letter_requests SET status = 'Rejected', manager_id = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $user_id, $request_id);
            if ($stmt->execute()) {
                $action_message = "<div class='alert alert-success'>" . ($translations[$lang]['rejected_success'] ?? 'Request rejected successfully.') . "</div>";
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Request ID $request_id rejected by manager_id=$user_id\n", FILE_APPEND);
            } else {
                $action_message = "<div class='alert alert-danger'>" . ($translations[$lang]['reject_failed'] ?? 'Failed to reject request.') . "</div>";
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Failed to reject request_id=$request_id: " . $conn->error . "\n", FILE_APPEND);
            }
            $stmt->close();
        }
    }
}

// Fetch pending and reported requests
$sql = "SELECT id, requester_name, requester_email, requester_phone, reason, 
               status, created_at 
        FROM support_letter_requests 
        WHERE status IN ('Pending', 'Reported') 
        ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['support_letter_approval'] ?? 'Support Letter Approval'; ?> - LIMS</title>
    <style>
        .content { padding: 20px; }
        .table th { background-color: #3498db; color: white; }
        .btn-sm { margin-left: 5px; }
        .btn-approve { background-color: #28a745; border: none; }
        .btn-approve:hover { background-color: #218838; }
        .btn-reject { background-color: #dc3545; border: none; }
        .btn-reject:hover { background-color: #c82333; }
        @media (max-width: 992px) { .content { padding: 15px; } }
    </style>
</head>
<body>
    <div class="content">
        <div class="container mt-3">
            <h2><?php echo $translations[$lang]['support_letter_approval'] ?? 'Support Letter Approval'; ?></h2>
            
            <?php if ($action_message): ?>
                <div class='mb-3'><?php echo $action_message; ?></div>
            <?php endif; ?>
            
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo $translations[$lang]['request_id'] ?? 'Request ID'; ?></th>
                        <th><?php echo $translations[$lang]['requester_name'] ?? 'Requester Name'; ?></th>
                        <th><?php echo $translations[$lang]['email'] ?? 'Email'; ?></th>
                        <th><?php echo $translations[$lang]['reason'] ?? 'Reason'; ?></th>
                        <th><?php echo $translations[$lang]['status'] ?? 'Status'; ?></th>
                        <th><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?></th>
                        <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="text-center"><?php echo $translations[$lang]['no_requests'] ?? 'No pending or reported requests found.'; ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['id']); ?></td>
                                <td><?php echo htmlspecialchars($request['requester_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['requester_email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($request['reason'] ?? 'N/A', 0, 50)) . (strlen($request['reason'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($request['status']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <form action="" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                                        <button type="submit" class="btn btn-approve btn-sm">
                                            <i class="bi bi-check-circle"></i> <?php echo $translations[$lang]['approve'] ?? 'Approve'; ?>
                                        </button>
                                    </form>
                                    <form action="" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($request['id']); ?>">
                                        <button type="submit" class="btn btn-reject btn-sm">
                                            <i class="bi bi-x-circle"></i> <?php echo $translations[$lang]['reject'] ?? 'Reject'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
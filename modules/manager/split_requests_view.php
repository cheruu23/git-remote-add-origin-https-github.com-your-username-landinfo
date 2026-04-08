<?php
ob_start(); // Start output buffering
require_once '../../includes/init.php';

// Suppress errors during POST to prevent stray output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', 0);
    error_reporting(0);
}

$lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING) ?: 'en';
$debug_log = __DIR__ . '/debug.log';

// Check session for non-AJAX requests
if (!isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header("Location: ../login.php?lang=$lang");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['user']['role'] ?? 'manager';

// Log session state
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Session user_id: " . var_export($user_id, true) . "\n", FILE_APPEND);

// Handle approval/rejection for split requests and cases
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear output buffer and set JSON header
    ob_clean();
    header('Content-Type: application/json');

    // Check session for AJAX requests
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in.']);
        exit;
    }

    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Handle split request actions
        if (isset($_POST['request_id'])) {
            $request_id = filter_input(INPUT_POST, 'request_id', FILTER_SANITIZE_NUMBER_INT);
            $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
            
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Split request: request_id=$request_id, action=$action\n", FILE_APPEND);
            
            if ($action === 'approve') {
                $status = 'Approved';
                $message = $translations[$lang]['request_approved'] ?? 'Request approved successfully';
            } elseif ($action === 'reject') {
                $status = 'Rejected';
                $message = $translations[$lang]['request_rejected'] ?? 'Request rejected';
            } else {
                throw new Exception('Invalid action');
            }

            if (!filter_var($request_id, FILTER_VALIDATE_INT) || $request_id <= 0) {
                throw new Exception('Invalid request ID');
            }

            $stmt = $conn->prepare("UPDATE split_requests SET status = :status, manager_id = :manager_id, updated_at = NOW() WHERE id = :id AND status = 'Pending'");
            $stmt->execute([
                ':status' => $status,
                ':manager_id' => $user_id,
                ':id' => $request_id
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => $message]);
                exit;
            } else {
                throw new Exception($translations[$lang]['request_already_processed'] ?? 'Request already processed or invalid');
            }
        }

        // Handle case actions
        if (isset($_POST['case_id'])) {
            $case_id = filter_input(INPUT_POST, 'case_id', FILTER_SANITIZE_NUMBER_INT);
            $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
            
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Case action: case_id=$case_id, action=$action, user_id=$user_id\n", FILE_APPEND);
            
            if (!filter_var($case_id, FILTER_VALIDATE_INT) || $case_id <= 0) {
                throw new Exception('Invalid case ID');
            }

            if ($action === 'approve_case') {
                $status = 'Approved';
                $message = $translations[$lang]['case_approved'] ?? 'Case approved successfully';
            } elseif ($action === 'reject_case') {
                $status = 'Rejected';
                $message = $translations[$lang]['case_rejected'] ?? 'Case rejected';
            } else {
                throw new Exception('Invalid case action');
            }

            // Verify case exists
            $stmt = $conn->prepare("SELECT id FROM cases WHERE id = :id AND investigation_status = 'Approved' AND assigned_to IS NOT NULL");
            $stmt->execute([':id' => $case_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Case not found or not eligible for approval');
            }

            $stmt = $conn->prepare("UPDATE cases SET status = :status, approved_by = :approved_by, updated_at = NOW() WHERE id = :id AND status NOT IN ('Approved', 'Rejected')");
            $stmt->execute([
                ':status' => $status,
                ':approved_by' => $user_id,
                ':id' => $case_id
            ]);

            if ($stmt->rowCount() > 0) {
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Case updated: case_id=$case_id, status=$status\n", FILE_APPEND);
                echo json_encode(['success' => true, 'message' => $message]);
                exit;
            } else {
                throw new Exception($translations[$lang]['case_already_processed'] ?? 'Case already processed or invalid');
            }
        }

        throw new Exception('No valid request or case ID provided');
    } catch (Exception $e) {
        $error = $e->getMessage();
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Approval error: $error\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
}

try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Query for pending split requests
    $sql = "SELECT sr.id, sr.original_land_id, sr.status, sr.created_at, 
                   lr.owner_name, sr.former_data, sr.new_data,
                   u.full_name as surveyor_name
            FROM split_requests sr 
            JOIN land_registration lr ON sr.original_land_id = lr.id 
            JOIN users u ON sr.surveyor_id = u.id
            WHERE sr.status = 'Pending'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query for completed cases (investigation_status = 'Approved' and assigned_to not null)
    $sql = "SELECT c.id, c.land_id, c.title, c.case_type, c.description, c.created_at, 
                   lr.owner_name, u.full_name as approved_by_name
            FROM cases c 
            JOIN land_registration lr ON c.land_id = lr.id
            LEFT JOIN users u ON c.approved_by = u.id
            WHERE c.investigation_status = 'Approved' 
              AND c.assigned_to IS NOT NULL 
              AND c.status NOT IN ('Approved', 'Rejected')";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $completed_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = $translations[$lang]['db_error'] ?? "Database error occurred.";
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Database error: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['pending_approvals'] ?? 'Pending Approvals'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .card {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .btn-approve {
            background: #16a34a;
            border: none;
        }
        .btn-reject {
            background: #dc3545;
            border: none;
        }
        .btn-preview {
            background: #0d6efd;
            border: none;
        }
        .data-cell {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .modal-body pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
    </style>
</head>
<body>    
    <!-- Toast Container -->
    <div class="toast-container">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <div class="main-content" id="main-content">
        <div class="container-fluid">
            <h1 class="h3 mb-4"><?php echo $translations[$lang]['pending_approvals'] ?? 'Pending Approvals'; ?></h1>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Split Requests Section -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4><?php echo $translations[$lang]['pending_split_requests'] ?? 'Pending Split Requests'; ?></h4>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo $translations[$lang]['request_id'] ?? 'Request ID'; ?></th>
                                <th><?php echo $translations[$lang]['land_id'] ?? 'Land ID'; ?></th>
                                <th><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?></th>
                                <th><?php echo $translations[$lang]['surveyor'] ?? 'Surveyor'; ?></th>
                                <th><?php echo $translations[$lang]['status'] ?? 'Status'; ?></th>
                                <th><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?></th>
                                <th><?php echo $translations[$lang]['actions'] ?? 'Actions'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <?php echo $translations[$lang]['no_requests'] ?? 'No pending requests found.'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['id']); ?></td>
                                        <td><?php echo htmlspecialchars($request['original_land_id']); ?></td>
                                        <td><?php echo htmlspecialchars($request['owner_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['surveyor_name']); ?></td>
                                        <td>
                                            <span class="badge bg-warning">
                                                <?php echo htmlspecialchars($request['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($request['created_at']))); ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-preview btn-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#previewModal"
                                                    data-former='<?php echo htmlspecialchars(json_encode(json_decode($request['former_data'])), JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                    data-new='<?php echo htmlspecialchars(json_encode(json_decode($request['new_data'])), JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                                    <i class="fas fa-eye"></i> <?php echo $translations[$lang]['preview'] ?? 'Preview'; ?>
                                                </button>
                                                
                                                <form method="POST" class="d-inline action-form">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-approve btn-sm">
                                                        <i class="fas fa-check"></i> <?php echo $translations[$lang]['approve'] ?? 'Approve'; ?>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline action-form">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-reject btn-sm">
                                                        <i class="fas fa-times"></i> <?php echo $translations[$lang]['reject'] ?? 'Reject'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Completed Cases for Approval Section -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4><?php echo $translations[$lang]['completed_cases_for_approval'] ?? 'Completed Cases for Approval'; ?></h4>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?php echo $translations[$lang]['case_id'] ?? 'Case ID'; ?></th>
                                <th><?php echo $translations[$lang]['land_id'] ?? 'Land ID'; ?></th>
                                <th><?php echo $translations[$lang]['case_type'] ?? 'Case Type'; ?></th>
                                <th><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?></th>
                                <th><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?></th>
                                <th><?php echo $translations[$lang]['actions'] ?? 'Actions'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($completed_cases)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <?php echo $translations[$lang]['no_completed_cases'] ?? 'No completed cases found.'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($completed_cases as $case): ?>
                                    <tr data-case-id="<?php echo htmlspecialchars($case['id']); ?>">
                                        <td><?php echo htmlspecialchars($case['id']); ?></td>
                                        <td><?php echo htmlspecialchars($case['land_id']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($case['case_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($case['owner_name']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($case['created_at']))); ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-preview btn-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#previewModal"
                                                    data-title='<?php echo htmlspecialchars($case['title'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                    data-case-type='<?php echo htmlspecialchars($case['case_type'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                    data-description='<?php echo htmlspecialchars($case['description'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                                    data-approved-by='<?php echo htmlspecialchars($case['approved_by_name'] ?? 'N/A', JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                                    <i class="fas fa-eye"></i> <?php echo $translations[$lang]['preview'] ?? 'Preview'; ?>
                                                </button>
                                                
                                                <form method="POST" class="d-inline action-form">
                                                    <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>">
                                                    <input type="hidden" name="action" value="approve_case">
                                                    <button type="submit" class="btn btn-approve btn-sm">
                                                        <i class="fas fa-check"></i> <?php echo $translations[$lang]['approve'] ?? 'Approve'; ?>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="d-inline action-form">
                                                    <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>">
                                                    <input type="hidden" name="action" value="reject_case">
                                                    <button type="submit" class="btn btn-reject btn-sm">
                                                        <i class="fas fa-times"></i> <?php echo $translations[$lang]['reject'] ?? 'Reject'; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel"><?php echo $translations[$lang]['request_details'] ?? 'Request Details'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="splitRequestPreview">
                        <h6><?php echo $translations[$lang]['current_data'] ?? 'Current Data'; ?></h6>
                        <pre id="modalFormerData"></pre>
                        
                        <h6 class="mt-4"><?php echo $translations[$lang]['proposed_changes'] ?? 'Proposed Changes'; ?></h6>
                        <pre id="modalNewData"></pre>
                    </div>
                    <div id="caseRequestPreview" style="display: none;">
                        <h6><?php echo $translations[$lang]['case_title'] ?? 'Case Title'; ?></h6>
                        <pre id="modalCaseTitle"></pre>
                        
                        <h6 class="mt-4"><?php echo $translations[$lang]['case_type'] ?? 'Case Type'; ?></h6>
                        <pre id="modalCaseType"></pre>
                        
                        <h6 class="mt-4"><?php echo $translations[$lang]['case_description'] ?? 'Description'; ?></h6>
                        <pre id="modalCaseDescription"></pre>
                        
                        <h6 class="mt-4"><?php echo $translations[$lang]['case_approved_by'] ?? 'Approved By'; ?></h6>
                        <pre id="modalApprovedBy"></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo $translations[$lang]['close'] ?? 'Close'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview modal handler
        document.addEventListener('DOMContentLoaded', function() {
            const previewModal = document.getElementById('previewModal');
            if (previewModal) {
                previewModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const splitRequestPreview = document.getElementById('splitRequestPreview');
                    const caseRequestPreview = document.getElementById('caseRequestPreview');

                    if (button.hasAttribute('data-former') && button.hasAttribute('data-new')) {
                        splitRequestPreview.style.display = 'block';
                        caseRequestPreview.style.display = 'none';

                        const formerData = JSON.parse(button.getAttribute('data-former'));
                        const newData = JSON.parse(button.getAttribute('data-new'));
                        
                        document.getElementById('modalFormerData').textContent = 
                            JSON.stringify(formerData, null, 2);
                        
                        document.getElementById('modalNewData').textContent = 
                            JSON.stringify(newData, null, 2);
                    } else if (button.hasAttribute('data-title')) {
                        splitRequestPreview.style.display = 'none';
                        caseRequestPreview.style.display = 'block';

                        const title = button.getAttribute('data-title');
                        const caseType = button.getAttribute('data-case-type');
                        const description = button.getAttribute('data-description');
                        const approvedBy = button.getAttribute('data-approved-by');
                        
                        document.getElementById('modalCaseTitle').textContent = title || 'No title provided';
                        document.getElementById('modalCaseType').textContent = caseType || 'No type provided';
                        document.getElementById('modalCaseDescription').textContent = description || 'No description provided';
                        document.getElementById('modalApprovedBy').textContent = approvedBy || 'Not specified';
                    }
                });
            }

            // Handle form submissions with AJAX for split and case actions
            document.querySelectorAll('.action-form').forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const formData = new FormData(form);
                    console.log('Form data:', Object.fromEntries(formData)); // Log form data

                    fetch(window.location.href, { // Use current URL
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status); // Log response status
                        return response.text().then(text => {
                            console.log('Response text:', text); // Log raw response
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}, response: ${text}`);
                            }
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                throw new Error('Invalid JSON: ' + text);
                            }
                        });
                    })
                    .then(data => {
                        console.log('Response data:', data); // Log parsed data
                        const successToast = document.getElementById('successToast');
                        const errorToast = document.getElementById('errorToast');

                        if (!data || typeof data.success === 'undefined') {
                            throw new Error('Invalid response data');
                        }

                        if (data.success) {
                            successToast.querySelector('.toast-body').textContent = data.message || 'Action completed';
                            const toast = new bootstrap.Toast(successToast);
                            toast.show();

                            // Remove the case row if it's a case action
                            if (formData.get('case_id')) {
                                const caseId = formData.get('case_id');
                                const row = document.querySelector(`tr[data-case-id="${caseId}"]`);
                                if (row) {
                                    row.remove();
                                }
                            }
                        } else {
                            errorToast.querySelector('.toast-body').textContent = data.message || 'Unknown error occurred';
                            const toast = new bootstrap.Toast(errorToast);
                            toast.show();
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error); // Log fetch error
                        const errorToast = document.getElementById('errorToast');
                        errorToast.querySelector('.toast-body').textContent = error.message || 'An error occurred';
                        const toast = new bootstrap.Toast(errorToast);
                        toast.show();
                    });
                });
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>
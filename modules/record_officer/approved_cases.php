<?php
require '../../vendor/autoload.php'; // Composer's autoloader for TCPDF

// Database connection for POST handling
require '../../includes/db.php';
$post_conn = getDBConnection();

// Define BASE_URL and paths
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}
$letters_dir = realpath(__DIR__ . '/../../letters');
if (!is_dir($letters_dir)) {
    mkdir($letters_dir, 0755, true);
}
if (!is_writable($letters_dir)) {
    die("Directory not writable: $letters_dir");
}

// Debug log
$debug_log = __DIR__ . '/debug.log';

// Handle POST actions (before any output)
$action_message = '';
$should_include_init = true; // Flag to control init.php inclusion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - POST request received: " . json_encode($_POST) . "\n", FILE_APPEND);
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - POST conn state: " . ($post_conn->ping() ? 'open' : 'closed') . "\n", FILE_APPEND);
    
    if ($_POST['action'] === 'finalize_case' && isset($_POST['case_id'])) {
        $case_id = (int)$_POST['case_id'];
        $lang = isset($_POST['lang']) && in_array($_POST['lang'], ['en', 'om']) ? $_POST['lang'] : 'om';
        
        // Log case details
        $sql = "SELECT case_type, title, land_id FROM cases WHERE id = ? AND status = 'Approved'";
        $stmt = $post_conn->prepare($sql);
        $stmt->bind_param('i', $case_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $case = $result->fetch_assoc();
        $stmt->close();
        
        $case_type = $case['case_type'] ?? null;
        $land_id = $case['land_id'] ?? null;
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - POST: case_id=$case_id, case_type='" . ($case_type ?? 'empty') . "', land_id=" . ($land_id ?? 'N/A') . "\n", FILE_APPEND);

        // Update case status to Serviced
        $sql = "UPDATE cases SET status = 'Serviced' WHERE id = ?";
        $stmt = $post_conn->prepare($sql);
        $stmt->bind_param('i', $case_id);
        if ($stmt->execute()) {
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Case ID $case_id status updated to Serviced, case_type='" . ($case_type ?? 'empty') . "'\n", FILE_APPEND);
            
            // Redirect to print page if eligible
            $printParcelCaseTypes = [
                'mirkaneessa_abbaa_qabiyyumma',
                'mirkaneessa_sirrumma_waraqa_ragaa',
                'qabiye_walitti_makuu',
                'jijjirra_maqaa'
            ];
            if ($case_type && in_array($case_type, $printParcelCaseTypes) && $land_id) {
                $redirect_url = "print_parcel.php?case_id=$case_id&land_id=$land_id&lang=$lang";
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Redirecting case_id=$case_id to $redirect_url\n", FILE_APPEND);
                $post_conn->close();
                header("Location: $redirect_url");
                exit;
            } else {
                $action_message = "<div class='alert alert-success'>Case finalized successfully.</div>";
            }
        } else {
            $action_message = "<div class='alert alert-danger'>Failed to finalize case. (Case ID: $case_id)</div>";
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Failed to update case ID $case_id to Serviced: " . $post_conn->error . "\n", FILE_APPEND);
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'finalize_support_letter' && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];
        $lang = isset($_POST['lang']) && in_array($_POST['lang'], ['en', 'om']) ? $_POST['lang'] : 'om';
        
        // Verify request
        $sql = "SELECT requester_name, requester_email, reason FROM support_letter_requests WHERE id = ? AND status = 'Approved'";
        $stmt = $post_conn->prepare($sql);
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();
        
        if ($request) {
            // Update status to Serviced
            $sql = "UPDATE support_letter_requests SET status = 'Serviced', updated_at = NOW() WHERE id = ?";
            $stmt = $post_conn->prepare($sql);
            $stmt->bind_param('i', $request_id);
            if ($stmt->execute()) {
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Support Letter ID $request_id status updated to Serviced\n", FILE_APPEND);
                
                // Generate PDF using TCPDF
                $pdf_path = "$letters_dir/support_letter_$request_id.pdf";
                if (!file_exists($pdf_path)) {
                    try {
                        // Initialize TCPDF
                        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                        $pdf->SetCreator(PDF_CREATOR);
                        $pdf->SetAuthor('LIMS');
                        $pdf->SetTitle('Support Letter');
                        $pdf->SetMargins(15, 15, 15);
                        $pdf->SetAutoPageBreak(true, 15);
                        
                        // Add a page
                        $pdf->AddPage();
                        
                        // Set font
                        $pdf->SetFont('helvetica', 'B', 16);
                        $pdf->Cell(0, 10, 'Support Letter', 0, 1, 'C');
                        $pdf->Ln(10);
                        
                        // Content
                        $pdf->SetFont('helvetica', '', 12);
                        $pdf->Write(0, "To Whom It May Concern,\n\n");
                        $pdf->Write(0, "This letter confirms that " . htmlspecialchars($request['requester_name']) . " has been approved for support.\n\n");
                        $pdf->Write(0, "Reason: " . htmlspecialchars($request['reason'] ?? 'N/A') . ".\n\n");
                        $pdf->Write(0, "Sincerely,\nLIMS Record Officer");
                        
                        // Output to file
                        $pdf->Output($pdf_path, 'F');
                        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Generated PDF: $pdf_path\n", FILE_APPEND);
                    } catch (Exception $e) {
                        $action_message = "<div class='alert alert-danger'>Failed to generate PDF: " . $e->getMessage() . "</div>";
                        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Failed to generate PDF for request_id=$request_id: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }
                
                // Redirect to print page
                if (file_exists($pdf_path)) {
                    $redirect_url = "print_support_parcel.php?id=$request_id&lang=$lang";
                    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Redirecting support_letter_id=$request_id to $redirect_url\n", FILE_APPEND);
                    $post_conn->close();
                    header("Location: $redirect_url");
                    exit;
                } else {
                    $action_message = "<div class='alert alert-danger'>Failed to generate PDF.</div>";
                }
            } else {
                $action_message = "<div class='alert alert-danger'>Failed to finalize support letter. (Request ID: $request_id)</div>";
                file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Failed to update support letter ID $request_id to Serviced: " . $post_conn->error . "\n", FILE_APPEND);
            }
            $stmt->close();
        } else {
            $action_message = "<div class='alert alert-danger'>Request not found or not approved.</div>";
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Support Letter ID $request_id not found or not approved\n", FILE_APPEND);
        }
    }
    $post_conn->close();
}

// Include init.php only if no redirect occurred
if ($should_include_init) {
    require '../../includes/init.php';
    
    // Database connection for GET requests
    $conn = getDBConnection();
    
    // Redirect if not a record officer
    redirectIfNotLoggedIn();
    if (!isRecordOfficer()) {
        die($translations[$lang]['access_denied'] ?? "Access denied!");
    }
    
    // Language handling
    $valid_langs = ['en', 'om'];
    $lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';
    
    // Page options for modal links
    $pageOptions = [
        'view_parcelprint.php' => $translations[$lang]['view_parcel'],
        'document.php' => $translations[$lang]['document'],
        'letter.php' => $translations[$lang]['letter'],
        'support_paper.php' => $translations[$lang]['support_paper'],
        'default_finalize.php' => $translations[$lang]['default_finalize']
    ];
    
    // Fetch approved cases
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - GET conn state before cases: " . ($conn->ping() ? 'open' : 'closed') . "\n", FILE_APPEND);
    $sql = "SELECT c.id, c.title, c.status, c.case_type, c.land_id, 
                   u.username AS reported_by, 
                   c.description,
                   lr.village
            FROM cases c 
            LEFT JOIN users u ON c.reported_by = u.id 
            LEFT JOIN land_registration lr ON c.land_id = lr.id 
            WHERE c.status = 'Approved'";
    if ($village = trim($_GET['village'] ?? '')) {
        $sql .= " AND lr.village = ?";
    }
    $stmt = $conn->prepare($sql);
    if ($village) {
        $stmt->bind_param('s', $village);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $cases = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Debug case types and land_id
    foreach ($cases as $case) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Case: case_id={$case['id']}, case_type='" . ($case['case_type'] ?? 'empty') . "', land_id=" . ($case['land_id'] ?? 'N/A') . "\n", FILE_APPEND);
    }
    
    // Fetch approved support letter requests
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - GET conn state before support letters: " . ($conn->ping() ? 'open' : 'closed') . "\n", FILE_APPEND);
    $sql = "SELECT slr.id, slr.requester_name, slr.requester_email, slr.reason, slr.status, 
                   u.username AS approved_by
            FROM support_letter_requests slr 
            LEFT JOIN users u ON slr.manager_id = u.id 
            WHERE slr.status = 'Approved'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $support_letters = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Debug support letter requests
    foreach ($support_letters as $letter) {
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Support Letter: id={$letter['id']}, requester_name='" . ($letter['requester_name'] ?? 'N/A') . "', status=" . ($letter['status'] ?? 'N/A') . "\n", FILE_APPEND);
    }
    
    // Fetch distinct villages
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - GET conn state before villages: " . ($conn->ping() ? 'open' : 'closed') . "\n", FILE_APPEND);
    $sql = "SELECT DISTINCT village FROM land_registration WHERE village IS NOT NULL AND village != '' ORDER BY village";
    $result = $conn->query($sql);
    $villages = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    
    // Close GET connection
    file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Closing GET conn\n", FILE_APPEND);
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['approved_cases_title'] ?? 'Approved Cases'; ?> - LIMS</title>
    <style>
        .content { padding: 20px; }
        .table th { background-color: #3498db; color: white; }
        .btn-sm { margin-left: 5px; }
        .modal-content { border-radius: 10px; }
        .action-btn { margin: 5px; }
        .btn-finalize { background-color: #28a745; border: none; }
        .btn-finalize:hover { background-color: #218838; }
        .btn-serviced { background-color: #28a745; border: none; }
        .btn-serviced:hover { background-color: #218838; }
        @media (max-width: 992px) { .content { padding: 15px; } }
    </style>
</head>
<body>
    <div class="content">
        <div class="container mt-3">
            <h2><?php echo $translations[$lang]['approved_cases_title'] ?? 'Approved Cases'; ?></h2>
            
            <?php if ($action_message): ?>
                <div class='mb-3'><?php echo $action_message; ?></div>
            <?php endif; ?>
            
            <!-- Approved Cases Table -->
            <h4><?php echo $translations[$lang]['approved_cases'] ?? 'Approved Cases'; ?></h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo $translations[$lang]['case_id'] ?? 'Case ID'; ?></th>
                        <th><?php echo $translations[$lang]['case_title'] ?? 'Title'; ?></th>
                        <th><?php echo $translations[$lang]['case_type'] ?? 'Case Type'; ?></th>
                        <th><?php echo $translations[$lang]['reported_by'] ?? 'Reported By'; ?></th>
                        <th><?php echo $translations[$lang]['village'] ?? 'Village'; ?></th>
                        <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cases)): ?>
                        <tr><td colspan="6" class="text-center"><?php echo $translations[$lang]['no_approved_cases'] ?? 'No approved cases found.'; ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($cases as $case): ?>
                            <?php
                            $case_type = $case['case_type'] ?? null;
                            $land_id = !empty($case['land_id']) && (int)$case['land_id'] > 0 ? (int)$case['land_id'] : null;
                            $showPrintButton = $case_type && in_array($case_type, ['mirkaneessa_abbaa_qabiyyumma', 'mirkaneessa_sirrumma_waraqa_ragaa', 'qabiye_walitti_makuu', 'jijjirra_maqaa']) && $land_id;
                            $modal_id = 'detailsModal-' . $case['id'];
                            $description = $case['description'] ?? 'N/A';
                            if ($description && json_decode($description, true)) {
                                $desc_data = json_decode($description, true);
                                $description = $desc_data['full_name'] ?? $description;
                            }
                            $description .= $land_id ? " (Land ID: $land_id)" : "";
                            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Case ID {$case['id']}: land_id=" . ($land_id ?? 'null') . ", showPrintButton=" . ($showPrintButton ? 'true' : 'false') . "\n", FILE_APPEND);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($case['id']); ?></td>
                                <td><?php echo htmlspecialchars($case['title'] ?? 'Untitled'); ?></td>
                                <td><?php echo htmlspecialchars($translations[$lang]['case_' . $case_type] ?? $case_type ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($case['reported_by'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($case['village'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($showPrintButton): ?>
                                        <form action="" method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="finalize_case">
                                            <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case['id']); ?>">
                                            <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                                            <button type="submit" class="btn btn-serviced btn-sm">
                                                <i class="bi bi-check-circle"></i> <?php echo $translations[$lang]['serviced'] ?? 'Serviced'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="btn btn-info btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#<?php echo $modal_id; ?>">
                                        <i class="bi bi-info-circle"></i> <?php echo $translations[$lang]['details'] ?? 'Details'; ?>
                                    </button>
                                </td>
                            </tr>
                            <!-- Modal for Case Details -->
                            <div class="modal fade" id="<?php echo $modal_id; ?>" tabindex="-1" aria-labelledby="<?php echo $modal_id; ?>Label" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="<?php echo $modal_id; ?>Label"><?php echo $translations[$lang]['case_details'] ?? 'Case Details'; ?> - Case ID: <?php echo htmlspecialchars($case['id']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong><?php echo $translations[$lang]['case_id'] ?? 'Case ID'; ?>:</strong> <?php echo htmlspecialchars($case['id']); ?></p>
                                            <p><strong><?php echo $translations[$lang]['case_title'] ?? 'Title'; ?>:</strong> <?php echo htmlspecialchars($case['title'] ?? 'Untitled'); ?></p>
                                            <p><strong><?php echo $translations[$lang]['case_type'] ?? 'Case Type'; ?>:</strong> <?php echo htmlspecialchars($translations[$lang]['case_' . $case_type] ?? $case_type ?: 'N/A'); ?></p>
                                            <p><strong><?php echo $translations[$lang]['reported_by'] ?? 'Reported By'; ?>:</strong> <?php echo htmlspecialchars($case['reported_by'] ?? 'Unknown'); ?></p>
                                            <p><strong><?php echo $translations[$lang]['village'] ?? 'Village'; ?>:</strong> <?php echo htmlspecialchars($case['village'] ?? 'N/A'); ?></p>
                                            <p><strong><?php echo $translations[$lang]['description'] ?? 'Description'; ?>:</strong> <?php echo htmlspecialchars($description); ?></p>
                                            <hr>
                                            <h6><?php echo $translations[$lang]['action'] ?? 'Action'; ?>:</h6>
                                            <div class="d-flex flex-wrap">
                                                <?php foreach ($pageOptions as $page => $label): ?>
                                                    <a href="<?php 
                                                        $url = BASE_URL . '/modules/record_officer/' . $page . '?';
                                                        if (in_array($page, ['view_parcel.php', 'document.php', 'letter.php', 'support_paper.php'])) {
                                                            $url .= 'id=' . htmlspecialchars($case['id']);
                                                        } else {
                                                            $url .= 'case_id=' . htmlspecialchars($case['id']);
                                                        }
                                                        $url .= ($land_id ? '&land_id=' . htmlspecialchars($land_id) : '') . '&lang=' . $lang;
                                                        echo $url;
                                                    ?>" 
                                                       class="btn btn-success btn-sm action-btn">
                                                        <i class="bi bi-arrow-right-circle"></i> <?php echo htmlspecialchars($label); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $translations[$lang]['close'] ?? 'Close'; ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Approved Support Letter Requests Table -->
            <h4 class="mt-5"><?php echo $translations[$lang]['approved_support_letters'] ?? 'Approved Support Letter Requests'; ?></h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo $translations[$lang]['request_id'] ?? 'Request ID'; ?></th>
                        <th><?php echo $translations[$lang]['requester_name'] ?? 'Requester Name'; ?></th>
                        <th><?php echo $translations[$lang]['reason'] ?? 'Reason'; ?></th>
                        <th><?php echo $translations[$lang]['approved_by'] ?? 'Approved By'; ?></th>
                        <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($support_letters)): ?>
                        <tr><td colspan="5" class="text-center"><?php echo $translations[$lang]['no_approved_support_letters'] ?? 'No approved support letter requests found.'; ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($support_letters as $letter): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($letter['id']); ?></td>
                                <td><?php echo htmlspecialchars($letter['requester_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($letter['reason'] ?? 'N/A', 0, 50)) . (strlen($letter['reason'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($letter['approved_by'] ?? 'Unknown'); ?></td>
                                <td>
                                    <form action="" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="finalize_support_letter">
                                        <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($letter['id']); ?>">
                                        <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                                        <button type="submit" class="btn btn-finalize btn-sm">
                                            <i class="bi bi-check-circle"></i> <?php echo $translations[$lang]['finalize'] ?? 'Finalize'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="mt-3">
                <a href="../dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> <?php echo $translations[$lang]['back_to_dashboard'] ?? 'Back to Dashboard'; ?>
                </a>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const villageFilter = document.getElementById('villageFilter');
            if (villageFilter) {
                villageFilter.addEventListener('change', () => {
                    const url = new URL(window.location);
                    url.searchParams.set('village', villageFilter.value);
                    url.searchParams.set('lang', '<?php echo $lang; ?>');
                    window.location = url;
                });
            }
        });
    </script>
</body>
</html>
<?php
session_start();
require '../../includes/auth.php';
require '../../includes/db.php';
require '../../includes/languages.php';
require '../../includes/logger.php';

redirectIfNotLoggedIn();
if (!isRecordOfficer()) {
    logAction('access_denied', 'Unauthorized access to finalize case page', 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    die($translations[$lang]['access_denied'] ?? "Access denied!");
}

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    die($translations[$lang]['db_connection_failed'] ?? "Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
logAction('db_connection_success', 'Successfully connected to database', 'info', ['user_id' => $_SESSION['user']['id'] ?? null]);

// Validate and sanitize lang parameter
$valid_langs = ['en', 'om', 'ar'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'en';
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';

// Fetch approved cases
$sql = "SELECT c.id, c.title, c.status, c.land_id, c.description, u.username AS reported_by 
        FROM cases c 
        LEFT JOIN users u ON c.reported_by = u.id 
        WHERE c.status = 'Approved'";
$stmt = $conn->prepare($sql);
$cases = [];
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['description'] = json_decode($row['description'], true) ?: [];
        $cases[] = $row;
    }
} else {
    logAction('query_failed', 'Approved cases query failed: ' . $conn->error, 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
}
$stmt->close();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    if (isset($_POST['action'])) {
        $log_context = ['user_id' => $_SESSION['user']['id'] ?? null, 'case_id' => $_POST['case_id'] ?? null];

        if ($_POST['action'] === 'get_case_details') {
            $case_id = filter_input(INPUT_POST, 'case_id', FILTER_VALIDATE_INT);
            if (!$case_id) {
                $response['message'] = $translations[$lang]['invalid_case_id'] ?? 'Invalid case ID';
                logAction('invalid_case_id', 'Invalid case ID: ' . $case_id, 'error', $log_context);
                echo json_encode($response);
                exit;
            }

            // Fetch case details
            $sql = "SELECT c.id, c.title, c.status, c.land_id, c.description, u.username AS reported_by 
                    FROM cases c 
                    LEFT JOIN users u ON c.reported_by = u.id 
                    WHERE c.id = ? AND c.status = 'Approved'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $case_id);
            $case = null;
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $case = $result->fetch_assoc();
                $case['description'] = json_decode($case['description'], true) ?: [];
            }
            $stmt->close();

            // Fetch evidence files
            $sql = "SELECT id, file_path, evidence_type FROM case_evidence WHERE case_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $case_id);
            $evidence_files = [];
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $evidence_files[] = $row;
                }
            }
            $stmt->close();

            // Fetch parcel details for relevant case types
            $parcel = null;
            if (in_array($case['title'], ['mirkaneessa_abbaa_qabiyyumma', 'mirkaneessa_sirrumma_waraqa_ragaa']) && $case['land_id']) {
                $sql = "SELECT owner_name, first_name, middle_name, zone, village, block_number, owner_photo 
                        FROM land_registration WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $case['land_id']);
                if ($stmt->execute()) {
                    $parcel = $stmt->get_result()->fetch_assoc();
                }
                $stmt->close();
            }

            if ($case) {
                $response['success'] = true;
                $response['data'] = [
                    'case' => $case,
                    'evidence_files' => $evidence_files,
                    'parcel' => $parcel
                ];
                logAction('view_case_details', 'Viewed case details for case ID: ' . $case_id, 'info', $log_context);
            } else {
                $response['message'] = $translations[$lang]['case_not_found'] ?? 'Case not found';
                logAction('case_not_found', 'Case ID not found: ' . $case_id, 'error', $log_context);
            }
        } elseif ($_POST['action'] === 'finalize_case') {
            $case_id = filter_input(INPUT_POST, 'case_id', FILTER_VALIDATE_INT);
            if (!$case_id) {
                $response['message'] = $translations[$lang]['invalid_case_id'] ?? 'Invalid case ID';
                logAction('invalid_case_id', 'Invalid case ID: ' . $case_id, 'error', $log_context);
                echo json_encode($response);
                exit;
            }

            $sql = "UPDATE cases SET status = 'Finalized', updated_at = NOW() WHERE id = ? AND status = 'Approved'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $case_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = $translations[$lang]['case_finalized'] ?? 'Case successfully finalized';
                logAction('case_finalized', 'Case ID ' . $case_id . ' finalized', 'info', $log_context);
            } else {
                $response['message'] = $translations[$lang]['finalize_failed'] ?? 'Failed to finalize case';
                logAction('finalize_failed', 'Failed to finalize case ID: ' . $case_id, 'error', $log_context);
            }
            $stmt->close();
        }

        echo json_encode($response);
        exit;
    }

    $response['message'] = $translations[$lang]['invalid_request'] ?? 'Invalid request';
    echo json_encode($response);
    exit;
}

include '../../templates/sidebar.php';
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>" dir="<?php echo $dir; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['finalize_cases_title'] ?? 'Finalize Cases'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: #f8f9fa;
            font-family: <?php echo $lang === 'ar' ? "'Amiri', serif" : "'Poppins', sans-serif"; ?>;
        }
        .content {
            margin-<?php echo $lang === 'ar' ? 'right' : 'left'; ?>: 250px;
            padding: 20px;
            transition: margin-<?php echo $lang === 'ar' ? 'right' : 'left'; ?> 0.3s;
        }
        .content.collapsed {
            margin-<?php echo $lang === 'ar' ? 'right' : 'left'; ?>: 60px;
        }
        h2.text-center {
            font-size: 2rem;
            font-weight: 600;
            color: #1a3c6d;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
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
            text-align: <?php echo $lang === 'ar' ? 'right' : 'left'; ?>;
        }
        .table td {
            vertical-align: middle;
            text-align: <?php echo $lang === 'ar' ? 'right' : 'left'; ?>;
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
            background: linear-gradient(135deg, #1e7e34, #166c29);
        }
        .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            border-bottom: none;
        }
        .modal-title {
            font-weight: 600;
        }
        .modal-body {
            padding: 20px;
        }
        .evidence-file {
            margin-bottom: 10px;
        }
        .evidence-file a {
            color: #007bff;
            text-decoration: none;
        }
        .evidence-file a:hover {
            text-decoration: underline;
        }
        .parcel-details, .merged-properties, .changed-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .print-btn {
            margin-top: 10px;
        }
        @media (max-width: 992px) {
            .content {
                margin-<?php echo $lang === 'ar' ? 'right' : 'left'; ?>: 60px;
            }
        }
        @media (max-width: 768px) {
            .content {
                margin-<?php echo $lang === 'ar' ? 'right' : 'left'; ?>: 0;
            }
            h2.text-center {
                font-size: 1.8rem;
            }
            .table {
                font-size: 0.9rem;
            }
            .modal-body {
                padding: 15px;
            }
        }
        @media (max-width: 576px) {
            .btn-sm {
                font-size: 0.85rem;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="content" id="main-content">
        <div class="container mt-3">
            <h2 class="text-center"><?php echo $translations[$lang]['finalize_cases_title'] ?? 'Finalize Cases'; ?></h2>
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($cases)): ?>
                        <p class="text-center text-muted m-3"><?php echo $translations[$lang]['no_approved_cases'] ?? 'No approved cases found.'; ?></p>
                    <?php else: ?>
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th><?php echo $translations[$lang]['id'] ?? 'ID'; ?></th>
                                    <th><?php echo $translations[$lang]['title'] ?? 'Title'; ?></th>
                                    <th><?php echo $translations[$lang]['status'] ?? 'Status'; ?></th>
                                    <th><?php echo $translations[$lang]['reported_by'] ?? 'Reported By'; ?></th>
                                    <th><?php echo $translations[$lang]['description'] ?? 'Description'; ?></th>
                                    <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cases as $case): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($case['id']); ?></td>
                                        <td><?php echo htmlspecialchars($case['title']); ?></td>
                                        <td><?php echo htmlspecialchars($case['status']); ?></td>
                                        <td><?php echo htmlspecialchars($case['reported_by'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($case['description']['full_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <button class="btn btn-success btn-sm finalize-btn" data-case-id="<?php echo htmlspecialchars($case['id']); ?>">
                                                <i class="fas fa-check-circle"></i> <?php echo $translations[$lang]['finalize'] ?? 'Finalize'; ?>
                                            </button>
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

    <!-- Modal for Case Details -->
    <div class="modal fade" id="caseDetailsModal" tabindex="-1" aria-labelledby="caseDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="caseDetailsModalLabel"><?php echo $translations[$lang]['case_details'] ?? 'Case Details'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="case-details-content">
                        <p><strong><?php echo $translations[$lang]['id'] ?? 'ID'; ?>:</strong> <span id="case-id"></span></p>
                        <p><strong><?php echo $translations[$lang]['title'] ?? 'Title'; ?>:</strong> <span id="case-title"></span></p>
                        <p><strong><?php echo $translations[$lang]['status'] ?? 'Status'; ?>:</strong> <span id="case-status"></span></p>
                        <p><strong><?php echo $translations[$lang]['reported_by'] ?? 'Reported By'; ?>:</strong> <span id="case-reported-by"></span></p>
                        <p><strong><?php echo $translations[$lang]['full_name'] ?? 'Full Name'; ?>:</strong> <span id="case-full-name"></span></p>
                        <p><strong><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?>:</strong> <span id="case-zone"></span></p>
                        <p><strong><?php echo $translations[$lang]['village'] ?? 'Village'; ?>:</strong> <span id="case-village"></span></p>
                        <p><strong><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?>:</strong> <span id="case-block-number"></span></p>
                        <div id="evidence-files" class="mt-3">
                            <h6><?php echo $translations[$lang]['evidence_files'] ?? 'Evidence Files'; ?>:</h6>
                            <div id="evidence-list"></div>
                        </div>
                        <div id="case-specific-content" class="mt-3"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $translations[$lang]['close'] ?? 'Close'; ?></button>
                    <button type="button" class="btn btn-success" id="confirm-finalize-btn"><?php echo $translations[$lang]['confirm_finalize'] ?? 'Confirm Finalization'; ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const finalizeButtons = document.querySelectorAll('.finalize-btn');
            const modal = new bootstrap.Modal(document.getElementById('caseDetailsModal'));
            const caseDetailsContent = document.getElementById('case-details-content');
            const evidenceList = document.getElementById('evidence-list');
            const caseSpecificContent = document.getElementById('case-specific-content');
            const confirmFinalizeBtn = document.getElementById('confirm-finalize-btn');

            finalizeButtons.forEach(button => {
                button.addEventListener('click', async () => {
                    const caseId = button.getAttribute('data-case-id');

                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: new URLSearchParams({
                                action: 'get_case_details',
                                case_id: caseId
                            })
                        });

                        const result = await response.json();
                        if (result.success) {
                            const { case: caseData, evidence_files, parcel } = result.data;

                            // Populate general case details
                            document.getElementById('case-id').textContent = caseData.id;
                            document.getElementById('case-title').textContent = caseData.title;
                            document.getElementById('case-status').textContent = caseData.status;
                            document.getElementById('case-reported-by').textContent = caseData.reported_by || 'Unknown';
                            document.getElementById('case-full-name').textContent = caseData.description.full_name || 'N/A';
                            document.getElementById('case-zone').textContent = caseData.description.zone || 'N/A';
                            document.getElementById('case-village').textContent = caseData.description.village || 'N/A';
                            document.getElementById('case-block-number').textContent = caseData.description.block_number || 'N/A';

                            // Populate evidence files
                            evidenceList.innerHTML = evidence_files.length > 0
                                ? evidence_files.map(file => `
                                    <div class="evidence-file">
                                        <a href="${file.file_path}" target="_blank">${file.evidence_type}</a>
                                    </div>
                                `).join('')
                                : '<p><?php echo $translations[$lang]['no_evidence'] ?? 'No evidence files available.'; ?></p>';

                            // Case-type-specific content
                            caseSpecificContent.innerHTML = '';
                            if (caseData.title === 'mirkaneessa_abbaa_qabiyyumma' && parcel) {
                                caseSpecificContent.innerHTML = `
                                    <div class="parcel-details">
                                        <h6><?php echo $translations[$lang]['parcel_details'] ?? 'Parcel Details'; ?></h6>
                                        <p><strong><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?>:</strong> ${parcel.owner_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['first_name'] ?? 'First Name'; ?>:</strong> ${parcel.first_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['middle_name'] ?? 'Middle Name'; ?>:</strong> ${parcel.middle_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?>:</strong> ${parcel.zone || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['village'] ?? 'Village'; ?>:</strong> ${parcel.village || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?>:</strong> ${parcel.block_number || 'N/A'}</p>
                                        ${parcel.owner_photo ? `<img src="${parcel.owner_photo}" alt="Owner Photo" class="img-thumbnail" style="max-width: 100px;">` : ''}
                                        <button class="btn btn-primary print-btn" onclick="printParcel('${caseId}')"><?php echo $translations[$lang]['print_parcel'] ?? 'Print Parcel'; ?></button>
                                    </div>
                                `;
                            } else if (caseData.title === 'mirkaneessa_sirrumma_waraqa_ragaa' && parcel) {
                                caseSpecificContent.innerHTML = `
                                    <div class="parcel-details">
                                        <h6><?php echo $translations[$lang]['parcel_details'] ?? 'Parcel Details'; ?></h6>
                                        <p><strong><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?>:</strong> ${parcel.owner_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['first_name'] ?? 'First Name'; ?>:</strong> ${parcel.first_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['middle_name'] ?? 'Middle Name'; ?>:</strong> ${parcel.middle_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?>:</strong> ${parcel.zone || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['village'] ?? 'Village'; ?>:</strong> ${parcel.village || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?>:</strong> ${parcel.block_number || 'N/A'}</p>
                                        ${parcel.owner_photo ? `<img src="${parcel.owner_photo}" alt="Owner Photo" class="img-thumbnail" style="max-width: 100px;">` : ''}
                                        <button class="btn btn-primary print-btn" onclick="printParcel('${caseId}')"><?php echo $translations[$lang]['print_parcel'] ?? 'Print Parcel'; ?></button>
                                    </div>
                                `;
                            } else if (caseData.title === 'waraqa_bade_bakka_buusu' && evidence_files.length > 0) {
                                caseSpecificContent.innerHTML = `
                                    <div class="evidence-selection">
                                        <h6><?php echo $translations[$lang]['select_evidence'] ?? 'Select Evidence for Printing'; ?></h6>
                                        ${evidence_files.map(file => `
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="${file.file_path}" id="evidence-${file.id}">
                                                <label class="form-check-label" for="evidence-${file.id}">
                                                    ${file.evidence_type} (<a href="${file.file_path}" target="_blank"><?php echo $translations[$lang]['view'] ?? 'View'; ?></a>)
                                                </label>
                                            </div>
                                        `).join('')}
                                        <button class="btn btn-primary print-btn" onclick="printSelectedEvidence()"><?php echo $translations[$lang]['print_selected'] ?? 'Print Selected'; ?></button>
                                    </div>
                                `;
                            } else if (caseData.title === 'qabiye_walitti_makuu') {
                                caseSpecificContent.innerHTML = `
                                    <div class="merged-properties">
                                        <h6><?php echo $translations[$lang]['merged_properties'] ?? 'Merged Properties'; ?></h6>
                                        <p><strong><?php echo $translations[$lang]['full_name'] ?? 'Full Name'; ?>:</strong> ${caseData.description.full_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?>:</strong> ${caseData.description.zone || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['village'] ?? 'Village'; ?>:</strong> ${caseData.description.village || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?>:</strong> ${caseData.description.block_number || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['details'] ?? 'Details'; ?>:</strong> ${caseData.description.other_case || 'No additional details'}</p>
                                    </div>
                                `;
                            } else if (caseData.title === 'jijjirra_maqaa') {
                                caseSpecificContent.innerHTML = `
                                    <div class="changed-info">
                                        <h6><?php echo $translations[$lang]['changed_ownership'] ?? 'Changed Ownership Information'; ?></h6>
                                        <p><strong><?php echo $translations[$lang]['new_name'] ?? 'New Name'; ?>:</strong> ${caseData.description.full_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['details'] ?? 'Details'; ?>:</strong> ${caseData.description.other_case || 'No additional details'}</p>
                                    </div>
                                `;
                            }

                            // Set up finalize button
                            confirmFinalizeBtn.setAttribute('data-case-id', caseId);
                            modal.show();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '<?php echo $translations[$lang]['error'] ?? 'Error'; ?>',
                                text: result.message || '<?php echo $translations[$lang]['case_not_found'] ?? 'Case not found'; ?>',
                                confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>',
                                direction: '<?php echo $dir; ?>'
                            });
                        }
                    } catch (error) {
                        Swal.fire({
                            icon: 'error',
                            title: '<?php echo $translations[$lang]['error'] ?? 'Error'; ?>',
                            text: '<?php echo $translations[$lang]['request_failed'] ?? 'Request failed'; ?>',
                            confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>',
                            direction: '<?php echo $dir; ?>'
                        });
                    }
                });
            });

            confirmFinalizeBtn.addEventListener('click', async () => {
                const caseId = confirmFinalizeBtn.getAttribute('data-case-id');
                const confirmed = await Swal.fire({
                    title: '<?php echo $translations[$lang]['confirm_finalize'] ?? 'Confirm Finalization'; ?>',
                    text: '<?php echo $translations[$lang]['finalize_confirm'] ?? 'Are you sure you want to finalize this case?'; ?>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '<?php echo $translations[$lang]['yes_finalize'] ?? 'Yes, finalize'; ?>',
                    cancelButtonText: '<?php echo $translations[$lang]['cancel'] ?? 'Cancel'; ?>',
                    direction: '<?php echo $dir; ?>'
                });

                if (!confirmed.isConfirmed) return;

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            action: 'finalize_case',
                            case_id: caseId
                        })
                    });

                    const result = await response.json();
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '<?php echo $translations[$lang]['success'] ?? 'Success'; ?>',
                            text: result.message,
                            confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>',
                            direction: '<?php echo $dir; ?>'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<?php echo $translations[$lang]['error'] ?? 'Error'; ?>',
                            text: result.message,
                            confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>',
                            direction: '<?php echo $dir; ?>'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: '<?php echo $translations[$lang]['error'] ?? 'Error'; ?>',
                        text: '<?php echo $translations[$lang]['request_failed'] ?? 'Request failed'; ?>',
                        confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>',
                        direction: '<?php echo $dir; ?>'
                    });
                }
            });

            window.printParcel = function(caseId) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'get_case_details',
                        case_id: caseId
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data.parcel) {
                        const parcel = result.data.parcel;
                        const printWindow = window.open('', '_blank');
                        printWindow.document.write(`
                            <html>
                                <head>
                                    <title><?php echo $translations[$lang]['print_parcel'] ?? 'Print Parcel'; ?></title>
                                    <style>
                                        body { font-family: Arial, sans-serif; margin: 20px; }
                                        h1 { text-align: center; }
                                        .parcel-details { max-width: 600px; margin: 0 auto; }
                                        p { margin: 10px 0; }
                                        img { max-width: 100px; }
                                    </style>
                                </head>
                                <body>
                                    <h1><?php echo $translations[$lang]['parcel_details'] ?? 'Parcel Details'; ?></h1>
                                    <div class="parcel-details">
                                        <p><strong><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?>:</strong> ${parcel.owner_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['first_name'] ?? 'First Name'; ?>:</strong> ${parcel.first_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['middle_name'] ?? 'Middle Name'; ?>:</strong> ${parcel.middle_name || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?>:</strong> ${parcel.zone || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['village'] ?? 'Village'; ?>:</strong> ${parcel.village || 'N/A'}</p>
                                        <p><strong><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?>:</strong> ${parcel.block_number || 'N/A'}</p>
                                        ${parcel.owner_photo ? `<img src="${parcel.owner_photo}" alt="Owner Photo">` : ''}
                                    </div>
                                    <script>
                                        window.onload = function() { window.print(); window.close(); };
                                    </script>
                                </body>
                            </html>
                        `);
                        printWindow.document.close();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<?php echo $translations[$lang]['error'] ?? 'Error'; ?>',
                            text: '<?php echo $translations[$lang]['parcel_not_found'] ?? 'Parcel details not found'; ?>',
                            confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>',
                            direction: '<?php echo $dir; ?>'
                        });
                    }
                });
            };

            window.printSelectedEvidence = function() {
                const selectedFiles = Array.from(document.querySelectorAll('#evidence-list input:checked')).map(input => input.value);
                if (selectedFiles.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: '<?php echo $translations[$lang]['no_selection'] ?? 'No Selection'; ?>',
                        text: '<?php echo $translations[$lang]['select_files'] ?? 'Please select at least one file to print'; ?>',
                        confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>',
                        direction: '<?php echo $dir; ?>'
                    });
                    return;
                }

                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title><?php echo $translations[$lang]['print_evidence'] ?? 'Print Evidence'; ?></title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                h1 { text-align: center; }
                                .evidence-container { max-width: 600px; margin: 0 auto; }
                                img, iframe { max-width: 100%; margin: 10px 0; }
                            </style>
                        </head>
                        <body>
                            <h1><?php echo $translations[$lang]['evidence_files'] ?? 'Evidence Files'; ?></h1>
                            <div class="evidence-container">
                                ${selectedFiles.map(file => `
                                    ${file.endsWith('.pdf') ? `<iframe src="${file}" style="width: 100%; height: 500px;"></iframe>` : `<img src="${file}" alt="Evidence">`}
                                `).join('')}
                            </div>
                            <script>
                                window.onload = function() { window.print(); window.close(); };
                            </script>
                        </body>
                    </html>
                `);
                printWindow.document.close();
            };
        });
    </script>
</body>
</html>
<?php
// Close connection only if it exists and is open
if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
    $conn->close();
}
?>
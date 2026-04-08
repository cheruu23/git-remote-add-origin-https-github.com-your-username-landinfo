<?php
ob_start();
require_once '../../includes/init.php';
redirectIfNotLoggedIn();
restrictAccess(['manager'], 'receive cases');

// Include sidebar.php once to prevent redeclaration of buildLanguageUrl()
include_once '../../templates/sidebar.php';

$conn = getDBConnection();
if ($conn->connect_error) {
    logAction('db_connection_error', 'Failed to connect to database: ' . $conn->connect_error, 'error');
    die($translations[$lang]['db_connection_failed'] ?? 'Database connection error.');
}
$conn->set_charset('utf8mb4');

// Log viewing receive cases
logAction('mirkaneessa_abbaa_qabiyyumma', 'Manager viewed mirkaneessa_abbaa_qabiyyumma cases', 'info');

$case_id = $_GET['case_id'] ?? null;
$view = $_GET['view'] ?? null; // Tracks which button was clicked (parcel or files)
$action_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['case_id'])) {
    $action = $_POST['action'];
    $post_case_id = (int)$_POST['case_id'];
    if ($post_case_id > 0 && in_array($action, ['approve', 'reject'])) {
        $stmt = $conn->prepare("UPDATE cases SET status = ?, approved_by = ? WHERE id = ?");
        $new_status = $action === 'approve' ? 'Approved' : 'Rejected';
        $stmt->bind_param("sii", $new_status, $user_id, $post_case_id);
        if ($stmt->execute()) {
            $modal_id = $action === 'approve' ? 'approveModal' : 'rejectModal';
            $action_message = "<script>document.addEventListener('DOMContentLoaded', function() { new bootstrap.Modal(document.getElementById('$modal_id')).show(); });</script>";
        }
        $stmt->close();
    }
}

$case = null;
if ($case_id) {
    $stmt = $conn->prepare("SELECT id, title, status, land_id FROM cases WHERE id = ?");
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $case = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch parcel details if view=parcel
$land = null;
$coordinates = [];
if ($view === 'parcel' && $case && isset($case['land_id'])) {
    $land_id = $case['land_id'];
    $sql = "SELECT id, owner_name, first_name, middle_name, gender, owner_phone, land_type, village, zone, 
                   block_number, parcel_number, effective_date, group_category, land_grade, land_service, 
                   neighbor_east, neighbor_west, neighbor_south, neighbor_north, id_front, id_back, 
                   xalayaa_miritii, nagaee_gibiraa, waligaltee_lease, tax_receipt, miriti_paper, 
                   caalbaasii_agreement, bita_fi_gurgurtaa_agreement, bita_fi_gurgurtaa_receipt, owner_photo, 
                   registration_date, created_at, agreement_number, duration, area, purpose, plot_number, 
                   coordinates, surveyor_name, head_surveyor_name, land_officer_name, has_parcel, 
                   parcel_lease_date, parcel_agreement_number, parcel_lease_duration, parcel_village, 
                   parcel_block_number, parcel_land_grade, parcel_land_area, parcel_land_service, 
                   parcel_registration_number, building_height_allowed, prepared_by_name, prepared_by_role, 
                   approved_by_name, approved_by_role, authorized_by_name, authorized_by_role, status, user_id
            FROM land_registration 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $land_id);
    $stmt->execute();
    $land = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Parse coordinates
    if ($land && $land['coordinates']) {
        $pairs = explode(';', trim($land['coordinates'], ';'));
        foreach ($pairs as $pair) {
            $parts = explode(',', $pair);
            if (count($parts) == 2) {
                $x = floatval($parts[0]);
                $y = floatval($parts[1]);
                $coordinates[] = ['x' => $x, 'y' => $y];
            }
        }
    }
}

// Fetch land registration records if view=files
$records = [];
if ($view === 'files' && $case && isset($case['land_id'])) {
    $land_id = $case['land_id'];
    $sql = "SELECT id, owner_name, first_name, middle_name, gender, land_type, village, zone, 
                   block_number, parcel_number, effective_date, owner_phone, group_category, land_grade, 
                   land_service, neighbor_east, neighbor_west, neighbor_south, neighbor_north, id_front, 
                   id_back, xalayaa_miritii, nagaee_gibiraa, waligaltee_lease, tax_receipt, miriti_paper, 
                   caalbaasii_agreement, bita_fi_gurgurtaa_agreement, bita_fi_gurgurtaa_receipt, owner_photo, 
                   registration_date, created_at, agreement_number, duration, area, purpose, plot_number, 
                   coordinates, surveyor_name, head_surveyor_name, land_officer_name, has_parcel, 
                   parcel_lease_date, parcel_agreement_number, parcel_lease_duration, parcel_village, 
                   parcel_block_number, parcel_land_grade, parcel_land_area, parcel_land_service, 
                   parcel_registration_number, building_height_allowed, prepared_by_name, prepared_by_role, 
                   approved_by_name, approved_by_role, authorized_by_name, authorized_by_role, status, user_id
            FROM land_registration 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $land_id);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Parse coordinates for each record
    foreach ($records as &$record) {
        $record['coordinates_array'] = [];
        if ($record['coordinates']) {
            $pairs = explode(';', trim($record['coordinates'], ';'));
            foreach ($pairs as $pair) {
                $parts = explode(',', $pair);
                if (count($parts) == 2) {
                    $record['coordinates_array'][] = ['x' => floatval($parts[0]), 'y' => floatval($parts[1])];
                }
            }
        }
    }
}

$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
$conn->close();

// Define buildLanguageUrl() only if not already defined (fallback)
if (!function_exists('buildLanguageUrl')) {
    function buildLanguageUrl($new_lang) {
        $query = $_GET;
        $query['lang'] = $new_lang;
        return '?' . http_build_query($query);
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['total_cases'] ?? 'Total Cases'; ?> - LIMS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        .btn-view-parcel,
        .btn-files {
            font-size: 0.9rem;
            padding: 8px 20px;
            border-radius: 6px;
            margin-left: 10px;
        }

        .btn-view-parcel {
            background: linear-gradient(135deg, #17a2b8, #117a8b);
            color: #fff;
        }

        .btn-files {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #fff;
        }

        .btn-view-parcel:hover {
            background: linear-gradient(135deg, #117a8b, #0d6a7a);
        }

        .btn-files:hover {
            background: linear-gradient(135deg, #e0a800, #c69500);
        }

        .btn-approve,
        .btn-reject {
            font-size: 0.9rem;
            padding: 8px 20px;
            border-radius: 6px;
            margin-left: 10px;
        }

        .btn-approve {
            background: linear-gradient(135deg, #28a745, #1e7e34);
            color: #fff;
        }

        .btn-reject {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: #fff;
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #1e7e34, #155724);
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #c82333, #a71d2a);
        }

        .content-section {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .certificate {
            width: 800px;
            border: 2px solid #000;
            background-color: #fffdf5;
            padding: 20px;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(255, 0, 0, 0.3);
            pointer-events: none;
            text-transform: uppercase;
            font-weight: bold;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
            position: relative;
        }

        .header h1,
        .header h2,
        .header h3 {
            margin: 5px 0;
            font-size: 18px;
            font-weight: bold;
            color: #c0392b;
        }

        .header .certificate-number {
            font-size: 16px;
            color: #000;
        }

        .logo {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 80px;
            height: 80px;
        }

        .owner-photo {
            position: absolute;
            top: 10px;
            right: 0;
            width: 100px;
            height: 120px;
            border: 2px solid #3498db;
            border-radius: 5px;
            overflow: hidden;
        }

        .owner-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .section {
            margin-bottom: 20px;
        }

        .section p {
            margin: 5px 0;
            font-size: 14px;
            color: #2c3e50;
        }

        .section h2 {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px dashed #c0392b;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: #c0392b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        table,
        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 14px;
            text-align: center;
        }

        th {
            background-color: #ecf0f1;
            color: #2c3e50;
        }

        td {
            color: #2c3e50;
        }

        .neighbors,
        .additional-info {
            display: flex;
            justify-content: space-between;
        }

        .neighbors div,
        .additional-info div {
            width: 45%;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 14px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }

        .signatures div {
            text-align: center;
            width: 30%;
        }

        .signatures p {
            margin: 5px 0;
            color: #2c3e50;
        }

        .signatures strong {
            color: #c0392b;
        }

        .signature-line {
            border-top: 1px dashed #000;
            margin-top: 20px;
            padding-top: 5px;
            font-style: italic;
            color: #7f8c8d;
        }

        .site-plan {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-top: 10px;
        }

        .site-plan svg {
            margin-right: 20px;
        }

        .table-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .table thead {
            background-color: #007bff;
            color: white;
        }

        .search-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
        }

        .search-bar .input-group {
            width: 200px;
            transition: width 0.3s;
        }

        .search-bar .input-group:focus-within {
            width: 300px;
        }

        .search-bar input {
            border-radius: 20px 0 0 20px !important;
            padding: 8px 15px;
        }

        .search-bar .input-group-text {
            border-radius: 0 20px 20px 0;
            background: #007bff;
            border: none;
            cursor: pointer;
        }

        .hidden {
            display: none;
        }

        .details-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            border: 2px solid #007bff;
            border-radius: 10px;
            background-color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .details-container h2,
        .details-container h3 {
            color: #007bff;
            margin-top: 20px;
        }

        .details-container p {
            margin: 5px 0;
            font-size: 16px;
        }

        .details-container strong {
            color: #007bff;
            display: inline-block;
            width: 250px;
        }

        .details-container .owner-photo {
            position: absolute;
            top: 20px;
            right: 20px;
        }

        .details-container .owner-photo img {
            width: 150px;
            border-radius: 8px;
            border: 2px solid #007bff;
        }

        .details-container table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }

        .details-container table th,
        .details-container table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .details-container table th {
            background-color: #007bff;
            color: white;
        }

        .details-container .document-list a {
            display: block;
            color: #007bff;
            text-decoration: none;
            cursor: pointer;
            margin: 5px 0;
        }

        .details-container .document-list a:hover {
            text-decoration: underline;
        }

        #documentImage {
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
            transition: transform 0.2s ease;
        }

        .modal-body {
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: auto;
        }

        .zoom-btn {
            font-size: 1rem;
            margin: 0 5px;
        }

        .no-parcel-message {
            color: #dc3545;
            font-weight: bold;
            margin-top: 10px;
            display: none;
        }

        .coordinates-table td {
            text-align: center;
        }

        .details-row {
            display: none;
        }

        .details-row.active {
            display: table-row;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <?php if ($case): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3><?php echo $translations[$lang]['total_cases'] ?? 'Total Cases'; ?>: <?php echo htmlspecialchars($case['title']); ?> (ID: <?php echo htmlspecialchars($case_id); ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="button-group">
                        <a href="<?php echo $base_path; ?>modules/<?php echo htmlspecialchars($role); ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <?php echo $translations[$lang]['back'] ?? 'Back'; ?></a>
                        <a href="?case_id=<?php echo htmlspecialchars($case_id); ?>&view=parcel" class="btn btn-view-parcel"><i class="fas fa-map"></i> <?php echo $translations[$lang]['view_parcel'] ?? 'View Parcel'; ?></a>
                        <a href="?case_id=<?php echo htmlspecialchars($case_id); ?>&view=files" class="btn btn-files"><i class="fas fa-folder"></i> <?php echo $translations[$lang]['files'] ?? 'Files'; ?></a>
                        <form action="" method="POST" style="display: inline;">
                            <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-approve"><i class="fas fa-check"></i> <?php echo $translations[$lang]['approve'] ?? 'Approve'; ?></button>
                        </form>
                        <form action="" method="POST" style="display: inline;">
                            <input type="hidden" name="case_id" value="<?php echo htmlspecialchars($case_id); ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-reject"><i class="fas fa-times"></i> <?php echo $translations[$lang]['reject'] ?? 'Reject'; ?></button>
                        </form>
                    </div>
                    <?php if ($view === 'parcel' && $land): ?>
                        <div class="content-section">
                            <div class="certificate">
                                <div class="watermark"><?php echo $translations[$lang]['copy'] ?? 'COPY'; ?></div>
                                <img class="logo" src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="<?php echo $translations[$lang]['oromia_regional_government'] ?? 'Logo of Oromia Regional Government'; ?>">
                                <div class="owner-photo">
                                    <img src="<?php echo htmlspecialchars($land['owner_photo'] ?? '/Uploads/owner-photo-placeholder.jpg'); ?>" alt="<?php echo $translations[$lang]['owner_photo'] ?? 'Owner Photo'; ?>">
                                </div>
                                <div class="header">
                                    <h1><?php echo $translations[$lang]['oromia_regional_government'] ?? 'Oromia Regional Government'; ?></h1>
                                    <h2><?php echo $translations[$lang]['land_admin_bureau'] ?? 'Oromia Land Administration and Use Bureau'; ?></h2>
                                    <p class="certificate-number"><?php echo $translations[$lang]['certificate_no'] ?? 'Certificate No'; ?>: <?php echo htmlspecialchars($land['parcel_registration_number'] ?? $land['parcel_number'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="section">
                                    <h2><?php echo $translations[$lang]['lease_holder'] ?? 'Lease Holder'; ?></h2>
                                    <p><strong><?php echo $translations[$lang]['name'] ?? 'Name'; ?>:</strong> <?php echo htmlspecialchars(trim(($land['first_name'] ?? '') . ' ' . ($land['middle_name'] ?? '') . ' ' . ($land['owner_name'] ?? ''))); ?></p>
                                </div>
                                <div class="section">
                                    <h2><?php echo $translations[$lang]['lease_details'] ?? 'Lease Details'; ?></h2>
                                    <p><strong><?php echo $translations[$lang]['date'] ?? 'Date'; ?>:</strong> <?php echo htmlspecialchars($land['parcel_lease_date'] ?? $land['effective_date'] ?? 'N/A'); ?></p>
                                    <p><strong><?php echo $translations[$lang]['agreement_number'] ?? 'Agreement Number'; ?>:</strong> <?php echo htmlspecialchars($land['parcel_agreement_number'] ?? $land['agreement_number'] ?? 'N/A'); ?></p>
                                    <p><strong><?php echo $translations[$lang]['duration'] ?? 'Duration'; ?>:</strong> <?php echo htmlspecialchars($land['parcel_lease_duration'] ?? $land['duration'] ?? 'N/A'); ?> <?php echo $translations[$lang]['years'] ?? 'years'; ?></p>
                                    <p><strong><?php echo $translations[$lang]['area'] ?? 'Area'; ?>:</strong> <?php echo htmlspecialchars($land['parcel_land_area'] ?? $land['area'] ?? 'N/A'); ?> m²</p>
                                    <p><strong><?php echo $translations[$lang]['purpose'] ?? 'Purpose'; ?>:</strong> <?php echo htmlspecialchars($land['purpose'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="section">
                                    <h2><?php echo $translations[$lang]['coordinates'] ?? 'Coordinates (XY)'; ?></h2>
                                    <table>
                                        <tr>
                                            <th><?php echo $translations[$lang]['point'] ?? 'Point'; ?></th>
                                            <th>X</th>
                                            <th>Y</th>
                                        </tr>
                                        <?php foreach ($coordinates as $index => $coord): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($coord['x']); ?></td>
                                                <td><?php echo htmlspecialchars($coord['y']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($coordinates)): ?>
                                            <tr>
                                                <td colspan="3"><?php echo $translations[$lang]['no_coordinates'] ?? 'No coordinates available'; ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="section">
                                    <h2><?php echo $translations[$lang]['site_plan'] ?? 'Plot/Site Plan'; ?></h2>
                                    <div class="site-plan">
                                        <svg width="150" height="100" viewBox="0 0 150 100" xmlns="http://www.w3.org/2000/svg">
                                            <?php if (!empty($coordinates)): ?>
                                                <polygon points="<?php
                                                                    $points = '';
                                                                    $min_x = min(array_column($coordinates, 'x'));
                                                                    $max_x = max(array_column($coordinates, 'x'));
                                                                    $min_y = min(array_column($coordinates, 'y'));
                                                                    $max_y = max(array_column($coordinates, 'y'));
                                                                    $scale_x = $max_x != $min_x ? 150 / ($max_x - $min_x) : 1;
                                                                    $scale_y = $max_y != $min_y ? 100 / ($max_y - $min_y) : 1;
                                                                    foreach ($coordinates as $coord) {
                                                                        $x = ($coord['x'] - $min_x) * $scale_x;
                                                                        $y = 100 - ($coord['y'] - $min_y) * $scale_y;
                                                                        $points .= "$x,$y ";
                                                                    }
                                                                    echo trim($points);
                                                                    ?>" fill="none" stroke="#000" stroke-width="1" />
                                                <rect x="40" y="20" width="50" height="30" fill="#d3d3d3" />
                                                <?php foreach ($coordinates as $index => $coord): ?>
                                                    <?php
                                                    $x = ($coord['x'] - $min_x) * $scale_x;
                                                    $y = 100 - ($coord['y'] - $min_y) * $scale_y;
                                                    ?>
                                                    <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="2" fill="#2c3e50" />
                                                    <text x="<?php echo $x - 5; ?>" y="<?php echo $y - 5; ?>" font-size="10" fill="#2c3e50"><?php echo $index + 1; ?></text>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <text x="75" y="50" font-size="10" fill="#2c3e50" text-anchor="middle"><?php echo $translations[$lang]['no_site_plan'] ?? 'No site plan available'; ?></text>
                                            <?php endif; ?>
                                        </svg>
                                    </div>
                                </div>
                                <div class="section neighbors">
                                    <div>
                                        <h2><?php echo $translations[$lang]['neighbors'] ?? 'Neighbors'; ?></h2>
                                        <p><strong><?php echo $translations[$lang]['east'] ?? 'East'; ?>:</strong> <?php echo htmlspecialchars($land['neighbor_east'] ?? 'N/A'); ?></p>
                                        <p><strong><?php echo $translations[$lang]['west'] ?? 'West'; ?>:</strong> <?php echo htmlspecialchars($land['neighbor_west'] ?? 'N/A'); ?></p>
                                        <p><strong><?php echo $translations[$lang]['north'] ?? 'North'; ?>:</strong> <?php echo htmlspecialchars($land['neighbor_north'] ?? 'N/A'); ?></p>
                                        <p><strong><?php echo $translations[$lang]['south'] ?? 'South'; ?>:</strong> <?php echo htmlspecialchars($land['neighbor_south'] ?? 'N/A'); ?></p>
                                        <h2><?php echo $translations[$lang]['land_details'] ?? 'Land Details'; ?></h2>
                                        <table>
                                            <tr>
                                                <th><?php echo $translations[$lang]['village'] ?? 'Village'; ?></th>
                                                <th><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?></th>
                                                <th><?php echo $translations[$lang]['land_grade'] ?? 'Land Grade'; ?></th>
                                                <th><?php echo $translations[$lang]['land_area'] ?? 'Land Area'; ?></th>
                                                <th><?php echo $translations[$lang]['land_service'] ?? 'Land Service'; ?></th>
                                                <th><?php echo $translations[$lang]['registration_number'] ?? 'Registration Number'; ?></th>
                                                <th><?php echo $translations[$lang]['building_height'] ?? 'Building Height Allowed'; ?></th>
                                            </tr>
                                            <tr>
                                                <td><?php echo htmlspecialchars($land['parcel_village'] ?? $land['village'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($land['parcel_block_number'] ?? $land['block_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($land['parcel_land_grade'] ?? $land['land_grade'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($land['parcel_land_area'] ?? $land['area'] ?? 'N/A'); ?> m²</td>
                                                <td><?php echo htmlspecialchars($land['parcel_land_service'] ?? $land['land_service'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($land['parcel_registration_number'] ?? $land['parcel_number'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($land['building_height_allowed'] ?? 'N/A'); ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div>
                                        <h2><?php echo $translations[$lang]['additional_info'] ?? 'Additional Information'; ?></h2>
                                        <p><strong><?php echo $translations[$lang]['plot_number'] ?? 'Plot Number'; ?>:</strong> <?php echo htmlspecialchars($land['plot_number'] ?? 'N/A'); ?></p>
                                        <p><strong><?php echo $translations[$lang]['land_grade'] ?? 'Land Grade'; ?>:</strong> <?php echo htmlspecialchars($land['parcel_land_grade'] ?? $land['land_grade'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="signatures">
                                    <div>
                                        <p><strong><?php echo $translations[$lang]['prepared_by'] ?? 'Prepared By'; ?>:</strong></p>
                                        <p><strong><?php echo $translations[$lang]['name'] ?? 'Name'; ?>:</strong> <?php echo htmlspecialchars($land['prepared_by_name'] ?? 'N/A'); ?></p>
                                        <p><strong><?php echo $translations[$lang]['role'] ?? 'Role'; ?>:</strong> <?php echo htmlspecialchars($land['prepared_by_role'] ?? 'Surveyor'); ?></p>
                                        <p class="signature-line">[<?php echo $translations[$lang]['signature'] ?? 'Signature'; ?>]</p>
                                    </div>
                                    <div>
                                        <p><strong><?php echo $translations[$lang]['approved_by'] ?? 'Approved By'; ?>:</strong></p>
                                        <p><strong><?php echo $translations[$lang]['name'] ?? 'Name'; ?>:</strong> <?php echo htmlspecialchars($land['approved_by_name'] ?? 'N/A'); ?></p>
                                        <p><strong><?php echo $translations[$lang]['role'] ?? 'Role'; ?>:</strong> <?php echo htmlspecialchars($land['approved_by_role'] ?? 'Head Surveyor'); ?></p>
                                        <p class="signature-line">[<?php echo $translations[$lang]['signature'] ?? 'Signature'; ?>]</p>
                                    </div>
                                    <div>
                                        <p><strong><?php echo $translations[$lang]['authorized_by'] ?? 'Authorized By'; ?>:</strong></p>
                                        <p><strong><?php echo $translations[$lang]['name'] ?? 'Name'; ?>:</strong> <?php echo htmlspecialchars($land['authorized_by_name'] ?? 'N/A'); ?></p>
                                        <p><strong><?php echo $translations[$lang]['role'] ?? 'Role'; ?>:</strong> <?php echo htmlspecialchars($land['authorized_by_role'] ?? 'Land Officer'); ?></p>
                                        <p class="signature-line">[<?php echo $translations[$lang]['signature'] ?? 'Signature'; ?>]</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($view === 'parcel' && !$land): ?>
                        <div class="content-section">
                            <p><?php echo $translations[$lang]['no_parcel_details'] ?? 'No parcel details available.'; ?></p>
                        </div>
                    <?php elseif ($view === 'files' && !empty($records)): ?>
                        <div class="content-section">
                            <div class="table-container">
                                <h1 class="text-center mb-4"><?php echo $translations[$lang]['land_records'] ?? 'Land Records'; ?></h1>
                                <div class="search-bar">
                                    <div class="input-group">
                                        <input type="text" id="searchInput" class="form-control" placeholder="<?php echo $translations[$lang]['search_placeholder'] ?? 'Search by ID, Name, Number'; ?>">
                                        <span class="input-group-text" id="searchButton">
                                            <i class="fas fa-search"></i>
                                        </span>
                                    </div>
                                </div>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th><?php echo $translations[$lang]['id'] ?? 'ID'; ?></th>
                                            <th><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?></th>
                                            <th><?php echo $translations[$lang]['first_name'] ?? 'First Name'; ?></th>
                                            <th><?php echo $translations[$lang]['middle_name'] ?? 'Middle Name'; ?></th>
                                            <th><?php echo $translations[$lang]['gender'] ?? 'Gender'; ?></th>
                                            <th><?php echo $translations[$lang]['land_type'] ?? 'Land Type'; ?></th>
                                            <th><?php echo $translations[$lang]['village'] ?? 'Village'; ?></th>
                                            <th><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?></th>
                                            <th><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?></th>
                                            <th><?php echo $translations[$lang]['parcel_number'] ?? 'Parcel Number'; ?></th>
                                            <th><?php echo $translations[$lang]['effective_date'] ?? 'Effective Date'; ?></th>
                                            <th><?php echo $translations[$lang]['actions'] ?? 'Actions'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="fileList">
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['id']); ?></td>
                                                <td><?php echo htmlspecialchars($record['owner_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['first_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['middle_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['gender']); ?></td>
                                                <td><?php echo htmlspecialchars($record['land_type']); ?></td>
                                                <td><?php echo htmlspecialchars($record['village']); ?></td>
                                                <td><?php echo htmlspecialchars($record['zone']); ?></td>
                                                <td><?php echo htmlspecialchars($record['block_number']); ?></td>
                                                <td><?php echo htmlspecialchars($record['parcel_number']); ?></td>
                                                <td><?php echo htmlspecialchars($record['effective_date']); ?></td>
                                                <td>
                                                    <button class="btn btn-primary btn-sm toggle-details" data-id="<?php echo htmlspecialchars($record['id']); ?>"><?php echo $translations[$lang]['view'] ?? 'View'; ?></button>
                                                </td>
                                            </tr>
                                            <tr class="details-row" id="details-<?php echo htmlspecialchars($record['id']); ?>">
                                                <td colspan="12">
                                                    <div class="details-container">
                                                        <div class="owner-photo">
                                                            <img src="<?php echo htmlspecialchars($record['owner_photo'] ?? '/Uploads/owner-photo-placeholder.jpg'); ?>" alt="<?php echo $translations[$lang]['owner_photo'] ?? 'Owner Photo'; ?>">
                                                        </div>
                                                        <h3><?php echo $translations[$lang]['personal_info'] ?? 'Personal Information'; ?></h3>
                                                        <p><strong><?php echo $translations[$lang]['id'] ?? 'ID'; ?>:</strong> <?php echo htmlspecialchars($record['id'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['full_name'] ?? 'Full Name'; ?>:</strong> <?php echo htmlspecialchars(trim(($record['owner_name'] ?? '') . ' ' . ($record['first_name'] ?? '') . ' ' . ($record['middle_name'] ?? ''))); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['owner_phone'] ?? 'Owner Phone'; ?>:</strong> <?php echo htmlspecialchars($record['owner_phone'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['gender'] ?? 'Gender'; ?>:</strong> <?php echo htmlspecialchars($record['gender'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['group_category'] ?? 'Group Category'; ?>:</strong> <?php echo htmlspecialchars($record['group_category'] ?? 'N/A'); ?></p>
                                                        <h3><?php echo $translations[$lang]['land_info'] ?? 'Land Information'; ?></h3>
                                                        <p><strong><?php echo $translations[$lang]['land_type'] ?? 'Land Type'; ?>:</strong> <?php echo htmlspecialchars($record['land_type'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['village'] ?? 'Village'; ?>:</strong> <?php echo htmlspecialchars($record['village'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?>:</strong> <?php echo htmlspecialchars($record['zone'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?>:</strong> <?php echo htmlspecialchars($record['block_number'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['parcel_number'] ?? 'Parcel Number'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_number'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['land_grade'] ?? 'Land Grade'; ?>:</strong> <?php echo htmlspecialchars($record['land_grade'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['land_service'] ?? 'Land Service'; ?>:</strong> <?php echo htmlspecialchars($record['land_service'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['area'] ?? 'Area'; ?>:</strong> <?php echo htmlspecialchars($record['area'] ?? 'N/A'); ?> m²</p>
                                                        <p><strong><?php echo $translations[$lang]['purpose'] ?? 'Purpose'; ?>:</strong> <?php echo htmlspecialchars($record['purpose'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['plot_number'] ?? 'Plot Number'; ?>:</strong> <?php echo htmlspecialchars($record['plot_number'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['effective_date'] ?? 'Effective Date'; ?>:</strong> <?php echo htmlspecialchars($record['effective_date'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['registration_date'] ?? 'Registration Date'; ?>:</strong> <?php echo htmlspecialchars($record['registration_date'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['created_at'] ?? 'Created At'; ?>:</strong> <?php echo htmlspecialchars($record['created_at'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['status'] ?? 'Status'; ?>:</strong> <?php echo htmlspecialchars($record['status'] ?? 'N/A'); ?></p>
                                                        <p>
                                                            <button class="btn btn-primary btn-sm toggle-parcel" data-id="<?php echo htmlspecialchars($record['id']); ?>" data-has-parcel="<?php echo htmlspecialchars($record['has_parcel'] ?? 0); ?>">
                                                                <?php echo $translations[$lang]['view_parcel'] ?? 'View Parcel'; ?>
                                                            </button>
                                                        </p>
                                                        <p class="no-parcel-message" id="noParcelMessage-<?php echo htmlspecialchars($record['id']); ?>">
                                                            <?php echo $translations[$lang]['no_parcel'] ?? 'This user doesn\'t have a parcel.'; ?> <a href="apply_parcel.php"><?php echo $translations[$lang]['apply_parcel'] ?? 'Apply for Parcel'; ?></a>
                                                        </p>
                                                        <h3><?php echo $translations[$lang]['parcel_info'] ?? 'Parcel Information'; ?></h3>
                                                        <p><strong><?php echo $translations[$lang]['parcel_village'] ?? 'Parcel Village'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_village'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['parcel_block_number'] ?? 'Parcel Block Number'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_block_number'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['parcel_land_grade'] ?? 'Parcel Land Grade'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_land_grade'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['parcel_land_area'] ?? 'Parcel Land Area'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_land_area'] ?? 'N/A'); ?> m²</p>
                                                        <p><strong><?php echo $translations[$lang]['parcel_land_service'] ?? 'Parcel Land Service'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_land_service'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['parcel_registration_number'] ?? 'Parcel Registration Number'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_registration_number'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['parcel_lease_date'] ?? 'Parcel Lease Date'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_lease_date'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['parcel_agreement_number'] ?? 'Parcel Agreement Number'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_agreement_number'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['parcel_lease_duration'] ?? 'Parcel Lease Duration'; ?>:</strong> <?php echo htmlspecialchars($record['parcel_lease_duration'] ?? 'N/A'); ?> <?php echo $translations[$lang]['years'] ?? 'years'; ?></p>
                                                        <p><strong><?php echo $translations[$lang]['building_height'] ?? 'Building Height Allowed'; ?>:</strong> <?php echo htmlspecialchars($record['building_height_allowed'] ?? 'N/A'); ?></p>
                                                        <h3><?php echo $translations[$lang]['coordinates'] ?? 'Coordinates'; ?></h3>
                                                        <?php if (!empty($record['coordinates_array'])): ?>
                                                            <table class="coordinates-table">
                                                                <tr>
                                                                    <th><?php echo $translations[$lang]['point'] ?? 'Point'; ?></th>
                                                                    <th>X</th>
                                                                    <th>Y</th>
                                                                </tr>
                                                                <?php foreach ($record['coordinates_array'] as $index => $coord): ?>
                                                                    <tr>
                                                                        <td><?php echo $index + 1; ?></td>
                                                                        <td><?php echo htmlspecialchars($coord['x']); ?></td>
                                                                        <td><?php echo htmlspecialchars($coord['y']); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </table>
                                                        <?php else: ?>
                                                            <p><?php echo $translations[$lang]['no_coordinates'] ?? 'No coordinates available.'; ?></p>
                                                        <?php endif; ?>
                                                        <h3><?php echo $translations[$lang]['land_boundaries'] ?? 'Land Boundaries'; ?></h3>
                                                        <table>
                                                            <tr>
                                                                <th><?php echo $translations[$lang]['direction'] ?? 'Direction'; ?></th>
                                                                <th><?php echo $translations[$lang]['neighbor'] ?? 'Neighbor'; ?></th>
                                                            </tr>
                                                            <tr>
                                                                <td><?php echo $translations[$lang]['east'] ?? 'East'; ?></td>
                                                                <td><?php echo htmlspecialchars($record['neighbor_east'] ?? 'N/A'); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php echo $translations[$lang]['west'] ?? 'West'; ?></td>
                                                                <td><?php echo htmlspecialchars($record['neighbor_west'] ?? 'N/A'); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php echo $translations[$lang]['south'] ?? 'South'; ?></td>
                                                                <td><?php echo htmlspecialchars($record['neighbor_south'] ?? 'N/A'); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><?php echo $translations[$lang]['north'] ?? 'North'; ?></td>
                                                                <td><?php echo htmlspecialchars($record['neighbor_north'] ?? 'N/A'); ?></td>
                                                            </tr>
                                                        </table>
                                                        <h3><?php echo $translations[$lang]['signatures'] ?? 'Signatures'; ?></h3>
                                                        <p><strong><?php echo $translations[$lang]['prepared_by_name'] ?? 'Prepared By Name'; ?>:</strong> <?php echo htmlspecialchars($record['prepared_by_name'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['prepared_by_role'] ?? 'Prepared By Role'; ?>:</strong> <?php echo htmlspecialchars($record['prepared_by_role'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['approved_by_name'] ?? 'Approved By Name'; ?>:</strong> <?php echo htmlspecialchars($record['approved_by_name'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['approved_by_role'] ?? 'Approved By Role'; ?>:</strong> <?php echo htmlspecialchars($record['approved_by_role'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['authorized_by_name'] ?? 'Authorized By Name'; ?>:</strong> <?php echo htmlspecialchars($record['authorized_by_name'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['authorized_by_role'] ?? 'Authorized By Role'; ?>:</strong> <?php echo htmlspecialchars($record['authorized_by_role'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['surveyor_name'] ?? 'Surveyor Name'; ?>:</strong> <?php echo htmlspecialchars($record['surveyor_name'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['head_surveyor_name'] ?? 'Head Surveyor Name'; ?>:</strong> <?php echo htmlspecialchars($record['head_surveyor_name'] ?? 'N/A'); ?></p>
                                                        <p><strong><?php echo $translations[$lang]['land_officer_name'] ?? 'Land Officer Name'; ?>:</strong> <?php echo htmlspecialchars($record['land_officer_name'] ?? 'N/A'); ?></p>
                                                        <h3><?php echo $translations[$lang]['documents'] ?? 'Documents'; ?></h3>
                                                        <div class="document-list">
                                                            <?php if (!empty($record['id_front'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['id_front']); ?>')"><?php echo $translations[$lang]['id_front'] ?? 'ID Front'; ?></a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['id_back'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['id_back']); ?>')"><?php echo $translations[$lang]['id_back'] ?? 'ID Back'; ?></a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['xalayaa_miritii'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['xalayaa_miritii']); ?>')"><?php echo $translations[$lang]['xalayaa_miritii'] ?? 'Xalayaa Miritii'; ?></a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['nagaee_gibiraa'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['nagaee_gibiraa']); ?>')"><?php echo $translations[$lang]['nagaee_gibiraa'] ?? 'Nagaee Gibiraa'; ?></a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['waligaltee_lease'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['waligaltee_lease']); ?>')"><?php echo $translations[$lang]['waligaltee_lease'] ?? 'Waligaltee Lease'; ?></a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['tax_receipt'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['tax_receipt']); ?>')"><?php echo $translations[$lang]['tax_receipt'] ?? 'Tax Receipt'; ?></a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['miriti_paper'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['miriti_paper']); ?>')"><?php echo $translations[$lang]['miriti_paper'] ?? 'Miriti Paper'; ?></a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['caalbaasii_agreement'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['caalbaasii_agreement']); ?>')"><?php echo $translations[$lang]['caalbaasii_agreement'] ?? 'Caalbaasii Agreement'; ?></a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['bita_fi_gurgurtaa_agreement'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['bita_fi_gurgurtaa_agreement']); ?>')"><?php echo $translations[$lang]['bita_fi_gurgurtaa_agreement'] ?? 'Bita fi Gurgurtaa Agreement'; ?></a>
                                                            <?php endif; ?>
                                                            <?php if (!empty($record['bita_fi_gurgurtaa_receipt'])): ?>
                                                                <a onclick="showDocument('<?php echo htmlspecialchars($record['bita_fi_gurgurtaa_receipt']); ?>')"><?php echo $translations[$lang]['bita_fi_gurgurtaa_receipt'] ?? 'Bita fi Gurgurtaa Receipt'; ?></a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php elseif ($view === 'files'): ?>
                        <div class="content-section">
                            <p><?php echo $translations[$lang]['no_records'] ?? 'No land registration records available.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <p class="text-center"><?php echo $translations[$lang]['no_case_selected'] ?? 'No case selected.'; ?></p>
        <?php endif; ?>
        <?php echo $action_message; ?>
        <div class="modal fade" id="approveModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><?php echo $translations[$lang]['success'] ?? 'Success'; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body"><?php echo $translations[$lang]['approve_success'] ?? 'Case approved successfully.'; ?></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?php echo $translations[$lang]['close'] ?? 'Close'; ?></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="rejectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><?php echo $translations[$lang]['success'] ?? 'Success'; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body"><?php echo $translations[$lang]['reject_success'] ?? 'Case rejected successfully.'; ?></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?php echo $translations[$lang]['close'] ?? 'Close'; ?></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="documentModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo $translations[$lang]['document'] ?? 'Document'; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="documentImage" class="img-fluid" src="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary zoom-btn" onclick="zoomIn()"><?php echo $translations[$lang]['zoom_in'] ?? 'Zoom In'; ?></button>
                        <button type="button" class="btn btn-primary zoom-btn" onclick="zoomOut()"><?php echo $translations[$lang]['zoom_out'] ?? 'Zoom Out'; ?></button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $translations[$lang]['close'] ?? 'Close'; ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchButton = document.getElementById('searchButton');
            const fileList = document.getElementById('fileList');
            const rows = Array.from(fileList ? fileList.getElementsByTagName('tr') : []);

            function filterRows() {
                const searchTerm = searchInput.value.toLowerCase();
                rows.forEach(row => {
                    const id = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase();
                    const ownerName = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase();
                    const firstName = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase();
                    const blockNumber = row.querySelector('td:nth-child(9)')?.textContent.toLowerCase();
                    if (id?.includes(searchTerm) || ownerName?.includes(searchTerm) || firstName?.includes(searchTerm) || blockNumber?.includes(searchTerm)) {
                        row.classList.remove('hidden');
                    } else {
                        row.classList.add('hidden');
                    }
                });
            }

            if (searchButton && searchInput) {
                searchButton.addEventListener('click', filterRows);
                searchInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') filterRows();
                });
            }

            const toggleButtons = document.querySelectorAll('.toggle-details');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const detailsRow = document.getElementById('details-' + id);
                    if (detailsRow) {
                        detailsRow.classList.toggle('active');
                    }
                });
            });

            let currentZoom = 1;
            const minZoom = 0.5;
            const maxZoom = 3;
            const zoomStep = 0.25;

            window.showDocument = function(src) {
                const img = document.getElementById('documentImage');
                img.src = src;
                currentZoom = 1;
                img.style.transform = `scale(${currentZoom})`;
                new bootstrap.Modal(document.getElementById('documentModal')).show();
            };

            window.zoomIn = function() {
                if (currentZoom < maxZoom) {
                    currentZoom += zoomStep;
                    document.getElementById('documentImage').style.transform = `scale(${currentZoom})`;
                }
            };

            window.zoomOut = function() {
                if (currentZoom > minZoom) {
                    currentZoom -= zoomStep;
                    document.getElementById('documentImage').style.transform = `scale(${currentZoom})`;
                }
            };

            document.getElementById('documentModal').addEventListener('hidden.bs.modal', function() {
                currentZoom = 1;
                document.getElementById('documentImage').style.transform = `scale(${currentZoom})`;
            });

            const toggleParcelButtons = document.querySelectorAll('.toggle-parcel');
            toggleParcelButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const hasParcel = this.getAttribute('data-has-parcel');
                    const message = document.getElementById('noParcelMessage-' + id);
                    if (hasParcel == 1) {
                        window.location.href = '?case_id=<?php echo htmlspecialchars($case_id); ?>&view=parcel';
                    } else {
                        message.style.display = 'block';
                    }
                });
            });
        });
    </script>
</body>
</html>
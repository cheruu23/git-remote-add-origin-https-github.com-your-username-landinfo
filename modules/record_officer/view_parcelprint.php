<?php
ob_start();
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Redirect if not logged in or not authorized
function isAuthorizedUser() {
    return isset($_SESSION['user']['role']) && 
           in_array($_SESSION['user']['role'], ['manager', 'record_officer', 'surveyor']);
}
if (!isAuthorizedUser()) {
    die("Access denied!");
}

// Database connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Dhaabbata database hin dandeenye: Connection error. Please try again later.");
}

$user_id = $_SESSION['user']['id'];
$case_id = (int)($_GET['id'] ?? $_GET['case_id'] ?? 0);
$debug_messages = [];
$debug_log = __DIR__ . '/debug.log';

// Fetch case and land_id
$land = null;
$land_id = null;
if ($case_id > 0) {
    try {
        $sql = "SELECT c.land_id, c.title, c.case_type 
                FROM cases c 
                WHERE c.id = :case_id AND c.status = 'Approved'";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':case_id', $case_id, PDO::PARAM_INT);
        $stmt->execute();
        $case_data = $stmt->fetch();
        if ($case_data) {
            $land_id = !empty($case_data['land_id']) && (int)$case_data['land_id'] > 0 ? (int)$case_data['land_id'] : null;
            $debug_messages[] = "Case ID $case_id found, land_id=" . ($land_id ?? 'null');
        } else {
            $debug_messages[] = "Case ID $case_id not found or not approved";
            die("Case not found or not approved for ID: $case_id");
        }
    } catch (PDOException $e) {
        $debug_messages[] = "Case query failed: " . $e->getMessage();
        error_log("Case query failed: " . $e->getMessage());
        die("Error fetching case details: Please try again later.");
    }
} else {
    $debug_messages[] = "Invalid or missing case ID";
    die("Invalid or missing case ID.");
}

// Fetch land details by land_id
if ($land_id) {
    $sql = "SELECT id, owner_name, first_name, middle_name, gender, owner_phone, land_type, village, zone, 
                   block_number, parcel_number, effective_date, group_category, land_grade, land_service, 
                   neighbor_east, neighbor_west, neighbor_south, neighbor_north, owner_photo, registration_date, 
                   agreement_number, duration, area, purpose, plot_number, coordinates, has_parcel, 
                   parcel_lease_date, parcel_agreement_number, parcel_lease_duration, parcel_village, 
                   parcel_block_number, parcel_land_grade, parcel_land_area, parcel_land_service, 
                   parcel_registration_number, building_height_allowed, prepared_by_name, prepared_by_role, 
                   approved_by_name, approved_by_role, authorized_by_name, authorized_by_role, status,
                   coord1_x, coord1_y, coord2_x, coord2_y, coord3_x, coord3_y, coord4_x, coord4_y
            FROM land_registration 
            WHERE id = :land_id";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':land_id', $land_id, PDO::PARAM_INT);
        $stmt->execute();
        $land = $stmt->fetch();
        if (!$land) {
            $debug_messages[] = "Land registration data not found for ID: $land_id";
            die("Land registration data not found for ID: " . htmlspecialchars($land_id));
        }
        $debug_messages[] = "Land ID $land_id found";
    } catch (PDOException $e) {
        $debug_messages[] = "Land query failed: " . $e->getMessage();
        error_log("Query failed: " . $e->getMessage());
        die("Error fetching land details: Please try again later.");
    }
} else {
    $debug_messages[] = "No valid land ID for case ID $case_id";
    die("No valid land ID associated with this case.");
}

// Fetch approved cases for this land_id
$cases = [];
try {
    $sql = "SELECT c.id, c.title, c.status, c.case_type, c.land_id, 
                   u.username AS reported_by, 
                   JSON_UNQUOTE(JSON_EXTRACT(c.description, '$.full_name')) AS full_name 
            FROM cases c 
            LEFT JOIN users u ON c.reported_by = u.id 
            LEFT JOIN land_registration lr ON c.land_id = lr.id 
            WHERE c.status = 'Approved' AND c.land_id = :land_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':land_id', $land_id, PDO::PARAM_INT);
    $stmt->execute();
    $cases = $stmt->fetchAll();
    $debug_messages[] = "Fetched " . count($cases) . " approved cases for land ID $land_id";
} catch (PDOException $e) {
    $debug_messages[] = "Cases query failed: " . $e->getMessage();
    error_log("Cases query failed: " . $e->getMessage());
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Get logo and stamp
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
$company_stamp = 'assets/images/stamp-placeholder.png';
try {
    $sql = "SELECT image_path FROM company_stamps ORDER BY uploaded_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stamp = $stmt->fetch();
    $stamp_path = $stamp ? $stamp['image_path'] : null;
    $full_path = $stamp_path ? __DIR__ . '/../../' . $stamp_path : null;
    if ($stamp_path && file_exists($full_path)) {
        $company_stamp = $stamp_path;
        $debug_messages[] = "Stamp found: $company_stamp";
    } else {
        $debug_messages[] = "Stamp not found: path=" . ($stamp_path ?? 'none');
    }
} catch (PDOException $e) {
    $debug_messages[] = "Stamp query failed: " . $e->getMessage();
}

// Normalize and log status
$status = isset($land['status']) ? strtolower(trim($land['status'])) : 'N/A';
$debug_messages[] = "Land ID $land_id status: $status";

// Parse coordinates
$coordinates = [];
if ($land['has_parcel']) {
    if ($land['coordinates']) {
        $pairs = explode(';', trim($land['coordinates'], ';'));
        foreach ($pairs as $pair) {
            $parts = explode(',', $pair);
            if (count($parts) == 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $coordinates[] = ['x' => floatval($parts[0]), 'y' => floatval($parts[1])];
            }
        }
    }
    if (empty($coordinates)) {
        if ($land['coord1_x'] !== null && $land['coord1_y'] !== null) {
            $coordinates[] = ['x' => floatval($land['coord1_x']), 'y' => floatval($land['coord1_y'])];
        }
        if ($land['coord2_x'] !== null && $land['coord2_y'] !== null) {
            $coordinates[] = ['x' => floatval($land['coord2_x']), 'y' => floatval($land['coord2_y'])];
        }
        if ($land['coord3_x'] !== null && $land['coord3_y'] !== null) {
            $coordinates[] = ['x' => floatval($land['coord3_x']), 'y' => floatval($land['coord3_y'])];
        }
        if ($land['coord4_x'] !== null && $land['coord4_y'] !== null) {
            $coordinates[] = ['x' => floatval($land['coord4_x']), 'y' => floatval($land['coord4_y'])];
        }
    }
}

// Log debug info
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - view_parcel.php: case_id=$case_id, land_id=" . ($land_id ?? 'N/A') . ", messages=" . json_encode($debug_messages) . "\n", FILE_APPEND);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land Lease Certificate #<?php echo htmlspecialchars($land_id); ?></title>
    <style>
        body {
            background-color: rgb(233, 245, 236);
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .print-button {
            margin-bottom: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-button:hover {
            background-color: #45a049;
        }
        .certificate {
            width: 800px;
            border: 2px solid #000;
            background-color: rgb(255, 255, 255);
            padding: 20px;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            page-break-after: always;
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
        .company-stamp {
            position: absolute;
            top: 60%;
            left: 70%;
            transform: translate(-50%, -50%) rotate(-45deg);
            opacity: 0.9;
            pointer-events: none;
        }
        .company-stamp img {
            width: 150px;
            height: 150px;
            object-fit: contain;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
            position: relative;
        }
        .header h1, .header h2, .header h3 {
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
            border-radius: 50px;
        }
        .owner-photo {
            width: 100px;
            height: 120px;
            border: 2px solid #3498db;
            border-radius: 5px;
            overflow: hidden;
            margin: 5px;
        }
        .owner-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-container {
            display: flex;
            justify-content: flex-end;
            position: absolute;
            top: 10px;
            right: 10px;
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
        table, th, td {
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
            flex-shrink: 0;
        }
        .top-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .lease-info {
            width: 48%;
        }
        .additional-info {
            width: 48%;
        }
        .lease-details {
            display: flex;
            justify-content: space-between;
        }
        .lease-details-left, .lease-details-right {
            width: 48%;
        }
        .neighbors-container {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        .neighbors-single-line {
            display: flex;
            justify-content: space-between;
        }
        .neighbors-single-line p {
            margin: 0;
            font-size: 14px;
        }
        .neighbors-single-line strong {
            color: #c0392b;
            margin-right: 5px;
        }
        .coordinates-table {
            margin-bottom: 20px;
        }
        .cases-section {
            margin-top: 20px;
        }
        .cases-section h2 {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px dashed #c0392b;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: #c0392b;
        }
        .debug-info {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #856404;
        }
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .print-button, .debug-info {
                display: none;
            }
            .certificate {
                border: none;
                box-shadow: none;
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 20px;
                page-break-after: avoid;
            }
            .section, .cases-section {
                page-break-inside: avoid;
            }
        
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin' && !empty($debug_messages)): ?>
        <div class="debug-info">
            <h4>Debug Information</h4>
            <?php foreach ($debug_messages as $msg): ?>
                <p><?php echo htmlspecialchars($msg); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <button class="print-button" onclick="window.print()">Print Certificate</button>
    
    <div class="certificate">
        <div class="watermark">COPY</div>
        <div class="company-stamp">
            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($company_stamp); ?>" alt="Company Stamp">
        </div>
        <img class="logo" src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo of Oromia Regional Government">
        <div class="photo-container">
            <div class="owner-photo">
                <img src="<?php 
                    echo ($land['owner_photo'] && file_exists(__DIR__ . '/../../' . $land['owner_photo'])) 
                        ? BASE_URL . '/' . htmlspecialchars($land['owner_photo']) . '?v=' . time() 
                        : BASE_URL . '/Uploads/owner-photo-placeholder.jpg'; 
                ?>" alt="Suuraa Abbaa Lafti">
            </div>
        </div>
        <div class="header">
            <h1>Bulchinsa Mootummaa Naannoo Oromiyaatti</h1>
            <h2>waraqaa mirkannessaa abbaa qabeenyummaa lafa magaala</h2>
            <p class="certificate-number">Certificate No: <?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_registration_number'] ?? $land['parcel_number'] ?? 'N/A') : 'N/A'); ?></p>
        </div>

        <div class="top-container">
            <div class="lease-info">
                <div class="section">
                    <h2>Lease Holder</h2>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars(trim(($land['first_name'] ?? '') . ' ' . ($land['middle_name'] ?? '') . ' ' . ($land['owner_name'] ?? ''))); ?></p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($land['gender'] ?? 'N/A'); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($land['owner_phone'] ?? 'N/A'); ?></p>
                </div>

                <div class="section">
                    <h2>Lease Details</h2>
                    <div class="lease-details">
                        <div class="lease-details-left">
                            <p><strong>Land Type:</strong> <?php echo htmlspecialchars($land['land_type'] ?? 'N/A'); ?></p>
                            <p><strong>Date:</strong> <?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_lease_date'] ?? $land['effective_date'] ?? 'N/A') : ($land['effective_date'] ?? 'N/A')); ?></p>
                            <p><strong>Agreement Number:</strong> <?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_agreement_number'] ?? $land['agreement_number'] ?? 'N/A') : ($land['agreement_number'] ?? 'N/A')); ?></p>
                        </div>
                        <div class="lease-details-right">
                            <p><strong>Duration:</strong> <?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_lease_duration'] ?? $land['duration'] ?? 'N/A') : ($land['duration'] ?? 'N/A')); ?> years</p>
                            <p><strong>Area:</strong> <?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_land_area'] ?? $land['area'] ?? 'N/A') : ($land['area'] ?? 'N/A')); ?> m²</p>
                            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($land['purpose'] ?? 'N/A'); ?></p>
                            <p><strong>Status:</strong> <?php echo htmlspecialchars($land['status'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="neighbors-container">
                    <h2>Neighbors</h2>
                    <div class="neighbors-single-line">
                        <p><strong>East:</strong> <?php echo htmlspecialchars($land['neighbor_east'] ?? 'N/A'); ?></p>
                        <p><strong>West:</strong> <?php echo htmlspecialchars($land['neighbor_west'] ?? 'N/A'); ?></p>
                        <p><strong>North:</strong> <?php echo htmlspecialchars($land['neighbor_north'] ?? 'N/A'); ?></p>
                        <p><strong>South:</strong> <?php echo htmlspecialchars($land['neighbor_south'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="additional-info">
                <div class="section">
                    <h2>Additional Information</h2>
                    <p><strong>Zone:</strong> <?php echo htmlspecialchars($land['zone'] ?? 'N/A'); ?></p>
                    <p><strong>Plot Number:</strong> <?php echo htmlspecialchars($land['plot_number'] ?? 'N/A'); ?></p>
                    <p><strong>Group Category:</strong> <?php echo htmlspecialchars($land['group_category'] ?? 'N/A'); ?></p>
                    <p><strong>Registration Date:</strong> <?php echo htmlspecialchars($land['registration_date'] ?? 'N/A'); ?></p>
                </div>
                
                <div class="coordinates-table">
                    <div class="section">
                        <h2>Coordinates (XY Koordineetii)</h2>
                        <table>
                            <tr>
                                <th>Point</th>
                                <th>X</th>
                                <th>Y</th>
                            </tr>
                            <?php if ($land['has_parcel'] && !empty($coordinates)): ?>
                                <?php foreach ($coordinates as $index => $coord): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo number_format($coord['x'], 2); ?></td>
                                        <td><?php echo number_format($coord['y'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3"><?php echo $land['has_parcel'] ? 'No coordinates available' : 'No parcel assigned'; ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Teessummaa Lafa (Plot/Site Plan)</h2>
            <div class="site-plan">
                <svg width="150" height="100" viewBox="0 0 150 100" xmlns="http://www.w3.org/2000/svg">
                    <?php if ($land['has_parcel'] && !empty($coordinates)): ?>
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
                        <text x="75" y="50" font-size="10" fill="#2c3e50" text-anchor="middle"><?php echo $land['has_parcel'] ? 'No site plan available' : 'No parcel assigned'; ?></text>
                    <?php endif; ?>
                </svg>
            </div>
        </div>

        <div class="section">
            <h2>Land Details</h2>
            <table>
                <tr>
                    <th>Ganda</th>
                    <th>Lakkoofsa Addaa Biloooki</th>
                    <th>Sadarkaa Lafaa</th>
                    <th>Balina Lafaa</th>
                    <th>Tajaajilaa Lafaa</th>
                    <th>Lakkoofsa Galmee</th>
                    <th>Dheriina Gamoo Heyyamamu</th>
                </tr>
                <tr>
                    <td><?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_village'] ?? $land['village'] ?? 'N/A') : ($land['village'] ?? 'N/A')); ?></td>
                    <td><?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_block_number'] ?? $land['block_number'] ?? 'N/A') : ($land['block_number'] ?? 'N/A')); ?></td>
                    <td><?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_land_grade'] ?? $land['land_grade'] ?? 'N/A') : ($land['land_grade'] ?? 'N/A')); ?></td>
                    <td><?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_land_area'] ?? $land['area'] ?? 'N/A') : ($land['area'] ?? 'N/A')); ?> m²</td>
                    <td><?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_land_service'] ?? $land['land_service'] ?? 'N/A') : ($land['land_service'] ?? 'N/A')); ?></td>
                    <td><?php echo htmlspecialchars($land['has_parcel'] ? ($land['parcel_registration_number'] ?? $land['parcel_number'] ?? 'N/A') : ($land['parcel_number'] ?? 'N/A')); ?></td>
                    <td><?php echo htmlspecialchars($land['has_parcel'] ? ($land['building_height_allowed'] ?? 'N/A') : 'N/A'); ?></td>
                </tr>
            </table>
        </div>

        <div class="signatures">
            <div>
                <p><strong>Prepared By:</strong></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($land['prepared_by_name'] ?? 'N/A'); ?></p>
                <p><strong>Gahee Hojii:</strong> <?php echo htmlspecialchars($land['prepared_by_role'] ?? 'Surveyor'); ?></p>
                <p class="signature-line">[Signature]</p>
            </div>
            <div>
                <p><strong>Approved By:</strong></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($land['approved_by_name'] ?? 'N/A'); ?></p>
                <p><strong>Gahee Hojii:</strong> <?php echo htmlspecialchars($land['approved_by_role'] ?? 'Head Surveyor'); ?></p>
                <p class="signature-line">[Signature]</p>
            </div>
            <div>
                <p><strong>Authorized (Kan Ragasisee):</strong></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($land['authorized_by_name'] ?? 'N/A'); ?></p>
                <p><strong>Gahee Hojii:</strong> <?php echo htmlspecialchars($land['authorized_by_role'] ?? 'Land Officer'); ?></p>
                <p class="signature-line">[Signature]</p>
            </div>
        </div>
    </div>

    <script>
        function beforePrint() {
            document.body.style.height = 'auto';
        }
        function afterPrint() {
            document.body.style.height = '';
        }
        if (window.matchMedia) {
            window.matchMedia('print').addListener(function(mql) {
                if (mql.matches) {
                    beforePrint();
                } else {
                    afterPrint();
                }
            });
        }
        window.onbeforeprint = beforePrint;
        window.onafterprint = afterPrint;
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
redirectIfNotLoggedIn();
include '../../templates/sidebar.php';
if (!function_exists('isSurveyor') || !isSurveyor()) {
    die("Access denied!");
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch case details by ID
if (!isset($_GET['case_id']) || !is_numeric($_GET['case_id'])) {
    die("No case ID provided or invalid ID.");
}
$case_id = (int)$_GET['case_id'];

$sql = "SELECT c.id, c.title, c.status, c.land_id, c.investigation_status, c.assigned_to
        FROM cases c 
        WHERE c.id = ? AND c.assigned_to = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $case_id, $user_id);
$case = null;
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    $case = $result->fetch_assoc();
} else {
    error_log("Fetch case query failed: " . $conn->error);
}
$stmt->close();

if (!$case) {
    die("Case not found or you are not assigned to this case.");
}

// Validate case for certificate generation
if ($case['investigation_status'] !== 'Completed' || !$case['status'] || !$case['land_id']) {
    die("No valid case found for case_id=$case_id. Please ensure the case exists, is assigned to you, and investigation is completed.<br>" .
        "Debug: Case query result: " . print_r($case, true) . "<br>Debug: User ID: $user_id");
}

// Fetch land registration details
$sql = "SELECT id, owner_name, first_name, middle_name, gender, land_type, village, zone, block_number, 
               parcel_number, effective_date, group_category, land_grade, land_service, neighbor_east, 
               neighbor_west, neighbor_south, neighbor_north, id_front, id_back, xalayaa_miritii, 
               nagaee_gibiraa, waligaltee_lease, tax_receipt, miriti_paper, caalbaasii_agreement, 
               bita_fi_gurgurtaa_agreement, bita_fi_gurgurtaa_receipt, owner_photo, registration_date, 
               created_at, agreement_number, duration, area, purpose, plot_number, coordinates, 
               surveyor_name, head_surveyor_name, land_officer_name, has_parcel, parcel_lease_date, 
               parcel_agreement_number, parcel_lease_duration, parcel_village, parcel_block_number, 
               parcel_land_grade, parcel_land_area, parcel_land_service, parcel_registration_number, 
               building_height_allowed, prepared_by_name, prepared_by_role, approved_by_name, 
               approved_by_role, authorized_by_name, authorized_by_role
        FROM land_registration 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $case['land_id']);
$land = null;
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    $land = $result->fetch_assoc();
} else {
    error_log("Fetch land query failed: " . $conn->error);
}
$stmt->close();

if (!$land) {
    die("Land registration data not found for land_id=" . htmlspecialchars($case['land_id']));
}

// Generate or use parcel_number
$parcel_number = $land['parcel_number'];
if (!$parcel_number) {
    // Generate unique parcel_number (MCA####/YY)
    $year = $land['effective_date'] ? substr($land['effective_date'], 2, 2) : date('y'); // e.g., '25' for 2025
    $prefix = 'MCA';
    $sql = "SELECT MAX(CAST(SUBSTRING(parcel_number, 4, 4) AS UNSIGNED)) AS max_num 
            FROM land_registration 
            WHERE parcel_number LIKE '$prefix____/$year'";
    $result = $conn->query($sql);
    $max_num = $result && $row = $result->fetch_assoc() ? (int)$row['max_num'] : 0;
    $new_num = $max_num + 1;
    $parcel_number = sprintf("%s%04d/%s", $prefix, $new_num, $year); // e.g., MCA0005/25

    // Verify uniqueness
    $sql = "SELECT COUNT(*) AS count FROM land_registration WHERE parcel_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $parcel_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();

    if ($count == 0) {
        // Update land_registration with new parcel_number
        $sql = "UPDATE land_registration SET parcel_number = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $parcel_number, $case['land_id']);
        if (!$stmt->execute()) {
            error_log("Failed to update parcel_number: " . $conn->error);
        }
        $stmt->close();
        $land['parcel_number'] = $parcel_number;
    } else {
        die("Generated parcel_number $parcel_number is not unique. Please try again.");
    }
}

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Get navbar logo and owner photo
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
$owner_photo = $_SESSION['settings']['owner_photo'] ?? ($land['owner_photo'] ?? '/Uploads/owner-photo-placeholder.jpg');

// Log if owner photo file is missing
$owner_photo_path = 'C:' . DIRECTORY_SEPARATOR . 'xampp' . DIRECTORY_SEPARATOR . 'htdocs' . DIRECTORY_SEPARATOR . 'landinfo' . DIRECTORY_SEPARATOR . ltrim($owner_photo, '/');
if (!file_exists($owner_photo_path)) {
    error_log("Owner photo not found: $owner_photo_path");
}

// Parse coordinates
$coordinates = [];
if ($land['coordinates']) {
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land Lease Certificate #<?php echo htmlspecialchars($case['id']); ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: rgba(95, 72, 9, 0.25);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .certificate {
            width: 800px;
            border: 2px solid #000;
            background-color: #fffdf5;
            padding: 20px;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(223, 13, 13, 0.3);
            pointer-events: none;
            text-transform: uppercase;
            font-weight: bold;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            margin-bottom: 20px;
            position: relative;
        }
        .header h1, .header h2, .header h3 {
            margin: 5px 0;
            font-size: 25px;
            font-weight: bold;
            color: rgb(9, 133, 25);
        }
        .header .certificate-number {
            font-size: 14px;
            color: #000;
        }
        .owner-photo {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 100px;
            height: 120px;
            border: 2px solid #3498db;
            border-radius: 5px;
        }
        .cert-logo {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 100px;
            height: 120px;
            border: 2px solid #3498db;
            border-radius: 5px;
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
            border-bottom: 1px dashed rgb(43, 192, 110);
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: rgb(26, 192, 11);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table, th, td {
            border: 1px solid #000;
            padding: 8px;
            font-size: 14px;
            text-align: left;
        }
        th {
            background-color: #ecf0f1;
            color: #2c3e50;
            font-weight: bold;
        }
        td {
            color: #2c3e50;
        }
        .neighbors, .additional-info {
            display: flex;
            justify-content: space-between;
        }
        .neighbors div, .additional-info div {
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
        @media print {
            body {
                background: none;
                padding: 0;
            }
            .certificate {
                border: none;
                box-shadow: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="watermark">MATTUCITY</div>
        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo" class="cert-logo">
        <img class="owner-photo" src="<?php echo BASE_URL . '/' . htmlspecialchars($owner_photo); ?>" alt="Suuraa Abbaa Lafti">
        <div class="header">
            <h1>Bulchinsa Mootummaa Naannoo Oromiyaa</h1>
            <h2>Wajjiraa lafa bulchinsa Magaala Mattuu</h2>
            <h3>Oromia Regional Government Mattu City Land Administration Office</h3>
            <p class="certificate-number">Certificate No: <?php echo htmlspecialchars($land['has_parcel'] ? $parcel_number : 'N/A'); ?></p>
        </div>

        <div class="section">
            <h2>Lease Holder</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars(trim(($land['first_name'] ?? '') . ' ' . ($land['middle_name'] ?? '') . ' ' . ($land['owner_name'] ?? ''))); ?></p>
            <p><strong>Gender:</strong> <?php echo htmlspecialchars($land['gender'] ?? 'N/A'); ?></p>
        </div>

        <div class="section">
            <h2>Lease Details</h2>
            <p><strong>Date:</strong> <?php echo htmlspecialchars($land['effective_date'] ?? 'N/A'); ?></p>
            <p><strong>Agreement Number:</strong> <?php echo htmlspecialchars($land['agreement_number'] ?? 'N/A'); ?></p>
            <p><strong>Duration:</strong> <?php echo htmlspecialchars($land['duration'] ?? 'N/A'); ?></p>
            <p><strong>Area:</strong> <?php echo htmlspecialchars($land['area'] ?? 'N/A'); ?></p>
            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($land['purpose'] ?? 'N/A'); ?></p>
        </div>

        <div class="section">
            <h2>Registration Details</h2>
            <table>
                <tr>
                    <th>Field</th>
                    <th>Details</th>
                </tr>
                <tr>
                    <td>Owner Name</td>
                    <td><?php echo htmlspecialchars($land['owner_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>First Name</td>
                    <td><?php echo htmlspecialchars($land['first_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Middle Name</td>
                    <td><?php echo htmlspecialchars($land['middle_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Gender</td>
                    <td><?php echo htmlspecialchars($land['gender'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Land Type</td>
                    <td><?php echo htmlspecialchars($land['land_type'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Village</td>
                    <td><?php echo htmlspecialchars($land['village'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Zone</td>
                    <td><?php echo htmlspecialchars($land['zone'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Block Number</td>
                    <td><?php echo htmlspecialchars($land['block_number'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Parcel Number</td>
                    <td><?php echo htmlspecialchars($land['parcel_number'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Effective Date</td>
                    <td><?php echo htmlspecialchars($land['effective_date'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Group Category</td>
                    <td><?php echo htmlspecialchars($land['group_category'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Land Grade</td>
                    <td><?php echo htmlspecialchars($land['land_grade'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Land Service</td>
                    <td><?php echo htmlspecialchars($land['land_service'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Neighbor East</td>
                    <td><?php echo htmlspecialchars($land['neighbor_east'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Neighbor West</td>
                    <td><?php echo htmlspecialchars($land['neighbor_west'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Neighbor South</td>
                    <td><?php echo htmlspecialchars($land['neighbor_south'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Neighbor North</td>
                    <td><?php echo htmlspecialchars($land['neighbor_north'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td>Has Parcel</td>
                    <td><?php echo $land['has_parcel'] ? 'Yes' : 'No'; ?></td>
                </tr>
                <?php if ($land['has_parcel']): ?>
                    <tr>
                        <td>Parcel Village</td>
                        <td><?php echo htmlspecialchars($land['parcel_village'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Parcel Block Number</td>
                        <td><?php echo htmlspecialchars($land['parcel_block_number'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Parcel Land Grade</td>
                        <td><?php echo htmlspecialchars($land['parcel_land_grade'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Parcel Land Area</td>
                        <td><?php echo htmlspecialchars($land['parcel_land_area'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Parcel Land Service</td>
                        <td><?php echo htmlspecialchars($land['parcel_land_service'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Parcel Registration Number</td>
                        <td><?php echo htmlspecialchars($land['parcel_registration_number'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Building Height Allowed</td>
                        <td><?php echo htmlspecialchars($land['building_height_allowed'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Parcel Lease Date</td>
                        <td><?php echo htmlspecialchars($land['parcel_lease_date'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Parcel Agreement Number</td>
                        <td><?php echo htmlspecialchars($land['parcel_agreement_number'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Parcel Lease Duration</td>
                        <td><?php echo htmlspecialchars($land['parcel_lease_duration'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="section">
            <h2>Coordinates (XY Koordineetii)</h2>
            <table>
                <tr>
                    <th>Point</th>
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
            </table>
        </div>

        <div class="section">
            <h2>Teessummaa Lafa (Plot/Site Plan)</h2>
            <div class="site-plan">
                <svg width="150" height="100" viewBox="0 0 150 100" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="<?php
                        $points = '';
                        foreach ($coordinates as $coord) {
                            $x = ($coord['x'] - 18) * 100;
                            $y = ($coord['y'] - 91) * 100;
                            $points .= "$x,$y ";
                        }
                        echo trim($points);
                    ?>" fill="none" stroke="#000" stroke-width="1" />
                    <rect x="40" y="20" width="50" height="30" fill="#d3d3d3" />
                    <?php foreach ($coordinates as $index => $coord): ?>
                        <?php
                            $x = ($coord['x'] - 18) * 100;
                            $y = ($coord['y'] - 91) * 100;
                        ?>
                        <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="2" fill="#2c3e50" />
                        <text x="<?php echo $x - 5; ?>" y="<?php echo $y - 5; ?>" font-size="10" fill="#2c3e50"><?php echo $index + 1; ?></text>
                    <?php endforeach; ?>
                </svg>
            </div>
        </div>

        <div class="section neighbors">
            <div>
                <h2>Neighbors</h2>
                <p><strong>East:</strong> <?php echo htmlspecialchars($land['neighbor_east'] ?? 'N/A'); ?></p>
                <p><strong>West:</strong> <?php echo htmlspecialchars($land['neighbor_west'] ?? 'N/A'); ?></p>
                <p><strong>North:</strong> <?php echo htmlspecialchars($land['neighbor_north'] ?? 'N/A'); ?></p>
                <p><strong>South:</strong> <?php echo htmlspecialchars($land['neighbor_south'] ?? 'N/A'); ?></p>
            </div>
            <div>
                <h2>Additional Information</h2>
                <p><strong>Plot Number:</strong> <?php echo htmlspecialchars($land['plot_number'] ?? 'N/A'); ?></p>
                <p><strong>Land Grade:</strong> <?php echo htmlspecialchars($land['land_grade'] ?? 'N/A'); ?></p>
                <p><strong>Zone:</strong> <?php echo htmlspecialchars($land['zone'] ?? 'N/A'); ?></p>
                <p><strong>Land Type:</strong> <?php echo htmlspecialchars($land['land_type'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <div class="signatures">
            <div>
                <p><strong>Prepared By:</strong></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($land['prepared_by_name'] ?? 'N/A'); ?></p>
                <p><strong>Gahee Hojii:</strong> <?php echo htmlspecialchars($land['prepared_by_role'] ?? 'N/A'); ?></p>
                <p class="signature-line">[Signature]</p>
            </div>
            <div>
                <p><strong>Approved By:</strong></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($land['approved_by_name'] ?? 'N/A'); ?></p>
                <p><strong>Gahee Hojii:</strong> <?php echo htmlspecialchars($land['approved_by_role'] ?? 'N/A'); ?></p>
                <p class="signature-line">[Signature]</p>
            </div>
            <div>
                <p><strong>Authorized (Kan Ragasisee):</strong></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($land['authorized_by_name'] ?? 'N/A'); ?></p>
                <p><strong>Gahee Hojii:</strong> <?php echo htmlspecialchars($land['authorized_by_role'] ?? 'N/A'); ?></p>
                <p class="signature-line">[Signature]</p>
            </div>
        </div>
    </div>
</body>
</html>
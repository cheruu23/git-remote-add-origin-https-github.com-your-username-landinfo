<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
include '../../templates/sidebar.php';

// Redirect if not logged in or not a record officer
redirectIfNotLoggedIn();
if (!isRecordOfficer() && !isManager()) {
    die("Access denied! Only record officers and managers can view parcel details.");
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
$land_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch land details by id
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
        WHERE id = :land_id";
try {
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':land_id', $land_id, PDO::PARAM_INT);
    $stmt->execute();
    $land = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Query failed: " . $e->getMessage());
    die("Error fetching land details: Please try again later.");
}

if (!$land) {
    die("Land registration data not found for ID: " . htmlspecialchars($land_id));
}

// Define BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/landinfo');
}

// Get logo
$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land Lease Certificate #<?php echo htmlspecialchars($land_id); ?></title>
    <style>
        body {
            background-color: #f5f5e9;
            margin: 0;

            display: flex;

            align-items: center;

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
        <div class="watermark">COPY</div>
        <img class="logo" src="<?php echo BASE_URL . '/' . htmlspecialchars($navbar_logo); ?>" alt="Logo of Oromia Regional Government">
        <div class="owner-photo">
            <img src="<?php echo htmlspecialchars($land['owner_photo'] ?? '/Uploads/owner-photo-placeholder.jpg'); ?>" alt="Suuraa Abbaa Lafti">
        </div>
        <div class="header">
            <h1>Bulchinsa Mootummaa Naannoo Oromiyaa</h1>
            <h2>Oromia Regional Government</h2>
            <h3>Oromia Land Administration and Use Bureau, Fayyadamala Lafa</h3>
            <p class="certificate-number">Certificate No: <?php echo htmlspecialchars($land['parcel_registration_number'] ?? $land['parcel_number'] ?? 'N/A'); ?></p>
        </div>

        <div class="section">
            <h2>Lease Holder</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars(trim(($land['first_name'] ?? '') . ' ' . ($land['middle_name'] ?? '') . ' ' . ($land['owner_name'] ?? ''))); ?></p>
        </div>

        <div class="section">
            <h2>Lease Details</h2>
            <p><strong>Date:</strong> <?php echo htmlspecialchars($land['parcel_lease_date'] ?? $land['effective_date'] ?? 'N/A'); ?></p>
            <p><strong>Agreement Number:</strong> <?php echo htmlspecialchars($land['parcel_agreement_number'] ?? $land['agreement_number'] ?? 'N/A'); ?></p>
            <p><strong>Duration:</strong> <?php echo htmlspecialchars($land['parcel_lease_duration'] ?? $land['duration'] ?? 'N/A'); ?> years</p>
            <p><strong>Area:</strong> <?php echo htmlspecialchars($land['parcel_land_area'] ?? $land['area'] ?? 'N/A'); ?> m²</p>
            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($land['purpose'] ?? 'N/A'); ?></p>
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
                <?php if (empty($coordinates)): ?>
                    <tr>
                        <td colspan="3">No coordinates available</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="section">
            <h2>Teessummaa Lafa (Plot/Site Plan)</h2>
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
                        <text x="75" y="50" font-size="10" fill="#2c3e50" text-anchor="middle">No site plan available</text>
                    <?php endif; ?>
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
                <h2>Land Details</h2>
                <table>
                    <tr>
                        <th>Ganda</th>
                        <th>Lakkoofsa Addaa Biloooki</th>
                        <th>Lakkoofsa Addaa Parceli Sadarkaa Lafaa</th>
                        <th>Balina Lafaa</th>
                        <th>Tajaajilaa Lafaa</th>
                        <th>Lakkoofsa Galmee</th>
                        <th>Dheriina Gamoo Heyyamamu</th>
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
                <h2>Additional Information</h2>
                <p><strong>Plot Number:</strong> <?php echo htmlspecialchars($land['plot_number'] ?? 'N/A'); ?></p>
                <p><strong>Land Grade:</strong> <?php echo htmlspecialchars($land['parcel_land_grade'] ?? $land['land_grade'] ?? 'N/A'); ?></p>
            </div>
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
</body>

</html>
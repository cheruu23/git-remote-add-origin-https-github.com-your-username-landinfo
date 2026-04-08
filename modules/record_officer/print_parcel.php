<?php
require '../../includes/init.php';

require '../../vendor/tcpdf/tcpdf.php'; // Adjust path to TCPDF

redirectIfNotLoggedIn();

// Validate and sanitize lang parameter
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'en';

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
    die($translations[$lang]['db_connection_failed'] ?? 'Database connection failed.');
}

$user = $_SESSION['user'];
$role = $user['role'];
$user_id = $_SESSION['user_id'] ?? $user['id'];

// Allowed case types for approved cases
$allowed_case_types = [
    'jijjirra_maqaa',
    'qabiye_walitti_makuu',
    'mirkaneessa_sirrumma_waraqa_ragaa',
    'mirkaneessa_abbaa_qabiyyumma'
];

// Fetch approved cases with allowed case types
$cases = [];
try {
    $stmt = $conn->prepare("
        SELECT c.id, c.title, c.case_type, c.land_id 
        FROM cases c
        WHERE c.status = 'Approved' 
        AND c.case_type IN (?,?,?,?)
    ");
    $stmt->execute($allowed_case_types);
    $cases = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch approved cases: " . $e->getMessage());
}

// Handle PDF generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['case_id'])) {
    $case_id = (int)$_POST['case_id'];

    // Fetch case and land details
    try {
        $stmt = $conn->prepare("
            SELECT c.id AS case_id, c.title, c.case_type, 
                   lr.id, lr.owner_name, lr.first_name, lr.middle_name, lr.gender, lr.owner_phone, 
                   lr.land_type, lr.village, lr.zone, lr.block_number, lr.parcel_number, 
                   lr.effective_date, lr.group_category, lr.land_grade, lr.land_service, 
                   lr.neighbor_east, lr.neighbor_west, lr.neighbor_south, lr.neighbor_north, 
                   lr.owner_photo, lr.registration_date, lr.agreement_number, lr.duration, 
                   lr.area, lr.purpose, lr.plot_number, lr.coordinates, lr.surveyor_name, 
                   lr.head_surveyor_name, lr.land_officer_name, lr.has_parcel, lr.parcel_lease_date, 
                   lr.parcel_agreement_number, lr.parcel_lease_duration, lr.parcel_village, 
                   lr.parcel_block_number, lr.parcel_land_grade, lr.parcel_land_area, 
                   lr.parcel_land_service, lr.parcel_registration_number, lr.building_height_allowed, 
                   lr.prepared_by_name, lr.prepared_by_role, lr.approved_by_name, lr.approved_by_role, 
                   lr.authorized_by_name, lr.authorized_by_role, lr.status,
                   lr.coord1_x, lr.coord1_y, lr.coord2_x, lr.coord2_y, lr.coord3_x, lr.coord3_y, 
                   lr.coord4_x, lr.coord4_y
            FROM cases c
            JOIN land_registration lr ON c.land_id = lr.id
            WHERE c.id = ? AND c.status = 'Approved' AND c.case_type IN (?,?,?,?)
        ");
        $stmt->execute([$case_id, ...$allowed_case_types]);
        $land = $stmt->fetch();

        if ($land) {
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

            // Get logo
            $navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
            $logo_path = dirname(__DIR__) . '/' . $navbar_logo;
            $logo_data = file_exists($logo_path) ? base64_encode(file_get_contents($logo_path)) : '';

            // Owner photo
            $owner_photo_path = $land['owner_photo'] && file_exists(dirname(__DIR__) . '/' . $land['owner_photo'])
                ? dirname(__DIR__) . '/' . $land['owner_photo']
                : dirname(__DIR__) . '/Uploads/owner-photo-placeholder.jpg';
            $owner_photo_data = base64_encode(file_get_contents($owner_photo_path));

            // Initialize TCPDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor($user['full_name']);
            $pdf->SetTitle('Land Lease Certificate #' . $land['id']);
            $pdf->SetSubject('Land Parcel Certificate');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(TRUE, 15);
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            $pdf->setFont('helvetica', '', 10);
            $pdf->AddPage();

            // HTML content based on land_lease_certificate.php
            $html = '
            <style>
                .certificate {
                    width: 100%;
                    border: 2px solid #000;
                    background-color: #fffdf5;
                    padding: 20px;
                    position: relative;
                }
                .watermark {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(-45deg);
                    font-size: 100px;
                    color: rgba(255, 0, 0, 0.3);
                    text-transform: uppercase;
                    font-weight: bold;
                }
                .header {
                    text-align: center;
                    border-bottom: 1px solid #000;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
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
                }
                .owner-photo {
                    width: 100px;
                    height: 120px;
                    border: 2px solid #3498db;
                    border-radius: 5px;
                    margin: 5px;
                }
                .photo-container {
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
                    margin-top: 10px;
                }
            </style>
            <div class="certificate">
                <div class="watermark">COPY</div>
                <img class="logo" src="data:image/png;base64,' . $logo_data . '" alt="Logo">
                <div class="photo-container">
                    <div class="owner-photo">
                        <img src="data:image/jpeg;base64,' . $owner_photo_data . '" alt="Owner Photo">
                    </div>
                </div>
                <div class="header">
                    <h1>Bulchinsa Mootummaa Naannoo Oromiyaa</h1>
                    <h2>Oromia Regional Government</h2>
                    <h3>Oromia Land Administration and Use Bureau, Fayyadamala Lafa</h3>
                    <p class="certificate-number">Certificate No: ' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_registration_number'] ?? $land['parcel_number'] ?? 'N/A') : 'N/A') . '</p>
                </div>
                <div class="section">
                    <h2>Lease Holder</h2>
                    <p><strong>Name:</strong> ' . htmlspecialchars(trim(($land['first_name'] ?? '') . ' ' . ($land['middle_name'] ?? '') . ' ' . ($land['owner_name'] ?? ''))) . '</p>
                    <p><strong>Gender:</strong> ' . htmlspecialchars($land['gender'] ?? 'N/A') . '</p>
                    <p><strong>Phone:</strong> ' . htmlspecialchars($land['owner_phone'] ?? 'N/A') . '</p>
                </div>
                <div class="section">
                    <h2>Lease Details</h2>
                    <p><strong>Case Title:</strong> ' . htmlspecialchars($land['title']) . '</p>
                    <p><strong>Case Type:</strong> ' . htmlspecialchars($translations[$lang]['case_' . $land['case_type']] ?? $land['case_type']) . '</p>
                    <p><strong>Land Type:</strong> ' . htmlspecialchars($land['land_type'] ?? 'N/A') . '</p>
                    <p><strong>Date:</strong> ' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_lease_date'] ?? $land['effective_date'] ?? 'N/A') : ($land['effective_date'] ?? 'N/A')) . '</p>
                    <p><strong>Agreement Number:</strong> ' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_agreement_number'] ?? $land['agreement_number'] ?? 'N/A') : ($land['agreement_number'] ?? 'N/A')) . '</p>
                    <p><strong>Duration:</strong> ' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_lease_duration'] ?? $land['duration'] ?? 'N/A') : ($land['duration'] ?? 'N/A')) . ' years</p>
                    <p><strong>Area:</strong> ' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_land_area'] ?? $land['area'] ?? 'N/A') : ($land['area'] ?? 'N/A')) . ' m²</p>
                    <p><strong>Purpose:</strong> ' . htmlspecialchars($land['purpose'] ?? 'N/A') . '</p>
                    <p><strong>Status:</strong> ' . htmlspecialchars($land['status'] ?? 'N/A') . '</p>
                </div>
                <div class="section">
                    <h2>Coordinates (XY Koordineetii)</h2>
                    <table>
                        <tr><th>Point</th><th>X</th><th>Y</th></tr>';
            if ($land['has_parcel'] && !empty($coordinates)) {
                foreach ($coordinates as $index => $coord) {
                    $html .= '<tr><td>' . ($index + 1) . '</td><td>' . $coord['x'] . '</td><td>' . $coord['y'] . '</td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="3">' . ($land['has_parcel'] ? 'No coordinates available' : 'No parcel assigned') . '</td></tr>';
            }
            $html .= '</table>
                </div>
                <div class="section">
                    <h2>Teessummaa Lafa (Plot/Site Plan)</h2>
                    <div class="site-plan">';
            if ($land['has_parcel'] && !empty($coordinates)) {
                $min_x = min(array_column($coordinates, 'x'));
                $max_x = max(array_column($coordinates, 'x'));
                $min_y = min(array_column($coordinates, 'y'));
                $max_y = max(array_column($coordinates, 'y'));
                $scale_x = $max_x != $min_x ? 150 / ($max_x - $min_x) : 1;
                $scale_y = $max_y != $min_y ? 100 / ($max_y - $min_y) : 1;
                $points = '';
                foreach ($coordinates as $coord) {
                    $x = ($coord['x'] - $min_x) * $scale_x;
                    $y = 100 - ($coord['y'] - $min_y) * $scale_y;
                    $points .= "$x,$y ";
                }
                $html .= '
                    <table><tr><td>
                    <svg width="150" height="100" viewBox="0 0 150 100">
                        <polygon points="' . trim($points) . '" fill="none" stroke="#000" stroke-width="1" />
                        <rect x="40" y="20" width="50" height="30" fill="#d3d3d3" />';
                foreach ($coordinates as $index => $coord) {
                    $x = ($coord['x'] - $min_x) * $scale_x;
                    $y = 100 - ($coord['y'] - $min_y) * $scale_y;
                    $html .= '
                        <circle cx="' . $x . '" cy="' . $y . '" r="2" fill="#2c3e50" />
                        <text x="' . ($x - 5) . '" y="' . ($y - 5) . '" font-size="10" fill="#2c3e50">' . ($index + 1) . '</text>';
                }
                $html .= '</svg></td></tr></table>';
            } else {
                $html .= '<p>' . ($land['has_parcel'] ? 'No site plan available' : 'No parcel assigned') . '</p>';
            }
            $html .= '
                    </div>
                </div>
                <div class="section neighbors">
                    <div>
                        <h2>Neighbors</h2>
                        <p><strong>East:</strong> ' . htmlspecialchars($land['neighbor_east'] ?? 'N/A') . '</p>
                        <p><strong>West:</strong> ' . htmlspecialchars($land['neighbor_west'] ?? 'N/A') . '</p>
                        <p><strong>North:</strong> ' . htmlspecialchars($land['neighbor_north'] ?? 'N/A') . '</p>
                        <p><strong>South:</strong> ' . htmlspecialchars($land['neighbor_south'] ?? 'N/A') . '</p>
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
                                <td>' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_village'] ?? $land['village'] ?? 'N/A') : ($land['village'] ?? 'N/A')) . '</td>
                                <td>' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_block_number'] ?? $land['block_number'] ?? 'N/A') : ($land['block_number'] ?? 'N/A')) . '</td>
                                <td>' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_land_grade'] ?? $land['land_grade'] ?? 'N/A') : ($land['land_grade'] ?? 'N/A')) . '</td>
                                <td>' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_land_area'] ?? $land['area'] ?? 'N/A') : ($land['area'] ?? 'N/A')) . ' m²</td>
                                <td>' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_land_service'] ?? $land['land_service'] ?? 'N/A') : ($land['land_service'] ?? 'N/A')) . '</td>
                                <td>' . htmlspecialchars($land['has_parcel'] ? ($land['parcel_registration_number'] ?? $land['parcel_number'] ?? 'N/A') : ($land['parcel_number'] ?? 'N/A')) . '</td>
                                <td>' . htmlspecialchars($land['has_parcel'] ? ($land['building_height_allowed'] ?? 'N/A') : 'N/A') . '</td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <h2>Additional Information</h2>
                        <p><strong>Zone:</strong> ' . htmlspecialchars($land['zone'] ?? 'N/A') . '</p>
                        <p><strong>Plot Number:</strong> ' . htmlspecialchars($land['plot_number'] ?? 'N/A') . '</p>
                        <p><strong>Group Category:</strong> ' . htmlspecialchars($land['group_category'] ?? 'N/A') . '</p>
                        <p><strong>Registration Date:</strong> ' . htmlspecialchars($land['registration_date'] ?? 'N/A') . '</p>
                    </div>
                </div>
                <div class="signatures">
                    <div>
                        <p><strong>Prepared By:</strong></p>
                        <p><strong>Name:</strong> ' . htmlspecialchars($land['prepared_by_name'] ?? 'N/A') . '</p>
                        <p><strong>Gahee Hojii:</strong> ' . htmlspecialchars($land['prepared_by_role'] ?? 'Surveyor') . '</p>
                        <p class="signature-line">[Signature]</p>
                    </div>
                    <div>
                        <p><strong>Approved By:</strong></p>
                        <p><strong>Name:</strong> ' . htmlspecialchars($land['approved_by_name'] ?? 'N/A') . '</p>
                        <p><strong>Gahee Hojii:</strong> ' . htmlspecialchars($land['approved_by_role'] ?? 'Head Surveyor') . '</p>
                        <p class="signature-line">[Signature]</p>
                    </div>
                    <div>
                        <p><strong>Authorized (Kan Ragasisee):</strong></p>
                        <p><strong>Name:</strong> ' . htmlspecialchars($land['authorized_by_name'] ?? 'N/A') . '</p>
                        <p><strong>Gahee Hojii:</strong> ' . htmlspecialchars($land['authorized_by_role'] ?? 'Land Officer') . '</p>
                        <p class="signature-line">[Signature]</p>
                    </div>
                </div>
            </div>';

            $pdf->writeHTML($html, true, false, true, false, '');

            // Output PDF to a file
            $pdf_file = dirname(__DIR__) . '/temp/parcel_' . $case_id . '.pdf';
            $pdf->Output($pdf_file, 'F');

            // Redirect to preview page
            header('Location: ' . BASE_URL . '/modules/' . $role . '/preview_parcel.php?file=' . urlencode(basename($pdf_file)) . '&lang=' . $lang);
            exit;
        } else {
            $error = $translations[$lang]['invalid_case'] ?? 'Invalid or unapproved case.';
        }
    } catch (PDOException $e) {
        error_log('Failed to generate PDF: ' . $e->getMessage());
        $error = $translations[$lang]['error_generating_pdf'] ?? 'Error generating PDF.';
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['print_parcel'] ?? 'Print Parcel Certificate'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f1f5f9;
        }
        .container {
            margin-top: 80px;
            max-width: 600px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: #1e40af;
            color: #fff;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
        }
        .btn-primary {
            background: #2563eb;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
        }
        .btn-primary:hover {
            background: #1e40af;
        }
        .alert {
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $translations[$lang]['print_parcel'] ?? 'Print Parcel Certificate'; ?></h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (empty($cases)): ?>
                    <div class="alert alert-warning"><?php echo $translations[$lang]['no_approved_cases'] ?? 'No approved cases found for the selected services.'; ?></div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="case_id" class="form-label"><?php echo $translations[$lang]['select_case'] ?? 'Select Approved Case'; ?></label>
                            <select name="case_id" id="case_id" class="form-select" required>
                                <option value=""><?php echo $translations[$lang]['option_select_case'] ?? '-- Select Case --'; ?></option>
                                <?php foreach ($cases as $case): ?>
                                    <option value="<?php echo $case['id']; ?>">
                                        <?php echo htmlspecialchars($case['title']) . ' (' . ($translations[$lang]['case_' . $case['case_type']] ?? $case['case_type']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-pdf"></i> <?php echo $translations[$lang]['generate_pdf'] ?? 'Generate PDF'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
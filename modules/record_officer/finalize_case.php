<?php
ob_start();
require '../../includes/auth.php'; require '../../includes/db.php'; require '../../includes/config.php'; require '../../includes/languages.php';

// TCPDF path detection
$tcpdf_path = null;
foreach ([__DIR__.'/../../vendor/tecnickcom/tcpdf/tcpdf.php',__DIR__.'/../../tcpdf/tcpdf.php'] as $path) {
    if (file_exists($path)) { $tcpdf_path = $path; break; }
}
if (!$tcpdf_path) die("Error: TCPDF library not found.");
require $tcpdf_path;

redirectIfNotLoggedIn();
if (!isRecordOfficer()) {
    $_SESSION['error'] = $translations['en']['access_denied'];
    header("Location: ".BASE_URL."/public/login.php"); exit;
}

$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['en','om']) ? $_GET['lang'] : 'om';
$conn = getDBConnection();
if ($conn->connect_error) die("Database connection error.");
$conn->set_charset('utf8mb4');

if (!isset($_GET['case_id'])) {
    $_SESSION['error'] = $translations[$lang]['case_not_found'];
    header("Location: ".BASE_URL."/modules/record_officer/approved_cases.php?lang=$lang"); exit;
}
$case_id = (int)$_GET['case_id'];

$stmt = $conn->prepare("SELECT c.id, c.status, c.land_id FROM cases c WHERE c.id = ? AND c.status = 'Approved'");
$stmt->bind_param("i", $case_id);
if ($stmt->execute()) {
    $case = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$case) {
        $_SESSION['error'] = $translations[$lang]['case_not_found'];
        header("Location: ".BASE_URL."/modules/record_officer/approved_cases.php?lang=$lang"); exit;
    }
}

$land_id = (int)$case['land_id'];
$stmt = $conn->prepare("SELECT * FROM land_registration WHERE id = ?");
$stmt->bind_param("i", $land_id);
if ($stmt->execute()) {
    $land = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$land) {
        $_SESSION['error'] = $translations[$lang]['land_not_found'];
        header("Location: ".BASE_URL."/modules/record_officer/approved_cases.php?lang=$lang"); exit;
    }
}

$coordinates = [];
if ($land['has_parcel']) {
    if ($land['coordinates']) {
        foreach (explode(';', trim($land['coordinates'], ';')) as $pair) {
            $parts = explode(',', $pair);
            if (count($parts) == 2) $coordinates[] = ['x' => floatval($parts[0]), 'y' => floatval($parts[1])];
        }
    }
    if (empty($coordinates)) {
        for ($i=1;$i<=4;$i++) {
            if ($land["coord{$i}_x"] !== null) $coordinates[] = ['x' => floatval($land["coord{$i}_x"]), 'y' => floatval($land["coord{$i}_y"])];
        }
    }
}

$navbar_logo = $_SESSION['settings']['navbar_logo'] ?? 'assets/images/default_navbar_logo.png';
$stamp_path = 'Uploads/stamp.png';

ob_start(); ?>
<!DOCTYPE html>
<html lang="<?=htmlspecialchars($lang)?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$translations[$lang]['land_lease_certificate']?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #f5f5e9;
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
            position: absolute;
            top: 10px;
            right: 10px;
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
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-top: 10px;
        }
        .site-plan svg {
            margin-right: 20px;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="watermark"><?=$translations[$lang]['copy']?></div>
        <img class="logo" src="<?=realpath(__DIR__.'/../../'.$navbar_logo)?>" alt="<?=$translations[$lang]['logo_alt']?>">
        <img class="stamp" src="<?=realpath(__DIR__.'/../../'.$stamp_path)?>" alt="Official Stamp">
        <div class="owner-photo">
            <img src="<?=($land['owner_photo']&&file_exists(__DIR__.'/../../'.$land['owner_photo']))?realpath(__DIR__.'/../../'.$land['owner_photo']):realpath(__DIR__.'/../../Uploads/owner-photo-placeholder.jpg')?>" alt="<?=$translations[$lang]['owner_photo_alt']?>">
        </div>
        <div class="header">
            <h1><?=$translations[$lang]['oromia_government']?></h1>
            <h2><?=$translations[$lang]['oromia_regional']?></h2>
            <h3><?=$translations[$lang]['land_bureau']?></h3>
            <p class="certificate-number"><?=$translations[$lang]['certificate_no']?>: <?=htmlspecialchars($land['has_parcel']?($land['parcel_registration_number']??$land['parcel_number']??'N/A'):'N/A')?></p>
        </div>

        <div class="section">
            <h2><?=$translations[$lang]['lease_holder']?></h2>
            <p><strong><?=$translations[$lang]['name']?>:</strong> <?=htmlspecialchars(trim(($land['first_name']??'').' '.($land['middle_name']??'').' '.($land['owner_name']??'')))?></p>
            <p><strong><?=$translations[$lang]['gender']?>:</strong> <?=htmlspecialchars($land['gender']??'N/A')?></p>
            <p><strong><?=$translations[$lang]['phone']?>:</strong> <?=htmlspecialchars($land['owner_phone']??'N/A')?></p>
        </div>

        <div class="section">
            <h2><?=$translations[$lang]['lease_details']?></h2>
            <p><strong><?=$translations[$lang]['land_type']?>:</strong> <?=htmlspecialchars($land['land_type']??'N/A')?></p>
            <p><strong><?=$translations[$lang]['date']?>:</strong> <?=htmlspecialchars($land['has_parcel']?($land['parcel_lease_date']??$land['effective_date']??'N/A'):($land['effective_date']??'N/A'))?></p>
            <p><strong><?=$translations[$lang]['agreement_number']?>:</strong> <?=htmlspecialchars($land['has_parcel']?($land['parcel_agreement_number']??$land['agreement_number']??'N/A'):($land['agreement_number']??'N/A'))?></p>
            <p><strong><?=$translations[$lang]['duration']?>:</strong> <?=htmlspecialchars($land['has_parcel']?($land['parcel_lease_duration']??$land['duration']??'N/A'):($land['duration']??'N/A'))?> <?=$translations[$lang]['years']?></p>
            <p><strong><?=$translations[$lang]['area']?>:</strong> <?=htmlspecialchars($land['has_parcel']?($land['parcel_land_area']??$land['area']??'N/A'):($land['area']??'N/A'))?> m²</p>
            <p><strong><?=$translations[$lang]['purpose']?>:</strong> <?=htmlspecialchars($land['purpose']??'N/A')?></p>
        </div>

        <div class="section">
            <h2><?=$translations[$lang]['coordinates_label']?></h2>
            <table>
                <tr>
                    <th><?=$translations[$lang]['point_label']?></th>
                    <th>X</th>
                    <th>Y</th>
                </tr>
                <?php if($land['has_parcel']&&!empty($coordinates)):foreach($coordinates as $i=>$c):?>
                    <tr><td><?=$i+1?></td><td><?=htmlspecialchars($c['x'])?></td><td><?=htmlspecialchars($c['y'])?></td></tr>
                <?php endforeach;else:?>
                    <tr><td colspan="3"><?=$land['has_parcel']?$translations[$lang]['no_coordinates']:$translations[$lang]['no_parcel']?></td></tr>
                <?php endif;?>
            </table>
        </div>

        <div class="section">
            <h2><?=$translations[$lang]['site_plan']?></h2>
            <div class="site-plan">
                <svg width="150" height="100" viewBox="0 0 150 100" xmlns="http://www.w3.org/2000/svg">
                    <?php if($land['has_parcel']&&!empty($coordinates)):
                        $min_x = min(array_column($coordinates, 'x'));
                        $max_x = max(array_column($coordinates, 'x'));
                        $min_y = min(array_column($coordinates, 'y'));
                        $max_y = max(array_column($coordinates, 'y'));
                        $scale_x = $max_x != $min_x ? 150 / ($max_x - $min_x) : 1;
                        $scale_y = $max_y != $min_y ? 100 / ($max_y - $min_y) : 1;
                        $points = '';
                        foreach($coordinates as $c) {
                            $x = ($c['x'] - $min_x) * $scale_x;
                            $y = 100 - ($c['y'] - $min_y) * $scale_y;
                            $points .= "$x,$y ";
                        }
                    ?>
                    <polygon points="<?=trim($points)?>" fill="none" stroke="#000" stroke-width="1" />
                    <rect x="40" y="20" width="50" height="30" fill="#d3d3d3" />
                    <?php foreach($coordinates as $i=>$c):
                        $x = ($c['x'] - $min_x) * $scale_x;
                        $y = 100 - ($c['y'] - $min_y) * $scale_y;
                    ?>
                        <circle cx="<?=$x?>" cy="<?=$y?>" r="2" fill="#2c3e50" />
                        <text x="<?=$x-5?>" y="<?=$y-5?>" font-size="10"><?=$i+1?></text>
                    <?php endforeach;?>
                    <?php else:?>
                    <text x="75" y="50" font-size="8" fill="#2c3e50" text-anchor="middle"><?=$land['has_parcel']?$translations[$lang]['no_coordinates']:$translations[$lang]['no_parcel']?></text>
                    <?php endif;?>
                </svg>
            </div>
        </div>

        <div class="section neighbors">
            <div>
                <h2><?=$translations[$lang]['neighbors']?></h2>
                <p><strong><?=$translations[$lang]['east']?>:</strong> <?=htmlspecialchars($land['neighbor_east']??'N/A')?></p>
                <p><strong><?=$translations[$lang]['west']?>:</strong> <?=htmlspecialchars($land['neighbor_west']??'N/A')?></p>
                <p><strong><?=$translations[$lang]['north']?>:</strong> <?=htmlspecialchars($land['neighbor_north']??'N/A')?></p>
                <p><strong><?=$translations[$lang]['south']?>:</strong> <?=htmlspecialchars($land['neighbor_south']??'N/A')?></p>
                <h2><?=$translations[$lang]['land_details']?></h2>
                <table>
                    <tr>
                        <th><?=$translations[$lang]['village']?></th>
                        <th><?=$translations[$lang]['block_number']?></th>
                        <th><?=$translations[$lang]['land_grade']?></th>
                        <th><?=$translations[$lang]['area']?></th>
                        <th><?=$translations[$lang]['land_service']?></th>
                        <th><?=$translations[$lang]['registration_number']?></th>
                        <th><?=$translations[$lang]['building_height_allowed']?></th>
                    </tr>
                    <tr>
                        <td><?=htmlspecialchars($land['has_parcel']?($land['parcel_village']??$land['village']??'N/A'):($land['village']??'N/A'))?></td>
                        <td><?=htmlspecialchars($land['has_parcel']?($land['parcel_block_number']??$land['block_number']??'N/A'):($land['block_number']??'N/A'))?></td>
                        <td><?=htmlspecialchars($land['has_parcel']?($land['parcel_land_grade']??$land['land_grade']??'N/A'):($land['land_grade']??'N/A'))?></td>
                        <td><?=htmlspecialchars($land['has_parcel']?($land['parcel_land_area']??$land['area']??'N/A'):($land['area']??'N/A'))?> m²</td>
                        <td><?=htmlspecialchars($land['has_parcel']?($land['parcel_land_service']??$land['land_service']??'N/A'):($land['land_service']??'N/A'))?></td>
                        <td><?=htmlspecialchars($land['has_parcel']?($land['parcel_registration_number']??$land['parcel_number']??'N/A'):($land['parcel_number']??'N/A'))?></td>
                        <td><?=htmlspecialchars($land['has_parcel']?($land['building_height_allowed']??'N/A'):'N/A')?></td>
                    </tr>
                </table>
            </div>
            <div>
                <h2><?=$translations[$lang]['additional_info']?></h2>
                <p><strong><?=$translations[$lang]['zone']?>:</strong> <?=htmlspecialchars($land['zone']??'N/A')?></p>
                <p><strong><?=$translations[$lang]['plot_number']?>:</strong> <?=htmlspecialchars($land['plot_number']??'N/A')?></p>
                <p><strong><?=$translations[$lang]['group_category']?>:</strong> <?=htmlspecialchars($land['group_category']??'N/A')?></p>
                <p><strong><?=$translations[$lang]['registration_date']?>:</strong> <?=htmlspecialchars($land['registration_date']??'N/A')?></p>
            </div>
        </div>

        <div class="signatures">
                <p><strong><?=$translations[$lang]['prepared_by']?>:</strong></p>
                <p><strong><?=$translations[$lang]['name']?>:</strong> <?=htmlspecialchars($land['prepared_by_name']??'N/A')?></p>
                <p><strong><?=$translations[$lang]['role']?>:</strong> <?=htmlspecialchars($land['prepared_by_role']??$translations[$lang]['surveyor']??'Surveyor')?></p>
                <p class="signature-line"><?=$translations[$lang]['signature']?></p>
            
                <p><strong><?=$translations[$lang]['approved_by']?>:</strong></p>
                <p><strong><?=$translations[$lang]['name']?>:</strong> <?=htmlspecialchars($land['approved_by_name']??'N/A')?></p>
                <p><strong><?=$translations[$lang]['role']?>:</strong> <?=htmlspecialchars($land['approved_by_role']??$translations[$lang]['head_surveyor']??'Head Surveyor')?></p>
                <p class="signature-line"><?=$translations[$lang]['signature']?></p>
            
           
                <p><strong><?=$translations[$lang]['authorized_by']?>:</strong></p>
                <p><strong><?=$translations[$lang]['name']?>:</strong> <?=htmlspecialchars($land['authorized_by_name']??'N/A')?></p>
                <p><strong><?=$translations[$lang]['role']?>:</strong> <?=htmlspecialchars($land['authorized_by_role']??$translations[$lang]['land_officer']??'Land Officer')?></p>
                <p class="signature-line"><?=$translations[$lang]['signature']?></p>
            </div>
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

try {
    $temp_dir = realpath(__DIR__.'/../../temp') ?: __DIR__.'/../../temp';
    if (!is_dir($temp_dir)) mkdir($temp_dir,0775,true);
    if (!is_writable($temp_dir)) {
        $_SESSION['error'] = $translations[$lang]['temp_dir_not_writable'] ?? 'Temp dir not writable';
        header("Location: ".BASE_URL."/modules/record_officer/approved_cases.php?lang=$lang"); exit;
    }

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR); $pdf->SetAuthor('Oromia Land Administration');
    $pdf->SetTitle('Land Lease Certificate #'.$case_id); $pdf->SetSubject('Land Lease Certificate');
    $pdf->SetMargins(10,10,10); $pdf->SetAutoPageBreak(false); $pdf->AddPage(); $pdf->writeHTML($html);

    $pdf_filename = 'certificate_'.$case_id.'.pdf';
    $pdf_file = $temp_dir.DIRECTORY_SEPARATOR.$pdf_filename;
    $pdf->Output($pdf_file, 'F');

    if (!file_exists($pdf_file)) {
        $_SESSION['error'] = $translations[$lang]['pdf_generation_failed'];
        header("Location: ".BASE_URL."/modules/record_officer/approved_cases.php?lang=$lang"); exit;
    }

    $stmt = $conn->prepare("UPDATE cases SET status = 'Serviced' WHERE id = ?");
    $stmt->bind_param("i", $case_id); $stmt->execute(); $stmt->close();
    
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, details, severity, ip_address) VALUES (?, 'finalize_case', ?, 'info', ?)");
    $details = "Finalized case ID: $case_id, generated certificate: $pdf_filename";
    $stmt->bind_param("iss", $user_id, $details, $_SERVER['REMOTE_ADDR']); $stmt->execute(); $stmt->close();
    $conn->close();

    header("Location: ".BASE_URL."/modules/record_officer/preview_parcel.php?file=".urlencode($pdf_filename)."&lang=$lang");
    ob_end_flush(); exit;
} catch (Exception $e) {
    $_SESSION['error'] = $translations[$lang]['pdf_generation_failed'];
    header("Location: ".BASE_URL."/modules/record_officer/approved_cases.php?lang=$lang");
    ob_end_flush(); exit;
}
?>
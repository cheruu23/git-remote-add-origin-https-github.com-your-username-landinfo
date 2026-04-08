<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

// Redirect if not logged in or not a record officer
redirectIfNotLoggedIn();
if (!isRecordOfficer()) {
    die("Access denied! Only record officers can view parcel records.");
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

// Fetch record by ID
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $sql = "SELECT * FROM land_registration WHERE id = :id";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $record = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage());
        die("Error fetching record: Please try again later.");
    }

    if (!$record) {
        die("Galmee hin argamne.");
    }
} else {
    die("ID hin galmaa'u.");
}

// Parse coordinates for display
$coordinates = [];
if (!empty($record['coordinates'])) {
    $pairs = explode(';', trim($record['coordinates'], ';'));
    foreach ($pairs as $pair) {
        $parts = explode(',', $pair);
        if (count($parts) == 2) {
            $coordinates[] = ['x' => floatval($parts[0]), 'y' => floatval($parts[1])];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="om">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ilaalii Galmee - ID: <?php echo htmlspecialchars($record['id']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
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
        h2, h3 {
            color: #007bff;
            margin-top: 20px;
        }
        p {
            margin: 5px 0;
            font-size: 16px;
        }
        strong {
            color: #007bff;
            display: inline-block;
            width: 250px;
        }
        .owner-photo {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .owner-photo img {
            width: 150px;
            border-radius: 8px;
            border: 2px solid #007bff;
        }
        table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        table th {
            background-color: #007bff;
            color: white;
        }
        .document-list a {
            display: block;
            color: #007bff;
            text-decoration: none;
            cursor: pointer;
            margin: 5px 0;
        }
        .document-list a:hover {
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
    </style>
</head>
<body>
    <?php include '../../templates/sidebar.php'; ?>
    <div class="details-container">
        <!-- Owner's Photo -->
        <div class="owner-photo">
            <img src="<?php echo htmlspecialchars($record['owner_photo'] ?? '/Uploads/owner-photo-placeholder.jpg'); ?>" alt="Suuraa Abbaa Lafti">
        </div>

        <!-- Odeeffannoo Dhuunfaa -->
        <h3>Odeeffannoo Dhuunfaa</h3>
        <p><strong>ID:</strong> <?php echo htmlspecialchars($record['id'] ?? 'N/A'); ?></p>
        <p><strong>Maqaa Guutuu:</strong> 
            <?php 
                echo htmlspecialchars(trim(
                    ($record['owner_name'] ?? '') . ' ' . 
                    ($record['first_name'] ?? '') . ' ' . 
                    ($record['middle_name'] ?? '')
                )); 
            ?>
        </p>
        <p><strong>Owner Phone:</strong> <?php echo htmlspecialchars($record['owner_phone'] ?? 'N/A'); ?></p>
        <p><strong>Saala:</strong> <?php echo htmlspecialchars($record['gender'] ?? 'N/A'); ?></p>
        <p><strong>Group Category:</strong> <?php echo htmlspecialchars($record['group_category'] ?? 'N/A'); ?></p>

        <!-- Odeeffannoo Lafti -->
        <h3>Odeeffannoo Lafa</h3>
        <p><strong>Gosa Lafti:</strong> <?php echo htmlspecialchars($record['land_type'] ?? 'N/A'); ?></p>
        <p><strong>Ganda:</strong> <?php echo htmlspecialchars($record['village'] ?? 'N/A'); ?></p>
        <p><strong>Gooxi:</strong> <?php echo htmlspecialchars($record['zone'] ?? 'N/A'); ?></p>
        <p><strong>Lak. Blookii:</strong> <?php echo htmlspecialchars($record['block_number'] ?? 'N/A'); ?></p>
        <p><strong>Lak. Parcelii:</strong> <?php echo htmlspecialchars($record['parcel_number'] ?? 'N/A'); ?></p>
        <p><strong>Sadarkaa Lafti:</strong> <?php echo htmlspecialchars($record['land_grade'] ?? 'N/A'); ?></p>
        <p><strong>Tajaajila Lafti:</strong> <?php echo htmlspecialchars($record['land_service'] ?? 'N/A'); ?></p>
        <p><strong>Balina Lafti:</strong> <?php echo htmlspecialchars($record['area'] ?? 'N/A'); ?> m²</p>
        <p><strong>Purpose:</strong> <?php echo htmlspecialchars($record['purpose'] ?? 'N/A'); ?></p>
        <p><strong>Plot Number:</strong> <?php echo htmlspecialchars($record['plot_number'] ?? 'N/A'); ?></p>
        <p><strong>Guyyaa Itti Fufiinsa:</strong> <?php echo htmlspecialchars($record['effective_date'] ?? 'N/A'); ?></p>
        <p><strong>Registration Date:</strong> <?php echo htmlspecialchars($record['registration_date'] ?? 'N/A'); ?></p>
        <p><strong>Created At:</strong> <?php echo htmlspecialchars($record['created_at'] ?? 'N/A'); ?></p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars($record['status'] ?? 'N/A'); ?></p>
        <p>
            <button class="btn btn-primary btn-sm" onclick="checkParcel(<?php echo htmlspecialchars($record['has_parcel'] ?? 0); ?>, <?php echo htmlspecialchars($record['id']); ?>)">
                Ilaali Parcel
            </button>
        </p>
        <p class="no-parcel-message" id="noParcelMessage">
            This user doesn't have a parcel.
        </p>

        <!-- Parcel Details -->
        <h3>Odeeffannoo Parcel</h3>
        <p><strong>Parcel Village:</strong> <?php echo htmlspecialchars($record['parcel_village'] ?? 'N/A'); ?></p>
        <p><strong>Parcel Block Number:</strong> <?php echo htmlspecialchars($record['parcel_block_number'] ?? 'N/A'); ?></p>
        <p><strong>Parcel Land Grade:</strong> <?php echo htmlspecialchars($record['parcel_land_grade'] ?? 'N/A'); ?></p>
        <p><strong>Parcel Land Area:</strong> <?php echo htmlspecialchars($record['parcel_land_area'] ?? 'N/A'); ?> m²</p>
        <p><strong>Parcel Land Service:</strong> <?php echo htmlspecialchars($record['parcel_land_service'] ?? 'N/A'); ?></p>
        <p><strong>Parcel Registration Number:</strong> <?php echo htmlspecialchars($record['parcel_registration_number'] ?? 'N/A'); ?></p>
        <p><strong>Parcel Lease Date:</strong> <?php echo htmlspecialchars($record['parcel_lease_date'] ?? 'N/A'); ?></p>
        <p><strong>Parcel Agreement Number:</strong> <?php echo htmlspecialchars($record['parcel_agreement_number'] ?? 'N/A'); ?></p>
        <p><strong>Parcel Lease Duration:</strong> <?php echo htmlspecialchars($record['parcel_lease_duration'] ?? 'N/A'); ?> years</p>
        <p><strong>Building Height Allowed:</strong> <?php echo htmlspecialchars($record['building_height_allowed'] ?? 'N/A'); ?></p>

        <!-- Coordinates -->
        <h3>Koordiineetii</h3>
        <?php if (!empty($coordinates)): ?>
            <table class="coordinates-table">
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
        <?php else: ?>
            <p>No coordinates available.</p>
        <?php endif; ?>

        <!-- Daangaa Lafti -->
        <h3>Odeeffannoo Daangaa Lafa</h3>
        <table>
            <tr>
                <th>Kallatti</th>
                <th>Dangesiitoota</th>
            </tr>
            <tr>
                <td>Bahaa</td>
                <td><?php echo htmlspecialchars($record['neighbor_east'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Dhiha</td>
                <td><?php echo htmlspecialchars($record['neighbor_west'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Kibba</td>
                <td><?php echo htmlspecialchars($record['neighbor_south'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td>Kaaba</td>
                <td><?php echo htmlspecialchars($record['neighbor_north'] ?? 'N/A'); ?></td>
            </tr>
        </table>

        <!-- Signatures -->
        <h3>Mallattoo</h3>
        <p><strong>Prepared By Name:</strong> <?php echo htmlspecialchars($record['prepared_by_name'] ?? 'N/A'); ?></p>
        <p><strong>Prepared By Role:</strong> <?php echo htmlspecialchars($record['prepared_by_role'] ?? 'N/A'); ?></p>
        <p><strong>Approved By Name:</strong> <?php echo htmlspecialchars($record['approved_by_name'] ?? 'N/A'); ?></p>
        <p><strong>Approved By Role:</strong> <?php echo htmlspecialchars($record['approved_by_role'] ?? 'N/A'); ?></p>
        <p><strong>Authorized By Name:</strong> <?php echo htmlspecialchars($record['authorized_by_name'] ?? 'N/A'); ?></p>
        <p><strong>Authorized By Role:</strong> <?php echo htmlspecialchars($record['authorized_by_role'] ?? 'N/A'); ?></p>
        <p><strong>Surveyor Name:</strong> <?php echo htmlspecialchars($record['surveyor_name'] ?? 'N/A'); ?></p>
        <p><strong>Head Surveyor Name:</strong> <?php echo htmlspecialchars($record['head_surveyor_name'] ?? 'N/A'); ?></p>
        <p><strong>Land Officer Name:</strong> <?php echo htmlspecialchars($record['land_officer_name'] ?? 'N/A'); ?></p>

        <!-- Dokumantoota -->
        <h3>Dokumantoota</h3>
        <div class="document-list">
            <?php if (!empty($record['id_front'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['id_front']); ?>')">ID Fuula Duraa</a>
            <?php endif; ?>
            <?php if (!empty($record['id_back'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['id_back']); ?>')">ID Fuula Dhuumaa</a>
            <?php endif; ?>
            <?php if (!empty($record['xalayaa_miritii'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['xalayaa_miritii']); ?>')">Xalayaa Mirittii</a>
            <?php endif; ?>
            <?php if (!empty($record['nagaee_gibiraa'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['nagaee_gibiraa']); ?>')">Nagaee Gibiraa</a>
            <?php endif; ?>
            <?php if (!empty($record['waligaltee_lease'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['waligaltee_lease']); ?>')">Waligaltee Lease</a>
            <?php endif; ?>
            <?php if (!empty($record['tax_receipt'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['tax_receipt']); ?>')">Tax Receipt</a>
            <?php endif; ?>
            <?php if (!empty($record['miriti_paper'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['miriti_paper']); ?>')">Miriti Paper</a>
            <?php endif; ?>
            <?php if (!empty($record['caalbaasii_agreement'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['caalbaasii_agreement']); ?>')">Caalbaasii Agreement</a>
            <?php endif; ?>
            <?php if (!empty($record['bita_fi_gurgurtaa_agreement'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['bita_fi_gurgurtaa_agreement']); ?>')">Bita fi Gurgurtaa Agreement</a>
            <?php endif; ?>
            <?php if (!empty($record['bita_fi_gurgurtaa_receipt'])): ?>
                <a onclick="showDocument('<?php echo htmlspecialchars($record['bita_fi_gurgurtaa_receipt']); ?>')">Bita fi Gurgurtaa Receipt</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for Documents -->
    <div class="modal fade" id="documentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dokumantii</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="documentImage" class="img-fluid" src="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary zoom-btn" onclick="zoomIn()">Zoom In</button>
                    <button type="button" class="btn btn-primary zoom-btn" onclick="zoomOut()">Zoom Out</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentZoom = 1;
        const minZoom = 0.5;
        const maxZoom = 3;
        const zoomStep = 0.25;

        function showDocument(src) {
            const img = document.getElementById('documentImage');
            img.src = src;
            currentZoom = 1;
            img.style.transform = `scale(${currentZoom})`;
            new bootstrap.Modal(document.getElementById('documentModal')).show();
        }

        function zoomIn() {
            if (currentZoom < maxZoom) {
                currentZoom += zoomStep;
                document.getElementById('documentImage').style.transform = `scale(${currentZoom})`;
            }
        }

        function zoomOut() {
            if (currentZoom > minZoom) {
                currentZoom -= zoomStep;
                document.getElementById('documentImage').style.transform = `scale(${currentZoom})`;
            }
        }

        document.getElementById('documentModal').addEventListener('hidden.bs.modal', function () {
            currentZoom = 1;
            document.getElementById('documentImage').style.transform = `scale(${currentZoom})`;
        });

        function checkParcel(hasParcel, id) {
            const message = document.getElementById('noParcelMessage');
            if (hasParcel == 1) {
                window.location.href = 'view_parcel.php?id=' + id;
            } else {
                message.style.display = 'block';
            }
        }
    </script>
</body>
</html>
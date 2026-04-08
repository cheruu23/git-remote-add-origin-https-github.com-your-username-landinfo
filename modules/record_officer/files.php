<?php
session_start();
require_once '../../includes/init.php';
require_once '../../includes/languages.php';

// Redirect if not logged in or not a record officer
redirectIfNotLoggedIn();
if (!isRecordOfficer()) {
    logAction('access_denied', 'Unauthorized access to land registration page', 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    die($translations[$lang]['access_denied'] ?? "Access denied!");
}

// Validate and sanitize lang parameter
$valid_langs = ['en', 'om'];
$lang = isset($_GET['lang']) && in_array($_GET['lang'], $valid_langs) ? $_GET['lang'] : 'om';

// Sanitize search and filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$village = isset($_GET['village']) ? trim($_GET['village']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10; // Records per page
$offset = ($page - 1) * $perPage;

// Database connection using PDO
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    logAction('db_connection_success', 'Successfully connected to database', 'info', ['user_id' => $_SESSION['user']['id'] ?? null]);
} catch (PDOException $e) {
    logAction('db_connection_error', 'Database connection failed: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    die($translations[$lang]['db_connection_failed'] ?? "Connection error. Please try again later.");
}

// Define the villages exactly as they appear in the registration form
$villages = [
    "Abba Sayyyaa",
    "Soor",
    "Taboo",
    "Abbaa moolee",
    "gaddisaa odaa",
    "qolloo kormaa"
];

// Fetch records with search and village filter
$sql = "SELECT id, owner_name, first_name, middle_name, owner_phone, gender, land_type, village, zone, 
               block_number, parcel_number, effective_date, has_parcel, owner_photo 
        FROM land_registration 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (id LIKE :search OR owner_name LIKE :search OR first_name LIKE :search OR middle_name LIKE :search OR owner_phone LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($village) {
    $sql .= " AND village = :village";
    $params[':village'] = $village;
}

$sql .= " ORDER BY id ASC LIMIT :offset, :perPage";

try {
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll();
    logAction('fetch_records', 'Successfully fetched land registration records', 'info', ['user_id' => $_SESSION['user']['id'] ?? null]);
} catch (PDOException $e) {
    logAction('query_failed', 'Query failed: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    die($translations[$lang]['error_fetching_records'] ?? "Error fetching records: Please try again later.");
}

// Count total records for pagination
try {
    $countSql = "SELECT COUNT(*) FROM land_registration WHERE 1=1";
    $countParams = [];
    if ($search) {
        $countSql .= " AND (id LIKE :search OR owner_name LIKE :search OR first_name LIKE :search OR middle_name LIKE :search OR owner_phone LIKE :search)";
        $countParams[':search'] = '%' . $search . '%';
    }
    if ($village) {
        $countSql .= " AND village = :village";
        $countParams[':village'] = $village;
    }
    $stmt = $conn->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);
} catch (PDOException $e) {
    logAction('count_failed', 'Failed to count records: ' . $e->getMessage(), 'error', ['user_id' => $_SESSION['user']['id'] ?? null]);
    $totalRecords = 0;
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['land_records_title'] ?? 'Land Records'; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Your existing CSS styles here */
    </style>
</head>
<body>
    <div class="container-fluid content" id="main-content">
        <div class="table-container">
            <h1 class="text-center mb-4"><?php echo $translations[$lang]['land_records_title'] ?? 'Land Records'; ?></h1>

            <?php if (empty($records) && !$search && !$village): ?>
                <div class="no-parcel-message">
                    <h3><?php echo $translations[$lang]['no_parcel_message'] ?? 'No land records available. Please apply for a parcel.'; ?></h3>
                    <a href="apply_parcel.php"><?php echo $translations[$lang]['apply_parcel'] ?? 'Apply for Parcel'; ?></a> | 
                    <a href="../dashboard.php"><?php echo $translations[$lang]['back_to_dashboard'] ?? 'Back to Dashboard'; ?></a>
                </div>
            <?php else: ?>
                <form id="searchForm" method="GET" action="">
                    <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                    <div class="search-bar">
                        <div class="input-group">
                            <input type="text" id="searchInput" name="search" class="form-control" placeholder="<?php echo $translations[$lang]['search_placeholder'] ?? 'Search by ID, Owner Name, First Name, Middle Name'; ?>" value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="input-group-text" id="searchButton">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div class="form-group">
                            <select id="villageFilter" name="village" class="form-control village-filter">
                                <option value=""><?php echo $translations[$lang]['select_village'] ?? 'Select Village'; ?></option>
                                <option value="Abba Sayyyaa" <?php echo $village === 'Abba Sayyyaa' ? 'selected' : ''; ?>>
                                    <?php echo $translations[$lang]['abba_sayyyaa'] ?? 'Abba Sayyyaa'; ?>
                                </option>
                                <option value="Soor" <?php echo $village === 'Soor' ? 'selected' : ''; ?>>
                                    <?php echo $translations[$lang]['soor'] ?? 'Soor'; ?>
                                </option>
                                <option value="Taboo" <?php echo $village === 'Taboo' ? 'selected' : ''; ?>>
                                    <?php echo $translations[$lang]['taboo'] ?? 'Taboo'; ?>
                                </option>
                                <option value="Abbaa moolee" <?php echo $village === 'Abbaa moolee' ? 'selected' : ''; ?>>
                                    <?php echo $translations[$lang]['abba_moolee'] ?? 'Abbaa Moolee'; ?>
                                </option>
                                <option value="gaddisaa odaa" <?php echo $village === 'gaddisaa odaa' ? 'selected' : ''; ?>>
                                    <?php echo $translations[$lang]['gaddisaa_odaa'] ?? 'Gaddisaa Odaa'; ?>
                                </option>
                                <option value="qolloo kormaa" <?php echo $village === 'qolloo kormaa' ? 'selected' : ''; ?>>
                                    <?php echo $translations[$lang]['qolloo_kormaa'] ?? 'Qolloo Kormaa'; ?>
                                </option>
                            </select>
                        </div>
                    </div>
                </form>
                <?php if (empty($records)): ?>
                    <div class="no-parcel-message">
                        <h3><?php echo $translations[$lang]['no_records_found'] ?? 'No records found for the selected village or search criteria.'; ?></h3>
                        <a href="?lang=<?php echo $lang; ?>"><?php echo $translations[$lang]['clear_filters'] ?? 'Clear Filters'; ?></a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?php echo $translations[$lang]['id'] ?? 'ID'; ?></th>
                                    <th><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?></th>
                                    <th><?php echo $translations[$lang]['first_name'] ?? 'First Name'; ?></th>
                                    <th><?php echo $translations[$lang]['middle_name'] ?? 'Middle Name'; ?></th>
                                    <th><?php echo $translations[$lang]['owner_phone'] ?? 'Phone'; ?></th>
                                    <th><?php echo $translations[$lang]['gender'] ?? 'Gender'; ?></th>
                                    <th><?php echo $translations[$lang]['land_type'] ?? 'Land Type'; ?></th>
                                    <th><?php echo $translations[$lang]['village'] ?? 'Village'; ?></th>
                                    <th><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?></th>
                                    <th><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?></th>
                                    <th><?php echo $translations[$lang]['parcel_number'] ?? 'Parcel Number'; ?></th>
                                    <th><?php echo $translations[$lang]['effective_date'] ?? 'Effective Date'; ?></th>
                                    <th><?php echo $translations[$lang]['has_parcel'] ?? 'Has Parcel'; ?></th>
                                    <th><?php echo $translations[$lang]['action'] ?? 'Action'; ?></th>
                                </tr>
                            </thead>
                            <tbody id="fileList">
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['owner_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['first_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['middle_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['owner_phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['gender'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['land_type'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['village'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['zone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['block_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['parcel_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['effective_date'] ?? 'N/A'); ?></td>
                                        <td class="<?php echo $record['has_parcel'] == 1 ? 'has-parcel' : 'no-parcel'; ?>">
                                            <?php echo $record['has_parcel'] == 1 ? ($translations[$lang]['yes'] ?? 'Yes') : ($translations[$lang]['no'] ?? 'No'); ?>
                                        </td>
                                        <td>
                                            <a href="detail.php?id=<?php echo htmlspecialchars($record['id']); ?>&lang=<?php echo $lang; ?>">
                                                <button class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> <?php echo $translations[$lang]['view'] ?? 'View'; ?>
                                                </button>
                                            </a>
                                            <button class="btn btn-success btn-sm print-parcel-btn" 
                                                    data-parcel-id="<?php echo htmlspecialchars($record['id']); ?>" 
                                                    data-owner-photo="<?php echo htmlspecialchars($record['owner_photo'] ?? ''); ?>">
                                                <i class="fas fa-print"></i> <?php echo $translations[$lang]['print_parcel'] ?? 'Print Parcel'; ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?lang=<?php echo $lang; ?>&search=<?php echo urlencode($search); ?>&village=<?php echo urlencode($village); ?>&page=<?php echo $page - 1; ?>">« Prev</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?lang=<?php echo $lang; ?>&search=<?php echo urlencode($search); ?>&village=<?php echo urlencode($village); ?>&page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?lang=<?php echo $lang; ?>&search=<?php echo urlencode($search); ?>&village=<?php echo urlencode($village); ?>&page=<?php echo $page + 1; ?>">Next »</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.getElementById('searchForm');
            const searchInput = document.getElementById('searchInput');
            const villageFilter = document.getElementById('villageFilter');

            // Submit form on Enter key in search input
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchForm.submit();
                }
            });

            // Submit form when village filter changes
            villageFilter.addEventListener('change', function() {
                searchForm.submit();
            });

            // Print parcel functionality
            const printButtons = document.querySelectorAll('.print-parcel-btn');
            printButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const parcelId = this.getAttribute('data-parcel-id');
                    const row = this.closest('tr');
                    const parcelData = {
                        id: row.querySelector('td:nth-child(1)')?.textContent || 'N/A',
                        owner_name: row.querySelector('td:nth-child(2)')?.textContent || 'N/A',
                        first_name: row.querySelector('td:nth-child(3)')?.textContent || 'N/A',
                        middle_name: row.querySelector('td:nth-child(4)')?.textContent || 'N/A',
                        owner_phone: row.querySelector('td:nth-child(5)')?.textContent || 'N/A',
                        gender: row.querySelector('td:nth-child(6)')?.textContent || 'N/A',
                        land_type: row.querySelector('td:nth-child(7)')?.textContent || 'N/A',
                        village: row.querySelector('td:nth-child(8)')?.textContent || 'N/A',
                        zone: row.querySelector('td:nth-child(9)')?.textContent || 'N/A',
                        block_number: row.querySelector('td:nth-child(10)')?.textContent || 'N/A',
                        parcel_number: row.querySelector('td:nth-child(11)')?.textContent || 'N/A',
                        effective_date: row.querySelector('td:nth-child(12)')?.textContent || 'N/A',
                        owner_photo: this.getAttribute('data-owner-photo') || ''
                    };

                    // Log print action
                    fetch('log_action.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            action: 'log_print',
                            parcel_id: parcelId
                        })
                    }).catch(error => console.error('Error logging print action:', error));

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
                                    <p><strong><?php echo $translations[$lang]['id'] ?? 'ID'; ?>:</strong> ${parcelData.id}</p>
                                    <p><strong><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?>:</strong> ${parcelData.owner_name}</p>
                                    <p><strong><?php echo $translations[$lang]['first_name'] ?? 'First Name'; ?>:</strong> ${parcelData.first_name}</p>
                                    <p><strong><?php echo $translations[$lang]['middle_name'] ?? 'Middle Name'; ?>:</strong> ${parcelData.middle_name}</p>
                                    <p><strong><?php echo $translations[$lang]['owner_phone'] ?? 'Phone'; ?>:</strong> ${parcelData.owner_phone}</p>
                                    <p><strong><?php echo $translations[$lang]['gender'] ?? 'Gender'; ?>:</strong> ${parcelData.gender}</p>
                                    <p><strong><?php echo $translations[$lang]['land_type'] ?? 'Land Type'; ?>:</strong> ${parcelData.land_type}</p>
                                    <p><strong><?php echo $translations[$lang]['village'] ?? 'Village'; ?>:</strong> ${parcelData.village}</p>
                                    <p><strong><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?>:</strong> ${parcelData.zone}</p>
                                    <p><strong><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?>:</strong> ${parcelData.block_number}</p>
                                    <p><strong><?php echo $translations[$lang]['parcel_number'] ?? 'Parcel Number'; ?>:</strong> ${parcelData.parcel_number}</p>
                                    <p><strong><?php echo $translations[$lang]['effective_date'] ?? 'Effective Date'; ?>:</strong> ${parcelData.effective_date}</p>
                                    ${parcelData.owner_photo ? `<img src="${parcelData.owner_photo}" alt="Owner Photo">` : ''}
                                </div>
                                <script>
                                    window.onload = function() { window.print(); window.close(); };
                                </script>
                            </body>
                        </html>
    </script>
</body>
</html>
<?php
// Close PDO connection
$conn = null;
?>
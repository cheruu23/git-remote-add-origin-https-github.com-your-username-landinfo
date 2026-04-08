<?php
// Database connection
$host = 'localhost';
$dbname = 'landinfo_new';
$username = 'root';
$password = '';
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Dhaabbata database hin dandeenye: " . $e->getMessage());
}

// Fetch all records from the land_registration table
$sql = "SELECT * FROM land_registration";
$stmt = $conn->prepare($sql);
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="om">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galmee Lafti</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
            margin-top: 20px;
        }

        .table-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .table thead {
            background-color: #007bff;
            color: white;
        }

        .details-row {
            display: none;
        }

        .details-row.active {
            display: table-row;
        }

        .details-row {
            background-color: #f9f9f9;
            border: 2px solid #007bff;
            border-radius: 10px;
            margin: 10px 0;
            padding: 15px;
        }

        .details-content {
            font-family: Arial, sans-serif;
            color: #333;
        }

        .details-content strong {
            color: #007bff;
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }

        .details-content p {
            margin: 8px 0;
            font-size: 14px;
        }

        .details-content img {
            width: 100px;
            height: auto;
            border-radius: 8px;
            border: 2px solid #007bff;
            margin-top: 10px;
        }

        .details-row:hover {
            background-color: #f1f1f1;
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
    </style>
</head>
<? include '../../templates/sidebar.php'; ?>

<body>
    <div class="table-container">
        <h1 class="text-center mb-4">Galmee Lafti</h1>

        <div class="search-bar">
            <div class="input-group">
                <input type="text" id="searchInput" class="form-control" placeholder="ID, Maqaa, Lakkoofsa">
                <span class="input-group-text" id="searchButton">
                    <i class="fas fa-search"></i>
                </span>
            </div>
        </div>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Maqaa Abbaa Lafti</th>
                    <th>Maqaa Duraa</th>
                    <th>Maqaa Giddu Galeessaa</th>
                    <th>Saala</th>
                    <th>Haala Argannaa Lafa</th>
                    <th>Ganda</th>
                    <th>Zone</th>
                    <th>Lak. Blooki</th>
                    <th>Lak. Parcelii</th>
                    <th>Guyyaa Itti Fufiinsa</th>
                    <th>Gocha</th>
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
                            <a href="detail.php?id=<?php echo htmlspecialchars($record['id']); ?>">
                                <button class="btn btn-primary btn-sm">ilaali</button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchButton = document.getElementById('searchButton');
            const fileList = document.getElementById('fileList');
            const rows = Array.from(fileList.getElementsByTagName('tr'));

            // Function to filter rows based on search term
            function filterRows() {
                const searchTerm = searchInput.value.toLowerCase();

                rows.forEach(row => {
                    const id = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                    const ownerName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    const firstName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                    const blockNumber = row.querySelector('td:nth-child(9)').textContent.toLowerCase();

                    // Check if the row matches the search term
                    if (id.includes(searchTerm) || ownerName.includes(searchTerm) || firstName.includes(searchTerm) || blockNumber.includes(searchTerm)) {
                        row.classList.remove('hidden'); // Show matching rows
                    } else {
                        row.classList.add('hidden'); // Hide non-matching rows
                    }
                });
            }

            // Trigger search on icon click
            searchButton.addEventListener('click', filterRows);

            // Optional: Trigger search on Enter key
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') filterRows();
            });
        });
    </script>
    <footer>

        <?php include '../../templates/footer.php'; ?>
    </footer>
</body>

</html>
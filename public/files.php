<?php
session_start();
require '../includes/auth.php';
require '../includes/db.php';
redirectIfNotLoggedIn();

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
    <title>Galmee Lafti - LIMS</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(45deg, #0f2027, #203a43, #2c5364);
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #fff;
            margin: 0;
            display: flex;
        }

        .sidebar {
            flex: 0 0 250px;
        }

        .content {
            flex: 1;
            padding: 20px;
        }

        .table-container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        h1 {
            font-size: 2rem;
            text-align: center;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
        }

        .table {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead {
            background: linear-gradient(90deg, #00ddeb, #00b4d8);
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border: none;
        }

        .table tbody tr {
            transition: background 0.3s;
        }

        .table tbody tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-primary {
            background: linear-gradient(90deg, #00ddeb, #00b4d8);
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: bold;
            text-transform: uppercase;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: linear-gradient(90deg, #00b4d8, #0096c7);
            box-shadow: 0 5px 15px rgba(0, 221, 235, 0.5);
            transform: translateY(-2px);
        }

        .search-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
        }

        .search-bar .input-group {
            width: 250px;
            transition: width 0.3s;
        }

        .search-bar .input-group:focus-within {
            width: 350px;
        }

        .search-bar input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px 0 0 20px;
            color: #fff;
            padding: 10px 15px;
        }

        .search-bar input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .search-bar .input-group-text {
            background: linear-gradient(90deg, #00ennen, #00b4d8);
            border: none;
            border-radius: 0 20px 20px 0;
            color: #fff;
            cursor: pointer;
        }

        .hidden {
            display: none;
        }

        footer {
            text-align: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        footer p {
            margin: 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }
    </style>
</head>
<body>
    <?php include '../templates/sidebar.php'; ?>
    <div class="content">
        <div class="table-container">
            <h1>Galmee Lafti</h1>

            <div class="search-bar">
                <div class="input-group">
                    <input type="text" id="searchInput" class="form-control" placeholder="ID, Maqaa, Ganda, Zone...">
                    <span class="input-group-text" id="searchButton">
                        <i class="fas fa-search"></i>
                    </span>
                </div>
            </div>

            <table class="table table-striped">
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
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Land Information Management System (LIMS). All rights reserved.</p>
    </footer>

    <!-- Bootstrap JS (for table responsiveness) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
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
                    const id = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                    const ownerName = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                    const firstName = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                    const middleName = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
                    const gender = row.querySelector('td:nth-child(5)')?.textContent.toLowerCase() || '';
                    const landType = row.querySelector('td:nth-child(6)')?.textContent.toLowerCase() || '';
                    const village = row.querySelector('td:nth-child(7)')?.textContent.toLowerCase() || '';
                    const zone = row.querySelector('td:nth-child(8)')?.textContent.toLowerCase() || '';
                    const blockNumber = row.querySelector('td:nth-child(9)')?.textContent.toLowerCase() || '';
                    const parcelNumber = row.querySelector('td:nth-child(10)')?.textContent.toLowerCase() || '';

                    // Check if the row matches the search term
                    if (
                        id.includes(searchTerm) ||
                        ownerName.includes(searchTerm) ||
                        firstName.includes(searchTerm) ||
                        middleName.includes(searchTerm) ||
                        gender.includes(searchTerm) ||
                        landType.includes(searchTerm) ||
                        village.includes(searchTerm) ||
                        zone.includes(searchTerm) ||
                        blockNumber.includes(searchTerm) ||
                        parcelNumber.includes(searchTerm)
                    ) {
                        row.classList.remove('hidden');
                    } else {
                        row.classList.add('hidden');
                    }
                });
            }

            // Real-time search
            searchInput.addEventListener('input', filterRows);

            // Trigger search on button click
            searchButton.addEventListener('click', filterRows);

            // Trigger search on Enter key
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') filterRows();
            });
        });
    </script>
</body>
</html>
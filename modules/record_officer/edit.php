<?php
// Database connection
$host = 'localhost'; // Replace with your database host
$dbname = 'your_database_name'; // Replace with your database name
$username = 'your_username'; // Replace with your database username
$password = 'your_password'; // Replace with your database password

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Dhaabbata database hin dandeenye: " . $e->getMessage());
}

// Fetch the record to be updated
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM land_holding WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        die("Galmee hin argamne.");
    }
} else {
    die("ID hin filatamne.");
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $owner_name = $_POST['owner_name'];
    $registration_number = $_POST['registration_number'];
    $village = $_POST['village'];
    $zone = $_POST['zone'];
    $land_grade = $_POST['land_grade'];
    $land_service = $_POST['land_service'];
    $parcel_number = $_POST['parcel_number'];
    $file_count = $_POST['file_count'];
    $block_number = $_POST['block_number'];
    $effective_date = $_POST['effective_date'];
    $gender = $_POST['gender'];
    $age = $_POST['age'];
    $land_type = $_POST['land_type'];

    // Update the record in the database
    $sql = "UPDATE land_holding SET 
            owner_name = :owner_name, 
            registration_number = :registration_number, 
            village = :village, 
            zone = :zone, 
            land_grade = :land_grade, 
            land_service = :land_service, 
            parcel_number = :parcel_number, 
            file_count = :file_count, 
            block_number = :block_number, 
            effective_date = :effective_date, 
            gender = :gender, 
            age = :age, 
            land_type = :land_type 
            WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':owner_name' => $owner_name,
        ':registration_number' => $registration_number,
        ':village' => $village,
        ':zone' => $zone,
        ':land_grade' => $land_grade,
        ':land_service' => $land_service,
        ':parcel_number' => $parcel_number,
        ':file_count' => $file_count,
        ':block_number' => $block_number,
        ':effective_date' => $effective_date,
        ':gender' => $gender,
        ':age' => $age,
        ':land_type' => $land_type,
        ':id' => $id
    ]);

    // Redirect to the view page after updating
    header("Location: view_registered_files.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="om">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galmee Lafti Sirreessuu</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .form-container h2 {
            color: #007bff;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="form-container">
        <h2 class="text-center">Galmee Lafti Sirreessuu</h2>

        <!-- Edit Form -->
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="owner_name">Maqaa Abbaa Lafti</label>
                        <input type="text" class="form-control" id="owner_name" name="owner_name" value="<?php echo htmlspecialchars($record['owner_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="registration_number">Lakkoofsa Galme</label>
                        <input type="text" class="form-control" id="registration_number" name="registration_number" value="<?php echo htmlspecialchars($record['registration_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="village">Ganda</label>
                        <input type="text" class="form-control" id="village" name="village" value="<?php echo htmlspecialchars($record['village']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="zone">Gooxi</label>
                        <input type="text" class="form-control" id="zone" name="zone" value="<?php echo htmlspecialchars($record['zone']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="land_grade">Sadarkaa Lafti</label>
                        <input type="text" class="form-control" id="land_grade" name="land_grade" value="<?php echo htmlspecialchars($record['land_grade']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="land_service">Tajaajilaa Lafti</label>
                        <input type="text" class="form-control" id="land_service" name="land_service" value="<?php echo htmlspecialchars($record['land_service']); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="parcel_number">Lakkoofsa Parcelii</label>
                        <input type="text" class="form-control" id="parcel_number" name="parcel_number" value="<?php echo htmlspecialchars($record['parcel_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="file_count">Baayina Fayila</label>
                        <input type="number" class="form-control" id="file_count" name="file_count" value="<?php echo htmlspecialchars($record['file_count']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="block_number">Lakkoofsa Blooki</label>
                        <input type="text" class="form-control" id="block_number" name="block_number" value="<?php echo htmlspecialchars($record['block_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="effective_date">Guyyaa Itti Fufiinsa</label>
                        <input type="date" class="form-control" id="effective_date" name="effective_date" value="<?php echo htmlspecialchars($record['effective_date']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Saala</label>
                        <select class="form-control" id="gender" name="gender" required>
                            <option value="Dhiira" <?php echo ($record['gender'] == 'Dhiira') ? 'selected' : ''; ?>>Dhiira</option>
                            <option value="Dubartii" <?php echo ($record['gender'] == 'Dubartii') ? 'selected' : ''; ?>>Dubartii</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="age">Umurii</label>
                        <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($record['age']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="land_type">Gosa Lafti</label>
                        <input type="text" class="form-control" id="land_type" name="land_type" value="<?php echo htmlspecialchars($record['land_type']); ?>" required>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary">Sirreessi</button>
                <a href="view_registered_files.php" class="btn btn-secondary">Deebi'i</a>
            </div>
        </form>
    </div>
</body>

</html>
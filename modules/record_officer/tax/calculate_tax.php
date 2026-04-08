<?php
include 'db.php';

if (isset($_GET['parcel_id'])) {
    $parcel_id = $_GET['parcel_id'];

    // Fetch land details
    $stmt = $conn->prepare("SELECT land_use, assessed_value, land_size, productive_value FROM LandParcels WHERE parcel_id = ?");
    $stmt->execute([$parcel_id]);
    $land = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$land) {
        echo json_encode(['error' => 'Land parcel not found!']);
        exit;
    }

    // Fetch tax rate
    $stmt = $conn->prepare("SELECT tax_rate FROM TaxRates WHERE land_use = ?");
    $stmt->execute([$land['land_use']]);
    $tax_rate = $stmt->fetchColumn();

    // Calculate tax
    if ($land['land_use'] == 'urban') {
        $tax = $land['assessed_value'] * ($tax_rate / 100);
    } else {
        $tax = $land['land_size'] * $land['productive_value'] * ($tax_rate / 100);
    }

    // Return tax amount
    echo json_encode(['taxAmount' => number_format($tax, 2)]);
} else {
    echo json_encode(['error' => 'Parcel ID is required!']);
}

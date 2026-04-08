<?php
// update_form.php

// Database connection
$conn = new mysqli("localhost", "root", "", "lims");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$id = $_POST['id'];
$full_name_of_landholder = $_POST['full_name_of_landholder'];
$gender = $_POST['gender'];
$age = $_POST['age'];
$spouse_name = $_POST['spouse_name'];
$spouse_age = $_POST['spouse_age'];
$spouse_gender = $_POST['spouse_gender'];
$family_relation = $_POST['family_relation'];
$parcel_code = $_POST['parcel_code'];
$number_of_parcels = $_POST['number_of_parcels'];
$area_in_kariti = $_POST['area_in_kariti'];
$area_in_hectare = $_POST['area_in_hectare'];
$land_value = $_POST['land_value'];
$holding_type = $_POST['holding_type'];
$land_use = $_POST['land_use'];
$neighboring_east = $_POST['neighboring_east'];
$neighboring_west = $_POST['neighboring_west'];
$neighboring_north = $_POST['neighboring_north'];
$neighboring_south = $_POST['neighboring_south'];
$method_of_access = $_POST['method_of_access'];
$year_of_acquisition = $_POST['year_of_acquisition'];
$measured_by = $_POST['measured_by'];
$registered_by_name = $_POST['registered_by_name'];
$registered_by_signature = $_POST['registered_by_signature'];
$registered_by_date = $_POST['registered_by_date'];

// Update query
$sql = "UPDATE land_holding SET
        full_name_of_landholder = '$full_name_of_landholder',
        gender = '$gender',
        age = '$age',
        spouse_name = '$spouse_name',
        spouse_age = '$spouse_age',
        spouse_gender = '$spouse_gender',
        family_relation = '$family_relation',
        parcel_code = '$parcel_code',
        number_of_parcels = '$number_of_parcels',
        area_in_kariti = '$area_in_kariti',
        area_in_hectare = '$area_in_hectare',
        land_value = '$land_value',
        holding_type = '$holding_type',
        land_use = '$land_use',
        neighboring_east = '$neighboring_east',
        neighboring_west = '$neighboring_west',
        neighboring_north = '$neighboring_north',
        neighboring_south = '$neighboring_south',
        method_of_access = '$method_of_access',
        year_of_acquisition = '$year_of_acquisition',
        measured_by = '$measured_by',
        registered_by_name = '$registered_by_name',
        registered_by_signature = '$registered_by_signature',
        registered_by_date = '$registered_by_date'
        WHERE id = $id";

if ($conn->query($sql) === TRUE) {
    echo "Record updated successfully.";
} else {
    echo "Error updating record: " . $conn->error;
}

$conn->close();

<?php
session_start();
require_once '../../includes/auth.php';
require '../../includes/db.php';
require '../../includes/languages.php';
require '../../includes/logger.php';

redirectIfNotLoggedIn();
if (!function_exists('isRecordOfficer') || !isRecordOfficer()) {
    logAction('access_denied', 'Unauthorized access to land registration form', 'error');
    die($translations[$lang]['access_denied'] ?? "Access denied!");
}

$lang = $_GET['lang'] ?? 'en'; // Default to English
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations[$lang]['land_registration_form'] ?? 'Land Registration Form'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .content.collapsed {
            margin-left: 60px;
        }
        .form-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 25px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .form-container h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #1a3c6d;
            text-align: center;
            margin-bottom: 30px;
            text-transform: uppercase;
        }
        .form-section {
            margin-bottom: 25px;
        }
        .form-section h2 {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            font-size: 1.3rem;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        .form-group label {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 5px;
            display: block;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        .document-section {
            display: none;
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f8f9fa;
            transition: opacity 0.3s ease;
        }
        .document-section.show {
            display: block;
            opacity: 1;
        }
        .document-section h3 {
            color: #1a3c6d;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        .photo-preview img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 2px solid #007bff;
            border-radius: 6px;
            margin-top: 10px;
        }
        .checkbox-group {
            margin: 15px 0;
        }
        .checkbox-group label {
            font-weight: 500;
            color: #1f2937;
            display: flex;
            align-items: center;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 1rem;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 1rem;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057, #343a40);
        }
        .is-invalid {
            border-color: #dc3545;
        }
        .invalid-feedback {
            color: #dc3545;
            font-size: 0.85rem;
        }
        @media (max-width: 992px) {
            .content {
                margin-left: 60px;
            }
        }
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            .form-container {
                padding: 15px;
                margin: 15px;
            }
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .form-group {
                min-width: 100%;
                margin-bottom: 15px;
            }
            .form-container h1 {
                font-size: 1.8rem;
            }
            .form-section h2 {
                font-size: 1.2rem;
            }
        }
        @media (max-width: 576px) {
            .photo-preview img {
                width: 100px;
                height: 100px;
            }
            .btn-primary,
            .btn-secondary {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../templates/sidebar.php'; ?>
    <div class="content" id="main-content">
        <div class="form-container">
            <h1><?php echo $translations[$lang]['land_registration_form'] ?? 'Land Registration Form'; ?></h1>
            <form action="submit_form.php" method="post" enctype="multipart/form-data" id="landForm" novalidate>
                <input type="hidden" name="lang" value="<?php echo htmlspecialchars($lang); ?>">
                <input type="hidden" name="has_parcel" value="0">
                <div class="form-section">
                    <h2><?php echo $translations[$lang]['personal_information'] ?? 'Personal Information'; ?></h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="owner_name"><?php echo $translations[$lang]['owner_name'] ?? 'Owner Name'; ?></label>
                            <input type="text" id="owner_name" name="owner_name" class="form-control" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="first_name"><?php echo $translations[$lang]['first_name'] ?? 'First Name'; ?></label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="middle_name"><?php echo $translations[$lang]['middle_name'] ?? 'Middle Name'; ?></label>
                            <input type="text" id="middle_name" name="middle_name" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender"><?php echo $translations[$lang]['gender'] ?? 'Gender'; ?></label>
                            <select id="gender" name="gender" class="form-control" required>
                                <option value=""><?php echo $translations[$lang]['select_gender'] ?? 'Select Gender'; ?></option>
                                <option value="Dhiira"><?php echo $translations[$lang]['male'] ?? 'Male'; ?></option>
                                <option value="Dubartii"><?php echo $translations[$lang]['female'] ?? 'Female'; ?></option>
                            </select>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="owner_phone"><?php echo $translations[$lang]['owner_phone'] ?? 'Phone Number'; ?></label>
                            <input type="text" id="owner_phone" name="owner_phone" class="form-control">
                        </div>
                        <div class="form-group"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h2><?php echo $translations[$lang]['land_acquisition_details'] ?? 'Land Acquisition Details'; ?></h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="land_type"><?php echo $translations[$lang]['land_type'] ?? 'Land Acquisition Type'; ?></label>
                            <select id="land_type" name="land_type" class="form-control" required onchange="toggleDocumentSection()">
                                <option value=""><?php echo $translations[$lang]['select_option'] ?? 'Select Option'; ?></option>
                                <option value="dhaala"><?php echo $translations[$lang]['dhaala'] ?? 'Inheritance'; ?></option>
                                <option value="lease_land"><?php echo $translations[$lang]['lease_land'] ?? 'Lease Land'; ?></option>
                                <option value="bita_fi_gurgurtaa"><?php echo $translations[$lang]['bita_fi_gurgurtaa'] ?? 'Purchase and Sale'; ?></option>
                                <option value="miritti"><?php echo $translations[$lang]['miritti'] ?? 'Right'; ?></option>
                                <option value="caalbaasii"><?php echo $translations[$lang]['caalbaasii'] ?? 'Employment'; ?></option>
                            </select>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="village"><?php echo $translations[$lang]['village'] ?? 'Village'; ?></label>
                            <select id="village" name="village" class="form-control" required>
                                <option value=""><?php echo $translations[$lang]['select_village'] ?? 'Select Village'; ?></option>
                                <option value="Abba Sayyyaa"><?php echo $translations[$lang]['abba_sayyyaa'] ?? 'Abba Sayyyaa'; ?></option>
                                <option value="Soor"><?php echo $translations[$lang]['soor'] ?? 'Soor'; ?></option>
                                <option value="Taboo"><?php echo $translations[$lang]['taboo'] ?? 'Taboo'; ?></option>
                                <option value="Abbaa moolee"><?php echo $translations[$lang]['abba_moolee'] ?? 'Abbaa Moolee'; ?></option>
                                <option value="gaddisaa odaa"><?php echo $translations[$lang]['gaddisaa_odaa'] ?? 'Gaddisaa Odaa'; ?></option>
                                <option value="qolloo kormaa"><?php echo $translations[$lang]['qolloo_kormaa'] ?? 'Qolloo Kormaa'; ?></option>
                            </select>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="zone"><?php echo $translations[$lang]['zone'] ?? 'Zone'; ?></label>
                            <select id="zone" name="zone" class="form-control" required>
                                <option value=""><?php echo $translations[$lang]['select_zone'] ?? 'Select Zone'; ?></option>
                                <option value="Zone 1"><?php echo $translations[$lang]['zone_1'] ?? 'Zone 1'; ?></option>
                                <option value="Zone 2"><?php echo $translations[$lang]['zone_2'] ?? 'Zone 2'; ?></option>
                            </select>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="block_number"><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?></label>
                            <input type="text" id="block_number" name="block_number" class="form-control" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="purpose"><?php echo $translations[$lang]['purpose'] ?? 'Purpose'; ?></label>
                            <input type="text" id="purpose" name="purpose" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="plot_number"><?php echo $translations[$lang]['plot_number'] ?? 'Plot Number'; ?></label>
                            <input type="text" id="plot_number" name="plot_number" class="form-control">
                        </div>
                    </div>
                    <div class="checkbox-group">
                        <label for="has_parcel">
                            <input type="checkbox" id="has_parcel" name="has_parcel" value="1" onchange="toggleParcelField()">
                            <?php echo $translations[$lang]['has_parcel'] ?? 'Does this land have a parcel?'; ?>
                        </label>
                    </div>
                    <div id="parcel_details_section" class="document-section">
                        <h3><?php echo $translations[$lang]['parcel_details'] ?? 'Parcel Details'; ?></h3>
                        <h4><?php echo $translations[$lang]['lease_details'] ?? 'Lease Details'; ?></h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="parcel_lease_date"><?php echo $translations[$lang]['parcel_lease_date'] ?? 'Lease Agreement Date'; ?></label>
                                <input type="date" id="parcel_lease_date" name="parcel_lease_date" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="parcel_agreement_number"><?php echo $translations[$lang]['parcel_agreement_number'] ?? 'Lease Agreement Number'; ?></label>
                                <input type="text" id="parcel_agreement_number" name="parcel_agreement_number" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="parcel_lease_duration"><?php echo $translations[$lang]['parcel_lease_duration'] ?? 'Lease Duration (Years)'; ?></label>
                                <input type="text" id="parcel_lease_duration" name="parcel_lease_duration" class="form-control">
                            </div>
                        </div>
                        <h4><?php echo $translations[$lang]['xy_coordinates'] ?? 'XY Coordinates'; ?></h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="coord1_x"><?php echo $translations[$lang]['coord1_x'] ?? 'Point 1 - X'; ?></label>
                                <input type="text" id="coord1_x" name="coord1_x" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="coord1_y"><?php echo $translations[$lang]['coord1_y'] ?? 'Point 1 - Y'; ?></label>
                                <input type="text" id="coord1_y" name="coord1_y" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="coord2_x"><?php echo $translations[$lang]['coord2_x'] ?? 'Point 2 - X'; ?></label>
                                <input type="text" id="coord2_x" name="coord2_x" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="coord2_y"><?php echo $translations[$lang]['coord2_y'] ?? 'Point 2 - Y'; ?></label>
                                <input type="text" id="coord2_y" name="coord2_y" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="coord3_x"><?php echo $translations[$lang]['coord3_x'] ?? 'Point 3 - X'; ?></label>
                                <input type="text" id="coord3_x" name="coord3_x" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="coord3_y"><?php echo $translations[$lang]['coord3_y'] ?? 'Point 3 - Y'; ?></label>
                                <input type="text" id="coord3_y" name="coord3_y" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="coord4_x"><?php echo $translations[$lang]['coord4_x'] ?? 'Point 4 - X'; ?></label>
                                <input type="text" id="coord4_x" name="coord4_x" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="coord4_y"><?php echo $translations[$lang]['coord4_y'] ?? 'Point 4 - Y'; ?></label>
                                <input type="text" id="coord4_y" name="coord4_y" class="form-control">
                            </div>
                        </div>
                        <h4><?php echo $translations[$lang]['specific_land_details'] ?? 'Specific Land Details'; ?></h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="parcel_village"><?php echo $translations[$lang]['village'] ?? 'Village'; ?></label>
                                <select id="parcel_village" name="parcel_village" class="form-control">
                                    <option value=""><?php echo $translations[$lang]['select_village'] ?? 'Select Village'; ?></option>
                                    <option value="Abba Sayyyaa"><?php echo $translations[$lang]['abba_sayyyaa'] ?? 'Abba Sayyyaa'; ?></option>
                                    <option value="Soor"><?php echo $translations[$lang]['soor'] ?? 'Soor'; ?></option>
                                    <option value="Taboo"><?php echo $translations[$lang]['taboo'] ?? 'Taboo'; ?></option>
                                    <option value="Abbaa moolee"><?php echo $translations[$lang]['abba_moolee'] ?? 'Abbaa Moolee'; ?></option>
                                    <option value="gaddisaa odaa"><?php echo $translations[$lang]['gaddisaa_odaa'] ?? 'Gaddisaa Odaa'; ?></option>
                                    <option value="qolloo kormaa"><?php echo $translations[$lang]['qolloo_kormaa'] ?? 'Qolloo Kormaa'; ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="parcel_block_number"><?php echo $translations[$lang]['block_number'] ?? 'Block Number'; ?></label>
                                <input type="text" id="parcel_block_number" name="parcel_block_number" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="parcel_number"><?php echo $translations[$lang]['parcel_number'] ?? 'Parcel Number'; ?></label>
                                <input type="text" id="parcel_number" name="parcel_number" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="parcel_land_grade"><?php echo $translations[$lang]['land_grade'] ?? 'Land Grade'; ?></label>
                                <select id="parcel_land_grade" name="parcel_land_grade" class="form-control">
                                    <option value=""><?php echo $translations[$lang]['select_option'] ?? 'Select Option'; ?></option>
                                    <option value="sad 1ffaa"><?php echo $translations[$lang]['sad_1ffaa'] ?? 'Grade 1'; ?></option>
                                    <option value="sad 2ffaa"><?php echo $translations[$lang]['sad_2ffaa'] ?? 'Grade 2'; ?></option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="parcel_land_area"><?php echo $translations[$lang]['land_area'] ?? 'Land Area (m²)'; ?></label>
                                <input type="text" id="parcel_land_area" name="parcel_land_area" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="parcel_land_service"><?php echo $translations[$lang]['land_service'] ?? 'Land Service'; ?></label>
                                <select id="parcel_land_service" name="parcel_land_service" class="form-control">
                                    <option value=""><?php echo $translations[$lang]['select_option'] ?? 'Select Option'; ?></option>
                                    <option value="lafa daldalaa"><?php echo $translations[$lang]['commercial'] ?? 'Commercial'; ?></option>
                                    <option value="lafa mana jireenyaa"><?php echo $translations[$lang]['residential'] ?? 'Residential'; ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="parcel_registration_number"><?php echo $translations[$lang]['registration_number'] ?? 'Registration Number'; ?></label>
                                <input type="text" id="parcel_registration_number" name="parcel_registration_number" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="building_height_allowed"><?php echo $translations[$lang]['building_height_allowed'] ?? 'Allowed Building Height (Meters)'; ?></label>
                                <input type="text" id="building_height_allowed" name="building_height_allowed" class="form-control">
                            </div>
                            <div class="form-group"></div>
                        </div>
                        <h4><?php echo $translations[$lang]['personnel_details'] ?? 'Personnel Details'; ?></h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="prepared_by_name"><?php echo $translations[$lang]['prepared_by_name'] ?? 'Prepared By: Full Name'; ?></label>
                                <input type="text" id="prepared_by_name" name="prepared_by_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="prepared_by_role"><?php echo $translations[$lang]['prepared_by_role'] ?? 'Prepared By: Role'; ?></label>
                                <input type="text" id="prepared_by_role" name="prepared_by_role" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="approved_by_name"><?php echo $translations[$lang]['approved_by_name'] ?? 'Approved By: Full Name'; ?></label>
                                <input type="text" id="approved_by_name" name="approved_by_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="approved_by_role"><?php echo $translations[$lang]['approved_by_role'] ?? 'Approved By: Role'; ?></label>
                                <input type="text" id="approved_by_role" name="approved_by_role" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="authorized_by_name"><?php echo $translations[$lang]['authorized_by_name'] ?? 'Authorized By: Full Name'; ?></label>
                                <input type="text" id="authorized_by_name" name="authorized_by_name" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="authorized_by_role"><?php echo $translations[$lang]['authorized_by_role'] ?? 'Authorized By: Role'; ?></label>
                                <input type="text" id="authorized_by_role" name="authorized_by_role" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="effective_date"><?php echo $translations[$lang]['effective_date'] ?? 'Effective Date'; ?></label>
                            <input type="date" id="effective_date" name="effective_date" class="form-control" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="group_category"><?php echo $translations[$lang]['group_category'] ?? 'Group Category'; ?></label>
                            <select id="group_category" name="group_category" class="form-control">
                                <option value=""><?php echo $translations[$lang]['select_option'] ?? 'Select Option'; ?></option>
                                <option value="Garee 1"><?php echo $translations[$lang]['group_1'] ?? 'Group 1'; ?></option>
                                <option value="Garee 2"><?php echo $translations[$lang]['group_2'] ?? 'Group 2'; ?></option>
                            </select>
                        </div>
                        <div class="form-group"></div>
                    </div>
                </div>
                <div class="form-section">
                    <h2><?php echo $translations[$lang]['neighbor_information'] ?? 'Neighbor Information'; ?></h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="neighbor_east"><?php echo $translations[$lang]['east_neighbor'] ?? 'East Neighbor'; ?></label>
                            <input type="text" id="neighbor_east" name="neighbor_east" class="form-control" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="neighbor_west"><?php echo $translations[$lang]['west_neighbor'] ?? 'West Neighbor'; ?></label>
                            <input type="text" id="neighbor_west" name="neighbor_west" class="form-control" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="neighbor_south"><?php echo $translations[$lang]['south_neighbor'] ?? 'South Neighbor'; ?></label>
                            <input type="text" id="neighbor_south" name="neighbor_south" class="form-control" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="neighbor_north"><?php echo $translations[$lang]['north_neighbor'] ?? 'North Neighbor'; ?></label>
                            <input type="text" id="neighbor_north" name="neighbor_north" class="form-control" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h2><?php echo $translations[$lang]['document_uploads'] ?? 'Document Uploads'; ?></h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="id_front"><?php echo $translations[$lang]['id_front'] ?? 'ID Front'; ?></label>
                            <input type="file" id="id_front" name="id_front" class="form-control" accept="image/*" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                        <div class="form-group">
                            <label for="id_back"><?php echo $translations[$lang]['id_back'] ?? 'ID Back'; ?></label>
                            <input type="file" id="id_back" name="id_back" class="form-control" accept="image/*" required>
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                        </div>
                    </div>
                    <div id="dhaala_docs" class="document-section">
                        <h3><?php echo $translations[$lang]['dhaala_docs'] ?? 'Documents Required for Inheritance'; ?></h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="xalayaa_miritii"><?php echo $translations[$lang]['xalayaa_miritii'] ?? 'Xalayaa Miritii'; ?></label>
                                <input type="file" id="xalayaa_miritii" name="xalayaa_miritii" class="form-control" accept="application/pdf,image/*">
                            </div>
                            <div class="form-group">
                                <label for="nagaee_gibiraa"><?php echo $translations[$lang]['nagaee_gibiraa'] ?? 'Naga\'ee Gibiraa'; ?></label>
                                <input type="file" id="nagaee_gibiraa" name="nagaee_gibiraa" class="form-control" accept="application/pdf,image/*">
                            </div>
                        </div>
                    </div>
                    <div id="lease_land_docs" class="document-section">
                        <h3><?php echo $translations[$lang]['lease_land_docs'] ?? 'Documents Required for Lease'; ?></h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="waligaltee_lease"><?php echo $translations[$lang]['waligaltee_lease'] ?? 'Lease Agreement'; ?></label>
                                <input type="file" id="waligaltee_lease" name="waligaltee_lease" class="form-control" accept="application/pdf,image/*">
                            </div>
                            <div class="form-group">
                                <label for="tax_receipt"><?php echo $translations[$lang]['tax_receipt'] ?? 'Tax Receipt'; ?></label>
                                <input type="file" id="tax_receipt" name="tax_receipt" class="form-control" accept="application/pdf,image/*">
                            </div>
                        </div>
                    </div>
                    <div id="miritti_docs" class="document-section">
                        <h3><?php echo $translations[$lang]['miritti_docs'] ?? 'Documents Required for Right'; ?></h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="miriti_paper"><?php echo $translations[$lang]['miriti_paper'] ?? 'Right Document'; ?></label>
                                <input type="file" id="miriti_paper" name="miriti_paper" class="form-control" accept="application/pdf,image/*">
                            </div>
                            <div class="form-group"></div>
                        </div>
                    </div>
                    <div id="caalbaasii_docs" class="document-section">
                        <h3><?php echo $translations[$lang]['caalbaasii_docs'] ?? 'Documents Required for Employment'; ?></h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="caalbaasii_agreement"><?php echo $translations[$lang]['caalbaasii_agreement'] ?? 'Employment Agreement'; ?></label>
                                <input type="file" id="caalbaasii_agreement" name="caalbaasii_agreement" class="form-control" accept="application/pdf,image/*">
                            </div>
                            <div class="form-group"></div>
                        </div>
                    </div>
                    <div id="bita_fi_gurgurtaa_docs" class="document-section">
                        <h3><?php echo $translations[$lang]['bita_fi_gurgurtaa_docs'] ?? 'Documents Required for Purchase and Sale'; ?></h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bita_fi_gurgurtaa_agreement"><?php echo $translations[$lang]['bita_fi_gurgurtaa_agreement'] ?? 'Purchase and Sale Agreement'; ?></label>
                                <input type="file" id="bita_fi_gurgurtaa_agreement" name="bita_fi_gurgurtaa_agreement" class="form-control" accept="application/pdf,image/*">
                            </div>
                            <div class="form-group">
                                <label for="bita_fi_gurgurtaa_receipt"><?php echo $translations[$lang]['bita_fi_gurgurtaa_receipt'] ?? 'Purchase and Sale Receipt'; ?></label>
                                <input type="file" id="bita_fi_gurgurtaa_receipt" name="bita_fi_gurgurtaa_receipt" class="form-control" accept="application/pdf,image/*">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <h2><?php echo $translations[$lang]['photo_uploads'] ?? 'Photo Uploads'; ?></h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="owner_photo"><?php echo $translations[$lang]['owner_photo'] ?? 'Owner Photo'; ?></label>
                            <input type="file" id="owner_photo" name="owner_photo" class="form-control" accept="image/*" required onchange="previewPhoto(event, 'owner_preview')">
                            <div class="invalid-feedback"><?php echo $translations[$lang]['required_field'] ?? 'This field is required.'; ?></div>
                            <div class="photo-preview">
                                <img id="owner_preview" src="placeholder.jpg" alt="<?php echo $translations[$lang]['owner_photo'] ?? 'Owner Photo'; ?>">
                            </div>
                        </div>
                        <div class="form-group"></div>
                        <div class="form-group"></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group text-center" style="flex-basis: 100%;">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-save"></i> <?php echo $translations[$lang]['submit_registration'] ?? 'Submit Registration'; ?></button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fas fa-undo"></i> <?php echo $translations[$lang]['reset'] ?? 'Reset'; ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDocumentSection() {
            const landType = document.getElementById('land_type').value;
            const sections = {
                dhaala: document.getElementById('dhaala_docs'),
                lease_land: document.getElementById('lease_land_docs'),
                bita_fi_gurgurtaa: document.getElementById('bita_fi_gurgurtaa_docs'),
                miritti: document.getElementById('miritti_docs'),
                caalbaasii: document.getElementById('caalbaasii_docs')
            };
            Object.values(sections).forEach(section => {
                if (section) {
                    section.classList.remove('show');
                    section.querySelectorAll('input, select, textarea').forEach(input => input.required = false);
                }
            });
            if (sections[landType]) {
                sections[landType].classList.add('show');
                sections[landType].querySelectorAll('input, select, textarea').forEach(input => input.required = true);
            }
        }

        function toggleParcelField() {
            const hasParcel = document.getElementById('has_parcel').checked;
            const parcelSection = document.getElementById('parcel_details_section');
            const parcelInputs = parcelSection.querySelectorAll('input, select, textarea');
            if (hasParcel) {
                parcelSection.classList.add('show');
                parcelInputs.forEach(input => input.required = true);
            } else {
                parcelSection.classList.remove('show');
                parcelInputs.forEach(input => {
                    input.required = false;
                    if (input.type !== 'file' && input.type !== 'checkbox') {
                        input.value = '';
                    }
                    if (input.tagName === 'SELECT') {
                        input.selectedIndex = 0;
                    }
                });
            }
        }

        function previewPhoto(event, previewId) {
            const reader = new FileReader();
            const preview = document.getElementById(previewId);
            reader.onload = () => preview.src = reader.result;
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            } else {
                preview.src = 'placeholder.jpg';
            }
        }

        function resetForm() {
            const form = document.getElementById('landForm');
            form.reset();
            document.getElementById('owner_preview').src = 'placeholder.jpg';
            toggleDocumentSection();
            toggleParcelField();
            form.querySelectorAll('.is-invalid').forEach(input => input.classList.remove('is-invalid'));
        }

        document.getElementById('landForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            if (!form.checkValidity()) {
                e.stopPropagation();
                form.classList.add('was-validated');
                return;
            }

            const confirmed = await Swal.fire({
                title: '<?php echo $translations[$lang]['confirm_submission'] ?? 'Confirm Submission'; ?>',
                text: '<?php echo $translations[$lang]['submit_form_confirm'] ?? 'Are you sure you want to submit the form?'; ?>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<?php echo $translations[$lang]['yes_submit'] ?? 'Yes, submit'; ?>',
                cancelButtonText: '<?php echo $translations[$lang]['cancel'] ?? 'Cancel'; ?>'
            });

            if (!confirmed.isConfirmed) return;

            const formData = new FormData(form);
            try {
                const response = await fetch('submit_form.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || '<?php echo $translations[$lang]['submission_failed'] ?? 'Submission failed'; ?>');
                logAction('form_submission_success', 'Land registration form submitted successfully', 'info');
                Swal.fire({
                    icon: 'success',
                    title: '<?php echo $translations[$lang]['success'] ?? 'Success'; ?>',
                    text: result.message || '<?php echo $translations[$lang]['registration_successful'] ?? 'Registration successful!'; ?>',
                    confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>'
                }).then(() => {
                    resetForm();
                });
            } catch (error) {
                logAction('form_submission_error', 'Land registration form submission failed: ' + error.message, 'error');
                Swal.fire({
                    icon: 'error',
                    title: '<?php echo $translations[$lang]['error'] ?? 'Error'; ?>',
                    text: error.message,
                    confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>'
                });
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            toggleDocumentSection();
            toggleParcelField();
            <?php if (isset($_SESSION['success_message'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: '<?php echo $translations[$lang]['success'] ?? 'Success'; ?>',
                    text: '<?php echo htmlspecialchars($_SESSION['success_message']); ?>',
                    confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>'
                }).then(() => {
                    resetForm();
                });
                <?php unset($_SESSION['success_message']); ?>
            <?php elseif (isset($_SESSION['error_message'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: '<?php echo $translations[$lang]['error'] ?? 'Error'; ?>',
                    text: '<?php echo htmlspecialchars($_SESSION['error_message']); ?>',
                    confirmButtonText: '<?php echo $translations[$lang]['ok'] ?? 'OK'; ?>'
                });
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
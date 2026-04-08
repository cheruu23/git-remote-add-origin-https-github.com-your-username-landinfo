<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land Tax Calculator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        form {
            max-width: 500px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
        }

        h1 {
            text-align: center;
        }

        label {
            display: block;
            margin: 10px 0 5px;
        }

        input,
        button {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background: #007bff;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }

        .result {
            background: #f4f4f4;
            padding: 20px;
            margin-top: 20px;
            border-radius: 5px;
        }

        .hidden {
            display: none;
        }

        .receipt {
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            max-width: 400px;
            margin: 20px auto;
        }

        .receipt h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .receipt p {
            margin: 10px 0;
        }

        .receipt ul {
            list-style-type: none;
            padding: 0;
        }

        .receipt ul li {
            margin: 10px 0;
        }

        .print-button {
            text-align: center;
            margin-top: 20px;
        }

        .print-button button {
            background: #28a745;
        }

        .print-button button:hover {
            background: #218838;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .receipt,
            .receipt * {
                visibility: visible;
            }

            .receipt {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
            }

            .print-button {
                display: none;
            }
        }
    </style>
</head>

<body>
    <h1>Land Tax Calculator</h1>
    <form id="taxForm">
        <label for="parcel_id">Parcel ID:</label>
        <input type="number" name="parcel_id" id="parcel_id" placeholder="Enter Parcel ID" required>
        <button type="submit">Calculate Tax</button>
    </form>

    <!-- Result Section -->
    <div id="resultSection" class="result hidden">
        <div class="receipt">
            <h2>Land Tax Payment Receipt</h2>
            <p><strong>Tax Amount:</strong> <span id="taxAmount"></span></p>
            <p><strong>Payment Details:</strong></p>
            <ul>
                <li><strong>Bank Name:</strong> Commercial Bank of Ethiopia</li>
                <li><strong>Account Name:</strong> Land Tax Office</li>
                <li><strong>Account Number:</strong> 100020003000</li>
                <li><strong>Reference:</strong> Land Tax - Parcel ID <span id="referenceId"></span></li>
            </ul>
            <p>Please pay the tax amount via bank transfer using the details above.</p>
            <p>After payment, contact the Land Tax Office for confirmation.</p>
        </div>
        <div class="print-button">
            <button onclick="window.print()">Print Receipt</button>
        </div>
    </div>

    <script>
        // Handle form submission
        const form = document.getElementById('taxForm');
        const resultSection = document.getElementById('resultSection');
        const taxAmount = document.getElementById('taxAmount');
        const referenceId = document.getElementById('referenceId');

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            // Get Parcel ID
            const parcelId = document.getElementById('parcel_id').value;
            referenceId.textContent = parcelId;

            // Show result section
            resultSection.classList.remove('hidden');
            taxAmount.textContent = 'Calculating...';

            // Fetch land details and calculate tax
            fetch('calculate_tax.php?parcel_id=' + parcelId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        taxAmount.textContent = data.error;
                    } else {
                        taxAmount.textContent = data.taxAmount + " ETB";
                    }
                })
                .catch(error => {
                    taxAmount.textContent = 'Error calculating tax. Please try again.';
                    console.error(error);
                });
        });
    </script>
</body>

</html>
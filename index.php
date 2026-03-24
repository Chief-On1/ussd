<?php

// Get data from Africa's Talking
$sessionId   = $_POST["sessionId"] ?? ''; 
$serviceCode = $_POST["serviceCode"] ?? ''; 
$phoneNumber = $_POST["phoneNumber"] ?? ''; 
$text        = $_POST["text"] ?? ''; 

// Split user input
$input = explode("*", $text);

// Default response
$response = "";

/*
|--------------------------------------------------------------------------
| USSD MENU LOGIC
|--------------------------------------------------------------------------
*/

if ($text == "") {

    $response  = "CON Welcome to IK Digitals Services\n";
    $response .= "1. Purchase Data Bundles\n";
    $response .= "2. Check Account Status\n";
    $response .= "3. Contact Support";

} elseif ($input[0] == "1") {

    // Step 1: Choose network
    if (!isset($input[1])) {
        $response  = "CON Please Select Your Network Provider\n";
        $response .= "1. MTN\n";
        $response .= "2. Telecel\n";
        $response .= "3. AirtelTigo";
    }

    // Step 2: Show bundles
    elseif (isset($input[1]) && !isset($input[2])) {

        $network = $input[1];

        switch ($network) {
            case "1": $networkName = "MTN"; break;
            case "2": $networkName = "Telecel"; break;
            case "3": $networkName = "AirtelTigo"; break;
            default:
                echo "END Invalid option selected";
                exit;
        }

        $bundles = getBundles($networkName);

        if (empty($bundles)) {
            $response = "END No data bundles available.";
        } else {
            $response = "CON Select a Data Bundle\n";

            foreach ($bundles as $index => $bundle) {
                $num = $index + 1;

                // ✅ FIXED STRING BUG
                $response .= $num . ". " 
                    . $bundle['name'] 
                    . " - GHS " 
                    . number_format($bundle['selling_price'], 2) 
                    . "\n";
            }
        }
    }

    // Step 3: Purchase
    elseif (isset($input[2])) {

        $bundleIndex = (int)$input[2] - 1;

        $networkMap = ["1" => "MTN", "2" => "Telecel", "3" => "AirtelTigo"];
        $networkName = $networkMap[$input[1]] ?? "MTN";

        $bundles = getBundles($networkName);

        if (!isset($bundles[$bundleIndex])) {
            echo "END Invalid bundle selection";
            exit;
        }

        $selectedBundle = $bundles[$bundleIndex];

        $userPrice   = $selectedBundle['selling_price'];
        $actualPrice = $selectedBundle['actual_price'];

        // 🔥 (Later: Add MoMo payment here)

        $result = buyData($phoneNumber, $selectedBundle['id']);

        if ($result && isset($result['status']) && $result['status'] == "success") {

            $profit = $userPrice - $actualPrice;

            $response = "END Transaction Successful!\n";
            $response .= "Data activated\n";
            $response .= "Amount: GHS " . number_format($userPrice, 2);

        } else {
            $response = "END Transaction failed. Try again.";
        }
    }

} elseif ($input[0] == "2") {

    $response = "END Phone: $phoneNumber\nContact support for details.";

} elseif ($input[0] == "3") {

    $response = "END Call: +233257906577";

} else {

    $response = "END Invalid option";
}

/*
|--------------------------------------------------------------------------
| FETCH DATA BUNDLES
|--------------------------------------------------------------------------
*/

function getBundles($network) {

    $apiKey = getenv("TOPPILY_API_KEY");

    $networkMap = [
        "MTN" => "MTN",
        "Telecel" => "VODAFONE",
        "AirtelTigo" => "AIRTELTIGO"
    ];

    $network_id = $networkMap[$network] ?? "MTN";

    $url = "https://agent.toppily.com/api/v1/data/types";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ]
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($result, true);

    $bundles = [];

    if (isset($response['data'])) {
        foreach ($response['data'] as $item) {

            if (strtoupper($item['network']) == $network_id) {

                $actualPrice = (float)$item['price'];

                // ✅ 20% PROFIT
                $sellingPrice = $actualPrice * 1.20;

                $bundles[] = [
                    "id" => $item['id'],
                    "name" => $item['name'],
                    "actual_price" => $actualPrice,
                    "selling_price" => round($sellingPrice, 2)
                ];
            }
        }
    }

    return $bundles;
}

/*
|--------------------------------------------------------------------------
| BUY DATA
|--------------------------------------------------------------------------
*/

function buyData($phone, $bundleId) {

    $apiKey = getenv("TOPPILY_API_KEY");

    $url = "https://agent.toppily.com/api/v1/data";

    $payload = [
        "phone" => $phone,
        "plan_id" => $bundleId
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ]
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

// Output response
header('Content-Type: text/plain');
echo $response;

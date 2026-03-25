<?php

header('Content-Type: text/plain');

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

        $result = buyData($phoneNumber, $selectedBundle);
        
        if if ($result && isset($result['success']) && $result['success'] === true) {

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

    $response = "END WhatsApp/Call: +233257906577\nEmail: ikdennisisgreat@gmail.com";

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

    $url = "https://agent.toppily.com/api/v1/fetch-data-packages";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "api-key: $apiKey",
            "Accept: application/json"
        ]
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($result, true);

    $bundles = [];

    if (is_array($response)) {

        foreach ($response as $item) {

            if (strtoupper($item['network']) == strtoupper($network)) {

                $actualPrice = (float)$item['console_price'];

                // ✅ Add 20% profit
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

function buyData($phone, $bundle) {

    $apiKey = getenv("TOPPILY_API_KEY");

    $url = "https://agent.toppily.com/api/v1/buy-data-package";

    $reference = "txn_" . time();

    $payload = [
        "recipient_msisdn" => $phone,
        "network_id" => getNetworkId($bundle['name']),
        "shared_bundle" => extractVolume($bundle['name']),
        "incoming_api_ref" => $reference
    ];

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "api-key: $apiKey",
            "Content-Type: application/json"
        ]
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

    // Helper functions
function getNetworkId($name) {
    if (stripos($name, "MTN") !== false) return 1;
    if (stripos($name, "Vodafone") !== false) return 2;
    if (stripos($name, "Airtel") !== false) return 3;
    return 1;
}

function extractVolume($name) {
    preg_match('/(\d+)/', $name, $matches);
    return isset($matches[1]) ? (int)$matches[1] * 1024 : 1024;
}

// Output response
echo $response;

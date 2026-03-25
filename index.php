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

    $response  = "CON Welcome to IK Digitals Services\n\n";
    $response .= "1. Purchase Data Bundles\n";
    $response .= "2. Check Status\n";
    $response .= "3. Contact Support";

} elseif ($input[0] == "1") {

    // STEP 0: WARNING SCREEN
    if (!isset($input[1])) {
        $response  = "CON Policy Notice\n\n";
        $response .= "Bundles may take 5 mins to 24 hrs to reflect.\n";
        $response .= "Proceed?\n\n";
        $response .= "1. Yes\n";
        $response .= "2. No";
    }

    // STEP 1: HANDLE WARNING RESPONSE
    elseif ($input[1] == "2") {
        $response = "END Thanks for visiting!\n\nYou can visit our website for more: https://ikdigitals.tplstores.com";
    }

    // STEP 2: SELECT NETWORK
    elseif ($input[1] == "1" && !isset($input[2])) {
        $response  = "CON Select Network\n\n";
        $response .= "1. MTN\n";
        $response .= "2. Telecel\n";
        $response .= "3. AirtelTigo";
    }

    // STEP 3: SHOW BUNDLES
    elseif ($input[1] == "1" && isset($input[2]) && !isset($input[3])) {

        $networkChoice = $input[2];

        switch ($networkChoice) {
            case "1": $networkName = "MTN"; break;
            case "2": $networkName = "Telecel"; break;
            case "3": $networkName = "AirtelTigo"; break;
            default:
                echo "END Invalid network";
                exit;
        }

        $bundles = getBundles($networkName);

        if (empty($bundles)) {
            $response = "END No data bundles available.";
        } else {
            $response = "CON Select Bundle\n\n";

            foreach ($bundles as $index => $bundle) {
                $num = $index + 1;
                $response .= $num . ". "
                    . $bundle['name']
                    . " - GHS "
                    . number_format($bundle['selling_price'], 2)
                    . "\n";
            }
        }
    }

    // STEP 4: PURCHASE
    elseif ($input[1] == "1" && isset($input[3])) {

        $bundleIndex = (int)$input[3] - 1;

        $networkMap = ["1" => "MTN", "2" => "Telecel", "3" => "AirtelTigo"];
        $networkName = $networkMap[$input[2]] ?? "MTN";

        $bundles = getBundles($networkName);

        if (!isset($bundles[$bundleIndex])) {
            echo "END Invalid selection";
            exit;
        }

        $selectedBundle = $bundles[$bundleIndex];

        $result = buyData($phoneNumber, $selectedBundle);

        if ($result && isset($result['success']) && $result['success'] === true) {

            $response = "END Success!\n";
            $response .= "Bundle activated\n";
            $response .= "GHS " . number_format($selectedBundle['selling_price'], 2);

        } else {
            $response = "END Failed. Try again.";
        }
    } 
} elseif ($input[0] == "2") {

    $response = 'END Kindly visit https://ikdigitals.tplstores.com, at the top left conner, click on "Track order".\n Select purchased date and input the phone number.';

} elseif ($input[0] == "3") {

    $response = "END WhatsApp/SMS/Call: +233257906577\nEmail: ikdennisisgreat@gmail.com";

} else {

    $response = "END Invalid option";
}

/*
|--------------------------------------------------------------------------
| FETCH DATA BUNDLES
|--------------------------------------------------------------------------
*/
function getBundles($network) {

    $apiKey = "dk_m7NNlH3qkEUyjgeFnyjrYJDf2vXThj3u";

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

    if (!$response || !is_array($response)) {
        return [];
    }

    $networkMap = [
        "MTN" => "MTN",
        "Telecel" => "VODAFONE",
        "AirtelTigo" => "AIRTELTIGO"
    ];

    $selectedNetwork = $networkMap[$network] ?? "MTN";

    $bundles = [];

    foreach ($response as $item) {

        if ($item['status'] !== "In Stock") continue;

        if (strtoupper($item['network']) == $selectedNetwork) {

            $actualPrice = (float)$item['console_price'];
            $sellingPrice = $actualPrice * 1.20;

            $bundles[] = [
                "id" => $item['id'],
                "name" => $item['name'],
                "actual_price" => $actualPrice,
                "selling_price" => round($sellingPrice, 2)
            ];
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

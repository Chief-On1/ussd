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
|----------------------------------------------------------------------|
| USSD MENU LOGIC                                                  |
|----------------------------------------------------------------------|
*/

if ($text == "") {

    // Main menu - Professional Greeting
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
        $response = "END No data bundles are currently available. Please try again later.";
    } else {
        $response = "CON Please Select a Data Bundle\n";

        foreach ($bundles as $index => $bundle) {
            $num = $index + 1;
            $response .= "$num. {
                $bundle['name']} - GHS {
                $bundle['selling_price']}\n";
        }
    }
}

    // Step 3: Confirm purchase
    elseif (isset($input[2])) {

    $bundleIndex = (int)$input[2] - 1;
    
    // Get bundles again to verify selection
    $networkMap = ["1" => "MTN", "2" => "Telecel", "3" => "AirtelTigo"];
    $networkName = $networkMap[$input[1]] ?? "MTN";
    $bundles = getBundles($networkName);

    if (!isset($bundles[$bundleIndex])) {
        echo "END Invalid bundle selection. Please try again.";
        exit;
    }

    $selectedBundle = $bundles[$bundleIndex];

    // ✅ SELLING PRICE (what user should pay)
    $userPrice = $selectedBundle['selling_price'];

    // ✅ ACTUAL PRICE (what you pay Toppily)
    $actualPrice = $selectedBundle['actual_price'];

    // 🔥 HERE YOU SHOULD:
    // 1. Charge user (MoMo / Payment)
    // 2. THEN call Toppily

    // For now (no payment yet)
    $result = buyData($phoneNumber, $selectedBundle['id']);

    if ($result && isset($result['status']) && $result['status'] == "success") {

        $profit = $userPrice - $actualPrice;

        $response = "END Transaction Successful!\n";
        $response .= "Data bundle activated.\n";
        $response .= "Amount: GHS " . number_format($userPrice, 2);

        // 🔥 You can log profit here (VERY IMPORTANT)
        // saveTransaction($phoneNumber, $actualPrice, $userPrice, $profit);

    } else {
        $response = "END Transaction failed. Please try again or contact support.";
    }
}

} elseif ($input[0] == "2") {

    $response = "END Account Status\nPhone: $phoneNumber\nFor more details, contact our support team.";

} elseif ($input[0] == "3") {

    $response = "END Support Contact\nPhone: +233 257 906 577\nEmail: support@ikdigitals.com";

} else {

    $response = "END Invalid option selected. Please start over.";
}

/*
|----------------------------------------------------------------------|
| FUNCTION TO GET BUNDLES                                             |
|----------------------------------------------------------------------|
*/

function getBundles($network) {

    $apiKey = "dk_m7NNlH3qkEUyjgeFnyjrYJDf2vXThj3u";

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

                // ✅ ADD 20% PROFIT
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

function buyData($phone, $bundleId) {

    $apiKey = "dk_m7NNlH3qkEUyjgeFnyjrYJDf2vXThj3u";

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
header('Content-type: text/plain');
echo $response;
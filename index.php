<?php
// Other parts of your PHP code

// Example of improved formatting for bundles
foreach ($bundles as $bundle) {
    echo "Bundle Name: " . $bundle['name'] . " - Price: " . number_format($bundle['selling_price'], 2) . "\n";
}

// Use getenv for API key assignments
$apiKey = getenv("TOPPILY_API_KEY");

// Other parts of your PHP code

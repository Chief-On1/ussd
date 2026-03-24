<?php
// Other PHP code above

function getBundles() {
    // Other code...
    $apiKey = getenv("TOPPILY_API_KEY"); // Changed from hardcoded key
    // Other code...
}

function buyData($data) {
    // Other code...
    $apiKey = getenv("TOPPILY_API_KEY"); // Changed from hardcoded key
    // Other code...
}

// Improved formatting for prices extraction in the foreach loop
foreach ($bundles as $bundle) {
    echo "<div class='bundle'>";
    echo "<h2>{$bundle['name']}</h2>";
    echo "<p>Price: " . number_format($bundle['price'], 2) . "</p>";
    echo "</div>";
}

// Other PHP code below
?>
<?php
// Example plain password you think is correct
$plain = "sarala@22";  

// Copy one hashed password directly from your database (users or patients table)
$hash = '$2y$10$ArZdaWAEX6syuOcMvlC3yegp5CZMFN07XQk/GDaaE4T';  

if (password_verify($plain, $hash)) {
    echo "✅ Password matches the hash";
} else {
    echo "❌ Password does not match";
}
?>
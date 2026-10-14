<?php
/**
 * Trust System Database Setup Script
 * Run this file once to create all necessary tables
 */

$mysqli = new mysqli('localhost', 'root', '', 'aleppogift');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Reading SQL file...\n";
$sql = file_get_contents(__DIR__ . '/database/setup_trust_system.sql');

echo "Executing SQL statements...\n";
if ($mysqli->multi_query($sql)) {
    do {
        if ($result = $mysqli->store_result()) {
            while ($row = $result->fetch_assoc()) {
                print_r($row);
            }
            $result->free();
        }
        if ($mysqli->more_results()) {
            echo ".";
        }
    } while ($mysqli->next_result());
}

if ($mysqli->errno) {
    echo "\nError: " . $mysqli->error . "\n";
} else {
    echo "\n✅ Trust system database setup complete!\n";
    echo "Tables created: product_reviews, testimonials, trust_badges, customer_photos, success_stories, social_proof_stats\n";
}

$mysqli->close();
?>

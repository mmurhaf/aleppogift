<?php
/**
 * FPDF Setup and Verification Script
 * This script ensures FPDF library is available for invoice generation
 */

echo "🔍 Checking FPDF library availability...\n";

$vendor_dir = __DIR__ . '/vendor';
$fpdf_dir = $vendor_dir . '/fpdf';
$fpdf_file = $fpdf_dir . '/fpdf.php';

// Create vendor directory if it doesn't exist
if (!is_dir($vendor_dir)) {
    echo "📁 Creating vendor directory...\n";
    mkdir($vendor_dir, 0755, true);
}

// Create fpdf directory if it doesn't exist
if (!is_dir($fpdf_dir)) {
    echo "📁 Creating fpdf directory...\n";
    mkdir($fpdf_dir, 0755, true);
}

// Check if FPDF file exists and is readable
if (file_exists($fpdf_file) && is_readable($fpdf_file)) {
    echo "✅ FPDF library found at: $fpdf_file\n";
    
    // Verify FPDF can be loaded
    try {
        require_once $fpdf_file;
        if (class_exists('FPDF')) {
            echo "✅ FPDF library loaded successfully\n";
            echo "✅ FPDF version: " . (defined('FPDF::VERSION') ? FPDF::VERSION : 'Unknown') . "\n";
            echo "✅ All checks passed - FPDF is ready for use\n";
        } else {
            echo "❌ FPDF class not found after loading file\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "❌ Error loading FPDF: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "❌ FPDF library not found at: $fpdf_file\n";
    echo "🔧 You need to download FPDF library manually:\n";
    echo "   1. Download FPDF from: http://www.fpdf.org/en/download.php\n";
    echo "   2. Extract the fpdf.php file to: $fpdf_file\n";
    echo "   3. Create font directory at: $fpdf_dir/font\n";
    echo "   4. Run this script again to verify\n";
    
    // Try to create a basic directory structure
    if (!is_dir($fpdf_dir . '/font')) {
        mkdir($fpdf_dir . '/font', 0755, true);
        echo "📁 Created font directory at: $fpdf_dir/font\n";
    }
    
    exit(1);
}

echo "\n🎉 FPDF setup verification completed successfully!\n";
?>

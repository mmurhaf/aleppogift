<?php
header('Content-Type: application/json');

$country = $_GET['country'] ?? '';
$country = strtolower(trim($country));

$cities = [
    'united arab emirates' => ['Abu Dhabi', 'Dubai', 'Sharjah', 'Ajman', 'Al Ain', 'Fujairah', 'Ras Al Khaimah'],
    'oman' => ['Muscat', 'Salalah', 'Sohar', 'Nizwa'],
    'qatar' => ['Doha', 'Al Rayyan'],
    'saudi arabia' => ['Riyadh', 'Jeddah', 'Dammam', 'Khobar', 'Mecca', 'Medina'],
    'kuwait' => ['Kuwait City', 'Hawalli'],
    'bahrain' => ['Manama', 'Muharraq'],
    'france' => ['Paris', 'Lyon', 'Marseille'],
    'germany' => ['Berlin', 'Munich', 'Frankfurt'],
    'italy' => ['Rome', 'Milan', 'Naples'],
    'spain' => ['Madrid', 'Barcelona', 'Valencia'],
    'united kingdom' => ['London', 'Manchester', 'Birmingham'],
    'usa' => ['New York', 'Los Angeles', 'Chicago'],
];

echo json_encode($cities[$country] ?? []);

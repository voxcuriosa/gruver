<?php
/**
 * Script to extract all sites with links to skiensatlas.org
 */

$jsonData = file_get_contents('full_data.json');
$data = json_decode($jsonData, true);

if (!$data || !isset($data['features'])) {
    die("Error: Could not decode full_data.json\n");
}

$results = [];
foreach ($data['features'] as $feature) {
    if (!isset($feature['properties']))
        continue;
    $props = $feature['properties'];
    $name = $props['name'] ?? 'Unknown';
    $links = $props['links'] ?? [];

    foreach ($links as $link) {
        if (strpos($link, 'skiensatlas.org') !== false) {
            // Clean up trailing ]]> if present (found in manual inspection)
            $cleanLink = str_replace(']]>', '', $link);
            $results[] = [
                'name' => $name,
                'link' => $cleanLink
            ];
        }
    }
}

// Write to text file
$output = "--- Sites with links to skiensatlas.org ---\n";
$output .= "Total found: " . count($results) . "\n\n";

foreach ($results as $item) {
    $output .= "[{$item['name']}] -> {$item['link']}\n";
}

file_put_contents('skiensatlas_links.txt', $output);
echo "Extracted " . count($results) . " links to skiensatlas_links.txt\n";

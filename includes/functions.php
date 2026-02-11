<?php
// includes/functions.php

function redirect($url) {
    header("Location: $url");
    exit;
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect(BASE_URL . 'login.php');
    }
}

function is_admin() {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        die("Access denied. Admin only.");
    }
}

/**
 * Creates a URL-friendly slug from a string
 */
function createSlug($str) {
    // Convert to lowercase
    $slug = strtolower($str);
    
    // Remove accents and special characters
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
    
    // Replace non-alphanumeric characters (except spaces/hyphens) with hyphens
    $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
    
    // Replace whitespace and multiple hyphens with a single hyphen
    $slug = preg_replace('/[\s\-]+/', '-', $slug);
    
    // Trim hyphens from start and end
    $slug = trim($slug, '-');
    
    return $slug;
}

function fetchGenericTracking($tracking_number, $carrier_info = [])
{
    // Return mock tracking data for generic carriers
    return [
        'success' => true,
        'carrier' => $carrier_info['name'] ?? 'Generic Carrier',
        'tracking_number' => $tracking_number,
        'status' => 'In Transit',
        'location' => 'Processing Facility',
        'estimated_delivery' => date('Y-m-d', strtotime('+3 days')),
        'last_update' => date('Y-m-d H:i:s'),
        'tracking_history' => [
            [
                'date' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'location' => 'Origin Facility',
                'description' => 'Shipment picked up'
            ],
            [
                'date' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'location' => 'Transit Hub',
                'description' => 'In transit'
            ],
            [
                'date' => date('Y-m-d H:i:s'),
                'location' => 'Local Delivery Center',
                'description' => 'Arrived at delivery center'
            ]
        ]
    ];
}


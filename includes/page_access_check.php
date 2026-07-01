<?php
/**
 * Page Access Checker
 * Include this file at the top of protected pages to check access permissions
 */

require_once __DIR__ . '/access_check.php';

// Get current page URL
$current_url = $_SERVER['REQUEST_URI'] ?? '';

// Check if this page requires access control
if (shouldCheckAccess($current_url)) {
    // Get menu name from URL
    $menu_name = getMenuNameFromUrl($current_url);
    
    // Determine action based on URL or request method
    $action = 'view';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check for specific actions in POST data
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
        } elseif (strpos($current_url, 'create') !== false || strpos($current_url, 'add') !== false) {
            $action = 'add';
        } elseif (strpos($current_url, 'edit') !== false || strpos($current_url, 'update') !== false) {
            $action = 'edit';
        } elseif (strpos($current_url, 'delete') !== false || strpos($current_url, 'hapus') !== false) {
            $action = 'delete';
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check for specific actions in GET data
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
        } elseif (strpos($current_url, 'create') !== false || strpos($current_url, 'add') !== false) {
            $action = 'add';
        } elseif (strpos($current_url, 'edit') !== false) {
            $action = 'edit';
        } elseif (strpos($current_url, 'delete') !== false || strpos($current_url, 'hapus') !== false) {
            $action = 'delete';
        }
    }
    
    // Check access
    if (!checkAccess($menu_name, $action)) {
        // Store unauthorized access attempt
        $_SESSION['unauthorized_access'] = [
            'menu' => $menu_name,
            'action' => $action,
            'timestamp' => time()
        ];
        
        // Redirect to dashboard with error message
        $_SESSION['error'] = "Akses tidak diizinkan untuk " . getActionDisplayName($action) . " menu " . getMenuDisplayName($menu_name);
        header("Location: " . url_for('dashboard.php'));
        exit();
    }
}

// Function to add access control attributes to HTML elements
function addAccessControl($menu_name, $action = 'view', $element_type = 'button') {
    $has_access = checkAccess($menu_name, $action);
    
    if ($has_access) {
        return '';
    } else {
        return 'data-access="' . $menu_name . ':' . $action . '"';
    }
}

// Function to check if user can perform action and return appropriate HTML
function renderWithAccess($menu_name, $action = 'view', $html = '', $fallback_html = '') {
    if (checkAccess($menu_name, $action)) {
        return $html;
    } else {
        return $fallback_html;
    }
}

// Function to render button with access control
function renderButtonWithAccess($menu_name, $action = 'view', $text = '', $class = 'btn btn-primary', $icon = '', $url = '#', $attributes = '') {
    if (checkAccess($menu_name, $action)) {
        $icon_html = $icon ? "<i class=\"$icon me-1\"></i>" : '';
        return "<a href=\"$url\" class=\"$class\" $attributes>$icon_html $text</a>";
    } else {
        $icon_html = $icon ? "<i class=\"$icon me-1\"></i>" : '';
        return "<button type=\"button\" class=\"$class\" data-access=\"$menu_name:$action\" $attributes disabled>$icon_html $text</button>";
    }
}

// Function to render form with access control
function renderFormWithAccess($menu_name, $action = 'view', $method = 'POST', $action_url = '', $attributes = '') {
    if (checkAccess($menu_name, $action)) {
        return "<form method=\"$method\" action=\"$action_url\" $attributes>";
    } else {
        return "<form method=\"$method\" action=\"$action_url\" data-access=\"$menu_name:$action\" $attributes>";
    }
}
?> 

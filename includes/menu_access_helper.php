<?php
/**
 * Menu Access Helper - Fungsi helper untuk kontrol akses menu
 */

require_once __DIR__ . '/access_check.php';

/**
 * Menampilkan tombol berdasarkan akses
 */
function showButtonIfAccess($menu_name, $action, $url, $text, $class = 'btn btn-primary', $icon = '') {
    if (checkAccess($menu_name, $action)) {
        $icon_html = $icon ? "<i class='$icon'></i> " : '';
        echo "<a href='$url' class='$class'>$icon_html$text</a>";
        return true;
    }
    return false;
}

/**
 * Menampilkan tombol dengan data-access untuk JavaScript
 */
function showButtonWithDataAccess($menu_name, $action, $url, $text, $class = 'btn btn-primary', $icon = '') {
    $icon_html = $icon ? "<i class='$icon'></i> " : '';
    echo "<a href='$url' class='$class' data-access='$menu_name:$action'>$icon_html$text</a>";
}

/**
 * Menampilkan tombol aksi untuk tabel
 */
function showActionButtons($menu_name, $id, $base_url = '') {
    echo '<div class="btn-group" role="group">';
    
    if (checkAccess($menu_name, 'edit')) {
        echo "<a href='{$base_url}edit.php?id=$id' class='btn btn-sm btn-warning' title='Edit'>";
        echo "<i class='bx bx-edit'></i>";
        echo '</a>';
    }
    
    if (checkAccess($menu_name, 'delete')) {
        echo "<a href='{$base_url}delete.php?id=$id' class='btn btn-sm btn-danger' title='Delete' onclick='return confirm(\"Yakin ingin menghapus?\")'>";
        echo "<i class='bx bx-trash'></i>";
        echo '</a>';
    }
    
    echo '</div>';
}

/**
 * Menampilkan tombol berdasarkan akses dengan fallback ke data-access
 */
function showButtonWithAccessControl($menu_name, $action, $url, $text, $class = 'btn btn-primary', $icon = '') {
    if (checkAccess($menu_name, $action)) {
        showButtonIfAccess($menu_name, $action, $url, $text, $class, $icon);
    } else {
        showButtonWithDataAccess($menu_name, $action, $url, $text, $class, $icon);
    }
}
?> 
<?php
/**
 * Access Control Helper Functions
 * File ini berisi fungsi-fungsi helper untuk memudahkan implementasi akses kontrol
 */

require_once __DIR__ . '/access_check.php';

/**
 * Menampilkan tombol aksi berdasarkan akses yang dimiliki user
 * @param string $menu_name Nama menu
 * @param int $id ID data
 * @param string $base_url URL dasar untuk aksi
 * @param array $options Opsi tambahan
 */
function renderActionButtons($menu_name, $id, $base_url = '', $options = []) {
    $default_options = [
        'show_view' => true,
        'show_edit' => true,
        'show_delete' => true,
        'view_url' => 'view.php',
        'edit_url' => 'edit.php',
        'delete_url' => 'delete.php',
        'view_text' => 'View',
        'edit_text' => 'Edit',
        'delete_text' => 'Delete',
        'view_class' => 'btn btn-sm btn-info',
        'edit_class' => 'btn btn-sm btn-warning',
        'delete_class' => 'btn btn-sm btn-danger',
        'view_icon' => 'bx bx-eye',
        'edit_icon' => 'bx bx-edit',
        'delete_icon' => 'bx bx-trash',
        'confirm_delete' => true,
        'delete_confirm_text' => 'Apakah Anda yakin ingin menghapus data ini?'
    ];
    
    $options = array_merge($default_options, $options);
    
    echo '<div class="btn-group" role="group">';
    
    if ($options['show_view'] && hasAccess($menu_name, 'view')) {
        echo "<a href=\"{$base_url}{$options['view_url']}?id=$id\" class=\"{$options['view_class']}\" title=\"{$options['view_text']}\">";
        echo "<i class=\"{$options['view_icon']}\"></i>";
        echo '</a>';
    }
    
    if ($options['show_edit'] && hasAccess($menu_name, 'edit')) {
        echo "<a href=\"{$base_url}{$options['edit_url']}?id=$id\" class=\"{$options['edit_class']}\" title=\"{$options['edit_text']}\">";
        echo "<i class=\"{$options['edit_icon']}\"></i>";
        echo '</a>';
    }
    
    if ($options['show_delete'] && hasAccess($menu_name, 'delete')) {
        $onclick = $options['confirm_delete'] ? "onclick=\"return confirm('{$options['delete_confirm_text']}')\"" : '';
        echo "<a href=\"{$base_url}{$options['delete_url']}?id=$id\" class=\"{$options['delete_class']}\" title=\"{$options['delete_text']}\" $onclick>";
        echo "<i class=\"{$options['delete_icon']}\"></i>";
        echo '</a>';
    }
    
    echo '</div>';
}

/**
 * Menampilkan tombol berdasarkan akses yang dimiliki user
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan
 * @param string $url URL tombol
 * @param string $text Text tombol
 * @param string $class Class tombol
 * @param string $icon Icon tombol
 * @param array $attributes Atribut tambahan
 */
function renderButtonWithAccess($menu_name, $action, $url, $text, $class = 'btn btn-primary', $icon = '', $attributes = []) {
    if (hasAccess($menu_name, $action)) {
        $attr_str = '';
        foreach ($attributes as $key => $value) {
            $attr_str .= " $key=\"$value\"";
        }
        
        $icon_html = $icon ? "<i class=\"$icon me-1\"></i>" : '';
        echo "<a href=\"$url\" class=\"$class\"$attr_str>$icon_html $text</a>";
        return true;
    }
    return false;
}

/**
 * Menampilkan form dengan akses kontrol
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan
 * @param string $method Method form
 * @param string $action_url URL action form
 * @param array $attributes Atribut tambahan
 */
function renderFormWithAccess($menu_name, $action, $method = 'POST', $action_url = '', $attributes = []) {
    if (hasAccess($menu_name, $action)) {
        $attr_str = '';
        foreach ($attributes as $key => $value) {
            $attr_str .= " $key=\"$value\"";
        }
        
        echo "<form method=\"$method\" action=\"$action_url\"$attr_str>";
        return true;
    }
    return false;
}

/**
 * Menampilkan modal dengan akses kontrol
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan
 * @param string $modal_id ID modal
 * @param string $title Judul modal
 * @param string $content Konten modal
 * @param array $options Opsi tambahan
 */
function renderModalWithAccess($menu_name, $action, $modal_id, $title, $content, $options = []) {
    if (hasAccess($menu_name, $action)) {
        $default_options = [
            'size' => 'modal-dialog',
            'footer_buttons' => [
                ['text' => 'Batal', 'class' => 'btn btn-secondary', 'action' => 'data-bs-dismiss="modal"'],
                ['text' => 'Simpan', 'class' => 'btn btn-primary', 'action' => 'type="submit"']
            ]
        ];
        
        $options = array_merge($default_options, $options);
        
        echo "<div class=\"modal fade\" id=\"$modal_id\" tabindex=\"-1\">";
        echo "<div class=\"{$options['size']}\">";
        echo "<div class=\"modal-content\">";
        echo "<div class=\"modal-header\">";
        echo "<h5 class=\"modal-title\">$title</h5>";
        echo "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>";
        echo "</div>";
        echo "<div class=\"modal-body\">$content</div>";
        echo "<div class=\"modal-footer\">";
        
        foreach ($options['footer_buttons'] as $button) {
            $action_attr = $button['action'];
            echo "<button type=\"button\" class=\"{$button['class']}\" $action_attr>{$button['text']}</button>";
        }
        
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        
        return true;
    }
    return false;
}

/**
 * Menampilkan menu berdasarkan akses yang dimiliki user
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan
 * @param string $url URL menu
 * @param string $icon Icon menu
 * @param string $text Text menu
 * @param string $class Class tambahan untuk styling
 */
function renderMenuWithAccess($menu_name, $action, $url, $icon = '', $text = '', $class = '') {
    if (hasAccess($menu_name, $action)) {
        $icon_html = $icon ? "<i class=\"$icon\"></i>" : '';
        echo "<a href=\"$url\" class=\"$class\">$icon_html $text</a>";
        return true;
    }
    return false;
}

/**
 * Menampilkan badge status akses
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan
 * @param string $text Text badge
 * @param string $class Class badge
 */
function renderAccessBadge($menu_name, $action, $text = '', $class = 'badge') {
    if (hasAccess($menu_name, $action)) {
        $status_class = $class . ' bg-success';
        $icon = '✓';
    } else {
        $status_class = $class . ' bg-danger';
        $icon = '✗';
    }
    
    echo "<span class=\"$status_class\">$icon $text</span>";
}

/**
 * Menampilkan pesan error jika tidak memiliki akses
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan
 * @param string $redirect_url URL redirect
 */
function checkAccessAndRedirect($menu_name, $action, $redirect_url = '../dashboard.php') {
    if (!hasAccess($menu_name, $action)) {
        $action_names = [
            'view' => 'melihat',
            'add' => 'menambah',
            'edit' => 'mengedit',
            'delete' => 'menghapus'
        ];
        
        $action_text = $action_names[$action] ?? $action;
        $_SESSION['error'] = "Akses tidak diizinkan untuk $action_text menu $menu_name";
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Menampilkan tombol berdasarkan akses dengan JavaScript confirmation
 * @param string $menu_name Nama menu
 * @param string $action Aksi yang diizinkan
 * @param string $url URL tombol
 * @param string $text Text tombol
 * @param string $class Class tombol
 * @param string $icon Icon tombol
 * @param string $confirm_text Text konfirmasi
 */
function renderButtonWithConfirm($menu_name, $action, $url, $text, $class = 'btn btn-primary', $icon = '', $confirm_text = '') {
    if (hasAccess($menu_name, $action)) {
        $onclick = $confirm_text ? "onclick=\"return confirm('$confirm_text')\"" : '';
        $icon_html = $icon ? "<i class=\"$icon me-1\"></i>" : '';
        echo "<a href=\"$url\" class=\"$class\" $onclick>$icon_html $text</a>";
        return true;
    }
    return false;
}
?> 
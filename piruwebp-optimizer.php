<?php
/**
 * Plugin Name: PiruWebP Optimizer
 * Description: Optimiza las imágenes y convierte formatos de imagen a WebP al subirlos.
 * Version: 0.0.5
 * Author: Pirulug
 * Author URI: https://github.com/pirulug
 * GitHub Plugin URI: https://github.com/pirulug/piruwebp-optimizer
 */


// Hook para procesar imágenes al subirlas
add_filter('wp_handle_upload', 'prwp_convert_to_webp');

function prwp_convert_to_webp($upload) {
  // Verificar que el archivo subido es una imagen
  $image_types = ['image/jpeg', 'image/png'];
  if (in_array($upload['type'], $image_types)) {
    $file_path = $upload['file'];
    $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file_path);

    // Crear imagen en WebP y optimizar
    $image = null;
    if ($upload['type'] === 'image/jpeg') {
      $image = imagecreatefromjpeg($file_path);
    } elseif ($upload['type'] === 'image/png') {
      $image = imagecreatefrompng($file_path);
    }

    if ($image) {
      imagewebp($image, $webp_path, 80); // 80 es la calidad (puedes ajustarla)
      imagedestroy($image);

      // Reemplazar la ruta del archivo por la versión WebP
      $upload['file'] = $webp_path;
      $upload['url']  = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $upload['url']);
      $upload['type'] = 'image/webp';

      // Eliminar la imagen original si ya no es necesaria
      unlink($file_path);
    }
  }

  return $upload;
}

// Añadir una columna nueva a la tabla de medios para mostrar el peso del archivo y el botón de conversión
add_filter('manage_media_columns', 'prwp_add_file_size_column');
function prwp_add_file_size_column($columns) {
  $columns['file_size'] = 'File Size';
  return $columns;
}

// Mostrar el peso del archivo y el botón de conversión en la columna
add_action('manage_media_custom_column', 'prwp_show_file_size_and_button', 10, 2);
function prwp_show_file_size_and_button($column_name, $post_id) {
  if ($column_name == 'file_size') {
    $file_path = get_attached_file($post_id);
    $file_type = wp_check_filetype($file_path);

    if (file_exists($file_path)) {
      $file_size = filesize($file_path);
      echo size_format($file_size, 2);
    } else {
      echo 'File not found';
    }
  }
}

// Agregar una página de configuración del plugin en el menú de administración
add_action('admin_menu', 'prwp_add_admin_menu');

function prwp_add_admin_menu() {
  add_menu_page(
    'PiruWebP Optimizer',
    'PiruWebP Optimizer',
    'manage_options',
    'prwp-optimizer',
    'prwp_settings_page',
    'dashicons-update',
    100
  );
}

// Función para renderizar la página de configuración del plugin
function prwp_settings_page() {
  ?>
  <div class="wrap">
    <h1>PiruWebP Optimizer</h1>
    <p>Verifica si hay actualizaciones del plugin y aplícalas manualmente.</p>
    <form method="post" action="">
      <?php wp_nonce_field('prwp_check_update_nonce', 'prwp_check_update_nonce'); ?>
      <input type="submit" name="check_update" id="check_update" class="button button-primary"
        value="Comprobar Actualizaciones">
    </form>
  </div>
  <?php

  // Comprobar si se ha hecho clic en el botón
  if (isset($_POST['check_update']) && check_admin_referer('prwp_check_update_nonce', 'prwp_check_update_nonce')) {
    prwp_check_and_update_plugin();
  }
}

// Función para verificar y actualizar el plugin desde GitHub
function prwp_check_and_update_plugin() {
  $user       = 'pirulug';
  $repository = 'piruwebp-optimizer';

  // Leer la versión actual del plugin desde los datos del plugin
  $plugin_data     = get_file_data(__FILE__, ['Version' => 'Version']);
  $current_version = $plugin_data['Version'];

  // Hacer la solicitud a GitHub
  $response = wp_remote_get("https://api.github.com/repos/$user/$repository/releases/latest");

  if (is_wp_error($response)) {
    echo '<div class="notice notice-error"><p>Error al comprobar actualizaciones.</p></div>';
    return;
  }

  $latest_release = json_decode(wp_remote_retrieve_body($response));

  if (isset($latest_release->tag_name) && version_compare($latest_release->tag_name, $current_version, '>')) {
    // Descargar y actualizar automáticamente
    $download_url = $latest_release->zipball_url;
    $tmp_file     = download_url($download_url);

    if (is_wp_error($tmp_file)) {
      echo '<div class="notice notice-error"><p>Error al descargar la actualización.</p></div>';
      return;
    }

    // Descomprimir y reemplazar el plugin
    $result = unzip_file($tmp_file, WP_PLUGIN_DIR);
    unlink($tmp_file); // Limpiar archivo temporal

    if (is_wp_error($result)) {
      echo '<div class="notice notice-error"><p>Error al descomprimir el archivo de actualización.</p></div>';
      return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>El plugin PiruWebP Optimizer se ha actualizado a la última versión (' . esc_html($latest_release->tag_name) . ').</p></div>';
  } else {
    echo '<div class="notice notice-info is-dismissible"><p>El plugin ya está actualizado.</p></div>';
  }
}

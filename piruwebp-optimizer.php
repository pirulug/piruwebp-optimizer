<?php
/**
 * Plugin Name: PiruWebP Optimizer
 * Description: Optimiza las imágenes y convierte formatos de imagen a WebP al subirlos.
 * Version: 0.0.4
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

// Sistema de actualización desde GitHub
add_action('admin_init', 'prwp_check_plugin_update_from_github');
function prwp_check_plugin_update_from_github() {
  $user       = 'pirulug';
  $repository = 'piruwebp-optimizer';

  // Leer la versión actual del plugin desde los datos del plugin
  $plugin_data     = get_file_data(__FILE__, ['Version' => 'Version']);
  $current_version = $plugin_data['Version'];

  // Hacer la solicitud a GitHub
  $response = wp_remote_get("https://api.github.com/repos/$user/$repository/releases/latest");

  if (is_wp_error($response)) {
    return;
  }

  $latest_release = json_decode(wp_remote_retrieve_body($response));

  if (isset($latest_release->tag_name) && version_compare($latest_release->tag_name, $current_version, '>')) {
    add_action('admin_notices', function () use ($latest_release) {
      echo '<div class="notice notice-warning is-dismissible">
              <p>Una nueva versión del plugin PiruWebP Optimizer está disponible. <a href="' . esc_url($latest_release->html_url) . '">Actualiza aquí</a>.</p>
            </div>';
    });
  }
}

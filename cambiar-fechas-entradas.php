<?php
/**
 * Plugin Name: Cambiar fechas de entradas
 * Description: Asigna fechas aleatorias a las entradas en lotes, con un rango de fechas seleccionable por el usuario.
 * Version: 1.0
 * Author: Albert Navarro
 * Author URI: https://www.linkedin.com/in/albert-n-579261256/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html

 */
// Este archivo es parte de of the Cambiar Fechas de Entradas plugin.
// (C) Albert, 2024
// This file is licensed under the GPL v2 or later.

function cambiar_fechas_optimizado_menu() {
    add_menu_page('Cambiar Fechas de Entradas', 'Cambiar Fechas', 'manage_options', 'cambiar-fechas-optimizado', 'cambiar_fechas_optimizado_page');
}

add_action('admin_menu', 'cambiar_fechas_optimizado_menu');

function cargar_datepicker_scripts() {
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
}

add_action('admin_enqueue_scripts', 'cargar_datepicker_scripts');

function cambiar_fechas_optimizado_page() {
    ?>
    <div class="wrap">
        <h2>Cambiar Fechas de Entradas</h2>
        <label for="fecha-minima">Fecha Mínima:</label>
        <input type="text" id="fecha-minima" name="fecha-minima">
        <label for="fecha-maxima">Fecha Máxima:</label>
        <input type="text" id="fecha-maxima" name="fecha-maxima">
        <button id="cambiar-fechas-btn" class="button button-primary">Empezar</button>
        <div id="cambiar-fechas-result"></div>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#fecha-minima, #fecha-maxima').datepicker({
                dateFormat: 'yy-mm-dd'
            });

            $('#cambiar-fechas-btn').click(function() {
                var fechaMinima = $('#fecha-minima').val();
                var fechaMaxima = $('#fecha-maxima').val();

                if (!fechaMinima || !fechaMaxima) {
                    alert('Por favor, rellena ambas fechas.');
                    return;
                }

                var data = {
                    'action': 'cambiar_fechas',
                    'start': 0,
                    'fecha_minima': fechaMinima,
                    'fecha_maxima': fechaMaxima
                };

                $('#cambiar-fechas-result').text('Procesando...');

                function cambiarFechasBatch(start) {
                    data.start = start;

                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            $('#cambiar-fechas-result').append('<p>' + response.data.message + '</p>');
                            if (response.data.next_start !== -1) {
                                cambiarFechasBatch(response.data.next_start);
                            } else {
                                $('#cambiar-fechas-result').append('<p>¡Proceso completado!</p>');
                            }
                        } else {
                            $('#cambiar-fechas-result').append('<p>Error: ' + response.data.message + '</p>');
                        }
                    });
                }

                cambiarFechasBatch(0);
            });
        });
    </script>
    <?php
}

function cambiar_fechas_optimizado_ajax() {
    $batch_size = 100; // Número de entradas a procesar en cada lote
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $fecha_minima = isset($_POST['fecha_minima']) ? $_POST['fecha_minima'] : null;
    $fecha_maxima = isset($_POST['fecha_maxima']) ? $_POST['fecha_maxima'] : null;

    if (!$fecha_minima || !$fecha_maxima || strtotime($fecha_minima) > strtotime($fecha_maxima)) {
        wp_send_json_error(['message' => 'Las fechas proporcionadas no son válidas.']);
        return;
    }

    global $wpdb;
    $entradas = $wpdb->get_results($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' LIMIT %d, %d", $start, $batch_size));

    if (empty($entradas)) {
        wp_send_json_success(['message' => 'No hay más entradas para procesar.', 'next_start' => -1]);
        return;
    }

    foreach ($entradas as $entrada) {
        $fecha_aleatoria = rand(strtotime($fecha_minima), strtotime($fecha_maxima));
        $nueva_fecha = date('Y-m-d H:i:s', $fecha_aleatoria);

        $wpdb->update(
            $wpdb->posts,
            ['post_date' => $nueva_fecha, 'post_date_gmt' => get_gmt_from_date($nueva_fecha), 'post_modified' => $nueva_fecha, 'post_modified_gmt' => get_gmt_from_date($nueva_fecha)],
            ['ID' => $entrada->ID]
        );
    }

    wp_send_json_success(['message' => 'Procesadas entradas ' . ($start + 1) . ' a ' . ($start + count($entradas)) . '.', 'next_start' => $start + $batch_size]);
}

add_action('wp_ajax_cambiar_fechas', 'cambiar_fechas_optimizado_ajax');

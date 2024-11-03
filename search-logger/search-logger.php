<?php
/*
Plugin Name:       Search Logger
Description:       Enregistre les recherches des visiteurs et affiche les statistiques des recherches dans l'administration.
Version:           1.0
Author:            deeweb
Author URI:        https://deeweb.fr/
Text Domain:       search-logger
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Création de la table à l'activation du plugin
function search_logger_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'search_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        search_term VARCHAR(255) NOT NULL,
        search_count BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
        last_searched DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE (search_term)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'search_logger_create_table');

// Fonction pour enregistrer les recherches
function search_logger_save_search() {
    if (is_search() && !empty(get_search_query())) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'search_logs';
        $search_term = sanitize_text_field(get_search_query());
        $current_time = current_time('mysql');

        // Vérifie si la recherche existe déjà
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE search_term = %s", $search_term));

        if ($row) {
            // Met à jour le compteur et la date
            $wpdb->update(
                $table_name,
                [
                    'search_count' => $row->search_count + 1,
                    'last_searched' => $current_time
                ],
                ['id' => $row->id]
            );
        } else {
            // Insère une nouvelle recherche
            $wpdb->insert(
                $table_name,
                [
                    'search_term' => $search_term,
                    'search_count' => 1,
                    'last_searched' => $current_time
                ]
            );
        }
    }
}
add_action('template_redirect', 'search_logger_save_search');

// Ajouter une page d'administration pour afficher les recherches
function search_logger_admin_menu() {
    add_menu_page(
        'Recherches des visiteurs',
        'Recherches',
        'manage_options',
        'search-logger',
        'search_logger_display_logs',
        'dashicons-search',
        20
    );
}
add_action('admin_menu', 'search_logger_admin_menu');

// Afficher les recherches dans la page d'administration
function search_logger_display_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'search_logs';

    // Récupère les recherches des 60 derniers jours
    $results = $wpdb->get_results("
        SELECT search_term, search_count 
        FROM $table_name 
        WHERE last_searched >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        ORDER BY search_count DESC
    ");

    echo '<div class="wrap">';
    echo '<h1>Recherches des visiteurs</h1>';
    echo '<table class="widefat fixed" cellspacing="0">';
    echo '<thead><tr><th>Recherche</th><th>Nombre de fois</th></tr></thead>';
    echo '<tbody>';

    if ($results) {
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->search_term) . '</td>';
            echo '<td>' . esc_html($row->search_count) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="2">Aucune recherche enregistrée au cours des 60 derniers jours.</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

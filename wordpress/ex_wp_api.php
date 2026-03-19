<?php
/**
 * Plugin Name: Casino Review API
 * Plugin URI: https://yourwebsite.com
 * Description: A plugin to receive casino reviews via a custom REST API and store them in WordPress pages.
 * Version: 1.4
 * Author: Your Name
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('casino/v1', '/add/', array(
        'methods'  => 'POST',
        'callback' => 'casino_review_api_callback',
        'permission_callback' => '__return_true',
    ));
});

function casino_review_api_callback(WP_REST_Request $request) {
    $data = $request->get_json_params();

    if (empty($data)) {
        return new WP_Error('missing_data', 'No JSON data received', array('status' => 400));
    }

    // 🔹 Debugging: Log received JSON data
    error_log('Casino API - Full Received Data: ' . json_encode($data, JSON_PRETTY_PRINT));

    // Extract data from nested structure
    $detail_info = $data['detail_info'] ?? [];
    $casino_name = $detail_info['casino_name'] ?? 'Not Available';
    $main_text = $data['main_content'] ?? 'Not Available';

    // Check if the page exists
    $existing_page = get_page_by_title($casino_name, OBJECT, 'page');

    // 🔹 Define meta fields to match JSON structure
    $meta_fields = [
        // Detail Info
        'safety_index' => $detail_info['safety_index'] ?? 'Not Available',
        'safety_rating' => $detail_info['safety_rating'] ?? 'Not Available',
        'user_feedback' => $detail_info['user_feedback'] ?? 'Not Available',
        'user_reviews_count' => $detail_info['user_reviews_count'] ?? '0',
        'accepts_vietnam' => $detail_info['accepts_vietnam'] ?? 'Not Available',
        'payment_methods' => $detail_info['payment_methods'] ?? [],
        'withdrawal_limits' => $detail_info['withdrawal_limits'] ?? 'Not Available',
        'owner' => $detail_info['owner'] ?? 'Not Available',
        'established' => $detail_info['established'] ?? 'Not Available',
        'estimated_annual_revenues' => $detail_info['estimated_annual_revenues'] ?? 'Not Available',
        'licensing_authorities' => $detail_info['licensing_authorities'] ?? [],

        // Bonuses
        'no_deposit_bonus' => $data['bonuses']['NO DEPOSIT BONUS'] ?? [],
        'deposit_bonus' => $data['bonuses']['DEPOSIT BONUS'] ?? [],

        // Games
        'available_games' => $data['games']['available_games'] ?? [],
        'unavailable_games' => $data['games']['unavailable_games'] ?? [],

        // Language Options
        'website_languages' => $data['language_options']['website_languages'] ?? [],
        'customer_support_languages' => $data['language_options']['customer_support_languages'] ?? [],
        'live_chat_languages' => $data['language_options']['live_chat_languages'] ?? [],

        // Game Providers
        'game_providers' => $data['game_providers']['providers'] ?? [],

        // Screenshots
        'screenshots' => $data['screenshots'] ?? [],

        // Pros and Cons
        'pros_cons' => [
            'positives' => $data['pros_cons']['positives'] ?? [],
            'negatives' => $data['pros_cons']['negatives'] ?? [],
            'interesting_facts' => $data['pros_cons']['interesting_facts'] ?? []
        ],

        // Additional metadata for template
        '_wp_page_template' => 'page-casino.php',
        'last_updated' => current_time('mysql')
    ];

    // Prepare post data
    $post_data = [
        'post_title' => wp_strip_all_tags($casino_name),
        'post_content' => wp_kses_post($main_text),
        'post_status' => 'publish',
        'post_type' => 'page',
        'meta_input' => $meta_fields
    ];

    if ($existing_page) {
        $post_data['ID'] = $existing_page->ID;
        $page_id = wp_update_post($post_data, true);
    } else {
        $page_id = wp_insert_post($post_data, true);
    }

    if (is_wp_error($page_id)) {
        error_log('Casino API: Failed to create/update page - ' . $page_id->get_error_message());
        return new WP_Error('page_creation_failed', 'Failed to create/update page: ' . $page_id->get_error_message(), ['status' => 500]);
    }

    // Store complex meta fields separately to ensure proper serialization
    $complex_fields = [
        'payment_methods',
        'licensing_authorities',
        'no_deposit_bonus',
        'deposit_bonus',
        'available_games',
        'unavailable_games',
        'website_languages',
        'customer_support_languages',
        'live_chat_languages',
        'game_providers',
        'screenshots',
        'pros_cons'
    ];

    foreach ($complex_fields as $field) {
        if (isset($meta_fields[$field])) {
            update_post_meta($page_id, $field, $meta_fields[$field]);
            error_log("Casino API - Stored Complex Meta: $field => " . json_encode(get_post_meta($page_id, $field, true)));
        }
    }

    // Clear cache
    clean_post_cache($page_id);
    wp_cache_flush();

    return rest_ensure_response([
        'status' => 'success',
        'message' => 'Casino page created/updated successfully!',
        'page_id' => $page_id,
        'permalink' => get_permalink($page_id)
    ]);
}
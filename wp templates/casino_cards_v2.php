<?php
/*
Template Name: All Casinos Main Page (WP Title)
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct script access denied.');
}

// --- Pagination Configuration ---
$casinos_per_page = 18;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Helper function to generate pagination URLs
function get_pagination_url($page_num) {
    $current_url = $_SERVER['REQUEST_URI'];
    $url_parts = parse_url($current_url);
    parse_str($url_parts['query'] ?? '', $query_params);
    $query_params['page'] = $page_num;
    $new_query = http_build_query($query_params);
    return $url_parts['path'] . '?' . $new_query;
}

// --- Sanitization and Helper Functions ---
function sanitize_logo_filename($name) {
    // Remove common suffixes - be more specific about suffix removal
    $name = str_replace('logo_', '', $name);
    
    // Remove "Casino Review", "Review", "Casino" suffixes - be very specific with word boundaries
    $name = preg_replace('/\s+Casino\s+Review\s*$/i', '', $name);
    $name = preg_replace('/\s+Review\s*$/i', '', $name);
    $name = preg_replace('/\s+Casino\s*$/i', '', $name);
    
    // Convert to lowercase
    $name = strtolower($name);
    
    // Remove spaces, hyphens, underscores, parentheses but PRESERVE ALL dots
    $name = preg_replace('/[\s\-_\(\)\[\]{}]+/', '', $name);
    
    // Trim any remaining whitespace
    $name = trim($name);
    
    return $name;
}
function get_logo_url($casino_name) {
    $image_base_url = site_url('/wp-content/uploads/logos_full/logos_remake/');
    $sanitized_name = sanitize_logo_filename($casino_name);
    
    // Add debug output
    echo "<!-- Debug Logo: Original name: '$casino_name' | Sanitized: '$sanitized_name' -->\n";
    
    // Check PNG first, then SVG, then JPG, JPEG, WEBP
    $img_extensions = ['png', 'svg', 'jpg', 'jpeg', 'webp'];
    $img_url = '';
    $img_found = false;
    
    foreach ($img_extensions as $ext) {
        $img_path = $image_base_url . $sanitized_name . '.' . $ext;
        echo "<!-- Debug Logo: Checking: $img_path -->\n";
        
        $ch = curl_init($img_path);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Increased timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('cURL error for logo ' . $img_path . ': ' . curl_error($ch));
            curl_close($ch);
            continue;
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<!-- Debug Logo: HTTP Status for $ext: $http_code -->\n";
        
        if ($http_code == 200) {
            $img_url = $img_path;
            $img_found = true;
            echo "<!-- Debug Logo: Found image: $img_path -->\n";
            // If we found a PNG, use it immediately
            if ($ext === 'png') {
                break;
            }
        }
    }
    
    if (!$img_found) {
        echo "<!-- Debug Logo: No logo found for '$casino_name' at '$image_base_url$sanitized_name' -->\n";
        error_log('No logo found for ' . $casino_name . ' at ' . $image_base_url . $sanitized_name);
    }
    
    return $img_found ? $img_url : '';
}
function sanitize_payment_name($name) {
    $sanitized = strtolower($name);
    $sanitized = iconv('UTF-8', 'ASCII//TRANSLIT', $sanitized);
    $sanitized = preg_replace('/[^a-z0-9.]/', '', $sanitized);
    return $sanitized;
}
function sanitize_game_name($name) {
    $sanitized = strtolower($name);
    $sanitized = iconv('UTF-8', 'ASCII//TRANSLIT', $sanitized);
    $sanitized = preg_replace('/[^a-z0-9.]/', '', $sanitized);
    return $sanitized;
}
function not_empty($value) {
    return isset($value) && trim($value) !== '' ? $value : 'Not specified';
}
function get_casino_review_url($casino_name, $json_file_path = '') {
    // Method 1: Try exact title match first
    $page = get_page_by_title($casino_name . ' Casino Review', OBJECT, 'page');
    if ($page) {
        return get_permalink($page->ID);
    }
    
    // Method 2: Try without "Casino Review" suffix
    $page = get_page_by_title($casino_name, OBJECT, 'page');
    if ($page) {
        return get_permalink($page->ID);
    }
    
    // Method 3: If we have the JSON file path, extract page ID from filename
    if ($json_file_path) {
        $filename = basename($json_file_path, '.json');
        if (preg_match('/page-(\d+)/', $filename, $matches)) {
            $page_id = $matches[1];
            $page = get_post($page_id);
            if ($page && $page->post_type === 'page') {
                return get_permalink($page_id);
            }
        }
    }
    
    // Method 4: Search through all pages with the main template
    $pages = get_pages(array(
        'meta_key' => '_wp_page_template',
        'meta_value' => 'casino_review_template.php'
    ));
    
    foreach ($pages as $page) {
        // Check if page title contains casino name
        if (stripos($page->post_title, $casino_name) !== false) {
            return get_permalink($page->ID);
        }
    }
    
    // Method 5: Dynamic URL fallback
    $base_review_page = get_page_by_title('Casino Review', OBJECT, 'page');
    if ($base_review_page) {
        $base_url = get_permalink($base_review_page->ID);
        $casino_slug = sanitize_title($casino_name);
        return add_query_arg('casino', $casino_slug, $base_url);
    }
    
    // Final fallback
    return home_url('/casino-reviews/');
}

// --- Load JSONs with Pagination ---
$json_dir = get_template_directory() . '/json/';
$json_files = glob($json_dir . 'page-*.json');

// Calculate pagination
$total_files = count($json_files);
$total_pages = ceil($total_files / $casinos_per_page);
$offset = ($current_page - 1) * $casinos_per_page;
$files_for_current_page = array_slice($json_files, $offset, $casinos_per_page);

$casinos = [];
foreach ($files_for_current_page as $file) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error for ' . $file . ': ' . json_last_error_msg());
        continue;
    }
    if (!$data) continue;
    $casino_name = $data['detail_info']['casino_name'] ?? $data['title'] ?? '';
    if (!$casino_name) {
        $casino_name = basename($file, '.json');
    }

    // --- Enhanced Bonus Information Extraction ---
    $primary_bonus_name = '';
    $primary_bonus_details = []; // Default to array
    $has_deposit_bonus = false;
    $has_nodeposit_bonus = false;
    $deposit_bonus_info = '';
    $nodeposit_bonus_info = '';

    // Check for deposit bonuses
    if (!empty($data['bonuses']) && is_array($data['bonuses'])) {
        // Check for standardized "DEPOSIT BONUS" key first
        if (isset($data['bonuses']['DEPOSIT BONUS'][0])) {
            $deposit_bonus = $data['bonuses']['DEPOSIT BONUS'][0];
            $has_deposit_bonus = $deposit_bonus['is_available'] ?? false;
            if ($has_deposit_bonus) {
                $primary_bonus_name = $deposit_bonus['name'] ?? '';
                $primary_bonus_details = $deposit_bonus['terms_and_conditions'] ?? [];
                $deposit_bonus_info = $primary_bonus_name;
            }
        } 
        // Fallback to looking for other deposit-type bonus keys
        elseif (isset($data['bonuses']['welcome'][0])) {
            $primary_bonus_name = $data['bonuses']['welcome'][0]['name'] ?? '';
            $primary_bonus_details = $data['bonuses']['welcome'][0]['terms_and_conditions'] ?? [];
            $has_deposit_bonus = true;
            $deposit_bonus_info = $primary_bonus_name;
        } elseif (isset($data['bonuses']['first_deposit'][0])) {
            $primary_bonus_name = $data['bonuses']['first_deposit'][0]['name'] ?? '';
            $primary_bonus_details = $data['bonuses']['first_deposit'][0]['terms_and_conditions'] ?? [];
            $has_deposit_bonus = true;
            $deposit_bonus_info = $primary_bonus_name;
        } else {
            // Look through all bonus types for any deposit-related bonus
            foreach ($data['bonuses'] as $bonus_type_key => $bonus_list) {
                // Skip no deposit bonuses and the standardized keys we already checked
                if (in_array($bonus_type_key, ['no_deposit', 'nodeposit', 'free', 'NO DEPOSIT BONUS', 'DEPOSIT BONUS'])) {
                    continue;
                }
                
                if (!empty($bonus_list) && is_array($bonus_list) && isset($bonus_list[0])) {
                    $current_bonus_entry = $bonus_list[0];
                    // If there's an is_available flag, check it
                    if (isset($current_bonus_entry['is_available']) && $current_bonus_entry['is_available'] === false) {
                        continue;
                    }
                    
                    $current_name = $current_bonus_entry['name'] ?? '';
                    $current_details = $current_bonus_entry['terms_and_conditions'] ?? [];
                    
                    if (!empty($current_name) && $current_name !== 'Not available') {
                        $primary_bonus_name = $current_name;
                        $primary_bonus_details = $current_details;
                        $has_deposit_bonus = true;
                        $deposit_bonus_info = $primary_bonus_name;
                        break;
                    }
                }
            }
        }
        
        // Check for no deposit bonus using standardized key
        if (isset($data['bonuses']['NO DEPOSIT BONUS'][0])) {
            $nodeposit_bonus = $data['bonuses']['NO DEPOSIT BONUS'][0];
            $has_nodeposit_bonus = $nodeposit_bonus['is_available'] ?? false;
            if ($has_nodeposit_bonus) {
                $nodeposit_bonus_name = $nodeposit_bonus['name'] ?? 'Free Bonus';
                $nodeposit_bonus_info = $nodeposit_bonus_name;
                
                // If we didn't find a deposit bonus but have a no deposit one, use it as primary
                if (!$has_deposit_bonus) {
                    $primary_bonus_name = $nodeposit_bonus_name;
                    $primary_bonus_details = $nodeposit_bonus['terms_and_conditions'] ?? [];
                }
            }
        }
        // Fallback to checking other no deposit bonus keys if the standardized one doesn't exist
        else {
            $nodeposit_types = ['no_deposit', 'nodeposit', 'free'];
            foreach ($nodeposit_types as $no_deposit_key) {
                if (isset($data['bonuses'][$no_deposit_key]) && !empty($data['bonuses'][$no_deposit_key][0])) {
                    $current_bonus_entry = $data['bonuses'][$no_deposit_key][0];
                    // If there's an is_available flag, check it
                    if (isset($current_bonus_entry['is_available']) && $current_bonus_entry['is_available'] === false) {
                        continue;
                    }
                    
                    $has_nodeposit_bonus = true;
                    $nodeposit_bonus_name = $current_bonus_entry['name'] ?? 'Free Bonus';
                    $nodeposit_bonus_info = $nodeposit_bonus_name;
                    
                    // If we didn't find a deposit bonus but have a no deposit one, use it as primary
                    if (!$has_deposit_bonus) {
                        $primary_bonus_name = $nodeposit_bonus_name;
                        $primary_bonus_details = $current_bonus_entry['terms_and_conditions'] ?? [];
                    }
                    break;
                }
            }
        }
    }

    // If no bonuses found through structured data, check if there's any text in a general bonus field
    if (!$has_deposit_bonus && !$has_nodeposit_bonus && !empty($data['detail_info']['bonus'])) {
        $bonus_text = $data['detail_info']['bonus'];
        if (stripos($bonus_text, 'no deposit') !== false || stripos($bonus_text, 'free') !== false) {
            $has_nodeposit_bonus = true;
            $nodeposit_bonus_info = $bonus_text;
            $primary_bonus_name = $bonus_text;
        } else {
            $has_deposit_bonus = true;
            $deposit_bonus_info = $bonus_text;
            $primary_bonus_name = $bonus_text;
        }
    }
    // --- End of Enhanced Bonus Information Extraction ---

    $logo = get_logo_url($casino_name);
    $safety_index = $data['detail_info']['safety_index'] ?? '';
    $safety_label = '';
    if (is_numeric($safety_index)) {
        if ($safety_index >= 8) $safety_label = 'HIGH';
        elseif ($safety_index >= 5) $safety_label = 'MEDIUM';
        else $safety_label = 'LOW';
    }
    $games = $data['games']['available_games'] ?? [];
    $payments = $data['detail_info']['payment_methods'] ?? [];
    $languages = $data['language_options']['website_languages'] ?? [];
    $support_langs = $data['language_options']['customer_support_languages'] ?? [];
    $livechat_langs = $data['language_options']['live_chat_languages'] ?? [];
    $pros = $data['pros_cons']['positives'] ?? [];
    $cons = $data['pros_cons']['negatives'] ?? [];
    $facts = $data['pros_cons']['interesting_facts'] ?? [];
    $permalink = get_casino_review_url($casino_name, $file);
    
    // Debug output for linking
    echo "<!-- Debug Link: Casino '$casino_name' -> '$permalink' (from file: " . basename($file) . ") -->\n";
    
    $vpn_note = $data['detail_info']['vpn_allowed'] ?? null;
    $features = [];
    if (!empty($data['detail_info']['is_international'])) {
        $features[] = ['icon' => '🌍', 'text' => 'International casino', 'type' => 'positive'];
    }
    if (!empty($data['detail_info']['accepts_vietnam'])) {
        $features[] = ['icon' => '🈚', 'text' => $data['detail_info']['accepts_vietnam'], 'type' => 'positive'];
    } else {
        $features[] = ['icon' => '🈚', 'text' => 'Consult with the casino if available', 'type' => 'partial'];
    }
    if (!empty($data['detail_info']['fast_withdrawal'])) {
        $features[] = ['icon' => '⚡', 'text' => 'Fast withdrawal processing based on players experience', 'type' => 'positive'];
    }
    if (!empty($data['detail_info']['live_chat_247'])) {
        $features[] = ['icon' => '💬', 'text' => 'Live chat is available 24/7, but not for all languages', 'type' => 'partial'];
    }
    $casinos[] = [
        'title' => $casino_name,
        'logo' => $logo,
        'logo_sanitized' => sanitize_logo_filename($casino_name),
        'safety_index' => $safety_index,
        'safety_label' => $safety_label,
        'bonus' => $primary_bonus_name,
        'bonus_details' => $primary_bonus_details,
        'has_deposit_bonus' => $has_deposit_bonus,
        'has_nodeposit_bonus' => $has_nodeposit_bonus,
        'deposit_bonus_info' => $deposit_bonus_info,
        'nodeposit_bonus_info' => $nodeposit_bonus_info,
        'games' => $games,
        'payments' => $payments,
        'languages' => $languages,
        'support_langs' => $support_langs,
        'livechat_langs' => $livechat_langs,
        'pros' => $pros,
        'cons' => $cons,
        'facts' => $facts,
        'permalink' => $permalink,
        'features' => $features,
        'vpn_note' => $vpn_note,
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Casinos</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #2a2a2a;
            color: #ffffff;
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .casino-card {
            background: #333333;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #555555;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .casino-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }
        
        .logo-section {
            background: #1a1a1a;
            padding: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 80px;
            border-bottom: 1px solid #555555;
            overflow: hidden;
        }
        
        .logo-section img {
            height: 150px;
            width: 150px;
            object-fit: cover;
            object-position: center;
            border-radius: 4px;
        }
        
        .logo-placeholder {
            width: 40px;
            height: 40px;
            background: #555555;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999999;
        }
        
        .card-content {
            padding: 1rem;
        }
        
        .casino-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: #00e640;
            margin-bottom: 1rem;
            text-shadow: 0 0 3px #00e640;
        }
        
        .safety-index {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .safety-label {
            font-size: 0.75rem;
            font-weight: bold;
            margin-right: 0.5rem;
            color: #cccccc;
        }
        
        .safety-value {
            font-size: 0.875rem;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        
        .safety-high { color: #00e640; }
        .safety-medium { color: #ff9800; }
        .safety-low { color: #f44336; }
        
        .safety-badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: bold;
            border-radius: 4px;
            color: #ffffff;
        }
        
        .badge-high { background: #00e640; }
        .badge-medium { background: #ff9800; }
        .badge-low { background: #f44336; }
        
        .features-list {
            list-style: none;
            margin-bottom: 1rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .feature-icon {
            margin-right: 0.5rem;
        }
        
        .feature-positive { color: #00e640; }
        .feature-partial { color: #ff9800; }
        .feature-negative { color: #f44336; }
        
        .bonus-section {
            background: #3a3a3a;
            border: 1px solid #00e640;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .bonus-header {
            display: flex;
            align-items: center;
            font-weight: bold;
            color: #00e640;
            margin-bottom: 0.5rem;
        }
        
        .bonus-header .icon {
            margin-right: 0.5rem;
        }
        
        .bonus-item {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .bonus-item .icon {
            margin-right: 0.5rem;
        }
        
        .bonus-available { color: #00e640; }
        .bonus-unavailable { color: #f44336; }
        
        .tc-link {
            font-size: 0.75rem;
            color: #00e640;
            text-decoration: none;
            margin-top: 0.5rem;
            display: inline-block;
        }
        
        .tc-link:hover {
            text-decoration: underline;
        }
        
        .detail-section {
            margin-bottom: 1rem;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            font-weight: bold;
            color: #cccccc;
            margin-bottom: 0.5rem;
        }
        
        .section-header .icon {
            margin-right: 0.5rem;
        }
        
        .detail-content {
            font-size: 0.875rem;
            color: #ffffff;
        }
        
        .detail-item {
            margin-bottom: 0.25rem;
        }
        
        .detail-item strong {
            color: #cccccc;
        }
        
        .show-btn {
            background: #3a3a3a;
            border: 1px solid #00e640;
            color: #00e640;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            margin-left: 0.5rem;
            text-decoration: none;
        }
        
        .show-btn:hover {
            background: #00e640;
            color: #ffffff;
        }
        
        .games-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .game-badge {
            background: #3a3a3a;
            border: 1px solid #555555;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            color: #ffffff;
            display: flex;
            align-items: center;
        }
        
        .game-badge img {
            height: 16px;
            width: auto;
            margin-right: 0.25rem;
        }
        
        .payment-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .payment-badge {
            background: #3a3a3a;
            border: 1px solid #555555;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            color: #ffffff;
            display: flex;
            align-items: center;
        }
        
        .payment-badge img {
            height: 16px;
            width: auto;
            margin-right: 0.25rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn {
            flex: 1;
            padding: 0.75rem;
            border-radius: 6px;
            text-align: center;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-visit {
            background: #00e640;
            color: #ffffff;
            border: none;
        }
        
        .btn-visit:hover {
            background: #00cc36;
        }
        
        .btn-review {
            background: transparent;
            color: #00e640;
            border: 2px solid #00e640;
        }
        
        .btn-review:hover {
            background: #00e640;
            color: #ffffff;
        }
        
        .vpn-notice {
            margin-top: 1rem;
            background: #4a2a2a;
            border: 1px solid #f44336;
            border-radius: 6px;
            padding: 0.75rem;
            font-size: 0.875rem;
            color: #f44336;
            display: flex;
            align-items: center;
        }
        
        .vpn-notice .icon {
            margin-right: 0.5rem;
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal {
            background: #333333;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            border: 1px solid #555555;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #555555;
        }
        
        .modal-title {
            font-size: 1.125rem;
            font-weight: bold;
            color: #ffffff;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #cccccc;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            color: #ffffff;
        }
        
        .modal-content {
            padding: 1.5rem;
            overflow-y: auto;
            color: #ffffff;
        }
        
        .lang-item {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .lang-flag {
            margin-right: 0.5rem;
            font-size: 1.125rem;
        }
        
        .term-item {
            display: flex;
            margin-bottom: 1rem;
        }
        
        .term-icon {
            flex-shrink: 0;
            margin-right: 1rem;
            font-size: 1.25rem;
        }
        
        .term-text {
            font-size: 0.875rem;
            line-height: 1.5;
        }
        
        .hidden {
            display: none !important;
        }
        
        /* Search Bar Styles */
        .search-section {
            background: #333333;
            border: 1px solid #555555;
            border-radius: 12px;
            padding: 0.625rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .search-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #00e640;
            text-align: center;
            margin-bottom: 0.5rem;
            text-shadow: 0 0 3px #00e640;
        }
        
        .search-container {
            display: flex;
            gap: 1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-input {
            flex: 1;
            background: #2a2a2a;
            border: 2px solid #555555;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #00e640;
            box-shadow: 0 0 8px rgba(0,230,64,0.3);
        }
        
        .search-input::placeholder {
            color: #999999;
        }
        
        .search-btn {
            background: #00e640;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            color: #ffffff;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .search-btn:hover {
            background: #00cc36;
            transform: translateY(-1px);
        }
        
        .clear-btn {
            background: transparent;
            border: 2px solid #00e640;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: #00e640;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .clear-btn:hover {
            background: #00e640;
            color: #ffffff;
        }
        
        .search-results-info {
            text-align: center;
            margin-top: 1rem;
            color: #cccccc;
            font-size: 0.875rem;
        }
        
        /* Active Filters Styles */
        .active-filters {
            margin-top: 1rem;
            padding: 0.75rem;
            background: #2a2a2a;
            border: 1px solid #555555;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .active-filters-label {
            font-size: 0.875rem;
            color: #00e640;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .filter-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            flex: 1;
        }
        
        .filter-tag {
            background: #00e640;
            color: #ffffff;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .filter-tag:hover {
            background: #00cc36;
        }
        
        .filter-tag-remove {
            background: none;
            border: none;
            color: #ffffff;
            font-size: 1rem;
            cursor: pointer;
            padding: 0;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .filter-tag-remove:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .casino-card.hidden {
            display: none;
        }
        
        /* Pagination Styles */
        .pagination-section {
            background: #333333;
            border: 1px solid #555555;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        
        .pagination-info {
            text-align: center;
            color: #cccccc;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            background: #2a2a2a;
            border: 2px solid #555555;
            color: #ffffff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            min-width: 44px;
            justify-content: center;
        }
        
        .pagination-btn:hover {
            background: #00e640;
            border-color: #00e640;
            color: #ffffff;
            transform: translateY(-1px);
        }
        
        .pagination-btn.active {
            background: #00e640;
            border-color: #00e640;
            color: #ffffff;
        }
        
        .pagination-btn.disabled {
            background: #1a1a1a;
            border-color: #333333;
            color: #666666;
            cursor: not-allowed;
            transform: none;
        }
        
        .pagination-btn.disabled:hover {
            background: #1a1a1a;
            border-color: #333333;
            color: #666666;
            transform: none;
        }
        
        .page-numbers {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }
        
        .page-ellipsis {
            color: #666666;
            padding: 0 0.5rem;
            font-weight: bold;
        }
        
        /* Top Pagination Styles */
        .top-pagination {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 1rem;
            gap: 0.25rem;
        }
        
        .top-pagination .pagination-btn {
            background: #2a2a2a;
            border: 1px solid #555555;
            color: #ffffff;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.125rem;
            min-width: 32px;
            justify-content: center;
            height: 28px;
        }
        
        .top-pagination .pagination-btn:hover {
            background: #00e640;
            border-color: #00e640;
            color: #ffffff;
        }
        
        .top-pagination .pagination-btn.active {
            background: #00e640;
            border-color: #00e640;
            color: #ffffff;
        }
        
        .top-pagination .pagination-btn.disabled {
            background: #1a1a1a;
            border-color: #333333;
            color: #666666;
            cursor: not-allowed;
        }
        
        .top-pagination .pagination-btn.disabled:hover {
            background: #1a1a1a;
            border-color: #333333;
            color: #666666;
        }
        
        .top-pagination .page-ellipsis {
            color: #666666;
            padding: 0 0.25rem;
            font-size: 0.75rem;
        }
        
        /* Responsive Search */
        @media (max-width: 768px) {
            .search-container {
                flex-direction: column;
            }
            
            .search-section {
                padding: 1.5rem;
            }
            
            .pagination-controls {
                gap: 0.25rem;
            }
            
            .pagination-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.75rem;
                min-width: 40px;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .card-content {
                padding: 1rem;
            }
            
            .modal {
                width: 95%;
                margin: 1rem;
            }
        }
        
        .advanced-search-btn {
            background: transparent;
            border: 2px solid #00e640;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: #00e640;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: bold;
        }
        
        .advanced-search-btn:hover {
            background: #00e640;
            color: #ffffff;
        }
        
        /* Advanced Search Modal Styles */
        .advanced-search-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1100;
        }
        
        .advanced-search-modal.hidden {
            display: none !important;
        }
        
        .advanced-search-content {
            background: #333333;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
            width: 95%;
            max-width: 900px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            border: 1px solid #555555;
            overflow: hidden;
        }
        
        .advanced-search-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #555555;
            background: #2a2a2a;
        }
        
        .advanced-search-title {
            font-size: 1.25rem;
            font-weight: bold;
            color: #00e640;
            margin: 0;
        }
        
        .advanced-search-close {
            background: none;
            border: none;
            color: #cccccc;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .advanced-search-close:hover {
            color: #ffffff;
        }
        
        .advanced-search-body {
            padding: 1.5rem;
            overflow-y: auto;
            color: #ffffff;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .filter-section {
            background: #2a2a2a;
            border: 1px solid #555555;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .filter-title {
            font-size: 1rem;
            font-weight: bold;
            color: #00e640;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #555555;
        }
        
        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .filter-checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.5rem;
            max-height: 150px;
            overflow-y: auto;
            padding: 0.5rem;
            background: #1a1a1a;
            border-radius: 6px;
        }
        
        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0;
        }
        
        .filter-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #00e640;
        }
        
        .filter-checkbox label {
            font-size: 0.875rem;
            color: #ffffff;
            cursor: pointer;
            line-height: 1.2;
        }
        
        .safety-range {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #1a1a1a;
            padding: 1rem;
            border-radius: 6px;
        }
        
        .safety-range label {
            font-size: 0.875rem;
            color: #cccccc;
            font-weight: bold;
        }
        
        .range-input {
            background: #333333;
            border: 2px solid #555555;
            border-radius: 6px;
            padding: 0.5rem;
            color: #ffffff;
            font-size: 0.875rem;
            width: 80px;
            text-align: center;
        }
        
        .range-input:focus {
            outline: none;
            border-color: #00e640;
        }
        
        .bonus-filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .bonus-filter {
            background: #1a1a1a;
            border: 2px solid #555555;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
            font-weight: bold;
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
        
        .bonus-filter.active {
            background: #00e640;
            border-color: #00e640;
            color: #ffffff;
        }
        
        .bonus-filter:hover {
            border-color: #00e640;
        }
        
        .advanced-search-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid #555555;
            background: #2a2a2a;
        }
        
        .filter-info {
            color: #cccccc;
            font-size: 0.875rem;
        }
        
        .filter-actions {
            display: flex;
            gap: 1rem;
        }
        
        .filter-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            border: none;
            font-size: 0.875rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn-apply {
            background: #00e640;
            color: #ffffff;
        }
        
        .filter-btn-apply:hover {
            background: #00cc36;
        }
        
        .filter-btn-reset {
            background: transparent;
            color: #00e640;
            border: 2px solid #00e640;
        }
        
        .filter-btn-reset:hover {
            background: #00e640;
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Search Section -->
        <div class="search-section">
            <h2 class="search-title">🔍 Find Your Perfect Casino</h2>
            <div class="search-container">
                <input type="text" 
                       class="search-input" 
                       id="casinoSearch" 
                       placeholder="Search casinos by name, safety rating, games, or features...">
                <button class="search-btn" id="searchBtn">
                    🔍 Search
                </button>
                <button class="advanced-search-btn" id="advancedSearchBtn" title="Advanced Search">
                    ⚙️ Filters
                </button>
                <button class="clear-btn" id="clearBtn">
                    Clear
                </button>
            </div>
            
            <!-- Active Filters Display -->
            <div class="active-filters" id="activeFilters" style="display: none;">
                <div class="active-filters-label">Active Filters:</div>
                <div class="filter-tags" id="filterTags"></div>
            </div>
            
            <div class="search-results-info" id="searchResults">
                Showing <?php echo count($casinos); ?> casinos from page <?php echo $current_page; ?> of <?php echo $total_pages; ?> (<?php echo $total_files; ?> total casinos)
            </div>
            
            <!-- Top Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="top-pagination">
                <!-- Previous Page -->
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo esc_url(get_pagination_url($current_page - 1)); ?>" class="pagination-btn" title="Previous page">
                        ⟨
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled" title="Previous page">
                        ⟨
                    </span>
                <?php endif; ?>
                
                <!-- Page Numbers (compact) -->
                <?php
                $start_page = max(1, $current_page - 1);
                $end_page = min($total_pages, $current_page + 1);
                
                // Show first page if we're not starting from it
                if ($start_page > 1) {
                    echo '<a href="' . esc_url(get_pagination_url(1)) . '" class="pagination-btn">1</a>';
                    if ($start_page > 2) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                }
                
                // Show current range
                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $current_page) {
                        echo '<span class="pagination-btn active">' . $i . '</span>';
                    } else {
                        echo '<a href="' . esc_url(get_pagination_url($i)) . '" class="pagination-btn">' . $i . '</a>';
                    }
                }
                
                // Show last page if we're not ending at it
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span class="page-ellipsis">...</span>';
                    }
                    echo '<a href="' . esc_url(get_pagination_url($total_pages)) . '" class="pagination-btn">' . $total_pages . '</a>';
                }
                ?>
                
                <!-- Next Page -->
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo esc_url(get_pagination_url($current_page + 1)); ?>" class="pagination-btn" title="Next page">
                        ⟩
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled" title="Next page">
                        ⟩
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="grid" id="casinoGrid">
            <?php foreach ($casinos as $idx => $casino): ?>
            <div class="casino-card">
                <!-- Logo -->
                <div class="logo-section">
                    <?php if ($casino['logo']): ?>
                        <img src="<?php echo esc_url($casino['logo']); ?>" alt="<?php echo esc_attr($casino['title']); ?>">
                    <?php else: ?>
                        <div class="logo-placeholder">
                            <svg width="32" height="32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect width="32" height="32" rx="8" fill="#555"/>
                                <path d="M16 9c-3.67 0-6.67 2.24-6.67 5 0 1.57 1.13 2.97 2.87 3.85l-.73 2.49a.67.67 0 0 0 .87.82l2.6-.87c.35.04.72.07 1.06.07 3.67 0 6.67-2.24 6.67-5S19.67 9 16 9Z" fill="#999"/>
                        </svg>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Content -->
                <div class="card-content">
                    <h2 class="casino-title"><?php echo esc_html($casino['title']); ?></h2>
                    <!-- Safety Index -->
                    <div class="safety-index">
                        <span class="safety-label">Safety Index:</span>
                        <span class="safety-value <?php
                            $safety_val = floatval($casino['safety_index']);
                            echo $safety_val >= 8 ? 'safety-high' : ($safety_val >= 5 ? 'safety-medium' : 'safety-low');
                        ?>">
                            <?php echo esc_html($casino['safety_index']); ?>
                        </span>
                        <?php if ($casino['safety_label']): ?>
                            <span class="safety-badge <?php
                                echo strtolower($casino['safety_label']) === 'high' ? 'badge-high' :
                                    (strtolower($casino['safety_label']) === 'medium' ? 'badge-medium' : 'badge-low');
                            ?>">
                                <?php echo esc_html($casino['safety_label']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <!-- Features -->
                    <ul class="features-list">
                        <?php foreach ($casino['features'] as $feature): ?>
                            <li class="feature-item <?php
                                echo $feature['type'] === 'positive' ? 'feature-positive' : ($feature['type'] === 'partial' ? 'feature-partial' : 'feature-negative');
                            ?>">
                                <span class="feature-icon"><?php echo $feature['icon']; ?></span>
                                <span><?php echo esc_html($feature['text']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <!-- Bonus -->
                    <div class="bonus-section">
                        <div class="bonus-header">
                            <span class="icon">🎁</span>
                            <span>Bonuses</span>
                        </div>

                        <div>
                            <!-- Deposit Bonus -->
                            <div class="bonus-item">
                                <span class="icon <?php echo $casino['has_deposit_bonus'] ? 'bonus-available' : 'bonus-unavailable'; ?>">
                                    <?php echo $casino['has_deposit_bonus'] ? '✅' : '❌'; ?>
                                </span>
                                <span>Deposit bonus <?php echo $casino['has_deposit_bonus'] ? 'available' : 'not available'; ?></span>
                            </div>
                            
                            <!-- No Deposit Bonus -->
                            <div class="bonus-item">
                                <span class="icon <?php echo $casino['has_nodeposit_bonus'] ? 'bonus-available' : 'bonus-unavailable'; ?>">
                                    <?php echo $casino['has_nodeposit_bonus'] ? '✅' : '❌'; ?>
                                </span>
                                <span>No-deposit bonus <?php echo $casino['has_nodeposit_bonus'] ? 'available' : 'not available'; ?></span>
                            </div>
                        </div>

                        <a href="#" class="tc-link tc-btn" data-idx="<?php echo $idx; ?>">*T&Cs apply</a>
                    </div>
                    <!-- Details -->
                    <div class="detail-section">
                        <div class="section-header">
                            <span class="icon">🌐</span>
                                <span>Language Options</span>
                            </div>
                        <div class="detail-content">
                            <div class="detail-item">
                                    <strong>Website:</strong> <?php echo count($casino['languages']); ?> languages
                                <a href="#" class="show-btn lang-btn" data-idx="<?php echo $idx; ?>" data-type="website">Show all</a>
                                </div>
                            <div class="detail-item">
                                    <strong>Live chat:</strong> <?php echo count($casino['livechat_langs']); ?> languages
                                <a href="#" class="show-btn lang-btn" data-idx="<?php echo $idx; ?>" data-type="livechat">Show all</a>
                                </div>
                            <div class="detail-item">
                                    <strong>Support:</strong> <?php echo count($casino['support_langs']); ?> languages
                                <a href="#" class="show-btn lang-btn" data-idx="<?php echo $idx; ?>" data-type="support">Show all</a>
                                </div>
                            </div>
                        </div>
                    <div class="detail-section">
                        <div class="section-header">
                            <span class="icon">🎰</span>
                                <span>Available Games</span>
                            </div>
                        <div class="games-grid">
                                <?php
                                $game_base_url = site_url('/wp-content/uploads/games_full/');
                                $games_to_show = array_slice($casino['games'], 0, 8);
                                $total_games = count($casino['games']);
                                $standard_games_map = [
                                    'slots' => '✔️', 'roulette' => '✔️', 'blackjack' => '✔️',
                                    'video poker' => '✔️', 'bingo' => '✔️', 'no betting' => '➖'
                                ];

                                foreach ($games_to_show as $game):
                                    $sanitized_for_image = sanitize_game_name($game);
                                    $game_lower = strtolower($game);

                                    // Attempt to find the image URL
                                    $img_url = '';
                                    foreach (['png', 'svg', 'jpg'] as $ext):
                                        $test_url = $game_base_url . $sanitized_for_image . '.' . $ext;
                                        $ch = curl_init($test_url);
                                        curl_setopt($ch, CURLOPT_NOBODY, true);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                                        curl_exec($ch);
                                    if (!curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200):
                                            $img_url = $test_url;
                                        curl_close($ch);
                                            break;
                                        endif;
                                    curl_close($ch);
                                    endforeach;
                            ?>
                                <div class="game-badge">
                                        <?php if ($img_url): ?>
                                        <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($game); ?>">
                                    <?php elseif (isset($standard_games_map[$game_lower])): ?>
                                        <span><?php echo $standard_games_map[$game_lower]; ?></span>
                                        <?php endif; ?>
                                    <span><?php echo esc_html($game); ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php if ($total_games > 8): ?>
                                <a href="#" class="show-btn show-all-btn" data-idx="<?php echo $idx; ?>" data-type="games">+<?php echo ($total_games - 8); ?> more</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <div class="detail-section">
                        <div class="section-header">
                            <span class="icon">💳</span>
                                <span>Payment Methods</span>
                            </div>
                        <div class="payment-grid">
                                <?php
                            $payment_base_url = site_url('/wp-content/uploads/payment_methods_full/');
                            $payments_to_show = array_slice($casino['payments'], 0, 6);
                                $total_payments = count($casino['payments']);

                            foreach ($payments_to_show as $payment):
                                $sanitized_for_image = sanitize_payment_name($payment);
                                
                                // Attempt to find the image URL
                                    $img_url = '';
                                foreach (['png', 'svg', 'jpg'] as $ext):
                                    $test_url = $payment_base_url . $sanitized_for_image . '.' . $ext;
                                        $ch = curl_init($test_url);
                                        curl_setopt($ch, CURLOPT_NOBODY, true);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                                        curl_exec($ch);
                                    if (!curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200):
                                            $img_url = $test_url;
                                        curl_close($ch);
                                            break;
                                        endif;
                                    curl_close($ch);
                                endforeach;
                            ?>
                                <div class="payment-badge">
                                        <?php if ($img_url): ?>
                                        <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($payment); ?>">
                                        <?php endif; ?>
                                    <span><?php echo esc_html($payment); ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php if ($total_payments > 6): ?>
                                <a href="#" class="show-btn show-all-btn" data-idx="<?php echo $idx; ?>" data-type="payments">+<?php echo ($total_payments - 6); ?> more</a>
                                <?php endif; ?>
                        </div>
                    </div>
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="<?php echo esc_url($casino['permalink']); ?>" target="_blank" class="btn btn-visit">
                            ▶ Visit Casino
                        </a>
                        <a href="<?php echo esc_url($casino['permalink']); ?>" target="_blank" class="btn btn-review">
                            📝 Read Review
                        </a>
                    </div>
                    <!-- VPN Note -->
                    <?php
                    $vpn_allowed_value = $casino['vpn_note'];
                    $show_vpn_not_allowed = is_bool($vpn_allowed_value) && $vpn_allowed_value === false ||
                        is_string($vpn_allowed_value) && in_array(strtolower($vpn_allowed_value), ['not allowed', 'not_allowed']);
                    if ($show_vpn_not_allowed): ?>
                        <div class="vpn-notice">
                            <span class="icon">🚫</span>VPN not allowed
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination Section -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-section">
            <div class="pagination-info">
                Showing page <?php echo $current_page; ?> of <?php echo $total_pages; ?> 
                (<?php echo count($casinos); ?> of <?php echo $total_files; ?> total casinos)
            </div>
            <div class="pagination-controls">
                <!-- First Page -->
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo esc_url(get_pagination_url(1)); ?>" class="pagination-btn">
                        ⟪ First
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">
                        ⟪ First
                    </span>
                <?php endif; ?>
                
                <!-- Previous Page -->
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo esc_url(get_pagination_url($current_page - 1)); ?>" class="pagination-btn">
                        ⟨ Prev
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">
                        ⟨ Prev
                    </span>
                <?php endif; ?>
                
                <!-- Page Numbers -->
                <div class="page-numbers">
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    // Show first page if we're not starting from it
                    if ($start_page > 1) {
                        echo '<a href="' . esc_url(get_pagination_url(1)) . '" class="pagination-btn">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                    }
                    
                    // Show current range
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $current_page) {
                            echo '<span class="pagination-btn active">' . $i . '</span>';
                        } else {
                            echo '<a href="' . esc_url(get_pagination_url($i)) . '" class="pagination-btn">' . $i . '</a>';
                        }
                    }
                    
                    // Show last page if we're not ending at it
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                        echo '<a href="' . esc_url(get_pagination_url($total_pages)) . '" class="pagination-btn">' . $total_pages . '</a>';
                    }
                    ?>
                </div>
                
                <!-- Next Page -->
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo esc_url(get_pagination_url($current_page + 1)); ?>" class="pagination-btn">
                        Next ⟩
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">
                        Next ⟩
                    </span>
                <?php endif; ?>
                
                <!-- Last Page -->
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo esc_url(get_pagination_url($total_pages)); ?>" class="pagination-btn">
                        Last ⟫
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">
                        Last ⟫
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- Modal -->
    <div class="modal-overlay hidden" id="modalOverlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle"></h3>
                <button class="modal-close" id="modalClose">×</button>
            </div>
            <div class="modal-content" id="modalList"></div>
        </div>
    </div>
    
    <!-- T&C Modal -->
    <div class="modal-overlay hidden" id="tcModalOverlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="tcModalTitle">T&Cs</h3>
                <button class="modal-close" id="tcModalClose">×</button>
            </div>
            <div class="modal-content" id="tcModalContent"></div>
        </div>
    </div>
    <!-- Advanced Search Modal -->
    <div class="advanced-search-modal hidden" id="advancedSearchModal">
        <div class="advanced-search-content">
            <div class="advanced-search-header">
                <h3 class="advanced-search-title">🔍 Advanced Search & Filters</h3>
                <button class="advanced-search-close" id="advancedSearchClose">×</button>
            </div>
            <div class="advanced-search-body">
                <!-- Safety Index Filter -->
                <div class="filter-section">
                    <div class="filter-title">
                        🛡️ Safety Index Range
                    </div>
                    <div class="safety-range">
                        <label>From:</label>
                        <input type="number" class="range-input" id="safetyMin" min="0" max="10" step="0.1" placeholder="0">
                        <label>To:</label>
                        <input type="number" class="range-input" id="safetyMax" min="0" max="10" step="0.1" placeholder="10">
                    </div>
                </div>

                <!-- Bonus Filters -->
                <div class="filter-section">
                    <div class="filter-title">
                        🎁 Bonus Types
                    </div>
                    <div class="bonus-filters">
                        <div class="bonus-filter" id="depositBonusFilter" data-filter="deposit">
                            ✅ Deposit Bonus
                        </div>
                        <div class="bonus-filter" id="noDepositBonusFilter" data-filter="nodeposit">
                            🆓 No Deposit Bonus
                        </div>
                    </div>
                </div>

                <!-- Language Options -->
                <div class="filter-section">
                    <div class="filter-title">
                        🌐 Website Languages
                    </div>
                    <div class="filter-checkbox-group" id="websiteLanguages">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <div class="filter-section">
                    <div class="filter-title">
                        💬 Live Chat Languages
                    </div>
                    <div class="filter-checkbox-group" id="livechatLanguages">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <div class="filter-section">
                    <div class="filter-title">
                        🎧 Support Languages
                    </div>
                    <div class="filter-checkbox-group" id="supportLanguages">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="filter-section">
                    <div class="filter-title">
                        💳 Payment Methods
                    </div>
                    <div class="filter-checkbox-group" id="paymentMethods">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <!-- Available Games -->
                <div class="filter-section">
                    <div class="filter-title">
                        🎰 Available Games
                    </div>
                    <div class="filter-checkbox-group" id="availableGames">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
            </div>
            <div class="advanced-search-footer">
                <div class="filter-info">
                    <span id="filterResultCount">All casinos</span>
                </div>
                <div class="filter-actions">
                    <button class="filter-btn filter-btn-reset" id="resetFilters">Reset All</button>
                    <button class="filter-btn filter-btn-apply" id="applyFilters">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var casinos = <?php echo json_encode($casinos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // Search functionality
        var searchInput = document.getElementById('casinoSearch');
        var searchBtn = document.getElementById('searchBtn');
        var clearBtn = document.getElementById('clearBtn');
        var searchResults = document.getElementById('searchResults');
        var casinoCards = document.querySelectorAll('.casino-card');
        var totalCasinos = casinoCards.length;
        
        // Advanced search elements
        var advancedSearchBtn = document.getElementById('advancedSearchBtn');
        var advancedSearchModal = document.getElementById('advancedSearchModal');
        var advancedSearchClose = document.getElementById('advancedSearchClose');
        var applyFiltersBtn = document.getElementById('applyFilters');
        var resetFiltersBtn = document.getElementById('resetFilters');
        var filterResultCount = document.getElementById('filterResultCount');
        
        // Active filters array to track what's in the search bar
        var activeFilters = [];
        
        // Filter state
        var advancedFilters = {
            safetyMin: null,
            safetyMax: null,
            hasDepositBonus: false,
            hasNoDepositBonus: false,
            websiteLanguages: [],
            livechatLanguages: [],
            supportLanguages: [],
            paymentMethods: [],
            games: []
        };
        
        // Add click handlers for filtering
        function addCardClickHandlers() {
            // Safety index filtering
            document.querySelectorAll('.safety-index').forEach(function(element, cardIndex) {
                element.style.cursor = 'pointer';
                element.title = 'Click to filter by this safety index';
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    var safetyValue = casinos[cardIndex].safety_index;
                    var safetyLabel = casinos[cardIndex].safety_label;
                    var filterText = 'safety:' + safetyValue + (safetyLabel ? '(' + safetyLabel.toLowerCase() + ')' : '');
                    addFilterToSearchBar(filterText);
                });
            });
            
            // Bonus filtering
            document.querySelectorAll('.bonus-item').forEach(function(element) {
                element.style.cursor = 'pointer';
                element.title = 'Click to filter by this bonus type';
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    var bonusText = this.textContent.trim();
                    var filterText = '';
                    
                    if (bonusText.includes('Deposit bonus available')) {
                        filterText = 'bonus:deposit';
                    } else if (bonusText.includes('No-deposit bonus available')) {
                        filterText = 'bonus:nodeposit';
                    } else if (bonusText.includes('Deposit bonus not available')) {
                        filterText = 'bonus:no-deposit';
                    } else if (bonusText.includes('No-deposit bonus not available')) {
                        filterText = 'bonus:no-nodeposit';
                    }
                    
                    if (filterText) {
                        addFilterToSearchBar(filterText);
                    }
                });
            });
            
            // Language filtering - make the detail items clickable
            document.querySelectorAll('.detail-section').forEach(function(section) {
                var header = section.querySelector('.section-header');
                if (header && header.textContent.includes('Language Options')) {
                    var detailItems = section.querySelectorAll('.detail-item');
                    detailItems.forEach(function(item) {
                        item.style.cursor = 'pointer';
                        item.title = 'Click to filter by this language option';
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            var text = this.textContent.trim();
                            var filterText = '';
                            
                            if (text.includes('Website:')) {
                                filterText = 'lang:website';
                            } else if (text.includes('Live chat:')) {
                                filterText = 'lang:livechat';
                            } else if (text.includes('Support:')) {
                                filterText = 'lang:support';
                            }
                            
                            if (filterText) {
                                addFilterToSearchBar(filterText);
                            }
                        });
                    });
                }
            });
            
            // Payment method filtering
            document.querySelectorAll('.payment-badge').forEach(function(element) {
                element.style.cursor = 'pointer';
                element.title = 'Click to filter by this payment method';
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    var paymentName = this.textContent.trim();
                    var filterText = 'payment:' + paymentName.toLowerCase();
                    addFilterToSearchBar(filterText);
                });
            });
            
            // Game filtering
            document.querySelectorAll('.game-badge').forEach(function(element) {
                element.style.cursor = 'pointer';
                element.title = 'Click to filter by this game type';
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    var gameName = this.textContent.trim();
                    var filterText = 'game:' + gameName.toLowerCase();
                    addFilterToSearchBar(filterText);
                });
            });
        }
        
        // Add filter to search bar
        function addFilterToSearchBar(filterText) {
            var currentValue = searchInput.value.trim();
            var filters = currentValue ? currentValue.split(' ') : [];
            
            // Check if this filter already exists
            var exists = filters.some(function(filter) {
                return filter === filterText;
            });
            
            if (!exists) {
                filters.push(filterText);
                searchInput.value = filters.join(' ');
                updateActiveFiltersDisplay();
                performFilteredSearch();
            }
        }
        
        // Remove filter from search bar
        function removeFilterFromSearchBar(filterText) {
            var currentValue = searchInput.value.trim();
            var filters = currentValue ? currentValue.split(' ') : [];
            filters = filters.filter(function(filter) {
                return filter !== filterText;
            });
            searchInput.value = filters.join(' ');
            updateActiveFiltersDisplay();
            performFilteredSearch();
        }
        
        // Update active filters display
        function updateActiveFiltersDisplay() {
            var searchText = searchInput.value.trim();
            var filters = parseFilters(searchText);
            var activeFiltersDiv = document.getElementById('activeFilters');
            var filterTagsDiv = document.getElementById('filterTags');
            
            // Clear existing tags
            filterTagsDiv.innerHTML = '';
            
            var hasFilters = false;
            
            // Add safety min/max filters
            if (filters.safetyMin !== null) {
                hasFilters = true;
                var tag = createFilterTag('Safety Min: ' + filters.safetyMin, 'safety:min' + filters.safetyMin);
                filterTagsDiv.appendChild(tag);
            }
            if (filters.safetyMax !== null) {
                hasFilters = true;
                var tag = createFilterTag('Safety Max: ' + filters.safetyMax, 'safety:max' + filters.safetyMax);
                filterTagsDiv.appendChild(tag);
            }
            
            // Add old-style safety filters
            filters.safety.forEach(function(safety) {
                hasFilters = true;
                var tag = createFilterTag('Safety: ' + safety, 'safety:' + safety);
                filterTagsDiv.appendChild(tag);
            });
            
            // Add bonus filters
            filters.bonus.forEach(function(bonus) {
                hasFilters = true;
                var label = '';
                switch(bonus) {
                    case 'deposit': label = 'Has Deposit Bonus'; break;
                    case 'nodeposit': label = 'Has No-Deposit Bonus'; break;
                    case 'no-deposit': label = 'No Deposit Bonus'; break;
                    case 'no-nodeposit': label = 'No No-Deposit Bonus'; break;
                    default: label = 'Bonus: ' + bonus;
                }
                var tag = createFilterTag(label, 'bonus:' + bonus);
                filterTagsDiv.appendChild(tag);
            });
            
            // Add website language filters
            filters.websiteLanguages.forEach(function(lang) {
                hasFilters = true;
                var tag = createFilterTag('Website: ' + lang.charAt(0).toUpperCase() + lang.slice(1), 'lang:website:' + lang);
                filterTagsDiv.appendChild(tag);
            });
            
            // Add live chat language filters
            filters.livechatLanguages.forEach(function(lang) {
                hasFilters = true;
                var tag = createFilterTag('Live Chat: ' + lang.charAt(0).toUpperCase() + lang.slice(1), 'lang:livechat:' + lang);
                filterTagsDiv.appendChild(tag);
            });
            
            // Add support language filters
            filters.supportLanguages.forEach(function(lang) {
                hasFilters = true;
                var tag = createFilterTag('Support: ' + lang.charAt(0).toUpperCase() + lang.slice(1), 'lang:support:' + lang);
                filterTagsDiv.appendChild(tag);
            });
            
            // Add old-style language filters
            filters.lang.forEach(function(lang) {
                hasFilters = true;
                var label = '';
                switch(lang) {
                    case 'website': label = 'Website Languages'; break;
                    case 'livechat': label = 'Live Chat Languages'; break;
                    case 'support': label = 'Support Languages'; break;
                    default: label = 'Language: ' + lang;
                }
                var tag = createFilterTag(label, 'lang:' + lang);
                filterTagsDiv.appendChild(tag);
            });
            
            // Add payment filters
            filters.payment.forEach(function(payment) {
                hasFilters = true;
                var tag = createFilterTag('Payment: ' + payment.charAt(0).toUpperCase() + payment.slice(1), 'payment:' + payment);
                filterTagsDiv.appendChild(tag);
            });
            
            // Add game filters
            filters.game.forEach(function(game) {
                hasFilters = true;
                var tag = createFilterTag('Game: ' + game.charAt(0).toUpperCase() + game.slice(1), 'game:' + game);
                filterTagsDiv.appendChild(tag);
            });
            
            // Show or hide the active filters section
            if (hasFilters) {
                activeFiltersDiv.style.display = 'flex';
            } else {
                activeFiltersDiv.style.display = 'none';
            }
        }
        
        // Create a filter tag element
        function createFilterTag(label, filterValue) {
            var tag = document.createElement('div');
            tag.className = 'filter-tag';
            
            var labelSpan = document.createElement('span');
            labelSpan.textContent = label;
            tag.appendChild(labelSpan);
            
            var removeBtn = document.createElement('button');
            removeBtn.className = 'filter-tag-remove';
            removeBtn.innerHTML = '×';
            removeBtn.title = 'Remove this filter';
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                removeFilterFromSearchBar(filterValue);
            });
            tag.appendChild(removeBtn);
            
            return tag;
        }
        
        // Parse filters from search input
        function parseFilters(searchText) {
            var filters = {
                safety: [],
                safetyMin: null,
                safetyMax: null,
                bonus: [],
                lang: [],
                websiteLanguages: [],
                livechatLanguages: [],
                supportLanguages: [],
                payment: [],
                game: [],
                text: []
            };
            
            var terms = searchText.split(' ');
            
            terms.forEach(function(term) {
                if (term.startsWith('safety:')) {
                    var safetyValue = term.substring(7);
                    if (safetyValue.startsWith('min')) {
                        filters.safetyMin = parseFloat(safetyValue.substring(3));
                    } else if (safetyValue.startsWith('max')) {
                        filters.safetyMax = parseFloat(safetyValue.substring(3));
                    } else {
                        filters.safety.push(safetyValue);
                    }
                } else if (term.startsWith('bonus:')) {
                    filters.bonus.push(term.substring(6));
                } else if (term.startsWith('lang:')) {
                    var langParts = term.substring(5).split(':');
                    if (langParts.length === 2) {
                        var langType = langParts[0];
                        var langName = langParts[1];
                        if (langType === 'website') {
                            filters.websiteLanguages.push(langName);
                        } else if (langType === 'livechat') {
                            filters.livechatLanguages.push(langName);
                        } else if (langType === 'support') {
                            filters.supportLanguages.push(langName);
                        }
                    } else {
                        filters.lang.push(langParts[0]);
                    }
                } else if (term.startsWith('payment:')) {
                    filters.payment.push(term.substring(8));
                } else if (term.startsWith('game:')) {
                    filters.game.push(term.substring(5));
                } else if (term.trim() !== '') {
                    filters.text.push(term);
                }
            });
            
            return filters;
        }
        
        // Perform filtered search
        function performFilteredSearch() {
            var searchText = searchInput.value.toLowerCase().trim();
            var filters = parseFilters(searchText);
            var visibleCount = 0;
            
            casinoCards.forEach(function(card, index) {
                var casino = casinos[index];
                var shouldShow = true;
                
                // Safety index filters
                if (filters.safety.length > 0) {
                    var safetyMatch = filters.safety.some(function(safetyFilter) {
                        if (safetyFilter.includes('(')) {
                            // Handle safety with label like "8.5(high)"
                            var parts = safetyFilter.split('(');
                            var value = parts[0];
                            var label = parts[1].replace(')', '');
                            return casino.safety_index.toString() === value && 
                                   casino.safety_label.toLowerCase() === label;
                } else {
                            return casino.safety_index.toString() === safetyFilter;
                        }
                    });
                    if (!safetyMatch) shouldShow = false;
                }
                
                // Safety min/max filters
                if (filters.safetyMin !== null && parseFloat(casino.safety_index) < filters.safetyMin) {
                    shouldShow = false;
                }
                if (filters.safetyMax !== null && parseFloat(casino.safety_index) > filters.safetyMax) {
                    shouldShow = false;
                }
                
                // Bonus filters
                if (filters.bonus.length > 0) {
                    var bonusMatch = filters.bonus.some(function(bonusFilter) {
                        switch (bonusFilter) {
                            case 'deposit':
                                return casino.has_deposit_bonus;
                            case 'nodeposit':
                                return casino.has_nodeposit_bonus;
                            case 'no-deposit':
                                return !casino.has_deposit_bonus;
                            case 'no-nodeposit':
                                return !casino.has_nodeposit_bonus;
                            default:
                                return false;
                        }
                    });
                    if (!bonusMatch) shouldShow = false;
                }
                
                // Specific language filters
                if (filters.websiteLanguages.length > 0) {
                    var websiteLangMatch = filters.websiteLanguages.some(function(lang) {
                        return casino.languages.some(function(casinoLang) {
                            return casinoLang.toLowerCase().includes(lang);
                        });
                    });
                    if (!websiteLangMatch) shouldShow = false;
                }
                
                if (filters.livechatLanguages.length > 0) {
                    var livechatLangMatch = filters.livechatLanguages.some(function(lang) {
                        return casino.livechat_langs.some(function(casinoLang) {
                            return casinoLang.toLowerCase().includes(lang);
                        });
                    });
                    if (!livechatLangMatch) shouldShow = false;
                }
                
                if (filters.supportLanguages.length > 0) {
                    var supportLangMatch = filters.supportLanguages.some(function(lang) {
                        return casino.support_langs.some(function(casinoLang) {
                            return casinoLang.toLowerCase().includes(lang);
                        });
                    });
                    if (!supportLangMatch) shouldShow = false;
                }
                
                // General language filters
                if (filters.lang.length > 0) {
                    var langMatch = filters.lang.some(function(langFilter) {
                        switch (langFilter) {
                            case 'website':
                                return casino.languages.length > 0;
                            case 'livechat':
                                return casino.livechat_langs.length > 0;
                            case 'support':
                                return casino.support_langs.length > 0;
                            default:
                                return false;
                        }
                    });
                    if (!langMatch) shouldShow = false;
                }
                
                // Payment method filters
                if (filters.payment.length > 0) {
                    var paymentMatch = filters.payment.some(function(paymentFilter) {
                        return casino.payments.some(function(payment) {
                            return payment.toLowerCase().includes(paymentFilter);
                        });
                    });
                    if (!paymentMatch) shouldShow = false;
                }
                
                // Game filters
                if (filters.game.length > 0) {
                    var gameMatch = filters.game.some(function(gameFilter) {
                        return casino.games.some(function(game) {
                            return game.toLowerCase().includes(gameFilter);
                        });
                    });
                    if (!gameMatch) shouldShow = false;
                }
                
                // Text search (non-filter terms)
                if (filters.text.length > 0) {
                    var textMatch = filters.text.some(function(textTerm) {
                        return casino.title.toLowerCase().includes(textTerm) ||
                               casino.safety_index.toString().includes(textTerm) ||
                               casino.safety_label.toLowerCase().includes(textTerm) ||
                               casino.games.some(function(game) { return game.toLowerCase().includes(textTerm); }) ||
                               casino.payments.some(function(payment) { return payment.toLowerCase().includes(textTerm); }) ||
                               casino.features.some(function(feature) { return feature.text.toLowerCase().includes(textTerm); }) ||
                               (casino.bonus && casino.bonus.toLowerCase().includes(textTerm));
                    });
                    if (!textMatch) shouldShow = false;
                }
                
                if (shouldShow) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });
            
            // Update results info
            if (searchText === '') {
                searchResults.textContent = 'Showing <?php echo count($casinos); ?> casinos from page <?php echo $current_page; ?> of <?php echo $total_pages; ?> (<?php echo $total_files; ?> total casinos)';
            } else {
                searchResults.textContent = 'Found ' + visibleCount + ' of ' + totalCasinos + ' casinos on this page';
            }
        }
        
        // Initialize advanced search
        function initAdvancedSearch() {
            // Collect all unique values from casinos data
            var allWebsiteLanguages = new Set();
            var allLivechatLanguages = new Set();
            var allSupportLanguages = new Set();
            var allPaymentMethods = new Set();
            var allGames = new Set();
            
            casinos.forEach(function(casino) {
                casino.languages.forEach(function(lang) { allWebsiteLanguages.add(lang); });
                casino.livechat_langs.forEach(function(lang) { allLivechatLanguages.add(lang); });
                casino.support_langs.forEach(function(lang) { allSupportLanguages.add(lang); });
                casino.payments.forEach(function(method) { allPaymentMethods.add(method); });
                casino.games.forEach(function(game) { allGames.add(game); });
            });
            
            // Populate filter options
            populateFilterOptions('websiteLanguages', Array.from(allWebsiteLanguages).sort());
            populateFilterOptions('livechatLanguages', Array.from(allLivechatLanguages).sort());
            populateFilterOptions('supportLanguages', Array.from(allSupportLanguages).sort());
            populateFilterOptions('paymentMethods', Array.from(allPaymentMethods).sort());
            populateFilterOptions('availableGames', Array.from(allGames).sort());
        }
        
        function populateFilterOptions(containerId, options) {
            var container = document.getElementById(containerId);
            container.innerHTML = '';
            
            options.forEach(function(option) {
                var checkboxDiv = document.createElement('div');
                checkboxDiv.className = 'filter-checkbox';
                
                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = containerId + '_' + option.replace(/\s+/g, '_');
                checkbox.value = option;
                
                var label = document.createElement('label');
                label.setAttribute('for', checkbox.id);
                label.textContent = option;
                
                checkboxDiv.appendChild(checkbox);
                checkboxDiv.appendChild(label);
                container.appendChild(checkboxDiv);
            });
        }
        
        // Advanced search modal handlers
        if (advancedSearchBtn && advancedSearchModal) {
            advancedSearchBtn.addEventListener('click', function() {
                advancedSearchModal.classList.remove('hidden');
                advancedSearchModal.style.display = 'flex';
            });
        } else {
            // Fallback: try to find the button again
            setTimeout(function() {
                var btn = document.getElementById('advancedSearchBtn');
                var modal = document.getElementById('advancedSearchModal');
                if (btn && modal) {
                    btn.addEventListener('click', function() {
                        modal.classList.remove('hidden');
                        modal.style.display = 'flex';
                    });
                }
            }, 100);
        }
        
        advancedSearchClose.addEventListener('click', function() {
            advancedSearchModal.classList.add('hidden');
            advancedSearchModal.style.display = 'none';
        });
        
        advancedSearchModal.addEventListener('click', function(e) {
            if (e.target === advancedSearchModal) {
                advancedSearchModal.classList.add('hidden');
                advancedSearchModal.style.display = 'none';
            }
        });
        
        // Bonus filter toggles
        document.getElementById('depositBonusFilter').addEventListener('click', function() {
            this.classList.toggle('active');
            advancedFilters.hasDepositBonus = this.classList.contains('active');
        });
        
        document.getElementById('noDepositBonusFilter').addEventListener('click', function() {
            this.classList.toggle('active');
            advancedFilters.hasNoDepositBonus = this.classList.contains('active');
        });
        
        // Apply filters
        applyFiltersBtn.addEventListener('click', function() {
            // Get safety range
            advancedFilters.safetyMin = parseFloat(document.getElementById('safetyMin').value) || null;
            advancedFilters.safetyMax = parseFloat(document.getElementById('safetyMax').value) || null;
            
            // Get selected options
            advancedFilters.websiteLanguages = getSelectedCheckboxValues('websiteLanguages');
            advancedFilters.livechatLanguages = getSelectedCheckboxValues('livechatLanguages');
            advancedFilters.supportLanguages = getSelectedCheckboxValues('supportLanguages');
            advancedFilters.paymentMethods = getSelectedCheckboxValues('paymentMethods');
            advancedFilters.games = getSelectedCheckboxValues('availableGames');
            
            // Convert advanced filters to search bar filters
            var searchFilters = [];
            
            // Safety range filters
            if (advancedFilters.safetyMin !== null) {
                searchFilters.push('safety:min' + advancedFilters.safetyMin);
            }
            if (advancedFilters.safetyMax !== null) {
                searchFilters.push('safety:max' + advancedFilters.safetyMax);
            }
            
            // Bonus filters
            if (advancedFilters.hasDepositBonus) {
                searchFilters.push('bonus:deposit');
            }
            if (advancedFilters.hasNoDepositBonus) {
                searchFilters.push('bonus:nodeposit');
            }
            
            // Language filters
            advancedFilters.websiteLanguages.forEach(function(lang) {
                searchFilters.push('lang:website:' + lang.toLowerCase());
            });
            advancedFilters.livechatLanguages.forEach(function(lang) {
                searchFilters.push('lang:livechat:' + lang.toLowerCase());
            });
            advancedFilters.supportLanguages.forEach(function(lang) {
                searchFilters.push('lang:support:' + lang.toLowerCase());
            });
            
            // Payment method filters
            advancedFilters.paymentMethods.forEach(function(method) {
                searchFilters.push('payment:' + method.toLowerCase());
            });
            
            // Game filters
            advancedFilters.games.forEach(function(game) {
                searchFilters.push('game:' + game.toLowerCase());
            });
            
            // Update search input with filters
            searchInput.value = searchFilters.join(' ');
            updateActiveFiltersDisplay();
            performFilteredSearch();
            
            // Close modal
            advancedSearchModal.classList.add('hidden');
            advancedSearchModal.style.display = 'none';
        });
        
        // Reset filters
        resetFiltersBtn.addEventListener('click', function() {
            // Reset form inputs
            document.getElementById('safetyMin').value = '';
            document.getElementById('safetyMax').value = '';
            
            // Reset bonus filters
            document.getElementById('depositBonusFilter').classList.remove('active');
            document.getElementById('noDepositBonusFilter').classList.remove('active');
            
            // Uncheck all checkboxes
            var checkboxes = advancedSearchModal.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });
            
            // Reset active filters
            advancedFilters = {
                safetyMin: null,
                safetyMax: null,
                hasDepositBonus: false,
                hasNoDepositBonus: false,
                websiteLanguages: [],
                livechatLanguages: [],
                supportLanguages: [],
                paymentMethods: [],
                games: []
            };
            
            // Clear search input and show all casinos
            searchInput.value = '';
            updateActiveFiltersDisplay();
            performFilteredSearch();
        });
        
        function getSelectedCheckboxValues(containerId) {
            var container = document.getElementById(containerId);
            var checkboxes = container.querySelectorAll('input[type="checkbox"]:checked');
            return Array.from(checkboxes).map(function(checkbox) {
                return checkbox.value;
            });
        }
        
        function performAdvancedSearch() {
            var visibleCount = 0;
            
            casinoCards.forEach(function(card, index) {
                var casino = casinos[index];
                var shouldShow = true;
                
                // Safety index filter
                if (advancedFilters.safetyMin !== null && parseFloat(casino.safety_index) < advancedFilters.safetyMin) {
                    shouldShow = false;
                }
                if (advancedFilters.safetyMax !== null && parseFloat(casino.safety_index) > advancedFilters.safetyMax) {
                    shouldShow = false;
                }
                
                // Bonus filters
                if (advancedFilters.hasDepositBonus && !casino.has_deposit_bonus) {
                    shouldShow = false;
                }
                if (advancedFilters.hasNoDepositBonus && !casino.has_nodeposit_bonus) {
                    shouldShow = false;
                }
                
                // Language filters
                if (advancedFilters.websiteLanguages.length > 0) {
                    if (!advancedFilters.websiteLanguages.some(function(lang) {
                        return casino.languages.includes(lang);
                    })) {
                        shouldShow = false;
                    }
                }
                
                if (advancedFilters.livechatLanguages.length > 0) {
                    if (!advancedFilters.livechatLanguages.some(function(lang) {
                        return casino.livechat_langs.includes(lang);
                    })) {
                        shouldShow = false;
                    }
                }
                
                if (advancedFilters.supportLanguages.length > 0) {
                    if (!advancedFilters.supportLanguages.some(function(lang) {
                        return casino.support_langs.includes(lang);
                    })) {
                        shouldShow = false;
                    }
                }
                
                // Payment method filters
                if (advancedFilters.paymentMethods.length > 0) {
                    if (!advancedFilters.paymentMethods.some(function(method) {
                        return casino.payments.includes(method);
                    })) {
                        shouldShow = false;
                    }
                }
                
                // Game filters
                if (advancedFilters.games.length > 0) {
                    if (!advancedFilters.games.some(function(game) {
                        return casino.games.includes(game);
                    })) {
                        shouldShow = false;
                    }
                }
                
                // Apply text search as well
                var searchTerm = searchInput.value.toLowerCase().trim();
                if (searchTerm !== '') {
                    var textMatch = filters.text.some(function(textTerm) {
                        return casino.title.toLowerCase().includes(textTerm) ||
                               casino.safety_index.toString().includes(textTerm) ||
                               casino.safety_label.toLowerCase().includes(textTerm) ||
                               casino.games.some(function(game) { return game.toLowerCase().includes(textTerm); }) ||
                               casino.payments.some(function(payment) { return payment.toLowerCase().includes(textTerm); }) ||
                               casino.features.some(function(feature) { return feature.text.toLowerCase().includes(textTerm); }) ||
                               (casino.bonus && casino.bonus.toLowerCase().includes(textTerm));
                    });
                    if (!textMatch) shouldShow = false;
                }
                
                if (shouldShow) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });
            
            // Update results info
                searchResults.textContent = 'Found ' + visibleCount + ' of ' + totalCasinos + ' casinos on this page';
            }
        
        function performSearch() {
            // Always use the new filtered search
            performFilteredSearch();
        }
        
        function hasActiveFilters() {
            return advancedFilters.safetyMin !== null ||
                   advancedFilters.safetyMax !== null ||
                   advancedFilters.hasDepositBonus ||
                   advancedFilters.hasNoDepositBonus ||
                   advancedFilters.websiteLanguages.length > 0 ||
                   advancedFilters.livechatLanguages.length > 0 ||
                   advancedFilters.supportLanguages.length > 0 ||
                   advancedFilters.paymentMethods.length > 0 ||
                   advancedFilters.games.length > 0;
        }
        
        function clearSearch() {
            searchInput.value = '';
            
            // Also clear filters if any are active
            if (hasActiveFilters()) {
                resetFiltersBtn.click();
            } else {
                updateActiveFiltersDisplay();
                performFilteredSearch();
            searchInput.focus();
            }
        }
        
        // Initialize everything
        initAdvancedSearch();
        addCardClickHandlers();
        
        // Event listeners
        searchInput.addEventListener('input', function() {
            updateActiveFiltersDisplay();
            performFilteredSearch();
        });
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performFilteredSearch();
            }
        });
        searchBtn.addEventListener('click', performFilteredSearch);
        clearBtn.addEventListener('click', clearSearch);
        
        // Modal and other functionality
        var modalOverlay = document.getElementById('modalOverlay');
        var modalTitle = document.getElementById('modalTitle');
        var modalList = document.getElementById('modalList');
        var modalClose = document.getElementById('modalClose');
        
        // T&C Modal elements
        var tcModalOverlay = document.getElementById('tcModalOverlay');
        var tcModalContent = document.getElementById('tcModalContent');
        var tcModalClose = document.getElementById('tcModalClose');
        
        var langFlags = {
            'Vietnamese': '🇻🇳', 'English': '🇬🇧', 'French': '🇫🇷', 'German': '🇩🇪', 'Italian': '🇮🇹',
            'Spanish': '🇪🇸', 'Russian': '🇷🇺', 'Albanian': '🇦🇱', 'Armenian': '🇦🇲', 'Azerbaijani': '🇦🇿',
            'Bengali': '🇩🇰', 'Bosnian': '🇧🇦', 'Bulgarian': '🇧🇬', 'Central Khmer': '🇰🇭', 'Chinese': '🇨🇳',
            'Croatian': '🇭🇷', 'Danish': '🇩🇰', 'Estonian': '🇪🇪', 'Finnish': '🇫🇮', 'Georgian': '🇬🇪',
            'Greek': '🇬🇷', 'Hebrew': '🇮🇱', 'Hindi': '🇮🇳', 'Hungarian': '🇭🇺', 'Indonesian': '🇮🇩',
            'Japanese': '🇯🇵', 'Kazakh': '🇰🇿', 'Korean': '🇰🇷', 'Kurdish': '🇮🇶', 'Latvian': '🇱🇻',
            'Lithuanian': '🇱🇹', 'Macedonian': '🇲🇰', 'Malay': '🇲🇾', 'Mongolian': '🇲🇳', 'Norwegian': '🇳🇴',
            'Persian': '🇮🇷', 'Polish': '🇵🇱', 'Portuguese': '🇵🇹', 'Romanian': '🇷🇴', 'Slovak': '🇸🇰',
            'Somali': '🇸🇴', 'Swahili': '🇰🇪', 'Swedish': '🇸🇪', 'Tajik': '🇹🇯', 'Thai': '🇹🇭',
            'Turkish': '🇹🇷', 'Ukrainian': '🇺🇦', 'Urdu': '🇵🇰', 'Uzbek': '🇺🇿'
        };
        
        // T&C modal handler
        document.querySelectorAll('.tc-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var idx = parseInt(this.dataset.idx);
                var casino = casinos[idx];
                
                // Build T&C content
                tcModalContent.innerHTML = '';
                
                // Add header with bonus name
                if (casino.has_deposit_bonus || casino.has_nodeposit_bonus) {
                    var depositHeader = document.createElement('div');
                    depositHeader.style.marginBottom = '1rem';
                    depositHeader.innerHTML = '<span style="margin-right: 0.5rem;">🎁</span><strong>' + 
                        (casino.has_deposit_bonus ? 'First deposit bonus' : 'No-deposit bonus') + '</strong>';
                    tcModalContent.appendChild(depositHeader);
                    
                    // Create list of terms
                    var termsList = document.createElement('div');
                    termsList.style.display = 'flex';
                    termsList.style.flexDirection = 'column';
                    termsList.style.gap = '1rem';
                    
                    // Check if we have detailed terms
                    if (casino.bonus_details && Object.keys(casino.bonus_details).length > 0) {
                        // Parse terms from bonus_details
                        var terms = casino.bonus_details;
                        
                        // Helper function to clean text and replace Unicode
                        function cleanText(text) {
                            if (!text) return '';
                            return text.toString()
                                .replace(/u20ac/g, '€')
                                .replace(/u2022/g, '•')
                                .replace(/u00e9/g, 'é')
                                .replace(/u00e0/g, 'à')
                                .replace(/u00f6/g, 'ö')
                                .replace(/u00fc/g, 'ü')
                                .replace(/u00df/g, 'ß')
                                .replace(/u00c4/g, 'Ä')
                                .replace(/u00d6/g, 'Ö')
                                .replace(/u00dc/g, 'Ü');
                        }
                        
                        // Minimum deposit
                        if (terms.minimum_deposit) {
                            var depositText = 'Minimum deposit: ' + cleanText(terms.minimum_deposit);
                            if (terms.maximum_cashout) {
                                depositText += ', Maximum cashout: ' + cleanText(terms.maximum_cashout);
                            } else {
                                depositText += ', Maximum cashout: Unlimited';
                            }
                            var depositItem = createTermItem('💰', depositText);
                            termsList.appendChild(depositItem);
                        }
                        
                        // Wagering requirements
                        if (terms.wagering_requirements) {
                            var wagerItem = createTermItem('🔒', 'Wagering requirements: ' + cleanText(terms.wagering_requirements));
                            termsList.appendChild(wagerItem);
                        }
                        
                        // Maximum bet
                        if (terms.maximum_bet) {
                            var betItem = createTermItem('💯', 'Maximum bet: ' + cleanText(terms.maximum_bet));
                            termsList.appendChild(betItem);
                        }
                        
                        // Expiration
                        if (terms.expiration) {
                            var expiryItem = createTermItem('⏱️', 'The process of getting this bonus should be relatively FAST. Bonus expiration: ' + cleanText(terms.expiration));
                            termsList.appendChild(expiryItem);
                        }
                        
                        // Free spins
                        if (terms.free_spins_details) {
                            var spinsItem = createTermItem('🎰', cleanText(terms.free_spins_details));
                            termsList.appendChild(spinsItem);
                        }
                        
                        // Free spins conditions
                        if (terms.free_spins_conditions) {
                            var spinsCondItem = createTermItem('🎮', 'Free spins conditions: ' + cleanText(terms.free_spins_conditions));
                            termsList.appendChild(spinsCondItem);
                        }
                        
                        // Additional terms
                        if (terms.additional_terms) {
                            var additionalItem = createTermItem('ℹ️', cleanText(terms.additional_terms));
                            termsList.appendChild(additionalItem);
                        }
                        
                        // How to get bonus
                        var howToItem = createTermItem('❓', 'How to get bonus?<br><strong>Activate bonus in your casino account</strong>');
                        termsList.appendChild(howToItem);
                    } else {
                        // Simple fallback if no detailed terms are available
                        var generalItem = document.createElement('div');
                        generalItem.innerHTML = 'Please visit the casino website for detailed terms and conditions regarding their bonus offers.';
                        termsList.appendChild(generalItem);
                    }
                    
                    tcModalContent.appendChild(termsList);
                } else {
                    tcModalContent.innerHTML = '<p>No bonus terms and conditions are available for this casino.</p>';
                }
                
                // Show the modal
                tcModalOverlay.classList.remove('hidden');
                tcModalOverlay.style.display = 'flex';
            });
        });
        
        // Helper function to create term items
        function createTermItem(icon, text) {
            var item = document.createElement('div');
            item.className = 'term-item';
            item.style.display = 'flex';
            item.style.alignItems = 'flex-start';
            item.style.gap = '0.75rem';
            item.style.marginBottom = '0.75rem';
            
            var iconSpan = document.createElement('div');
            iconSpan.className = 'term-icon';
            iconSpan.style.fontSize = '1.25rem';
            iconSpan.style.flexShrink = '0';
            iconSpan.innerHTML = icon;
            
            var textDiv = document.createElement('div');
            textDiv.className = 'term-text';
            textDiv.style.fontSize = '0.875rem';
            textDiv.style.lineHeight = '1.5';
            textDiv.style.color = '#ffffff';
            textDiv.innerHTML = text;
            
            item.appendChild(iconSpan);
            item.appendChild(textDiv);
            
            return item;
        }
        
        document.querySelectorAll('.show-all-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var idx = parseInt(this.dataset.idx);
                var type = this.dataset.type;
                var items = type === 'games' ? casinos[idx].games : casinos[idx].payments;
                var title = type === 'games' ? 'All Games' : 'All Payment Methods';
                modalTitle.textContent = title + ' for ' + casinos[idx].title;
                modalList.innerHTML = '';
                var base_url = type === 'games' ? '<?php echo esc_url(site_url("/wp-content/uploads/games_full/")); ?>' : '<?php echo esc_url(site_url("/wp-content/uploads/payment_methods_full/")); ?>';
                items.forEach(function(item) {
                    var sanitized = type === 'games' ? sanitize_game_name(item) : sanitize_payment_name(item);
                    var span = document.createElement('span');
                    span.className = 'game-badge';
                    span.style.margin = '0.25rem';
                    span.style.display = 'inline-flex';
                    var exts = ['png', 'svg', 'jpg'];
                    var tryNext = function(i) {
                        if (i >= exts.length) {
                            span.textContent = item;
                            return;
                        }
                        var url = base_url + sanitized + '.' + exts[i];
                        var img = new Image();
                        img.onload = function() {
                            span.innerHTML = '';
                            img.alt = item;
                            img.style.height = '16px';
                            img.style.width = 'auto';
                            img.style.marginRight = '0.25rem';
                            span.appendChild(img);
                            var textSpan = document.createElement('span');
                            textSpan.textContent = item;
                            span.appendChild(textSpan);
                        };
                        img.onerror = function() {
                            tryNext(i + 1);
                        };
                        img.src = url;
                    };
                    tryNext(0);
                    modalList.appendChild(span);
                });
                modalOverlay.classList.remove('hidden');
                modalOverlay.style.display = 'flex';
            });
        });
        document.querySelectorAll('.lang-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var idx = parseInt(this.dataset.idx);
                var type = this.dataset.type;
                var casino = casinos[idx];
                var group, groupTitle;

                if (type === 'website') {
                    group = casino.languages;
                    groupTitle = 'Website Languages';
                } else if (type === 'livechat') {
                    group = casino.livechat_langs;
                    groupTitle = 'Live Chat Languages';
                } else if (type === 'support') {
                    group = casino.support_langs;
                    groupTitle = 'Support Languages';
                }

                modalTitle.textContent = groupTitle + ' for ' + casino.title;
                modalList.innerHTML = '';

                if (group.length) {
                    group.forEach(function(lang) {
                        var div = document.createElement('div');
                        div.className = 'lang-item';
                        var flag = document.createElement('span');
                        flag.className = 'lang-flag';
                        flag.textContent = langFlags[lang] || '';
                        div.appendChild(flag);
                        div.appendChild(document.createTextNode(lang));
                        modalList.appendChild(div);
                    });
                } else {
                    modalList.innerHTML = '<div style="font-size: 0.875rem; color: #cccccc;">No languages available.</div>';
                }

                modalOverlay.classList.remove('hidden');
                modalOverlay.style.display = 'flex';
            });
        });
        
        // Modal close handlers
        modalClose.addEventListener('click', function() {
            modalOverlay.classList.add('hidden');
            modalOverlay.style.display = 'none';
        });
        modalOverlay.addEventListener('click', function(e) {
            if (e.target === modalOverlay) {
                modalOverlay.classList.add('hidden');
                modalOverlay.style.display = 'none';
            }
        });
        
        // T&C Modal close handlers
        tcModalClose.addEventListener('click', function() {
            tcModalOverlay.classList.add('hidden');
            tcModalOverlay.style.display = 'none';
        });
        tcModalOverlay.addEventListener('click', function(e) {
            if (e.target === tcModalOverlay) {
                tcModalOverlay.classList.add('hidden');
                tcModalOverlay.style.display = 'none';
            }
        });
        
        function sanitize_game_name(name) {
            return name.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/[^a-z0-9.]/g, '');
        }
        function sanitize_payment_name(name) {
            return name.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/[^a-z0-9.]/g, '');
        }
        
        if (advancedSearchClose && advancedSearchModal) {
            advancedSearchClose.addEventListener('click', function() {
                advancedSearchModal.classList.add('hidden');
                advancedSearchModal.style.display = 'none';
            });
        }
        
        if (advancedSearchModal) {
            advancedSearchModal.addEventListener('click', function(e) {
                if (e.target === advancedSearchModal) {
                    advancedSearchModal.classList.add('hidden');
                    advancedSearchModal.style.display = 'none';
                }
            });
        }
    });
    </script>
</body>
</html>
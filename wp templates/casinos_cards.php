<?php
/*
Template Name: All Casinos Main Page (WP Title)
*/

// --- Sanitization and Helper Functions (copied from main template) ---
function sanitize_logo_filename($name) {
    $name = strtolower($name);
    $name = preg_replace('/logo_/', '', $name);
    $name = preg_replace('/casino review|casino|review/', '', $name);
    $name = preg_replace('/[^a-z0-9]/', '', $name);
    return $name;
}
function get_logo_url($casino_name) {
    $image_base_url = site_url('/wp-content/uploads/logos_full/logos_remake/');
    $sanitized_name = sanitize_logo_filename($casino_name);
    $img_extensions = ['png', 'svg', 'jpg', 'jpeg', 'webp'];
    $img_url = '';
    $img_found = false;
    foreach ($img_extensions as $ext) {
        $img_path = $image_base_url . $sanitized_name . '.' . $ext;
        $ch = curl_init($img_path);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code == 200) {
            $img_url = $img_path;
            $img_found = true;
            if ($ext === 'png') {
                break;
            }
        }
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
function get_permalink_by_title($title) {
    $page = get_page_by_title($title, OBJECT, 'page');
    return $page ? get_permalink($page->ID) : '#';
}

// --- Load JSONs ---
$json_dir = get_template_directory() . '/json/';
$json_files = glob($json_dir . 'page-*.json');
$casinos = [];
foreach ($json_files as $file) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!$data) continue;
    $casino_name = $data['detail_info']['casino_name'] ?? $data['title'] ?? '';
    if (!$casino_name) {
        $casino_name = basename($file, '.json');
    }
    $logo = get_logo_url($casino_name);
    $safety_index = $data['detail_info']['safety_index'] ?? '';
    $safety_label = '';
    if (is_numeric($safety_index)) {
        if ($safety_index >= 8) $safety_label = 'HIGH';
        elseif ($safety_index >= 5) $safety_label = 'MEDIUM';
        else $safety_label = 'LOW';
    }
    $bonus = $data['bonuses']['welcome'][0]['name'] ?? '';
    $bonus_details = $data['bonuses']['welcome'][0]['terms_and_conditions'] ?? [];
    $games = $data['games']['available_games'] ?? [];
    $payments = $data['detail_info']['payment_methods'] ?? [];
    $languages = $data['language_options']['website_languages'] ?? [];
    $support_langs = $data['language_options']['customer_support_languages'] ?? [];
    $livechat_langs = $data['language_options']['live_chat_languages'] ?? [];
    $pros = $data['pros_cons']['positives'] ?? [];
    $cons = $data['pros_cons']['negatives'] ?? [];
    $facts = $data['pros_cons']['interesting_facts'] ?? [];
    $permalink = get_permalink_by_title($casino_name);
    $vpn_note = $data['detail_info']['vpn_allowed'] ?? null;
    // Features mapping
    $features = [];
    if (!empty($data['detail_info']['is_international'])) {
        $features[] = [
            'icon' => '🌍',
            'text' => 'International casino',
            'type' => 'positive',
        ];
    }
    if (count($languages) > 1) {
        $features[] = [
            'icon' => '🈚',
            'text' => 'Website supports many languages',
            'type' => 'positive',
        ];
    }
    if (!empty($data['detail_info']['fast_withdrawal'])) {
        $features[] = [
            'icon' => '⚡',
            'text' => 'Fast withdrawal processing based on players experience',
            'type' => 'positive',
        ];
    }
    if (!empty($data['detail_info']['live_chat_247'])) {
        $features[] = [
            'icon' => '💬',
            'text' => 'Live chat is available 24/7, but not for all languages',
            'type' => 'partial',
        ];
    }
    // Add more features as needed from JSON fields
    $casinos[] = [
        'title' => $casino_name,
        'logo' => $logo,
        'logo_sanitized' => sanitize_logo_filename($casino_name),
        'safety_index' => $safety_index,
        'safety_label' => $safety_label,
        'bonus' => $bonus,
        'bonus_details' => $bonus_details,
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
    <title>All Casinos (WP Title)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; }
        .casinos-list { display: flex; flex-wrap: wrap; gap: 32px; justify-content: center; padding: 32px 0; }
        .casino-main-card { display: flex; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.10); width: 800px; min-height: 180px; overflow: hidden; }
        .casino-sidebar { width: 210px; background: #181c23; color: #fff; display: flex; flex-direction: column; align-items: center; padding: 16px 12px; }
        .casino-sidebar .logo-box {
            width: 100%;
            height: 60px;
            background: none;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            padding: 0 2px;
            box-sizing: border-box;
            overflow: hidden;
            position: relative;
        }
        .casino-sidebar .logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
            transform: scale(1.5);
            transform-origin: center center;
        }
        .casino-sidebar .logo-box span { font-size: 1.1em; color: #fff; }
        .casino-sidebar .safety-index { font-size: 1em; font-weight: bold; color: #8bc34a; margin-bottom: 10px; text-align: center; }
        .casino-sidebar .safety-index.high { color: #8bc34a; }
        .casino-sidebar .safety-index.medium { color: #ff9800; }
        .casino-sidebar .safety-index.low { color: #e53935; }
        .casino-sidebar .safety-label { font-size: 0.95em; font-weight: bold; margin-left: 4px; }
        .casino-sidebar .safety-label.high { color: #8bc34a; }
        .casino-sidebar .safety-label.medium { color: #ff9800; }
        .casino-sidebar .safety-label.low { color: #e53935; }
        .casino-sidebar .sidebar-section { margin-bottom: 10px; text-align: center; }
        .casino-sidebar .sidebar-section strong { color: #b0b0b0; font-size: 0.95em; display: block; margin-bottom: 2px; }
        .casino-sidebar .sidebar-btns { margin-top: auto; width: 100%; display: flex; gap: 8px; margin-bottom: 0; }
        .casino-sidebar .visit-btn, .casino-sidebar .review-btn { flex: 1; padding: 8px 0; border: none; border-radius: 5px; font-size: 0.98em; font-weight: bold; cursor: pointer; text-decoration: none; text-align: center; }
        .casino-sidebar .visit-btn { background: #43a047; color: #fff; }
        .casino-sidebar .review-btn { background: #f3f3f3; color: #333; border: 1px solid #bbb; }
        .casino-main-content { flex: 1; padding: 18px 22px 12px 22px; display: flex; flex-direction: column; gap: 10px; }
        .casino-main-content h2 { font-size: 1.25em; margin: 0 0 4px 0; color: #222; font-weight: bold; }
        .features-list { list-style: none; padding: 0; margin: 0 0 8px 0; display: flex; flex-wrap: wrap; gap: 10px 18px; }
        .feature { display: flex; align-items: center; gap: 5px; font-size: 0.98em; }
        .feature.positive { color: #388e3c; }
        .feature.partial { color: #ff9800; }
        .feature.negative { color: #e53935; }
        .feature-icon { font-size: 1.1em; }
        .bonus-block { background: #e8f5e9; color: #388e3c; border-radius: 5px; padding: 6px 10px; font-weight: bold; margin-bottom: 6px; font-size: 0.98em; display: flex; align-items: center; gap: 6px; }
        .bonus-tnc a { color: #1a237e; text-decoration: underline; font-weight: normal; }
        .info-block { margin-bottom: 6px; }
        .info-block-title { font-size: 1em; font-weight: bold; color: #388e3c; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .info-block-title .block-icon { font-size: 1em; }
        .info-block-content { margin-bottom: 2px; font-size: 0.97em; }
        .games-list, .payments-list { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 4px; }
        .game-icon, .payment-icon { background: #f7f7f7; border: 1px solid #e0e0e0; border-radius: 4px; padding: 4px 8px; font-size: 12px; color: #333; min-width: 40px; min-height: 28px; display: flex; align-items: center; justify-content: center; }
        .game-icon img, .payment-icon img { max-width: 28px; max-height: 16px; object-fit: contain; }
        .show-all-btn { background: #e3eafc; color: #1a237e; border: none; border-radius: 4px; padding: 3px 8px; font-size: 0.95em; cursor: pointer; margin-left: 6px; }
        .lang-btn { background: #f5f7fa; color: #0073aa; border: 1px solid #0073aa; border-radius: 4px; padding: 3px 8px; font-size: 0.95em; cursor: pointer; margin-left: 6px; }
        .pros-cons-facts { display: flex; gap: 10px; margin-top: 6px; }
        .pros-cons-facts .block { flex: 1; background: #f8fafc; border-radius: 6px; padding: 8px 7px; border: 1px solid #e0e0e0; min-width: 0; }
        .pros-cons-facts .block h4 { margin: 0 0 4px 0; font-size: 1em; color: #388e3c; }
        .pros-cons-facts ul { margin: 0; padding-left: 16px; font-size: 0.96em; }
        .vpn-note { background: #e0e0e0; color: #333; border-radius: 4px; padding: 5px 10px; font-size: 0.97em; margin-top: 8px; display: block; text-align: left; }
        @media (max-width: 900px) { .casino-main-card { flex-direction: column; width: 98vw; min-width: 0; } .casino-sidebar { width: 100%; flex-direction: row; justify-content: flex-start; align-items: flex-start; padding: 12px; } .casino-main-content { padding: 12px; } }
        /* Modal styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.3); display: none; justify-content: center; align-items: center; z-index: 3000; }
        .modal-overlay.open { display: flex; }
        .modal { background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.14); width: 380px; max-width: 95vw; max-height: 90vh; display: flex; flex-direction: column; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px 6px 14px; border-bottom: 1px solid #eee; }
        .modal-header h3 { margin: 0; font-size: 16px; color: #222; }
        .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #888; }
        .modal-body { padding: 12px; overflow-y: auto; }
        .modal-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .modal-list .game-icon, .modal-list .payment-icon { margin-bottom: 6px; }
        .modal-list .lang-group { margin-bottom: 12px; }
        .modal-list .lang-group-title { font-weight: bold; font-size: 1em; margin-bottom: 4px; color: #0073aa; }
        .modal-list .lang-item { font-size: 13px; color: #444; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .modal-list .lang-flag { font-size: 16px; width: 18px; }
    </style>
</head>
<body>
    <div class="casinos-list">
        <?php foreach ($casinos as $idx => $casino): ?>
        <div class="casino-main-card" style="display:flex;">
            <!-- Left: Logo -->
            <div class="casino-sidebar">
                <div class="logo-box">
                    <?php if ($casino['logo']): ?>
                        <img src="<?php echo esc_url($casino['logo']); ?>" alt="<?php echo esc_attr($casino['title']); ?>">
                    <?php else: ?>
                        <span style="font-size:2.2em; color:#bbb; display:flex; align-items:center; justify-content:center; width:100%; height:100%;"><svg width="48" height="48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="48" height="48" rx="12" fill="#e0e0e0"/><path d="M24 14c-5.5 0-10 3.36-10 7.5 0 2.36 1.7 4.45 4.3 5.77l-1.1 3.73a1 1 0 0 0 1.3 1.23l3.9-1.3c.53.06 1.08.1 1.6.1 5.5 0 10-3.36 10-7.5S29.5 14 24 14Z" fill="#bdbdbd"/></svg></span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Right: Main Info + Details -->
            <div class="casino-right" style="display:flex; flex:1;">
                <!-- Center: Main Info -->
                <div class="casino-main-info" style="flex:1.2; display:flex; flex-direction:column; padding:16px 20px; gap:8px;">
                    <h2 style="font-size:1.35em; color:#222; font-weight:600; margin:0;"><?php echo esc_html($casino['title']); ?> Review</h2>
                    
                    <!-- Safety Index -->
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span style="color:#222; font-weight:600;">SAFETY INDEX:</span>
                        <span style="color:#8bc34a; font-weight:600;">
                            <?php echo esc_html($casino['safety_index']); ?>/10
                        </span>
                    </div>

                    <!-- Features List -->
                    <div class="features-list" style="display:flex; flex-direction:column; gap:6px;">
                        <?php
                        // Define the features we want to display
                        $required_features = [
                            'Website supports many languages' => ['icon' => '🈚', 'type' => 'positive'],
                            'International casino' => ['icon' => '🌍', 'type' => 'positive'],
                            'Fast withdrawal processing based on players experience' => ['icon' => '⚡', 'type' => 'positive'],
                            'Live chat is available 24/7, but not for all languages' => ['icon' => '💬', 'type' => 'partial']
                        ];

                        foreach ($casino['features'] as $feature):
                            if (array_key_exists($feature['text'], $required_features)):
                                $feature_data = $required_features[$feature['text']];
                        ?>
                            <div class="feature" style="display:flex; align-items:center; gap:6px; 
                                <?php echo ($feature_data['type'] === 'positive') ? 'color:#388e3c;' : 'color:#ff9800;'; ?>">
                                <span class="feature-icon" style="font-size:1.1em; min-width:20px;">
                                    <?php echo $feature_data['icon']; ?>
                                </span>
                                <span style="font-size:0.95em;"><?php echo esc_html($feature['text']); ?></span>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>

                    <!-- Bonus Block -->
                    <div class="bonus-block" style="background:#e8f5e9; padding:10px 12px; border-radius:4px; margin:8px 0; display:flex; align-items:center; flex-wrap:wrap; gap:4px;">
                        <span style="color:#388e3c; font-weight:600;">BONUS:</span>
                        <span style="color:#388e3c;">100% up to €300</span>
                        <span style="color:#388e3c;">and 100 extra spins</span>
                        <span style="color:#388e3c;">(€0.1/spin)</span>
                        <span style="margin-left:auto; font-size:0.9em;">
                            <a href="#" style="color:#1a237e; text-decoration:none;">*T&Cs apply</a>
                        </span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons" style="display:flex; gap:10px; margin-top:4px;">
                        <a href="<?php echo esc_url($casino['permalink']); ?>" target="_blank" 
                           style="flex:1; padding:8px 0; background:#43a047; color:#fff; text-decoration:none; 
                           border-radius:4px; text-align:center; font-weight:600; font-size:0.95em;">
                            Visit Casino
                        </a>
                        <a href="<?php echo esc_url($casino['permalink']); ?>" target="_blank" 
                           style="flex:1; padding:8px 0; background:#fff; color:#7b1fa2; text-decoration:none; 
                           border:2px solid #7b1fa2; border-radius:4px; text-align:center; font-weight:600; font-size:0.95em;">
                            Read Review
                        </a>
                    </div>

                    <!-- VPN Note -->
                    <?php if ($casino['vpn_note'] === false || $casino['vpn_note'] === 'not_allowed'): ?>
                    <div class="vpn-note" style="background:#e0e0e0; color:#333; border-radius:4px; padding:6px 10px; 
                         margin-top:8px; font-size:0.9em;">
                        VPN not allowed
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Divider -->
                <div style="width:1px; background:#e0e0e0; margin:18px 0;"></div>
                <!-- Right: Details -->
                <div class="casino-details" style="flex:1.1; display:flex; flex-direction:column; gap:12px; padding:16px 0 16px 20px;">
                    <div class="info-block">
                        <div class="info-block-title" style="font-size:1em; font-weight:bold; color:#388e3c; margin-bottom:2px; display:flex; align-items:center; gap:6px;">
                            <span class="block-icon">🌐</span>LANGUAGE OPTIONS
                        </div>
                        <div class="info-block-content" style="line-height:1.4;">
                            <span style="font-weight:bold;">Website:</span> <?php echo count($casino['languages']); ?> languages,
                            <span style="font-weight:bold;">Live chat:</span> <?php echo count($casino['livechat_langs']); ?> languages,
                            <span style="font-weight:bold;">Customer support:</span> <?php echo count($casino['support_langs']); ?> languages
                            <button class="lang-btn" data-idx="<?php echo $idx; ?>" style="background:#e3eafc; color:#1a237e; border:none; border-radius:4px; padding:2px 8px; font-size:0.95em; cursor:pointer; margin-left:6px;">Show all</button>
                        </div>
                    </div>
                    <div class="info-block">
                        <div class="info-block-title" style="font-size:1em; font-weight:bold; color:#388e3c; margin-bottom:2px; display:flex; align-items:center; gap:6px;">
                            <span class="block-icon">🎰</span>AVAILABLE GAMES
                        </div>
                        <div class="games-list" style="display:flex; flex-wrap:wrap; gap:4px; margin:4px 0;">
                            <?php
                            $game_base_url = site_url('/wp-content/uploads/games_full/');
                            $games = $casino['games'];
                            foreach (array_slice($games, 0, 6) as $gidx => $game) {
                                $sanitized = sanitize_game_name($game);
                                $img_url = '';
                                foreach (['png', 'svg', 'jpg'] as $ext) {
                                    $img_path = $game_base_url . $sanitized . '.' . $ext;
                                    $ch = curl_init($img_path);
                                    curl_setopt($ch, CURLOPT_NOBODY, true);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                    curl_exec($ch);
                                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);
                                    if ($http_code == 200) { $img_url = $img_path; break; }
                                }
                                if ($img_url) {
                                    echo '<span class="game-icon"><img src="' . esc_url($img_url) . '" alt="' . esc_attr($game) . '"></span>';
                                } else {
                                    echo '<span class="game-icon">' . esc_html($game) . '</span>';
                                }
                            }
                            if (count($games) > 6) {
                                echo '<button type="button" class="show-all-btn" data-type="games" data-idx="' . $idx . '" style="background:#e3eafc; color:#1a237e; border:none; border-radius:4px; padding:3px 8px; font-size:0.95em; cursor:pointer; margin-left:6px;">Show all</button>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-block">
                        <div class="info-block-title" style="font-size:1em; font-weight:bold; color:#388e3c; margin-bottom:2px; display:flex; align-items:center; gap:6px;">
                            <span class="block-icon">💳</span>PAYMENT METHODS
                        </div>
                        <div class="payments-list" style="display:flex; flex-wrap:wrap; gap:4px; margin:4px 0;">
                            <?php
                            $pay_base_url = site_url('/wp-content/uploads/payment_methods_full/');
                            $payments = $casino['payments'];
                            foreach (array_slice($payments, 0, 6) as $pidx => $method) {
                                $sanitized = sanitize_payment_name($method);
                                $img_url = '';
                                foreach (['png', 'svg', 'jpg'] as $ext) {
                                    $img_path = $pay_base_url . $sanitized . '.' . $ext;
                                    $ch = curl_init($img_path);
                                    curl_setopt($ch, CURLOPT_NOBODY, true);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                    curl_exec($ch);
                                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);
                                    if ($http_code == 200) { $img_url = $img_path; break; }
                                }
                                if ($img_url) {
                                    echo '<span class="payment-icon"><img src="' . esc_url($img_url) . '" alt="' . esc_attr($method) . '"></span>';
                                } else {
                                    echo '<span class="payment-icon">' . esc_html($method) . '</span>';
                                }
                            }
                            if (count($payments) > 6) {
                                echo '<button type="button" class="show-all-btn" data-type="payments" data-idx="' . $idx . '" style="background:#e3eafc; color:#1a237e; border:none; border-radius:4px; padding:3px 8px; font-size:0.95em; cursor:pointer; margin-left:6px;">Show all (' . count($payments) . ')</button>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- Modal Overlay -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle"></h3>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-list" id="modalList"></div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Data for modals
        var casinos = <?php echo json_encode($casinos); ?>;
        var modalOverlay = document.getElementById('modalOverlay');
        var modalTitle = document.getElementById('modalTitle');
        var modalList = document.getElementById('modalList');
        var modalClose = document.getElementById('modalClose');
        // Language to flag emoji mapping
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
        // Show all games/payments modal
        document.querySelectorAll('.show-all-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx);
                var type = this.dataset.type;
                var items = type === 'games' ? casinos[idx].games : casinos[idx].payments;
                var title = type === 'games' ? 'All Games' : 'All Payment Methods';
                modalTitle.textContent = title + ' for ' + casinos[idx].title;
                modalList.innerHTML = '';
                var base_url = type === 'games' ? '<?php echo esc_url(site_url("/wp-content/uploads/games_full/")); ?>' : '<?php echo esc_url(site_url("/wp-content/uploads/payment_methods_full/")); ?>';
                items.forEach(function(item) {
                    var sanitized = type === 'games'
                        ? sanitized_game_name(item)
                        : sanitized_payment_name(item);
                    var span = document.createElement('span');
                    span.className = type === 'games' ? 'game-icon' : 'payment-icon';

                    // Try each extension in order, use the first that loads
                    var exts = ['png', 'svg', 'jpg'];
                    var found = false;
                    var tryNext = function(i) {
                        if (i >= exts.length) {
                            span.textContent = item; // fallback to text
                            return;
                        }
                        var url = base_url + sanitized + '.' + exts[i];
                        var img = new Image();
                        img.onload = function() {
                            span.innerHTML = '';
                            img.alt = item;
                            span.appendChild(img);
                            found = true;
                        };
                        img.onerror = function() {
                            tryNext(i + 1);
                        };
                        img.src = url;
                    };
                    tryNext(0);

                    modalList.appendChild(span);
                });
                modalOverlay.classList.add('open');
            });
        });
        // Show all languages modal
        document.querySelectorAll('.lang-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(this.dataset.idx);
                var casino = casinos[idx];
                modalTitle.textContent = 'Languages for ' + casino.title;
                modalList.innerHTML = '';
                var langGroups = [
                    {title: 'Website Languages', list: casino.languages},
                    {title: 'Support Languages', list: casino.support_langs},
                    {title: 'Live Chat Languages', list: casino.livechat_langs}
                ];
                langGroups.forEach(function(group) {
                    if (group.list.length) {
                        var groupDiv = document.createElement('div');
                        groupDiv.className = 'lang-group';
                        var groupTitle = document.createElement('div');
                        groupTitle.className = 'lang-group-title';
                        groupTitle.textContent = group.title + ' (' + group.list.length + ')';
                        groupDiv.appendChild(groupTitle);
                        group.list.forEach(function(lang) {
                            var div = document.createElement('div');
                            div.className = 'lang-item';
                            var flag = document.createElement('span');
                            flag.className = 'lang-flag';
                            flag.textContent = langFlags[lang] || '';
                            div.appendChild(flag);
                            div.appendChild(document.createTextNode(lang));
                            groupDiv.appendChild(div);
                        });
                        modalList.appendChild(groupDiv);
                    }
                });
                modalOverlay.classList.add('open');
            });
        });
        modalClose.addEventListener('click', function() { modalOverlay.classList.remove('open'); });
        modalOverlay.addEventListener('click', function(e) { if (e.target === modalOverlay) modalOverlay.classList.remove('open'); });
        // JS sanitization helpers (for modal)
        function sanitized_game_name(name) {
            return name.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/[^a-z0-9.]/g, '');
        }
        function sanitized_payment_name(name) {
            return name.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/[^a-z0-9.]/g, '');
        }
    });
    </script>
</body>
</html>
<?php
/**
 * Plugin Name: Casino Review Importer
 * Description: Imports casino reviews from JSON files and creates custom post types
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CasinoReviewImporter {
    public function __construct() {
        add_action('init', array($this, 'register_casino_review_post_type'));
        add_action('admin_menu', array($this, 'add_import_menu'));
        add_action('admin_post_import_casino_reviews', array($this, 'handle_import'));
    }

    public function register_casino_review_post_type() {
        $labels = array(
            'name' => 'Casino Reviews',
            'singular_name' => 'Casino Review',
            'menu_name' => 'Casino Reviews',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Casino Review',
            'edit_item' => 'Edit Casino Review',
            'new_item' => 'New Casino Review',
            'view_item' => 'View Casino Review',
            'search_items' => 'Search Casino Reviews',
            'not_found' => 'No casino reviews found',
            'not_found_in_trash' => 'No casino reviews found in trash'
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-games',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'rewrite' => array('slug' => 'casino-review')
        );

        register_post_type('casino_review', $args);
    }

    public function add_import_menu() {
        add_submenu_page(
            'edit.php?post_type=casino_review',
            'Import Casino Reviews',
            'Import Reviews',
            'manage_options',
            'import-casino-reviews',
            array($this, 'render_import_page')
        );
    }

    public function render_import_page() {
        ?>
        <div class="wrap">
            <h1>Import Casino Reviews</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_casino_reviews">
                <?php wp_nonce_field('import_casino_reviews_nonce', 'import_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="json_files">Select JSON Files</label></th>
                        <td><input type="file" name="json_files[]" id="json_files" multiple accept=".json"></td>
                    </tr>
                </table>
                <?php submit_button('Import Reviews'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        check_admin_referer('import_casino_reviews_nonce', 'import_nonce');

        if (!isset($_FILES['json_files'])) {
            wp_redirect(admin_url('edit.php?post_type=casino_review&page=import-casino-reviews&error=no_files'));
            exit;
        }

        $files = $_FILES['json_files'];
        $imported = 0;
        $errors = array();

        foreach ($files['tmp_name'] as $index => $tmp_name) {
            if ($files['error'][$index] !== UPLOAD_ERR_OK) {
                continue;
            }

            $content = file_get_contents($tmp_name);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "Error parsing {$files['name'][$index]}: " . json_last_error_msg();
                continue;
            }

            try {
                $post_id = $this->create_casino_review($data);
                if ($post_id) {
                    $imported++;
                }
            } catch (Exception $e) {
                $errors[] = "Error importing {$files['name'][$index]}: " . $e->getMessage();
            }
        }

        $redirect_url = add_query_arg(array(
            'imported' => $imported,
            'errors' => count($errors)
        ), admin_url('edit.php?post_type=casino_review&page=import-casino-reviews'));

        wp_redirect($redirect_url);
        exit;
    }

    private function create_casino_review($data) {
        $detail_info = $data['detail_info'];
        
        $post_data = array(
            'post_title' => $detail_info['casino_name'],
            'post_content' => $data['main_content'],
            'post_type' => 'casino_review',
            'post_status' => 'publish'
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }

        // Store detail info
        update_post_meta($post_id, 'safety_index', $detail_info['safety_index']);
        update_post_meta($post_id, 'safety_rating', $detail_info['safety_rating']);
        update_post_meta($post_id, 'user_feedback', $detail_info['user_feedback']);
        update_post_meta($post_id, 'user_reviews_count', $detail_info['user_reviews_count']);
        update_post_meta($post_id, 'accepts_vietnam', $detail_info['accepts_vietnam']);
        update_post_meta($post_id, 'payment_methods', $detail_info['payment_methods']);
        update_post_meta($post_id, 'withdrawal_limits', $detail_info['withdrawal_limits']);
        update_post_meta($post_id, 'owner', $detail_info['owner']);
        update_post_meta($post_id, 'established', $detail_info['established']);
        update_post_meta($post_id, 'estimated_annual_revenues', $detail_info['estimated_annual_revenues']);
        update_post_meta($post_id, 'licensing_authorities', $detail_info['licensing_authorities']);

        // Store bonuses
        update_post_meta($post_id, 'bonuses', $data['bonuses']);

        // Store games
        update_post_meta($post_id, 'available_games', $data['games']['available_games']);
        update_post_meta($post_id, 'unavailable_games', $data['games']['unavailable_games']);

        // Store language options
        update_post_meta($post_id, 'language_options', $data['language_options']);

        // Store game providers
        update_post_meta($post_id, 'game_providers', $data['game_providers']);

        // Store screenshots
        update_post_meta($post_id, 'screenshots', $data['screenshots']);

        // Store pros and cons
        update_post_meta($post_id, 'pros_cons', $data['pros_cons']);

        return $post_id;
    }
}

// Initialize the plugin
new CasinoReviewImporter(); 
new OCEANWP_Theme_Class();

// Create or update JSON file when a page is published or updated
add_action('save_post', function($post_id) {
    // Basic checks
    if (wp_is_post_revision($post_id)) return;
    if (get_post_type($post_id) !== 'page') return;
    if (get_post_status($post_id) !== 'publish') return;

    // Get post content
    $post = get_post($post_id);
    if (!$post || empty($post->post_content)) {
        error_log("No content found for post $post_id");
        return;
    }

    // Extract JSON content
    $content = $post->post_content;
    if (!preg_match('/<pre><code.*?>(.*?)<\/code><\/pre>/s', $content, $matches)) {
        error_log("No JSON block found in post $post_id");
        return;
    }

    $json_content = trim($matches[1]);
    if (empty($json_content)) {
        error_log("Empty JSON content in post $post_id");
        return;
    }

    // Validate JSON
    $decoded = json_decode($json_content, true);
    if ($decoded === null) {
        error_log("Invalid JSON in post $post_id: " . json_last_error_msg());
        return;
    }

    // Set up directory using absolute path
    $json_dir = ABSPATH . 'wp-content/themes/oceanwp/json/';
    error_log("Attempting to use directory: " . $json_dir);
    
    if (!file_exists($json_dir)) {
        if (!mkdir($json_dir, 0755, true)) {
            error_log("Failed to create directory: $json_dir");
            return;
        }
    }

    // Save JSON file
    $json_file = $json_dir . 'page-' . $post_id . '.json';
    if (file_put_contents($json_file, $json_content) === false) {
        error_log("Failed to write JSON file: $json_file");
        // Try to check permissions
        error_log("Directory permissions: " . substr(sprintf('%o', fileperms($json_dir)), -4));
        error_log("PHP process user: " . exec('whoami'));
        return;
    }

    // Save backup in post meta
    update_post_meta($post_id, 'complete_json', $json_content);
    error_log("Successfully saved JSON for post $post_id to: " . $json_file);
});

// Delete JSON file when a page is deleted
add_action('before_delete_post', function($post_id) {
    if (get_post_type($post_id) !== 'page') return;

    $json_file = ABSPATH . 'wp-content/themes/oceanwp/json/page-' . $post_id . '.json';
    if (file_exists($json_file)) {
        unlink($json_file);
    }
}); 
# Casino Review Importer for WordPress

This plugin allows you to import casino review JSON files into WordPress as custom post types with all associated metadata.

## Installation

1. Create a new directory called `casino-review-importer` in your WordPress plugins directory (`wp-content/plugins/`)
2. Copy the following files into the directory:
   - `casino-review-importer.php`
   - `single-casino_review.php`
   - `casino-review-styles.css`

3. In your WordPress admin panel, go to Plugins and activate "Casino Review Importer"

4. Copy the `single-casino_review.php` template file to your theme directory

5. Add the following code to your theme's `functions.php` to enqueue the styles:

```php
function enqueue_casino_review_styles() {
    if (is_singular('casino_review')) {
        wp_enqueue_style('casino-review-styles', plugins_url('casino-review-styles.css', __FILE__));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_casino_review_styles');
```

## Usage

1. In WordPress admin, you'll find a new menu item called "Casino Reviews"
2. Click on "Import Reviews" in the submenu
3. Select one or multiple JSON files to import
4. Click "Import Reviews" to start the import process

The importer will:
- Create a new casino review post for each JSON file
- Import all metadata (safety ratings, games, payment methods, etc.)
- Set up proper taxonomies and relationships
- Import screenshots and other media

## JSON Structure

The importer expects JSON files in the following format:

```json
{
    "detail_info": {
        "casino_name": "Casino Name",
        "safety_index": "8.1/10",
        "safety_rating": "SAFETY INDEX HIGH",
        "user_feedback": "...",
        "user_reviews_count": "0",
        "accepts_vietnam": "...",
        "payment_methods": ["..."],
        "withdrawal_limits": {
            "month": "...",
            "week": "...",
            "day": "..."
        },
        "owner": "...",
        "established": "...",
        "estimated_annual_revenues": "...",
        "licensing_authorities": ["..."]
    },
    "main_content": "HTML content...",
    "bonuses": {...},
    "games": {
        "available_games": ["..."],
        "unavailable_games": ["..."]
    },
    "language_options": {...},
    "game_providers": {...},
    "screenshots": ["..."],
    "pros_cons": {
        "positives": ["..."],
        "negatives": ["..."],
        "interesting_facts": ["..."]
    }
}
```

## Customization

The plugin creates a custom post type called `casino_review`. You can customize the display of casino reviews by:

1. Modifying the `single-casino_review.php` template file in your theme
2. Adjusting the styles in `casino-review-styles.css`
3. Adding custom fields or taxonomies in the main plugin file

## Support

For support or feature requests, please open an issue in the repository. 
<?php
get_header();

while (have_posts()) :
    the_post();
    
    // Get all the custom fields
    $detail_info = array(
        'safety_index' => get_post_meta(get_the_ID(), 'safety_index', true),
        'safety_rating' => get_post_meta(get_the_ID(), 'safety_rating', true),
        'user_feedback' => get_post_meta(get_the_ID(), 'user_feedback', true),
        'user_reviews_count' => get_post_meta(get_the_ID(), 'user_reviews_count', true),
        'accepts_vietnam' => get_post_meta(get_the_ID(), 'accepts_vietnam', true),
        'payment_methods' => get_post_meta(get_the_ID(), 'payment_methods', true),
        'withdrawal_limits' => get_post_meta(get_the_ID(), 'withdrawal_limits', true),
        'owner' => get_post_meta(get_the_ID(), 'owner', true),
        'established' => get_post_meta(get_the_ID(), 'established', true),
        'estimated_annual_revenues' => get_post_meta(get_the_ID(), 'estimated_annual_revenues', true),
        'licensing_authorities' => get_post_meta(get_the_ID(), 'licensing_authorities', true)
    );

    $bonuses = get_post_meta(get_the_ID(), 'bonuses', true);
    $games = array(
        'available' => get_post_meta(get_the_ID(), 'available_games', true),
        'unavailable' => get_post_meta(get_the_ID(), 'unavailable_games', true)
    );
    $language_options = get_post_meta(get_the_ID(), 'language_options', true);
    $game_providers = get_post_meta(get_the_ID(), 'game_providers', true);
    $screenshots = get_post_meta(get_the_ID(), 'screenshots', true);
    $pros_cons = get_post_meta(get_the_ID(), 'pros_cons', true);
?>

<div class="casino-review-container">
    <header class="casino-review-header">
        <h1><?php the_title(); ?></h1>
        
        <div class="safety-rating">
            <div class="rating-score"><?php echo esc_html($detail_info['safety_index']); ?></div>
            <div class="rating-text"><?php echo esc_html($detail_info['safety_rating']); ?></div>
        </div>
    </header>

    <div class="casino-quick-info">
        <div class="info-item">
            <strong>Established:</strong> <?php echo esc_html($detail_info['established']); ?>
        </div>
        <div class="info-item">
            <strong>Owner:</strong> <?php echo esc_html($detail_info['owner']); ?>
        </div>
        <div class="info-item">
            <strong>License:</strong> <?php echo esc_html(implode(', ', (array)$detail_info['licensing_authorities'])); ?>
        </div>
    </div>

    <?php if (!empty($pros_cons)) : ?>
    <div class="pros-cons-section">
        <div class="pros">
            <h3>Advantages</h3>
            <ul>
                <?php foreach ($pros_cons['positives'] as $pro) : ?>
                    <li><?php echo esc_html($pro); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="cons">
            <h3>Disadvantages</h3>
            <ul>
                <?php foreach ($pros_cons['negatives'] as $con) : ?>
                    <li><?php echo esc_html($con); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-content">
        <?php the_content(); ?>
    </div>

    <?php if (!empty($detail_info['withdrawal_limits'])) : ?>
    <div class="withdrawal-limits">
        <h3>Withdrawal Limits</h3>
        <ul>
            <?php foreach ($detail_info['withdrawal_limits'] as $period => $limit) : ?>
                <li><strong><?php echo ucfirst($period); ?>:</strong> <?php echo esc_html($limit); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($games['available'])) : ?>
    <div class="available-games">
        <h3>Available Games</h3>
        <ul>
            <?php foreach ($games['available'] as $game) : ?>
                <li><?php echo esc_html($game); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($detail_info['payment_methods'])) : ?>
    <div class="payment-methods">
        <h3>Payment Methods</h3>
        <ul>
            <?php foreach ($detail_info['payment_methods'] as $method) : ?>
                <li><?php echo esc_html($method); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($screenshots)) : ?>
    <div class="casino-screenshots">
        <h3>Screenshots</h3>
        <div class="screenshot-grid">
            <?php foreach ($screenshots as $screenshot) : ?>
                <div class="screenshot">
                    <img src="<?php echo esc_url($screenshot); ?>" alt="Casino Screenshot">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
endwhile;

get_footer();
?> 
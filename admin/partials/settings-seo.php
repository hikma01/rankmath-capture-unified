<?php
/**
 * SEO Settings Partial
 * 
 * @package RMCU
 * @subpackage Admin/Partials
 */

if (!defined('ABSPATH')) {
    exit;
}

$seo_settings = get_option('rmcu_seo_settings', []);
$rankmath_active = class_exists('RankMath');
?>

<div class="rmcu-settings-section">
    <h2><?php _e('SEO & RankMath Integration', 'rmcu'); ?></h2>
    
    <?php if (!$rankmath_active): ?>
        <div class="notice notice-warning inline">
            <p><?php _e('RankMath is not active. Some features may be limited.', 'rmcu'); ?></p>
        </div>
    <?php endif; ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="enable_schema"><?php _e('Add Schema Markup', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="enable_schema" 
                       name="rmcu_seo_settings[enable_schema]" 
                       value="1" 
                       <?php checked(isset($seo_settings['enable_schema']) ? $seo_settings['enable_schema'] : 1); ?>>
                <p class="description"><?php _e('Add VideoObject schema to captured videos for better SEO', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="auto_generate_thumbnail"><?php _e('Auto-generate Thumbnails', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="auto_generate_thumbnail" 
                       name="rmcu_seo_settings[auto_thumbnail]" 
                       value="1" 
                       <?php checked(isset($seo_settings['auto_thumbnail']) ? $seo_settings['auto_thumbnail'] : 1); ?>>
                <p class="description"><?php _e('Automatically create thumbnails from video captures', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="thumbnail_time"><?php _e('Thumbnail Capture Time', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="number" 
                       id="thumbnail_time" 
                       name="rmcu_seo_settings[thumbnail_time]" 
                       value="<?php echo esc_attr($seo_settings['thumbnail_time'] ?? 2); ?>" 
                       min="0" 
                       max="10" 
                       step="0.5">
                <span><?php _e('seconds', 'rmcu'); ?></span>
                <p class="description"><?php _e('Time in video to capture thumbnail', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="add_to_sitemap"><?php _e('Add to Sitemap', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="add_to_sitemap" 
                       name="rmcu_seo_settings[add_sitemap]" 
                       value="1" 
                       <?php checked(isset($seo_settings['add_sitemap']) ? $seo_settings['add_sitemap'] : 0); ?>>
                <p class="description"><?php _e('Include video captures in XML sitemap', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <?php if ($rankmath_active): ?>
        <tr>
            <th scope="row">
                <label for="rankmath_integration"><?php _e('RankMath Integration', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="rankmath_integration" 
                       name="rmcu_seo_settings[rankmath_integration]" 
                       value="1" 
                       <?php checked(isset($seo_settings['rankmath_integration']) ? $seo_settings['rankmath_integration'] : 1); ?>>
                <p class="description"><?php _e('Enable deep integration with RankMath SEO', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="use_as_featured"><?php _e('Use as Featured Image', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="use_as_featured" 
                       name="rmcu_seo_settings[use_as_featured]" 
                       value="1" 
                       <?php checked(isset($seo_settings['use_as_featured']) ? $seo_settings['use_as_featured'] : 0); ?>>
                <p class="description"><?php _e('Automatically set video thumbnail as post featured image', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="add_to_content_ai"><?php _e('RankMath Content AI', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="add_to_content_ai" 
                       name="rmcu_seo_settings[content_ai]" 
                       value="1" 
                       <?php checked(isset($seo_settings['content_ai']) ? $seo_settings['content_ai'] : 0); ?>>
                <p class="description"><?php _e('Include captures in RankMath Content AI analysis', 'rmcu'); ?></p>
            </td>
        </tr>
        <?php endif; ?>
        
        <tr>
            <th scope="row">
                <label for="meta_title_template"><?php _e('Meta Title Template', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="text" 
                       id="meta_title_template" 
                       name="rmcu_seo_settings[title_template]" 
                       value="<?php echo esc_attr($seo_settings['title_template'] ?? '%title% - Video'); ?>" 
                       class="regular-text">
                <p class="description"><?php _e('Variables: %title%, %site_name%, %date%', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="meta_description_template"><?php _e('Meta Description Template', 'rmcu'); ?></label>
            </th>
            <td>
                <textarea id="meta_description_template" 
                          name="rmcu_seo_settings[description_template]" 
                          rows="3" 
                          class="large-text"><?php echo esc_textarea($seo_settings['description_template'] ?? 'Watch this video capture from %title% on %site_name%'); ?></textarea>
                <p class="description"><?php _e('Variables: %title%, %site_name%, %date%, %duration%', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="og_tags"><?php _e('Open Graph Tags', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="og_tags" 
                       name="rmcu_seo_settings[og_tags]" 
                       value="1" 
                       <?php checked(isset($seo_settings['og_tags']) ? $seo_settings['og_tags'] : 1); ?>>
                <p class="description"><?php _e('Add Open Graph meta tags for social sharing', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="twitter_cards"><?php _e('Twitter Cards', 'rmcu'); ?></label>
            </th>
            <td>
                <select id="twitter_cards" name="rmcu_seo_settings[twitter_card_type]">
                    <option value="summary" <?php selected($seo_settings['twitter_card_type'] ?? '', 'summary'); ?>>
                        <?php _e('Summary', 'rmcu'); ?>
                    </option>
                    <option value="summary_large_image" <?php selected($seo_settings['twitter_card_type'] ?? 'summary_large_image', 'summary_large_image'); ?>>
                        <?php _e('Summary with Large Image', 'rmcu'); ?>
                    </option>
                    <option value="player" <?php selected($seo_settings['twitter_card_type'] ?? '', 'player'); ?>>
                        <?php _e('Player Card', 'rmcu'); ?>
                    </option>
                </select>
                <p class="description"><?php _e('Twitter Card type for video captures', 'rmcu'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="lazy_load"><?php _e('Lazy Load Videos', 'rmcu'); ?></label>
            </th>
            <td>
                <input type="checkbox" 
                       id="lazy_load" 
                       name="rmcu_seo_settings[lazy_load]" 
                       value="1" 
                       <?php checked(isset($seo_settings['lazy_load']) ? $seo_settings['lazy_load'] : 1); ?>>
                <p class="description"><?php _e('Improve page load speed with lazy loading', 'rmcu'); ?></p>
            </td>
        </tr>
    </table>
</div>
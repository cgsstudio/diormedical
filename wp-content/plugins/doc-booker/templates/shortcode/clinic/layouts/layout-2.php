<?php
/**
 * @package WPDDocBooker
 * @var array $args
 * @var int   $wpddbclinic_id
 * @var array $custom_image_size
 * @version 1.0.0
 */


use WpDreamers\WPDDB\Controllers\Helper\Helper;
use WpDreamers\WPDDB\Controllers\Model\Clinic;


defined( 'ABSPATH' ) || exit;

?>
<div class="wpddb-shortcode-item clinic-item posts-item">
    <div class="clinic-thumb">
        <?php if (has_post_thumbnail()){ ?>
            <a href="<?php echo esc_url(get_the_permalink()); ?>" class="wpddb-media">
                <?php
                $img = Helper::getFeatureImage($wpddbclinic_id,'wpddb_custom',$custom_image_size);
                Helper::print_html($img);
                ?>
            </a>
        <?php } else {
            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
            echo '<a href="'. esc_url(get_the_permalink()) .'"><img class="wpddb-media no-image" src="' . esc_url(wpddb()->get_assets_uri('public/img/no-image-570x400.jpg')) . '" alt="'. the_title_attribute( array( 'echo'=> false ) ) .'"/></a>';
        } ?>
    </div>
    <div class="clinic-content">
        <h3 class="wpddb-clinic-title"><a href="<?php echo esc_url(get_the_permalink()); ?>"><?php the_title(); ?></a></h3>
        <?php
            do_action('wpddb_clinic_shortcode_content', $args);
        ?>
        <?php
        Clinic::clinic_hotline(get_the_ID());
        do_action('wpddb_clinic_shortcode_map_btn', $args);
        ?>
    </div>
</div>

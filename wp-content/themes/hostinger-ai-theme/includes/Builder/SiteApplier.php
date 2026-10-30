<?php

namespace Hostinger\AiTheme\Builder;

use Closure;
use Hostinger\AiTheme\Builder\Elementor\KitManager;
use Hostinger\AiTheme\Builder\Envelopes\CreatedPages;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeAffiliateShortcodes;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeCacheBuster;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeCleaner;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeContactDetails;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeCustomization;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeHostingerEcommerce;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeMenu;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopePages;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopePosts;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeReachSignups;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeSiteSettings;
use Hostinger\AiTheme\Builder\Envelopes\EnvelopeWooCommerce;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

defined( 'ABSPATH' ) || exit;

class SiteApplier {

    private AffiliateBuilder $affiliate_builder;

    private EnvelopeCleaner $cleaner;

    private EnvelopeSiteSettings $site_settings;

    private EnvelopeReachSignups $reach_signups;

    private EnvelopeContactDetails $contact_details;

    private EnvelopeWooCommerce $woocommerce;

    private EnvelopeHostingerEcommerce $hostinger_ecommerce;

    private EnvelopeMenu $menu;

    private EnvelopeCacheBuster $cache_buster;

    private ?Closure $step_reporter = null;

    public function __construct(
        ?AffiliateBuilder $affiliate_builder = null,
        ?HostingerReachBuilder $reach_builder = null,
        ?EnvelopeCleaner $cleaner = null,
        ?EnvelopeSiteSettings $site_settings = null,
        ?EnvelopeReachSignups $reach_signups = null,
        ?EnvelopeContactDetails $contact_details = null,
        ?EnvelopeWooCommerce $woocommerce = null,
        ?EnvelopeHostingerEcommerce $hostinger_ecommerce = null,
        ?EnvelopeMenu $menu = null,
        ?EnvelopeCacheBuster $cache_buster = null
    ) {
        $this->affiliate_builder   = $affiliate_builder ?? new AffiliateBuilder();
        $this->cleaner             = $cleaner ?? new EnvelopeCleaner( $this->affiliate_builder );
        $this->site_settings       = $site_settings ?? new EnvelopeSiteSettings();
        $this->reach_signups       = $reach_signups ?? new EnvelopeReachSignups( $reach_builder );
        $this->contact_details     = $contact_details ?? new EnvelopeContactDetails();
        $this->woocommerce         = $woocommerce ?? new EnvelopeWooCommerce();
        $this->hostinger_ecommerce = $hostinger_ecommerce ?? new EnvelopeHostingerEcommerce();
        $this->menu                = $menu ?? new EnvelopeMenu();
        $this->cache_buster        = $cache_buster ?? new EnvelopeCacheBuster();
    }

    /**
     * @throws InvalidArgumentException When the envelope is unsupported/malformed (HTTP 422).
     * @throws RuntimeException         When every page fails to apply (HTTP 500).
     */
    public function apply( array $envelope, ?callable $on_step = null ): array {
        $this->step_reporter = $on_step === null ? null : Closure::fromCallable( $on_step );

        try {
            return $this->apply_envelope( $envelope );
        } finally {
            $this->step_reporter = null;
        }
    }

    private function apply_envelope( array $envelope ): array {
        if ( ! BundleSchema::validate( $envelope ) ) {
            throw new InvalidArgumentException( 'Unsupported or malformed website envelope.' );
        }

        $editor = (string) $envelope['editor'];
        $this->guard_editor( $editor );

        $this->report_step( 'prepare_site' );

        $warnings      = array();
        $customization = $this->prepare_site( $envelope, $editor );

        $this->report_step( 'reach_signups' );

        $envelope = $this->reach_signups->prepare( $envelope, $editor, $warnings );
        $envelope = $this->contact_details->prepare( $envelope, $editor );

        $this->report_step( 'sideload_media' );

        $media      = new MediaSideloader();
        $sideloaded = $media->sideload(
            $media->collect_envelope_urls( $envelope, $editor ),
            fn( int $index, int $total ) => $this->report_step( 'sideload_media', sprintf( '%d/%d', $index + 1, $total ) )
        );
        $media_map  = $sideloaded['map'];
        $warnings   = array_merge( $warnings, $sideloaded['warnings'] );

        $pages    = $this->create_pages( $envelope, $editor, $media, $media_map );
        $warnings = array_merge( $warnings, $pages['warnings'] );

        if ( empty( $pages['post_ids'] ) ) {
            throw new RuntimeException( 'Every page failed to apply.' );
        }

        // Track ids immediately: clean-before-apply is destructive.
        update_option( BundleSchema::OPTION_PAGES, $pages['post_ids'] );
        CreatedPages::record_all( $envelope['pages'], $pages['id_map'] );

        if ( $editor === 'elementor' ) {
            $this->polish_elementor_kit( $customization );
        }

        $this->report_step( 'woocommerce' );

        $products_created = $this->woocommerce->create_products( $envelope['woocommerce'] ?? null, $media_map, $warnings );
        $this->woocommerce->ensure_store_pages();

        if ( HostingerEcommerceHelper::is_active() ) {
            $this->hostinger_ecommerce->ensure_shop_page( $envelope, $editor, $pages );
        }

        $this->report_step( 'menu' );

        $menu_id = $this->build_menu_safely( $envelope, $pages, $warnings );

        $this->report_step( 'brand' );

        $logo_url = $this->site_settings->apply_brand(
            is_array( $envelope['brand'] ?? null ) ? $envelope['brand'] : array(),
            $media_map
        );
        if ( $logo_url !== '' ) {
            $customization['logo_url'] = $logo_url;
        }

        $this->report_step( 'blog' );

        $posts_created = $this->apply_blog( $envelope['blog'] ?? null, $media, $media_map );

        if ( $menu_id > 0 ) {
            update_option( BundleSchema::OPTION_MENU, $menu_id );
        }

        $this->report_step( 'finish' );

        $this->finish_apply( $editor, $pages['post_ids'] );

        GenerationState::complete();

        return array(
            'pages_created'     => count( $pages['post_ids'] ),
            'front_page_id'     => $pages['front_id'],
            'menu_id'           => $menu_id,
            'products_created'  => $products_created,
            'posts_created'     => $posts_created,
            'assets_sideloaded' => count( $media_map ),
            'customization'     => $customization,
            'warnings'          => array_values( $warnings ),
        );
    }

    private function report_step( string $step, string $progress = '' ): void {
        if ( $this->step_reporter !== null ) {
            ( $this->step_reporter )( $step, $progress );
        }
    }

    private function guard_editor( string $editor ): void {
        if ( $editor === 'elementor' && ! Helper::is_elementor_active() ) {
            throw new InvalidArgumentException( 'Elementor is required for this website but is not active.' );
        }
    }

    private function prepare_site( array $envelope, string $editor ): array {
        $this->cleaner->clean();
        $this->site_settings->set_mode( $editor );
        $this->site_settings->apply_language( $envelope['language'] ?? array() );
        $this->site_settings->apply_single_page_chrome( ! empty( $envelope['single_page'] ) );

        return EnvelopeCustomization::capture(
            is_array( $envelope['theme'] ?? null ) ? $envelope['theme'] : array(),
            $editor
        );
    }

    private function create_pages( array $envelope, string $editor, MediaSideloader $media, array $media_map ): array {
        $pages = new EnvelopePages(
            $media,
            null,
            null,
            new EnvelopeAffiliateShortcodes( $this->affiliate_builder ),
            $this->hostinger_ecommerce
        );

        return $pages->create( $envelope, $editor, $media_map, fn() => $this->report_step( 'create_pages' ) );
    }

    // Kit save() no-ops when Elementor has not created a kit yet.
    private function polish_elementor_kit( array $customization ): void {
        EnvelopeCustomization::update_elementor_heading_fonts(
            (string) ( $customization['font_pair']['heading']['font_family'] ?? '' )
        );

        $kit = new KitManager();
        $kit->set_global_image_border_radius( 8 );
        $kit->set_global_container_mobile_layout_padding( 1 );
    }

    private function build_menu_safely( array $envelope, array $pages, array &$warnings ): int {
        try {
            return $this->menu->build( $envelope['menu'] ?? array(), $pages['id_map'], $pages['post_ids'] );
        } catch ( Throwable ) {
            $warnings[] = 'Navigation menu failed to apply.';

            return 0;
        }
    }

    private function apply_blog( $blog, MediaSideloader $media, array $media_map ): int {
        if ( empty( $blog ) || ! is_array( $blog ) ) {
            return 0;
        }

        $result = ( new EnvelopePosts( $media, null, $this->affiliate_builder ) )->create( $blog, $media_map );

        update_option( BundleSchema::OPTION_BLOG_POSTS, $result['post_ids'] );
        update_option( BundleSchema::OPTION_BLOG_CATS, $result['category_ids'] );

        return count( $result['post_ids'] );
    }

    private function finish_apply( string $editor, array $post_ids ): void {
        $this->site_settings->ensure_pretty_permalinks();
        $this->cache_buster->bust( $editor, $post_ids );

        $this->report_step( 'install_language' );

        $this->site_settings->install_selected_language();
    }
}

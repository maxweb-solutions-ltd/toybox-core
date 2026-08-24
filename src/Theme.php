<?php

namespace Toybox\Core;

use Exception;
use Toybox\Core\Components\ACF;
use Toybox\Core\Components\Admin;
use Toybox\Core\Components\AdminBar;
use Toybox\Core\Components\Assets;
use Toybox\Core\Components\Blocks;
use Toybox\Core\Components\Comments;
use Toybox\Core\Components\Content;
use Toybox\Core\Components\Customizer;
use Toybox\Core\Components\Dashboard;
use Toybox\Core\Components\Dashicons;
use Toybox\Core\Components\Embeds;
use Toybox\Core\Components\Emoji;
use Toybox\Core\Components\Header;
use Toybox\Core\Components\HTTP;
use Toybox\Core\Components\Image;
use Toybox\Core\Components\jQuery;
use Toybox\Core\Components\Login;
use Toybox\Core\Components\Misc;
use Toybox\Core\Components\Pattern;
use Toybox\Core\Components\PostType;
use Toybox\Core\Components\Styles;
use Toybox\Core\Components\Shortcode;
use Toybox\Core\Components\Snippets;
use Toybox\Core\Components\User;
use Toybox\Core\Components\XMLRPC;
use WP_Screen;

// Disable file editing from wp-admin
if (! defined("DISALLOW_FILE_EDIT")) {
    define('DISALLOW_FILE_EDIT', true);
}

class Theme
{
    /**
     * The theme version.
     */
    const VERSION = "3.0.10";

    /**
     * This directory.
     */
    const CORE = __DIR__;

    /**
     * Boots the theme.
     *
     * @param bool  $disableCritical If set to true, the critical style will not be enqueued.
     * @param array $args            Settings to pass into Theme::setup().
     *
     * @return void
     * @throws Exception
     */
    public static function boot(bool $disableCritical = false, array $args = []): void
    {
        // Load snippets
        Snippets::boot();

        // Theme setup
        self::setup($args);

        // Enqueue styles and scripts
        Styles::boot($disableCritical);

        // Register blocks
        Blocks::boot();

        // Register post types
        PostType::boot();

        // Register ACF fields
        ACF::loadBlockACFFields();
        ACF::setPaths();

        // Register shortcodes
        Shortcode::boot();

        // Boot customizer sections
        Customizer::boot();

        // Boot admin styles
        Admin::boot();
    }

    /**
     * Initializes and configures the theme setup with provided arguments. This method handles theme support settings,
     * script and style enqueueing, admin customization, and other optimizations for the theme.
     *
     * @param array $args                           {
     *                                              Optional. An array of key-value pairs to configure the theme setup. Default values are:
     *
     * @type bool   $automatic_content_lazy_loading Enables content lazy loading for images and iframes. Default false.
     * @type bool   $automatic_feed_links           Adds default posts and comments RSS feed links. Default true.
     * @type bool   $custom_background              Enables support for custom backgrounds. Default true.
     * @type bool   $custom_logo                    Enables support for custom logos. Default true.
     * @type bool   $deregister_default_patterns    Deregisters default block patterns. Default true.
     * @type bool   $disable_dashicons              Disables WordPress dashicons for non-logged-in users. Default true.
     * @type bool   $disable_jquery_migrate         Disables jQuery Migrate script. Default true.
     * @type string $editor_styles                  Path to the editor styles CSS file, or false to disable. Default "assets/css/editor.css".
     * @type bool   $hide_welcome_panel             Hides the WordPress "Welcome Panel" in the dashboard. Default true.
     * @type bool   $post_thumbnails                Enables support for post thumbnails. Default true.
     * @type bool   $set_first_visit_cookie         Sets a cookie on the user's first visit. Default true.
     *                                              }
     *
     * @return void
     */
    private static function setup(array $args = []): void
    {
        // Default params
        $args = wp_parse_args($args, [
            "automatic_content_lazy_loading" => false,
            "automatic_feed_links"           => true,
            "custom_background"              => true,
            "custom_logo"                    => true,
            "deregister_default_patterns"    => true,
            "disable_dashicons"              => true,
            "disable_jquery_migrate"         => true,
            "do_shortcodes"                  => true,
            "editor_styles"                  => Assets::getPath("resources/scss/editor.scss"),
            "hide_welcome_panel"             => true,
            "post_thumbnails"                => true,
            "set_first_visit_cookie"         => true,
        ]);

        // Toybox setup start hook
        do_action("toybox_setup_start");

        add_action("after_setup_theme", function () use ($args) {
            if ($args["custom_logo"] === true) {
                // Enable custom logo
                add_theme_support('custom-logo');
            }

            if ($args["custom_background"]) {
                // Enable custom backgrounds
                add_theme_support('custom-background');
            }

            if ($args["automatic_feed_links"]) {
                // Add default posts and comments RSS feed links to <head>.
                add_theme_support('automatic-feed-links');
            }

            if ($args["post_thumbnails"]) {
                // Enable support for post thumbnails and featured images.
                add_theme_support('post-thumbnails');
            }

            if ($args["editor_styles"] !== false) {
                // Add the editor stylesheet (includes some core Gutenberg fixes)
                add_theme_support('editor-styles');
                add_editor_style($args["editor_styles"]);
            }

            // Add support for block styles
            add_theme_support('wp-block-styles');

            // Add support for responsive embeds
            add_theme_support('responsive-embeds');

            // Title tag support
            add_theme_support('title-tag');

            // HTML5 support
            add_theme_support('html5', ['comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script']);
        }, 2);

        // Enqueue the editor.js file when we're in the block editor.
        add_action('enqueue_block_editor_assets', function () {
            wp_enqueue_style('toybox-editor-css', Assets::getPath("/resources/scss/editor.scss"), [], '1.0.0');
            wp_enqueue_script('toybox-editor-js', Assets::getPath("/resources/js/editor.js"), [], '1.0.0', 'true');
        });

        add_filter("script_loader_tag", function ($tag, $handle) {
            if ($handle === "editor-js") {
                $tag = str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);

        // Tweak ACF WYSIWYG
        add_filter('acf/fields/wysiwyg/toolbars', function ($toolbars) {
            // Edit the "Full" toolbar and add 'fontsizeselect' if not already present.
            if ((array_search('fontsizeselect', $toolbars['Full'][2])) !== true) {
                $toolbars['Full'][2][] = 'fontsizeselect';
            }

            return $toolbars;
        });

        // Load only the blocks required for the page
        add_filter('should_load_separate_core_block_assets', '__return_true');

        // Set the first visit cookie
        if ($args["set_first_visit_cookie"] === true) {
            add_action("init", function () {
                if (isset($_COOKIE['_wp_first_time']) || User::loggedIn()) {
                    return false;
                } else {
                    // expires in 30 days.
                    setcookie('_wp_first_time', 1, time() + (WEEK_IN_SECONDS * 4), COOKIEPATH, COOKIE_DOMAIN, false);

                    return true;
                }
            });
        }

        // Include the style vars inside wp_head().
        add_action("wp_head", function () {
            include(Theme::CORE . "/stubs/StyleVars.php");
        });

        add_action("current_screen", function (WP_Screen $screen) {
            if (trim($screen->id) === "wp_block") {
                include(Theme::CORE . "/stubs/StyleVars.php");
            }
        });

        // Deregister core block patterns
        if ($args["deregister_default_patterns"] === true) {
            Pattern::deregisterDefaultPatterns();
        }

        // Register our default pattern
        Pattern::registerCategory("Theme Patterns", "theme-patterns");

        // Perform table optimisation
        Misc::optimizeTables();

        if ($args["hide_welcome_panel"] === true) {
            Admin::hideWelcomePanel();
        }

        Admin::disableUpdateNag();
        Admin::setFooterText();
        AdminBar::setLogo();
        AdminBar::addDocumentationLink();
        AdminBar::addClearCacheLink();
        Dashboard::hideWidgets();
        Header::cleanup();
        Misc::clearSettingsCacheOnOptionsSave();
        Login::boot();

        // Enables content lazy loading by automatically adding the "loading" attribute to all images and iframes.
        if ($args["automatic_content_lazy_loading"]) {
            Content::lazyLoad();
        }

        // Automatically parse shortcodes inside ACF fields
        if ($args["do_shortcodes"] !== false) {
            if ($args["do_shortcodes"] === true) {
                ACF::doShortcodes();
            } elseif (is_array($args["do_shortcodes"])) {
                ACF::doShortcodes($args["do_shortcodes"]);
            }
        }

        // Disables dashicons for non-logged-in users
        if ($args["disable_dashicons"]) {
            Dashicons::disable();
        }

        // Disable jQuery Migrate
        if ($args["disable_jquery_migrate"] === true) {
            jQuery::removeMigrate();
        }

        // Toybox setup complete hook
        do_action("toybox_setup_complete");
    }

    /**
     * Applies default configurations to enhance site performance, security, and functionality. This method manages settings
     * such as disabling unnecessary features, enabling support for additional file types, and optimizing image handling during uploads.
     *
     * @param array $args                           {
     *                                              Optional. An array of key-value pairs to configure the feature settings. Defaults are:
     *
     * @type bool   $add_file_upload_support        Enables support for additional file types (e.g., SVG, WebP, AVIF). Default true.
     * @type bool   $convert_images_on_upload       Converts uploaded images to WebP format with quality optimization. Default true.
     * @type bool   $disable_comments               Disables comments globally across the site. Default true.
     * @type bool   $disable_embeds                 Disables WordPress embed functionality (e.g., embedded external content). Default true.
     * @type bool   $disable_emoji                  Disables emoji scripts and styles for improved performance. Default true.
     * @type bool   $disable_xmlrpc                 Disables the XMLRPC feature for enhanced security. Default true.
     *                                              }
     *
     * @return void
     */
    public static function safeDefaults(array $args = []): void
    {
        $args = wp_parse_args($args, [
            "add_file_upload_support"  => true,
            "convert_images_on_upload" => true,
            "disable_comments"         => true,
            "disable_embeds"           => true,
            "disable_emoji"            => true,
            "disable_xmlrpc"           => true,
        ]);

        if ($args["disable_comments"] === true) {
            // Disable comments
            Comments::disable();
        }

        if ($args["disable_embeds"] === true) {
            // Disable embeds
            Embeds::disable();
        }

        if ($args["disable_emoji"] === true) {
            // Disable emoji
            Emoji::disable();
        }

        // Preload assets
        HTTP::preload();

        // Add early hints
        HTTP::hint(Assets::getPath('resources/scss/critical.scss'), "rel=preload; as=style");

        if ($args["convert_images_on_upload"] === true) {
            // Convert images to WebP on upload
            Image::convertOnUpload("webp", 80);
        }

        if ($args["add_file_upload_support"] === true) {
            // Adds support for additional file types
            Misc::addFileSupport([
                "svg"  => "image/svg+xml",
                "webp" => "image/webp",
                "avif" => "image/avif",
            ]);
        }

        // Stop WordPress auto-changing "Wordpress" to "WordPress".
        Misc::disableCapitalPDangit();

        if ($args["disable_xmlrpc"] === true) {
            // Disable XMLRPC.
            XMLRPC::disable();
        }
    }
}

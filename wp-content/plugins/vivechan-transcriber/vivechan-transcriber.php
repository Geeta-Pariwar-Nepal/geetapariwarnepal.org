<?php
/**
 * Plugin Name:       Vivechan Transcriber
 * Description:       YouTube transcript cleaner with AI (Groq / DeepSeek / Gemini) running inside WordPress. Access is restricted to logged-in users with the "Vivechak" or "Vivechan Editor" role, or administrators.
 * Version:           1.6.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Geeta Pariwar Nepal
 * Text Domain:       vivechan-transcriber
 * License:           GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

define('VIVECHAN_VERSION', '1.6.0');
define('VIVECHAN_FILE', __FILE__);
define('VIVECHAN_PATH', plugin_dir_path(__FILE__));
define('VIVECHAN_URL', plugin_dir_url(__FILE__));

require_once VIVECHAN_PATH . 'src/autoload.php';

register_activation_hook(__FILE__, ['Vivechan\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Vivechan\\Activator', 'deactivate']);

add_action('plugins_loaded', ['Vivechan\\Plugin', 'init']);

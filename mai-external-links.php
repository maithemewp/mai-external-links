<?php

/**
 * Plugin Name:     Mai External Links
 * Plugin URI:      https://bizbudding.com/
 * Description:     Finds external links in post content and comments and adds `target="_blank"` and `rel="noopener noreferrer"` so they safely open in a new tab.
 * Version:         1.1.0
 * Requires PHP:    8.2
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

declare( strict_types=1 );

namespace Mai\ExternalLinks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The updater needs the main plugin file's path, and __FILE__ inside src/ is
// the wrong file.
define( 'MAI_EXTERNAL_LINKS_FILE', __FILE__ );

// Include dependencies.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Links.php';
require_once __DIR__ . '/src/Updater.php';

/**
 * Boots the plugin once WordPress has loaded every other plugin.
 *
 * plugins_loaded, not an immediate call: Links reads home_url() and Updater
 * looks for a theme helper, and neither is reliable at file-include time.
 *
 * @since 1.1.0
 *
 * @return void
 */
add_action(
	'plugins_loaded',
	static function (): void {
		( new Links() )->register();
		( new Updater() )->register();
	}
);

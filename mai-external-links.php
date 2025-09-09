<?php

/**
 * Plugin Name:     Mai External Links
 * Plugin URI:      https://bizbudding.com/
 * Description:     Finds external links in the content and adds `target="_blank"` and `rel="noopener noreferrer"` so they safely open in a new tab.
 * Version:         0.1.0
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

namespace Mai\ExternalLinks;

use WP_HTML_Tag_Processor;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Include dependencies.
require_once __DIR__ . '/vendor/autoload.php';

add_filter( 'the_content', __NAMESPACE__ . '\handle_links', 20 );
/**
 * Handles the content links.
 *
 * @since 0.1.0
 *
 * @param string $content The content.
 *
 * @return string
 */
function handle_links( $content ) {
	// Bail if no content or not the main query.
	if ( ! $content || ! is_main_query() ) {
		return $content;
	}

	// Get home url host.
	$host = wp_parse_url( home_url(), PHP_URL_HOST );

	// Bail if no host.
	if ( ! $host ) {
		return $content;
	}

	// Set up tag processor.
	$tags = new WP_HTML_Tag_Processor( $content );

	// Loop through anchor link tags.
	while ( $tags->next_tag( [ 'tag_name' => 'a' ] ) ) {
		// Get href attribute.
		$href = $tags->get_attribute( 'href' );

		// Skip if no href or href contains the host.
		if ( ! $href || str_contains( $href, $host ) ) {
			continue;
		}

		// Skip if not an valid/absolute URL.
		if ( ! wp_http_validate_url( $href ) ) {
			continue;
		}

		// Set target to blank and rel to noopener noreferrer.
		$tags->set_attribute( 'target', '_blank' );
		$tags->set_attribute( 'rel', 'noopener noreferrer' );
	}

	// Get updated content.
	$content = $tags->get_updated_html();

	return $content;
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\updater' );
/**
 * Setup the updater.
 *
 * composer require yahnis-elsts/plugin-update-checker
 *
 * @since 0.1.0
 *
 * @uses https://github.com/YahnisElsts/plugin-update-checker/
 *
 * @return void
 */
function updater() {
	// Bail if plugin updater is not loaded.
	if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		return;
	}

	// Setup the updater.
	$updater = PucFactory::buildUpdateChecker( 'https://github.com/maithemewp/mai-external-links/', __FILE__, 'mai-external-links' );

	// Maybe set github api token.
	if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
		$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
	}

	// Add icons for Dashboard > Updates screen.
	if ( function_exists( 'mai_get_updater_icons' ) && $icons = \mai_get_updater_icons() ) {
		$updater->addResultFilter(
			function ( $info ) use ( $icons ) {
				$info->icons = $icons;
				return $info;
			}
		);
	}
}
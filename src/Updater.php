<?php

declare( strict_types=1 );

namespace Mai\ExternalLinks;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Self-updates from the GitHub repo.
 *
 * @since 1.1.0
 *
 * @uses https://github.com/YahnisElsts/plugin-update-checker/
 */
final class Updater {

	private const REPO = 'https://github.com/maithemewp/mai-external-links/';

	private const SLUG = 'mai-external-links';

	/**
	 * Wires the update checker to the GitHub repo.
	 *
	 * Returns early rather than failing when the library is absent, so a tree
	 * deployed without its vendor directory still loads and simply does not
	 * self-update.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$updater = PucFactory::buildUpdateChecker( self::REPO, MAI_EXTERNAL_LINKS_FILE, self::SLUG );

		if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
			$updater->setAuthentication( \MAI_GITHUB_API_TOKEN );
		}

		// Icons for the Dashboard > Updates screen, when the host theme supplies them.
		if ( ! function_exists( 'mai_get_updater_icons' ) ) {
			return;
		}

		$icons = \mai_get_updater_icons();

		if ( ! $icons ) {
			return;
		}

		$updater->addResultFilter(
			static function ( $info ) use ( $icons ) {
				$info->icons = $icons;

				return $info;
			}
		);
	}
}

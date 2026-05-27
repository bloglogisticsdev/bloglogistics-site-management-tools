<?php
/**
 * GitHub updater for BlogLogistics Site Management Tools.
 *
 * @package BlogLogistics_Site_Management_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! class_exists( 'BlogLogistics_Site_Management_Tools_GitHub_Updater', false ) ) {

    final class BlogLogistics_Site_Management_Tools_GitHub_Updater {

        /**
         * Initialise GitHub-based plugin updates.
         *
         * @param array<string, string> $args Updater arguments.
         */
        public static function init( array $args ): void {
            if (
                empty( $args['repo_url'] ) ||
                empty( $args['plugin_file'] ) ||
                empty( $args['slug'] )
            ) {
                return;
            }

            if ( ! class_exists( PucFactory::class ) ) {
                return;
            }

            $update_checker = PucFactory::buildUpdateChecker(
                $args['repo_url'],
                $args['plugin_file'],
                $args['slug']
            );

            $update_checker->getVcsApi()->enableReleaseAssets( '/\\.zip($|[?&#])/i' );
        }
    }
}

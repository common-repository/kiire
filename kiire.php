<?php
/**
 * Plugin Name: Kiire
 * Plugin URI:  https://implenton.com/kiire/
 * Description: Mark posts, pages or custom post types as important for easy access from a separate section or from toolbar.
 * Version:     1.0
 * Author:      implenton
 * Author URI:  https://implenton.com/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: kiire
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'WP_List_Table' ) ) {

    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

}

register_activation_hook( __FILE__, 'kiire_activate' );

add_action( 'admin_menu', 'kiire_register_page' );
add_action( 'admin_post_kiire', 'kiire_add_remove_important_post' );
add_action( 'admin_notices', 'kiire_notice' );
add_action( 'post_submitbox_misc_actions', 'kiire_add_publish_meta_action' );
add_action( 'admin_bar_menu', 'kiire_toolbar_link', 90 );
add_action( 'admin_enqueue_scripts', 'kiire_css' );
add_action( 'wp_enqueue_scripts', 'kiire_toolbar_frontend_css' );
add_action( 'plugins_loaded', 'kiire_load_textdomain' );

add_filter( 'page_row_actions', 'kiire_add_row_action', 10, 2 );
add_filter( 'post_row_actions', 'kiire_add_row_action', 10, 2 );
add_filter( 'set-screen-option', 'kiire_set_screen_option', 10, 3 );

function kiire_activate() {

    register_uninstall_hook( __FILE__, 'kiire_uninstall' );

}

function kiire_register_page() {

    global $kiire_page;

    $kiire_page = add_menu_page(
        __( 'Importants', 'kiire' ),
        __( 'Importants', 'kiire' ),
        'edit_others_posts',
        'kiire',
        'kiire_posts_page',
        'dashicons-sticky',
        2
    );

    add_action( 'load-' . $kiire_page, 'kiire_screen_options' );

}

function kiire_screen_options() {

    global $kiire_page;

    $screen = get_current_screen();

    if ( ! is_object( $screen ) || $screen->id != $kiire_page ) {

        return;

    }

    $args = array(
        'label'   => esc_html__( 'Importants per page', 'kiire' ),
        'default' => 20,
        'option'  => 'kiire_per_page',
    );

    add_screen_option( 'per_page', $args );

}

function kiire_set_screen_option( $status, $option, $value ) {

    if ( 'kiire_per_page' == $option ) {

        return $value;

    }

}

/**
 * Handles both adding and removing posts from the importants lists.
 *
 * It saved the ID of the post, the time it was marked and the user (for future use).
 * After updating it redirects back.
 *
 * When marking or removing from the admin area it appends arguments and params
 * to the URL to trigger the notifications.
 */
function kiire_add_remove_important_post() {

    if ( ! check_admin_referer( 'kiire_add_remove' ) ) {

        wp_die();

    }

    $post_id         = $_REQUEST['id'];

    $important_posts = kiire_get_importants();

    if ( kiire_is_important_post( $post_id, $important_posts ) ) {

        $action = 'remove';
        unset( $important_posts[$post_id] );

    } else {

        $action = 'add';
        $important_posts[$post_id] = array(
            'post_id' => $post_id,
            'time'    => current_time( 'mysql' ),
            'user'    => get_current_user_id(),
        );

    }

    update_option( 'kiire_posts_' . get_current_user_id(), $important_posts );

    if ( false === strpos( wp_get_referer(), admin_url() ) ) {

        $redirect_to = wp_get_referer();

    } else {

        $redirect_to = add_query_arg( array(
            'kiire' => $action,
            'id'    => $post_id,
        ), wp_get_referer() );

    }

    wp_safe_redirect( $redirect_to );

}

/**
 * Handles bulk removing from the table list.
 */
function kiire_bulk_remove_important_posts( $posts ) {

    $important_posts = kiire_get_importants();

    foreach ( $posts as $post ) {

        unset( $important_posts[$post] );

    }

    update_option( 'kiire_posts_' . get_current_user_id(), $important_posts );

}

/**
 * Build up and show the admin notification after marking or removing a post
 * from the importants list.
 *
 * It includes:
 * - the title of the post
 * - undo link
 * - a link to the important pages section
 */
function kiire_notice() {

    if ( ! isset( $_GET['kiire'] ) ) {

        return;

    }

    switch ( $_GET['kiire'] ) {
        case 'add':
            $message = __( 'was added to importants', 'kiire' );
            break;
        case 'remove':
            $message = __( 'was removed from importants', 'kiire' );
            break;
    }

    $view_posts = sprintf(
        '&mdash; <a href="%1$s">%2$s</a>',
        esc_url( wp_nonce_url( add_query_arg( array(
            'action' => 'kiire',
            'id'     => $_GET['id'],
        ), get_admin_url( null, 'admin-post.php' ) ), 'kiire_add_remove' ) ),
        esc_html__( 'Undo', 'kiire' )
    );

    if ( kiire_get_importants() && get_current_screen()->parent_base != 'kiire' ) {

        $view_posts = sprintf(
            '&mdash; <a href="%1$s">%2$s</a> &mdash; <a href="%3$s">%4$s</a>',
            esc_url( wp_nonce_url( add_query_arg( array(
                'action' => 'kiire',
                'id'     => $_GET['id'],
            ), get_admin_url( null, 'admin-post.php' ) ), 'kiire_add_remove' ) ),
            esc_html__( 'Undo', 'kiire' ),
            esc_url( menu_page_url( 'kiire', false ) ),
            esc_html__( 'View all your important posts', 'kiire' )
        );

    }

    echo sprintf(
        '<div class="notice notice-success is-dismissible"><p>"%1$s" %2$s %3$s</p></div>',
        esc_html( get_the_title( $_GET['id'] ) ),
        esc_html( $message ),
        $view_posts
    );

}

/**
 * Appends the link to the Publish meta box
 */
function kiire_add_publish_meta_action( $post ) {

    if ( kiire_should_we_add_the_link( $post ) ) {

        echo sprintf(
            '<div class="misc-pub-section misc-pub-kiire">%1$s</div>',
            kiire_build_action_link( $post )
        );

    }

}

function kiire_toolbar_link() {

    global $wp_admin_bar;
    global $post;

    if ( current_user_can( 'edit_others_posts' ) ) {

        $wp_admin_bar->add_node( array(
            'id'     => 'kiire',
            'title' => sprintf(
                '<span class="ab-icon"></span><span class="ab-label">%1$s</span>',
                esc_html__( 'Importants', 'kiire' )
            ),
            'href'   => esc_url( add_query_arg( 'page', 'kiire', get_admin_url( null, 'admin.php' ) ) ),
        ) );

        if ( ! is_admin() && is_singular() && kiire_should_we_add_the_link( $post ) ) {

            $post_id = get_the_id();
            $label   = esc_html__( 'Mark as important', 'kiire' );

            if ( kiire_is_important_post( $post_id ) ) {

                $label = esc_html__( 'Remove from importants', 'kiire' );

            }

            $wp_admin_bar->add_node( array(
                'id'     => 'kiire_current_page',
                'title'  => $label,
                'href'   => esc_url( wp_nonce_url( add_query_arg( array(
                    'id'     => $post_id,
                    'action' => 'kiire',
                ), get_admin_url( null, 'admin-post.php' ) ), 'kiire_add_remove' ) ),
                'parent' => 'kiire',
            ) );

        }

        if ( $important_posts = kiire_get_importants() ) {

            $wp_admin_bar->add_group( array(
                'id'     => 'kiire_posts',
                'parent' => 'kiire',
                'meta'   => array(
                    'class' => 'kiire-posts-group',
                ),
            ) );

            $wp_admin_bar->add_node( array(
                'id'     => 'kiire_posts_title',
                'title'  => esc_html__( 'Last marked:', 'kiire' ),
                'parent' => 'kiire_posts',
                'meta'   => array(
                    'class' => 'kiire-toolbar-last-title'
                )
            ) );

            $important_posts = array_slice( $important_posts, 0, 5, true );

            foreach ( $important_posts as $key => $value ) {

                $important_post = get_post( $key );

                $wp_admin_bar->add_node( array(
                    'id'     => 'kiire_' . esc_attr( $important_post->post_name ),
                    'title'  => sprintf(
                        '%s <small>&ndash; %s</small>',
                        esc_html( $important_post->post_title ),
                        esc_html( get_post_type_object( $important_post->post_type )->labels->singular_name )
                    ),
                    'href'   => esc_url( add_query_arg( array(
                        'post'   => $important_post->ID,
                        'action' => 'edit',
                    ), get_admin_url( null, 'post.php' ) ) ),
                    'parent' => 'kiire_posts',
                ) );

            }

        }

    }

}

/**
 * Add CSS to the admin area.
 */
function kiire_css() {

    wp_register_style( 'kiire', plugin_dir_url( __FILE__ ) . 'kiire.css', false, '1.0.0' );
    wp_enqueue_style( 'kiire' );

}

/**
 * Add the CSS necesary for the toolbar on the frontend.
 *
 * Make use of wp_add_inline_style to reduce the number of requested CSS files.
 */
function kiire_toolbar_frontend_css() {

    $css = '
    #wpadminbar #wp-admin-bar-kiire .ab-icon:before {
        content: "\f537";
        top: 3px;
    }

    .kiire-toolbar-last-title {
        opacity: .5;
        font-weight: bold;
    }

    #wpadminbar .kiire-posts-group small {
        font-size: 11px;
    }
    ';

    wp_add_inline_style( 'admin-bar', $css );

}

function kiire_load_textdomain() {

    load_plugin_textdomain( 'kiire', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

}

/**
 * Check if we should append and links to the screen.
 *
 * By default it appends to all post types, however you can exclude some
 * post types using the "kiire_exclude_post_type" filter.
 */
function kiire_should_we_add_the_link( $post ) {

    $post_type    = get_post_type_object( $post->post_type );

    $exclude_from = apply_filters( 'kiire_exclude_post_type', array() );

    if ( in_array( $post->post_type, $exclude_from ) || ! current_user_can( 'edit_others_posts' ) ) {

        return false;

    }

    return true;

}

/**
 * Appends the link to row actions.
 */
function kiire_add_row_action( $actions, $post ) {

    if ( kiire_should_we_add_the_link( $post ) ) {

        $actions[] = kiire_build_action_link( $post );

    }

    return $actions;

}

function kiire_get_importants() {

    if ( $posts = get_option( 'kiire_posts_' . get_current_user_id() ) ) {

        uasort( $posts, function( $a, $b ) {
            return -strcmp( $a['time'], $b['time'] );
        } );

        return $posts;

    }

    return array();

}

/**
 * Determining if a post is in the importants list.
 *
 * Returns true of false.
 */
function kiire_is_important_post( $post_id, $posts = null ) {

    if ( is_null( $posts ) ) {

        $important_posts = kiire_get_importants();

    } else {

        $important_posts = $posts;

    }

    if ( array_key_exists( $post_id, $important_posts ) ) {

        return true;

    }

    return false;

}

/**
 * Builds the anchor tag with the proper URL and text.
 *
 * It does escaping and checks if the post is already in the importants list.
 */
function kiire_build_action_link( $post ) {

    $post_id = $post->ID;

    $link    = wp_nonce_url( add_query_arg( array(
        'action' => 'kiire',
        'id'     => $post_id,
    ), get_admin_url( null, 'admin-post.php' ) ), 'kiire_add_remove' );

    $label   = __( 'Mark as important', 'kiire' );

    if ( kiire_is_important_post( $post_id ) ) {
        $label = __( 'Remove from importants', 'kiire' );
    }

    return sprintf(
        '<a class="kiire-link" href="%1$s">%2$s</a>',
        esc_url( $link ),
        esc_html( $label )
    );

}

function kiire_posts_page() {

    ?>

    <div class="wrap">

        <h1><?php esc_html_e( 'Importants', 'kiire' ); ?></h1>

        <form id="kiire-filter" method="get">

            <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>">

            <?php

            $important_posts_table = new Kiire_List_Table();
            $important_posts_table->prepare_items();
            $important_posts_table->display();

            ?>

        </form>

    </div>

    <?php

}

/**
 * Remove the data from the _options after uninstalling
 */
function kiire_uninstall() {

    global $wpdb;

    $wpdb->query( $wpdb->prepare(
        "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
        'kiire_posts_%'
    ) );

}

class Kiire_List_Table extends WP_List_Table {

    function __construct() {

        global $status, $page;

        parent::__construct( array(
            'singular'  => 'important',
            'plural'    => 'importants',
            'ajax'      => false
        ) );

    }

    function get_data() {

        $data = array();

        if ( $important_posts = kiire_get_importants() ) {

            foreach ( $important_posts as $key => $value ) {

                $important_post = get_post( $key );

                $data[] = array(
                    'title'     => $important_post->post_title,
                    'post_type' => $important_post->post_type,
                    'id'        => $important_post->ID,
                    'time'      => $value['time'],
                );

            }

        }

        return $data;

    }

    function get_bulk_actions() {

        $actions = array(
            'remove' => 'Remove',
        );

        return $actions;

    }

    function process_bulk_action() {

        if ( 'remove' === $this->current_action() ) {

            kiire_bulk_remove_important_posts( $_REQUEST['important'] );

        }

    }

    function get_columns() {

        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'title'     => esc_html__( 'Title', 'kiire' ),
            'post_type' => esc_html__( 'Post Type', 'kiire' ),
            'time'      => esc_html__( 'Time added', 'kiire' ),
        );

        return $columns;

    }

    function column_cb( $item ) {

        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'],
            $item['id']
        );

    }

    function column_title( $item ) {

        $actions = array(
            'edit' => sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url( add_query_arg( array(
                    'action' => 'edit',
                    'post'   => $item['id'],
                ), get_admin_url( null, 'post.php' ) ) ),
                esc_html__( 'Edit', 'kiire' )
            ),
            'view' => sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url( get_permalink( $item['id'] ) ),
                esc_html__( 'View', 'kiire' )
            ),
            'mark' => sprintf(
                '<a class="kiire-link" href="%1$s">%2$s</a>',
                esc_url( wp_nonce_url( add_query_arg( array(
                    'action' => 'kiire',
                    'id'     => $item['id'],
                ), get_admin_url( null, 'admin-post.php' ) ), 'kiire_add_remove' ) ),
                esc_html__( 'Remove from importants', 'kiire' )
            )
        );

        return sprintf('%s %s',
            sprintf(
                '<a href="%1$s" class="row-title"><strong>%2$s</strong></a>',
                esc_url( add_query_arg( array(
                    'action' => 'edit',
                    'post'   => $item['id'],
                ), get_admin_url( null, 'post.php' ) ) ),
                esc_html( $item['title'] )
            ),
            $this->row_actions($actions)
        );

    }

    function column_post_type( $item ) {

        return esc_html( get_post_type_object( $item['post_type'] )->labels->singular_name );

    }

    function column_time( $item ) {

        return sprintf(
            _x( '%s ago', '%s = human-readable time difference', 'kiire' ),
            human_time_diff( strtotime( $item['time'] ), current_time( 'timestamp' ) )
        );

    }

    function get_sortable_columns() {

        $sortable_columns = array(
            'title'     => array( 'title', false ),
            'post_type' => array( 'post_type', false ),
            'time'      => array( 'time', false ),
        );

        return $sortable_columns;

    }

    function prepare_items() {

        $user = get_current_user_id();

        $screen = get_current_screen();

        $screen_option = $screen->get_option( 'per_page', 'option' );

        $per_page = get_user_meta( $user, $screen_option, true );

        if ( empty ( $per_page) || $per_page < 1 ) {

            $per_page = $screen->get_option( 'per_page', 'default' );

        }

        $columns  = $this->get_columns();

        $hidden   = array();

        $sortable = $this->get_sortable_columns();

        $data     = $this->get_data();

        $this->process_bulk_action();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        function uasort_order( $a, $b ) {

            $orderby = ( !empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'time';
            $order   = ( !empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'desc';
            $result  = strcmp( $a[$orderby], $b[$orderby] );

            return ( $order === 'asc' ) ? $result : -$result;

        }

        $current_page = $this->get_pagenum();

        $total_items  = count( $data );

        if ( $data ) {

            uasort( $data, 'uasort_order' );

            $data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

        }

        $this->items = $data;

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );

    }

}
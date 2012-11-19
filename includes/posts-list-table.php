<?php
/**
 * Class for instant search on Manage Posts screens.
 *
 * @package Better_Internal_Link_Search
 *
 * @since 1.2.0
 */
class Better_Internal_Link_Search_Posts_List_Table {
	/**
	 * Load the post list table instant search feature.
	 *
	 * @since 1.2.0
	 */
	public static function load() {
		add_action( 'init', array( __CLASS__, 'init' ) );
	}
	
	/**
	 * Setup the post list table search functionality.
	 *
	 * @since 1.2.0
	 */
	public static function init() {
		add_action( 'wp_ajax_bils_get_posts_list_table', array( __CLASS__, 'ajax_get_posts_list_table' ) );
		add_action( 'admin_head-edit.php', array( __CLASS__, 'admin_head_edit' ) );
		add_action( 'admin_head-upload.php', array( __CLASS__, 'admin_head_edit' ) );
	}
	
	/**
	 * Enqueue javascript and output CSS for post list table searching.
	 *
	 * @todo Implement paging if necessary.
	 *
	 * @since 1.2.0
	 */
	public static function admin_head_edit() {
		$screen = get_current_screen();
		
		wp_enqueue_script( 'bils-posts-list-table', BETTER_INTERNAL_LINK_SEARCH_URL . 'js/posts-list-table.js', array( 'jquery' ) );
		wp_localize_script( 'bils-posts-list-table', 'BilsListTable', array(
			'nonce'          => wp_create_nonce( 'bils-posts-list-table-instant-search' ),
			'postMimeType'   => ( isset( $_REQUEST['post_mime_type'] ) ) ? $_REQUEST['post_mime_type'] : null,
			'postType'       => ( 'upload' == $screen->id ) ? 'attachment' : $screen->post_type,
			'screen'         => $screen->id,
			'spinner'        => self::spinner( array( 'echo' => false ) ),
			'subtitlePrefix' => __( 'Search results for &#8220;%s&#8221;', 'better-internal-link-search-i18n' )
		) );
		?>
		<style type="text/css">
		.wp-list-table #the-list .no-items img.ajax-loading,
		#posts-filter .search-box img.ajax-loading { visibility: visible;}
		.wp-list-table #the-list .no-items .spinner { display: inline;}
		#posts-filter .search-box .spinner { display: none; float: left; margin: 5px 3px 0 0;}
		</style>
		<?php
	}
	
	/**
	 * Get the post list table rows for the searched term.
	 *
	 * Mimics admin/edit.php without all the chrome elements.
	 *
	 * @since 1.2.0
	 */
	public static function ajax_get_posts_list_table() {
		global $post_type, $post_type_object, $per_page, $mode, $wp_query;
		
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'bils-posts-list-table-instant-search' ) ) {
			echo '<tr class="no-items bils-error"><td class="colspanchange">Invalid nonce.</td></tr>';
			wp_die();
		}
		
		$post_type = $_REQUEST['post_type'];
		$post_type_object = get_post_type_object( $post_type );
		
		// Determine the orderby argument.
		if ( isset( $_REQUEST['orderby'] ) && ! empty( $_REQUEST['orderby'] ) ) {
			$orderby = $_REQUEST['orderby'];
		} else {
			$orderby = ( $post_type_object->hierarchical ) ? 'title' : 'post_date';
		}
		
		// Determine the order argument.
		if ( isset( $_REQUEST['order'] ) && ! empty( $_REQUEST['order'] ) ) {
			$order = ( 'asc' == strtolower( $_REQUEST['order'] ) ) ? 'asc' : 'desc';
		} else {
			$order = ( $post_type_object->hierarchical ) ? 'asc' : 'desc';
		}
		
		$args = array(
			's'                => $_REQUEST['s'],
			'post_type'        => $post_type,
			'post_status'      => $_REQUEST['post_status'],
			'orderby'          => $orderby,
			'order'            => $order,
			'posts_per_page'   => 20,
			'suppress_filters' => true
		);
		
		if ( 'attachment' == $post_type ) {
			$args['post_status'] = 'inherit';
			$args['post_mime_type'] = $_REQUEST['post_mime_type'];
		}
		
		set_current_screen( $_REQUEST['screen'] );
		
		add_filter( 'posts_search', array( 'Better_Internal_Link_Search', 'limit_search_to_title' ), 10, 2 );
		
		$wp_query = new WP_Query( $args );
		
		if ( 'attachment' == $post_type ) {
			$wp_list_table = _get_list_table( 'WP_Media_List_Table' );
		} else {
			$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		}
		
		$wp_list_table->prepare_items();
		$wp_list_table->display_rows_or_placeholder();
		
		wp_die();
	}
	
	/**
	 * Backwards compatible spinner.
	 * 
	 * Displays the correct spinner depending on the version of WordPress.
	 *
	 * @since 1.2.0
	 *
	 * @param array $args Array of args to modify output.
	 * @return void|string Echoes spinner HTML or returns it.
	 */
	function spinner( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'id' => '',
			'class' => 'ajax-loading',
			'echo' => true
		) );
		
		if ( version_compare( get_bloginfo( 'version' ), '3.5-beta-1', '<' ) ) {
			$spinner = sprintf( '<img src="%1$s" id="%2$s" class="spinner %3$s" alt="">',
				esc_url( admin_url( 'images/wpspin_light.gif' ) ),
				esc_attr( $args['id'] ),
				esc_attr( $args['class'] )
			);
		} else {
			$spinner = sprintf( '<span id="%1$s" class="spinner"></span>', esc_attr( $args['id'] ) );
		}
		
		if ( $args['echo'] ) {
			echo $spinner;
		} else {
			return $spinner;
		}
	}
}
?>
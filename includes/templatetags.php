<?php

if ( !function_exists( 'bp_is_root_blog' ) ) :
	/**
	 * Is this BP_ROOT_BLOG?
	 *
	 * I'm hoping to have this function in BP 1.3 core, but just in case, here's a
	 * conditionally-loaded version. Checks against $wpdb->blogid, which provides greater
	 * support for switch_to_blog()
	 *
	 * @package BuddyPress Docs
	 * @since 1.0.4
	 *
	 * @return bool $is_root_blog Returns true if this is BP_ROOT_BLOG. Always true on non-MS
	 */
	function bp_is_root_blog() {
		global $wpdb;

		$is_root_blog = true;

		if ( is_multisite() && $wpdb->blogid != BP_ROOT_BLOG )
			$is_root_blog = false;

		return apply_filters( 'bp_is_root_blog', $is_root_blog );
	}
endif;

/**
 * Initiates a BuddyPress Docs query
 *
 * @since 1.2
 */
function bp_docs_has_docs( $args = array() ) {
	global $bp;

	// The if-empty is because, like with WP itself, we use bp_docs_has_docs() both for the
	// initial 'if' of the loop, as well as for the 'while' iterator. Don't want infinite
	// queries
	if ( empty( $bp->bp_docs->doc_query ) ) {
		// Build some intelligent defaults

		// Default to current group id, if available
		$d_group_id  = bp_is_group() ? bp_get_current_group_id() : array();

		// If this is a Started By tab, set the author ID
		$d_author_id = bp_docs_is_started_by() ? bp_displayed_user_id() : array();

		// If this is an Edited By tab, set the edited_by id
		$d_edited_by_id = bp_docs_is_edited_by() ? bp_displayed_user_id() : array();

		// Default to the tags in the URL string, if available
		$d_tags = isset( $_REQUEST['bpd_tag'] ) ? explode( ',', urldecode( $_REQUEST['bpd_tag'] ) ) : array();

		// Order and orderby arguments
		$d_orderby = !empty( $_GET['orderby'] ) ? urldecode( $_GET['orderby'] ) : apply_filters( 'bp_docs_default_sort_order', 'modified' ) ;

		if ( empty( $_GET['order'] ) ) {
			// If no order is explicitly stated, we must provide one.
			// It'll be different for date fields (should be DESC)
			if ( 'modified' == $d_orderby || 'date' == $d_orderby )
				$d_order = 'DESC';
			else
				$d_order = 'ASC';
		} else {
			$d_order = $_GET['order'];
		}

		// Search
		$d_search_terms = !empty( $_GET['s'] ) ? urldecode( $_GET['s'] ) : '';

		// Parent id
		$d_parent_id = !empty( $_REQUEST['parent_doc'] ) ? (int)$_REQUEST['parent_doc'] : '';

		// Page number, posts per page
		$d_paged          = !empty( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$d_posts_per_page = !empty( $_GET['posts_per_page'] ) ? absint( $_GET['posts_per_page'] ) : 10;

		// doc_slug
		$d_doc_slug = !empty( $bp->bp_docs->query->doc_slug ) ? $bp->bp_docs->query->doc_slug : '';

		$defaults = array(
			'doc_id'         => array(),      // Array or comma-separated string
			'doc_slug'       => $d_doc_slug,  // String (post_name/slug)
			'group_id'       => $d_group_id,  // Array or comma-separated string
			'parent_id'      => $d_parent_id, // int
			'author_id'      => $d_author_id, // Array or comma-separated string
			'edited_by_id'   => $d_edited_by_id, // Array or comma-separated string
			'tags'           => $d_tags,      // Array or comma-separated string
			'order'          => $d_order,        // ASC or DESC
			'orderby'        => $d_orderby,   // 'modified', 'title', 'author', 'created'
			'paged'	         => $d_paged,
			'posts_per_page' => $d_posts_per_page,
			'search_terms'   => $d_search_terms
		);
		$r = wp_parse_args( $args, $defaults );

		$doc_query_builder      = new BP_Docs_Query( $r );
		$bp->bp_docs->doc_query = $doc_query_builder->get_wp_query();
	}

	return $bp->bp_docs->doc_query->have_posts();
}

/**
 * Part of the bp_docs_has_docs() loop
 *
 * @since 1.2
 */
function bp_docs_the_doc() {
	global $bp;

	return $bp->bp_docs->doc_query->the_post();
}

/**
 * Determine whether you are viewing a BuddyPress Docs page
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @return bool
 */
function bp_docs_is_bp_docs_page() {
	global $bp, $post;

	$is_bp_docs_page = false;

	// This is intentionally ambiguous and generous, to account for BP Docs is different
	// components. Probably should be cleaned up at some point
	if ( isset( $bp->bp_docs->slug ) && $bp->bp_docs->slug == bp_current_component()
	     ||
	     isset( $bp->bp_docs->slug ) && $bp->bp_docs->slug == bp_current_action()
	     ||
	     isset( $post->post_type ) && bp_docs_get_post_type_name() == $post->post_type
	     ||
	     is_post_type_archive( bp_docs_get_post_type_name() )
	   )
		$is_bp_docs_page = true;

	return apply_filters( 'bp_docs_is_bp_docs_page', $is_bp_docs_page );
}


/**
 * Returns true if the current page is a BP Docs edit or create page (used to load JS)
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @returns bool
 */
function bp_docs_is_wiki_edit_page() {
	global $bp;

	$item_type = BP_Docs_Query::get_item_type();
	$current_view = BP_Docs_Query::get_current_view( $item_type );

	return apply_filters( 'bp_docs_is_wiki_edit_page', $is_wiki_edit_page );
}


/**
 * Echoes the output of bp_docs_get_info_header()
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 */
function bp_docs_info_header() {
	echo bp_docs_get_info_header();
}
	/**
	 * Get the info header for a list of docs
	 *
	 * Contains things like tag filters
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 *
	 * @param int $doc_id optional The post_id of the doc
	 * @return str Permalink for the group doc
	 */
	function bp_docs_get_info_header() {
		$filters = bp_docs_get_current_filters();

		// Set the message based on the current filters
		if ( empty( $filters ) ) {
			$message = __( 'You are viewing <strong>all</strong> docs.', 'bp-docs' );
		} else {
			$message = array();

			$message = apply_filters( 'bp_docs_info_header_message', $message, $filters );

			$message = implode( "\n", $message );

			// We are viewing a subset of docs, so we'll add a link to clear filters
			$message .= ' - ' . sprintf( __( '<strong><a href="%s" title="View All Docs">View All Docs</a></strong>', 'bp_docs' ), bp_docs_get_item_docs_link() );
		}

		?>

		<p class="currently-viewing"><?php echo $message ?></p>

		<form action="<?php bp_docs_item_docs_link() ?>" method="post">

			<div class="docs-filters">
				<?php do_action( 'bp_docs_filter_markup' ) ?>
			</div>

			<div class="clear"> </div>

			<?php /*
			<input class="button" id="docs-filter-submit" name="docs-filter-submit" value="<?php _e( 'Submit', 'bp-docs' ) ?>" type="submit" />
			*/ ?>

		</form>


		<?php
	}

/**
 * Filters the output of the doc list header for search terms
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @return array $filters
 */
function bp_docs_search_term_filter_text( $message, $filters ) {
	if ( !empty( $filters['search_terms'] ) ) {
		$message[] = sprintf( __( 'You are searching for docs containing the term <em>%s</em>', 'bp-docs' ), esc_html( $filters['search_terms'] ) );
	}

	return $message;
}
add_filter( 'bp_docs_info_header_message', 'bp_docs_search_term_filter_text', 10, 2 );

/**
 * Get the filters currently being applied to the doc list
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @return array $filters
 */
function bp_docs_get_current_filters() {
	$filters = array();

	// First check for tag filters
	if ( !empty( $_REQUEST['bpd_tag'] ) ) {
		// The bpd_tag argument may be comma-separated
		$tags = explode( ',', urldecode( $_REQUEST['bpd_tag'] ) );

		foreach( $tags as $tag ) {
			$filters['tags'][] = $tag;
		}
	}

	// Now, check for search terms
	if ( !empty( $_REQUEST['s'] ) ) {
		$filters['search_terms'] = urldecode( $_REQUEST['s'] );
	}

	return apply_filters( 'bp_docs_get_current_filters', $filters );
}

/**
 * Echoes the output of bp_docs_get_doc_link()
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 */
function bp_docs_doc_link( $doc_id = false ) {
	echo bp_docs_get_doc_link( $doc_id );
}
	/**
	 * Get the doc's permalink
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 *
	 * @param int $doc_id
	 * @return str URL of the doc
	 */
	function bp_docs_get_doc_link( $doc_id = false ) {
		if ( false === $doc_id && $q = get_queried_object() ) {
			$doc_id = isset( $q->ID ) ? $q->ID : 0;
		}

		return apply_filters( 'bp_docs_get_doc_link', get_permalink( $doc_id ), $doc_id );
	}

/**
 * Echoes the output of bp_docs_get_doc_edit_link()
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */
function bp_docs_doc_edit_link( $doc_id = false ) {
	echo bp_docs_get_doc_edit_link( $doc_id );
}
	/**
	 * Get the edit link for a doc
	 *
	 * @package BuddyPress_Docs
	 * @since 1.2
	 *
	 * @param int $doc_id
	 * @return str URL of the edit page for the doc
	 */
	function bp_docs_get_doc_edit_link( $doc_id = false ) {
		return apply_filters( 'bp_docs_get_doc_edit_link', trailingslashit( bp_docs_get_doc_link( $doc_id ) . BP_DOCS_EDIT_SLUG ) );
	}

/**
 * Echoes the output of bp_docs_get_archive_link()
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */
function bp_docs_archive_link() {
        echo bp_docs_get_archive_link();
}
        /**
         * Get the link to the main site Docs archive
         *
         * @package BuddyPress_Docs
         * @since 1.2
         */
        function bp_docs_get_archive_link() {
                return apply_filters( 'bp_docs_get_archive_link', trailingslashit( get_post_type_archive_link( bp_docs_get_post_type_name() ) ) );
        }

/**
 * Echoes the output of bp_docs_get_mygroups_link()
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */
function bp_docs_mygroups_link() {
        echo bp_docs_get_mygroups_link();
}
        /**
         * Get the link the My Groups tab of the Docs archive
         *
         * @package BuddyPress_Docs
         * @since 1.2
         */
        function bp_docs_get_mygroups_link() {
                return apply_filters( 'bp_docs_get_mygroups_link', trailingslashit( bp_docs_get_archive_link() . BP_DOCS_MY_GROUPS_SLUG ) );
        }

/**
 * Echoes the output of bp_docs_get_mydocs_link()
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */
function bp_docs_mydocs_link() {
        echo bp_docs_get_mydocs_link();
}
        /**
         * Get the link to the My Docs tab of the logged in user
         *
         * @package BuddyPress_Docs
         * @since 1.2
         */
        function bp_docs_get_mydocs_link() {
                return apply_filters( 'bp_docs_get_mydocs_link', trailingslashit( bp_loggedin_user_domain() . bp_docs_get_slug() ) );
        }

/**
 * Echoes the output of bp_docs_get_mydocs_started_link()
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */
function bp_docs_mydocs_started_link() {
        echo bp_docs_get_mydocs_started_link();
}
        /**
         * Get the link to the Started By Me tab of the logged in user
         *
         * @package BuddyPress_Docs
         * @since 1.2
         */
        function bp_docs_get_mydocs_started_link() {
                return apply_filters( 'bp_docs_get_mydocs_started_link', trailingslashit( bp_docs_get_mydocs_link() . BP_DOCS_STARTED_SLUG ) );
        }

/**
 * Echoes the output of bp_docs_get_mydocs_edited_link()
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */
function bp_docs_mydocs_edited_link() {
        echo bp_docs_get_mydocs_edited_link();
}
        /**
         * Get the link to the Edited By Me tab of the logged in user
         *
         * @package BuddyPress_Docs
         * @since 1.2
         */
        function bp_docs_get_mydocs_edited_link() {
                return apply_filters( 'bp_docs_get_mydocs_edited_link', trailingslashit( bp_docs_get_mydocs_link() . BP_DOCS_EDITED_SLUG ) );
        }




/**
 * Echoes the output of bp_docs_get_create_link()
 *
 * @package BuddyPress_Docs
 * @since 1.2
 */
function bp_docs_create_link() {
        echo bp_docs_get_create_link();
}
        /**
         * Get the link to create a Doc
         *
         * @package BuddyPress_Docs
         * @since 1.2
         */
        function bp_docs_get_create_link() {
                return apply_filters( 'bp_docs_get_create_link', trailingslashit( bp_docs_get_archive_link() . BP_DOCS_CREATE_SLUG ) );
        }

/**
 * Echoes the output of bp_docs_get_item_docs_link()
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 */
function bp_docs_item_docs_link() {
	echo bp_docs_get_item_docs_link();
}
	/**
	 * Get the link to the docs section of an item
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 *
	 * @return array $filters
	 */
	function bp_docs_get_item_docs_link( $args = array() ) {
		global $bp;

		// @todo Disabling for now!!
		return;

		$d_item_type = '';
		if ( bp_is_user() ) {
			$d_item_type = 'user';
		} else if ( bp_is_active( 'groups' ) && bp_is_group() ) {
			$d_item_type = 'group';
		}

		switch ( $d_item_type ) {
			case 'user' :
				$d_item_id = bp_displayed_user_id();
				break;
			case 'group' :
				$d_item_id = bp_get_current_group_id();
				break;
		}

		$defaults = array(
			'item_id'	=> $d_item_id,
			'item_type'	=> $d_item_type
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		if ( !$item_id || !$item_type )
			return false;

		switch ( $item_type ) {
			case 'group' :
				if ( !$group = $bp->groups->current_group )
					$group = groups_get_group( array( 'group_id' => $item_id ) );

				$base_url = bp_get_group_permalink( $group );
				break;

			case 'user' :
				$base_url = bp_core_get_user_domain( $item_id );
				break;
		}

		return apply_filters( 'bp_docs_get_item_docs_link', $base_url . $bp->bp_docs->slug . '/', $base_url, $r );
	}

/**
 * Get the sort order for sortable column links
 *
 * Detects the current sort order and returns the opposite
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @return str $new_order Either desc or asc
 */
function bp_docs_get_sort_order( $orderby = 'modified' ) {

	$new_order	= false;

	// We only want a non-default order if we are currently ordered by this $orderby
	// The default order is Last Edited, so we must account for that
	$current_orderby	= !empty( $_GET['orderby'] ) ? $_GET['orderby'] : apply_filters( 'bp_docs_default_sort_order', 'modified' );

	if ( $orderby == $current_orderby ) {
		// Default sort orders are different for different fields
		if ( empty( $_GET['order'] ) ) {
			// If no order is explicitly stated, we must provide one.
			// It'll be different for date fields (should be DESC)
			if ( 'modified' == $current_orderby || 'date' == $current_orderby )
				$current_order = 'DESC';
			else
				$current_order = 'ASC';
		} else {
			$current_order = $_GET['order'];
		}

		$new_order = 'ASC' == $current_order ? 'DESC' : 'ASC';
	}

	return apply_filters( 'bp_docs_get_sort_order', $new_order );
}

/**
 * Echoes the output of bp_docs_get_order_by_link()
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @param str $orderby The order_by item: title, author, created, edited, etc
 */
function bp_docs_order_by_link( $orderby = 'modified' ) {
	echo bp_docs_get_order_by_link( $orderby );
}
	/**
	 * Get the URL for the sortable column header links
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta
	 *
	 * @param str $orderby The order_by item: title, author, created, modified, etc
	 * @return str The URL with args attached
	 */
	function bp_docs_get_order_by_link( $orderby = 'modified' ) {
		$args = array(
			'orderby' 	=> $orderby,
			'order'		=> bp_docs_get_sort_order( $orderby )
		);

		return apply_filters( 'bp_docs_get_order_by_link', add_query_arg( $args ), $orderby, $args );
	}

/**
 * Echoes current-orderby and order classes for the column currently being ordered by
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @param str $orderby The order_by item: title, author, created, modified, etc
 */
function bp_docs_is_current_orderby_class( $orderby = 'modified' ) {
	// Get the current orderby column
	$current_orderby	= !empty( $_GET['orderby'] ) ? $_GET['orderby'] : apply_filters( 'bp_docs_default_sort_order', 'modified' );

	// Does the current orderby match the $orderby parameter?
	$is_current_orderby 	= $current_orderby == $orderby ? true : false;

	$class = '';

	// If this is indeed the current orderby, we need to get the asc/desc class as well
	if ( $is_current_orderby ) {
		$class = ' current-orderby';

		if ( empty( $_GET['order'] ) ) {
			// If no order is explicitly stated, we must provide one.
			// It'll be different for date fields (should be DESC)
			if ( 'modified' == $current_orderby || 'date' == $current_orderby )
				$class .= ' desc';
			else
				$class .= ' asc';
		} else {
			$class .= 'DESC' == $_GET['order'] ? ' desc' : ' asc';
		}
	}

	echo apply_filters( 'bp_docs_is_current_orderby', $class, $is_current_orderby, $current_orderby );
}

/**
 * Prints the inline toggle setup script
 *
 * Ideally, I would put this into an external document; but the fact that it is supposed to hide
 * content immediately on pageload means that I didn't want to wait for an external script to
 * load, much less for document.ready. Sorry.
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 */
function bp_docs_inline_toggle_js() {
	?>
	<script type="text/javascript">
		/* Swap toggle text with a dummy link and hide toggleable content on load */
		var togs = jQuery('.toggleable');

		jQuery(togs).each(function(){
			var ts = jQuery(this).children('.toggle-switch');

			/* Get a unique identifier for the toggle */
			var tsid = jQuery(ts).attr('id').split('-');
			var type = tsid[0];

			/* Append the static toggle text with a '+' sign and linkify */
			var toggleid = type + '-toggle-link';
			var plus = '<span class="plus-or-minus">+</span>';

			jQuery(ts).html('<a href="#" id="' + toggleid + '" class="toggle-link">' + plus + jQuery(ts).html() + '</a>');

			/* Hide the toggleable area */
			jQuery(this).children('.toggle-content').toggle();
		});

	</script>
	<?php
}

/**
 * A hook for intregration pieces to insert their settings markup
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 */
function bp_docs_doc_settings_markup() {
	global $bp;

	$doc = bp_docs_get_current_doc();

	$doc_settings = ! empty( $doc->ID ) ? get_post_meta( $doc->ID, 'bp_docs_settings', true ) : array();

	$settings_fields = array(
		'read' => array(
			'name'  => 'read',
			'label' => __( 'Who can read this doc?', 'bp-docs' )
		),
		'edit' => array(
			'name'  => 'edit',
			'label' => __( 'Who can edit this doc?', 'bp-docs' )
		),
		'read_comments' => array(
			'name'  => 'read_comments',
			'label' => __( 'Who can read comments on this doc?', 'bp-docs' )
		),
		'post_comments' => array(
			'name'  => 'post_comments',
			'label' => __( 'Who can post comments on this doc?', 'bp-docs' )
		),
		'view_history' => array(
			'name'  => 'view_history',
			'label' => __( 'Who can view the history of this doc?', 'bp-docs' )
		)
	);

	foreach ( $settings_fields as $settings_field ) {
		bp_docs_access_options_helper( $settings_field );
	}

	// Hand off the creation of additional settings to individual integration pieces
	do_action( 'bp_docs_doc_settings_markup', $doc_settings );
}

function bp_docs_access_options_helper( $settings_field ) {
	$doc_settings = get_post_meta( get_the_ID(), 'bp_docs_settings', true );
	$setting = isset( $doc_settings[ $settings_field['name'] ] ) ? $doc_settings[ $settings_field['name'] ] : '';
	?>
	<tr class="bp-docs-access-row bp-docs-access-row-<?php echo esc_attr( $settings_field['name'] ) ?>">
		<td class="desc-column">
			<label for="settings[<?php echo esc_attr( $settings_field['name'] ) ?>]"><?php echo esc_html( $settings_field['label'] ) ?></label>
		</td>

		<td class="content-column">
			<select name="settings[<?php echo esc_attr( $settings_field['name'] ) ?>]">
				<?php $access_options = bp_docs_get_access_options( $settings_field['name'] ) ?>
				<?php foreach ( $access_options as $key => $option ) : ?>
					<?php
					$selected = selected( $setting, $option['name'], false );
					if ( empty( $selected ) && ! empty( $option['default'] ) ) {
						$selected = selected( 1, 1, false );
					}
					?>
					<option value="<?php echo esc_attr( $option['name'] ) ?>" <?php echo $selected ?>><?php echo esc_attr( $option['label'] ) ?></option>
				<?php endforeach ?>
			</select>
		</td>
	</tr>

	<?php
}

/**
 * Outputs the links that appear under each Doc in the Doc listing
 *
 * @package BuddyPress Docs
 */
function bp_docs_doc_action_links() {
	$links   = array();

	$links[] = '<a href="' . get_permalink() . '">' . __( 'Read', 'bp-docs' ) . '</a>';

	if ( current_user_can( 'edit_bp_doc', get_the_ID() ) ) {
		$links[] = '<a href="' . get_permalink() . BP_DOCS_EDIT_SLUG . '">' . __( 'Edit', 'bp-docs' ) . '</a>';
	}

	if ( current_user_can( 'view_bp_doc_history', get_the_ID() ) ) {
		$links[] = '<a href="' . get_permalink() . BP_DOCS_HISTORY_SLUG . '">' . __( 'History', 'bp-docs' ) . '</a>';
	}

	echo implode( ' &#124; ', $links );
}

function bp_docs_current_group_is_public() {
	global $bp;

	if ( !empty( $bp->groups->current_group->status ) && 'public' == $bp->groups->current_group->status )
		return true;

	return false;
}

/**
 * Get the lock status of a doc
 *
 * The function first tries to get the lock status out of $bp. If it has to look it up, it
 * stores the data in $bp for future use.
 *
 * @package BuddyPress Docs
 * @since 1.0-beta-2
 *
 * @param int $doc_id Optional. Defaults to the doc currently being viewed
 * @return int Returns 0 if there is no lock, otherwise returns the user_id of the locker
 */
function bp_docs_is_doc_edit_locked( $doc_id = false ) {
	global $bp, $post;

	// Try to get the lock out of $bp first
	if ( isset( $bp->bp_docs->current_doc_lock ) ) {
		$is_edit_locked = $bp->bp_docs->current_doc_lock;
	} else {
		$is_edit_locked = 0;

		if ( empty( $doc_id ) )
			$doc_id = !empty( $post->ID ) ? $post->ID : false;

		if ( $doc_id ) {
			// Make sure that wp-admin/includes/post.php is loaded
			if ( !function_exists( 'wp_check_post_lock' ) )
				require_once( ABSPATH . 'wp-admin/includes/post.php' );

			// Because we're not using WP autosave at the moment, ensure that
			// the lock interval always returns as in process
			add_filter( 'wp_check_post_lock_window', create_function( false, 'return time();' ) );

			$is_edit_locked = wp_check_post_lock( $doc_id );
		}

		// Put into the $bp global to avoid extra lookups
		$bp->bp_docs->current_doc_lock = $is_edit_locked;
	}

	return apply_filters( 'bp_docs_is_doc_edit_locked', $is_edit_locked, $doc_id );
}

/**
 * Echoes the output of bp_docs_get_current_doc_locker_name()
 *
 * @package BuddyPress Docs
 * @since 1.0-beta-2
 */
function bp_docs_current_doc_locker_name() {
	echo bp_docs_get_current_doc_locker_name();
}
	/**
	 * Get the name of the user locking the current document, if any
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta-2
	 *
	 * @return string $locker_name The full name of the locking user
	 */
	function bp_docs_get_current_doc_locker_name() {
		$locker_name = '';

		$locker_id = bp_docs_is_doc_edit_locked();

		if ( $locker_id )
			$locker_name = bp_core_get_user_displayname( $locker_id );

		return apply_filters( 'bp_docs_get_current_doc_locker_name', $locker_name, $locker_id );
	}

/**
 * Echoes the output of bp_docs_get_force_cancel_edit_lock_link()
 *
 * @package BuddyPress Docs
 * @since 1.0-beta-2
 */
function bp_docs_force_cancel_edit_lock_link() {
	echo bp_docs_get_force_cancel_edit_lock_link();
}
	/**
	 * Get the URL for canceling the edit lock on the current doc
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta-2
	 *
	 * @return string $cancel_link href for the cancel edit lock link
	 */
	function bp_docs_get_force_cancel_edit_lock_link() {
		global $post;

		$doc_id = !empty( $post->ID ) ? $post->ID : false;

		if ( !$doc_id )
			return false;

		$doc_permalink = bp_docs_get_doc_link( $doc_id );

		$cancel_link = wp_nonce_url( add_query_arg( 'bpd_action', 'cancel_edit_lock', $doc_permalink ), 'bp_docs_cancel_edit_lock' );

		return apply_filters( 'bp_docs_get_force_cancel_edit_lock_link', $cancel_link, $doc_permalink );
	}

/**
 * Echoes the output of bp_docs_get_cancel_edit_link()
 *
 * @package BuddyPress Docs
 * @since 1.0-beta-2
 */
function bp_docs_cancel_edit_link() {
	echo bp_docs_get_cancel_edit_link();
}
	/**
	 * Get the URL for canceling out of Edit mode on a doc
	 *
	 * This used to be a straight link back to non-edit mode, but something fancier is needed
	 * in order to detect the Cancel and to remove the edit lock.
	 *
	 * @package BuddyPress Docs
	 * @since 1.0-beta-2
	 *
	 * @return string $cancel_link href for the cancel edit link
	 */
	function bp_docs_get_cancel_edit_link() {
		global $bp, $post;

		$doc_id = !empty( $bp->bp_docs->current_post->ID ) ? $bp->bp_docs->current_post->ID : false;

		if ( !$doc_id )
			return false;

		$doc_permalink = bp_docs_get_doc_link( $doc_id );

		$cancel_link = add_query_arg( 'bpd_action', 'cancel_edit', $doc_permalink );

		return apply_filters( 'bp_docs_get_cancel_edit_link', $cancel_link, $doc_permalink );
	}

/**
 * Echoes the output of bp_docs_get_delete_doc_link()
 *
 * @package BuddyPress Docs
 * @since 1.0.1
 */
function bp_docs_delete_doc_link() {
	echo bp_docs_get_delete_doc_link();
}
	/**
	 * Get the URL to delete the current doc
	 *
	 * @package BuddyPress Docs
	 * @since 1.0.1
	 *
	 * @return string $delete_link href for the delete doc link
	 */
	function bp_docs_get_delete_doc_link() {
		global $bp, $post;

		$doc_id = !empty( $bp->bp_docs->current_post->ID ) ? $bp->bp_docs->current_post->ID : false;

		if ( !$doc_id )
			return false;

		$doc_permalink = bp_docs_get_doc_link( $doc_id );

		$delete_link = wp_nonce_url( $doc_permalink . '/' . BP_DOCS_DELETE_SLUG, 'bp_docs_delete' );

		return apply_filters( 'bp_docs_get_delete_doc_link', $delete_link, $doc_permalink );
	}

/**
 * Echo the pagination links for the doc list view
 *
 * @package BuddyPress Docs
 * @since 1.0-beta-2
 */
function bp_docs_paginate_links() {
	global $bp;

	$cur_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        $page_links_total = $bp->bp_docs->doc_query->max_num_pages;

        $page_links = paginate_links( array(
		'base' 		=> add_query_arg( 'paged', '%#%' ),
		'format' 	=> '',
		'prev_text' 	=> __('&laquo;'),
		'next_text' 	=> __('&raquo;'),
		'total' 	=> $page_links_total,
		'current' 	=> $cur_page
        ));

        echo apply_filters( 'bp_docs_paginate_links', $page_links );
}

/**
 * Get the start number for the current docs view (ie "Viewing *5* - 8 of 12")
 *
 * Here's the math: Subtract one from the current page number; multiply times posts_per_page to get
 * the last post on the previous page; add one to get the start for this page.
 *
 * @package BuddyPress Docs
 * @since 1.0-beta-2
 *
 * @return int $start The start number
 */
function bp_docs_get_current_docs_start() {
	global $bp;

	$paged = !empty( $bp->bp_docs->doc_query->query_vars['paged'] ) ? $bp->bp_docs->doc_query->query_vars['paged'] : 1;

	$posts_per_page = !empty( $bp->bp_docs->doc_query->query_vars['posts_per_page'] ) ? $bp->bp_docs->doc_query->query_vars['posts_per_page'] : 10;

	$start = ( ( $paged - 1 ) * $posts_per_page ) + 1;

	return apply_filters( 'bp_docs_get_current_docs_start', $start );
}

/**
 * Get the end number for the current docs view (ie "Viewing 5 - *8* of 12")
 *
 * Here's the math: Multiply the posts_per_page by the current page number. If it's the last page
 * (ie if the result is greater than the total number of docs), just use the total doc count
 *
 * @package BuddyPress Docs
 * @since 1.0-beta-2
 *
 * @return int $end The start number
 */
function bp_docs_get_current_docs_end() {
	global $bp;

	$paged = !empty( $bp->bp_docs->doc_query->query_vars['paged'] ) ? $bp->bp_docs->doc_query->query_vars['paged'] : 1;

	$posts_per_page = !empty( $bp->bp_docs->doc_query->query_vars['posts_per_page'] ) ? $bp->bp_docs->doc_query->query_vars['posts_per_page'] : 10;

	$end = $paged * $posts_per_page;

	if ( $end > bp_docs_get_total_docs_num() )
		$end = bp_docs_get_total_docs_num();

	return apply_filters( 'bp_docs_get_current_docs_end', $end );
}

/**
 * Get the total number of found docs out of $wp_query
 *
 * @package BuddyPress Docs
 * @since 1.0-beta-2
 *
 * @return int $total_doc_count The start number
 */
function bp_docs_get_total_docs_num() {
	global $bp;

	$total_doc_count = !empty( $bp->bp_docs->doc_query->found_posts ) ? $bp->bp_docs->doc_query->found_posts : 0;

	return apply_filters( 'bp_docs_get_total_docs_num', $total_doc_count );
}

/**
 * Display a Doc's comments
 *
 * This function was introduced to make sure that the comment display callback function can be
 * filtered by site admins. Originally, wp_list_comments() was called directly from the template
 * with the callback bp_dtheme_blog_comments, but this caused problems for sites not running a
 * child theme of bp-default.
 *
 * Filter bp_docs_list_comments_args to provide your own comment-formatting function.
 *
 * @package BuddyPress Docs
 * @since 1.0.5
 */
function bp_docs_list_comments() {
	$args = array();

	if ( function_exists( 'bp_dtheme_blog_comments' ) )
		$args['callback'] = 'bp_dtheme_blog_comments';

	$args = apply_filters( 'bp_docs_list_comments_args', $args );

	wp_list_comments( $args );
}

/**
 * Are we looking at an existing doc?
 *
 * @package BuddyPress Docs
 * @since 1.0-beta
 *
 * @return bool True if it's an existing doc
 */
function bp_docs_is_existing_doc() {
	$post_type_obj = get_queried_object();
	return is_single() && isset( $post_type_obj->post_type ) && bp_docs_get_post_type_name() == $post_type_obj->post_type;
}

/**
 * What's the current view?
 *
 * @package BuddyPress Docs
 * @since 1.1
 *
 * @return str $current_view The current view
 */
function bp_docs_current_view() {
	global $bp;

	$view = !empty( $bp->bp_docs->current_view ) ? $bp->bp_docs->current_view : false;

	return apply_filters( 'bp_docs_current_view', $view );
}

/**
 * Todo: Make less hackish
 */
function bp_docs_doc_permalink() {
	if ( bp_is_group() ) {
		bp_docs_group_doc_permalink();
	} else {
		the_permalink();
	}
}

function bp_docs_slug() {
	echo bp_docs_get_slug();
}
	function bp_docs_get_slug() {
		global $bp;
		return apply_filters( 'bp_docs_get_slug', $bp->bp_docs->slug );
	}

/**
 * Outputs the tabs at the top of the Docs view (All Docs, New Doc, etc)
 *
 * At the moment, the group-specific stuff is hard coded in here.
 * @todo Get the group stuff out
 */
function bp_docs_tabs() {
	global $bp, $post, $bp_version;

	$current_view = '';

	?>

	<ul id="bp-docs-all-docs">
		<li<?php if ( bp_docs_is_global_directory() ) : ?> class="current"<?php endif; ?>><a href="<?php bp_docs_archive_link() ?>"><?php _e( 'All Docs', 'bp-docs' ) ?></a></li>

		<?php if ( is_user_logged_in() ) : ?>
			<li><a href="<?php bp_docs_mydocs_started_link() ?>"><?php _e( 'Started By Me', 'bp-docs' ) ?></a></li>
			<li><a href="<?php bp_docs_mydocs_edited_link() ?>"><?php _e( 'Edited By Me', 'bp-docs' ) ?></a></li>

			<?php if ( bp_is_active( 'groups' ) ) : ?>
				<li<?php if ( bp_docs_is_mygroups_docs() ) : ?> class="current"<?php endif; ?>><a href="<?php bp_docs_mygroups_link() ?>"><?php _e( 'My Groups', 'bp-docs' ) ?></a></li>
			<?php endif ?>
		<?php endif ?>
	</ul>

	<?php /*
	<?php if ( $is_group ) : ?>
		<li<?php if ( 'group_list' == $current_view ) : ?> class="current"<?php endif; ?>><a href="<?php echo esc_url( $group_docs_link_url ) ?>"><?php echo esc_html( $group_docs_link_text ) ?></a></li>
	<?php endif ?>

	<?php if ( bp_docs_current_user_can( 'create' ) ) : ?>
		<li<?php if ( 'create' == $current_view ) : ?> class="current"<?php endif; ?>><a href="<?php echo get_post_type_archive_link( bp_docs_get_post_type_name() ) ?>/create"><?php _e( 'New Doc', 'bp-docs' ) ?></a></li>
	<?php endif ?>

	<?php if ( bp_docs_is_existing_doc() ) : ?>
		<li class="current"><a href="<?php the_permalink() ?>"><?php the_title() ?></a></li>
	<?php endif;

	*/

}

/**
 * Markup for the Doc Permissions snapshot
 *
 * Markup is built inline. Someday I may abstract it. In the meantime, suck a lemon
 *
 * @since 1.2
 */
function bp_docs_doc_permissions_snapshot() {
	$html = '';

	$doc_group_ids = bp_docs_get_associated_group_id( get_the_ID(), 'full', true );
	$doc_groups = array();
	foreach( $doc_group_ids as $dgid ) {
		$maybe_group = groups_get_group( 'group_id=' . $dgid );
		if ( !empty( $maybe_group->name ) ) {
			$doc_groups[] = $maybe_group;
		}
	}

	// we'll need a list of comma-separated group names
	$group_names = implode( ',', wp_list_pluck( $doc_groups, 'name' ) );

	$levels = array(
		'anyone'        => __( 'Anyone', 'bp-docs' ),
		'loggedin'      => __( 'Logged-in Users', 'bp-docs' ),
		'friends'       => __( 'My Friends', 'bp-docs' ),
		'group-members' => __( 'Members of: %s', 'bp-docs' ), // @todo
		'admins-mods'   => __( 'Admins and mods of the group', 'bp-docs' ),
		'creator'       => __( 'The Doc author only', 'bp-docs' ),
		'no-one'        => __( 'Just Me', 'bp-docs' )
	);

	if ( get_the_author_meta( 'ID' ) == bp_loggedin_user_id() ) {
		$levels['creator'] = __( 'The Doc author only (that\'s you!)', 'bp-docs' );
	}

	$settings = bp_docs_get_doc_settings();

	// Read
	$read_class = bp_docs_get_permissions_css_class( $settings['read'] );
	$read_text  = sprintf( __( 'This Doc can be read by: <strong>%s</strong>', 'bp-docs' ), $levels[ $settings['read'] ] );

	// Edit
	$edit_class = bp_docs_get_permissions_css_class( $settings['edit'] );
	$edit_text  = sprintf( __( 'This Doc can be edited by: <strong>%s</strong>', 'bp-docs' ), $levels[ $settings['edit'] ] );

	// Read Comments
	$read_comments_class = bp_docs_get_permissions_css_class( $settings['read_comments'] );
	$read_comments_text  = sprintf( __( 'Comments are visible to: <strong>%s</strong>', 'bp-docs' ), $levels[ $settings['read_comments'] ] );

	// Post Comments
	$post_comments_class = bp_docs_get_permissions_css_class( $settings['post_comments'] );
	$post_comments_text  = sprintf( __( 'Comments can be posted by: <strong>%s</strong>', 'bp-docs' ), $levels[ $settings['post_comments'] ] );

	// View History
	$view_history_class = bp_docs_get_permissions_css_class( $settings['view_history'] );
	$view_history_text  = sprintf( __( 'History can be viewed by: <strong>%s</strong>', 'bp-docs' ), $levels[ $settings['view_history'] ] );

	// Calculate summary
	// Summary works like this:
	//  'public'  - all read_ items set to 'anyone', all others to 'anyone' or 'loggedin'
	//  'private' - everything set to 'admins-mods', 'creator', 'no-one', 'friends', or 'group-members' where the associated group is non-public
	//  'limited' - everything else
	$anyone_count  = 0;
	$private_count = 0;
	$public_settings = array(
		'read'          => 'anyone',
		'edit'          => 'loggedin',
		'read_comments' => 'anyone',
		'post_comments' => 'loggedin',
		'view_history'  => 'anyone'
	);

	foreach ( $settings as $l => $v ) {
		if ( 'anyone' == $v || $public_settings[ $l ] == $v ) {

			$anyone_count++;

		} else if ( in_array( $v, array( 'admins-mods', 'creator', 'no-one', 'friends', 'group-members' ) ) ) {

			if ( 'group-members' == $v ) {
				if ( ! isset( $group_status ) ) {
					$group_status = 'foo'; // todo
				}

				if ( 'public' != $group_status ) {
					$private_count++;
				}
			} else {
				$private_count++;
			}

		}
	}

	$settings_count = count( $settings );
	if ( $settings_count == $private_count ) {
		$summary       = 'private';
		$summary_label = __( 'Private', 'bp-docs' );
	} else if ( $settings_count == $anyone_count ) {
		$summary       = 'public';
		$summary_label = __( 'Public', 'bp-docs' );
	} else {
		$summary       = 'limited';
		$summary_label = __( 'Limited', 'bp-docs' );
	}

	$html .= '<div id="doc-permissions-summary" class="doc-' . $summary . '">';
	$html .=   sprintf( __( 'Access: <strong>%s</strong>', 'bp-docs' ), $summary_label );
	$html .=   '<a href="#" class="doc-permissions-toggle" id="doc-permissions-more">' . __( 'Details', 'bp-docs' ) . '</a>';
	$html .= '</div>';

	$html .= '<div id="doc-permissions-details">';
	$html .=   '<ul>';
	$html .=     '<li class="bp-docs-can-read ' . $read_class . '"><span class="bp-docs-level-icon"></span>' . $read_text . '</li>';
	$html .=     '<li class="bp-docs-can-edit ' . $edit_class . '"><span class="bp-docs-level-icon"></span>' . $edit_text . '</li>';
	$html .=     '<li class="bp-docs-can-read_comments ' . $read_comments_class . '"><span class="bp-docs-level-icon"></span>' . $read_comments_text . '</li>';
	$html .=     '<li class="bp-docs-can-post_comments ' . $post_comments_class . '"><span class="bp-docs-level-icon"></span>' . $post_comments_text . '</li>';
	$html .=     '<li class="bp-docs-can-view_history ' . $view_history_class . '"><span class="bp-docs-level-icon"></span>' . $view_history_text . '</li>';
	$html .=   '</ul>';

	if ( bp_docs_current_user_can( 'manage' ) )
		$html .=   '<a href="' . bp_docs_get_doc_edit_link() . '#permissions" id="doc-permissions-edit">' . __( 'Edit', 'bp-docs' ) . '</a>';

	$html .=   '<a href="#" class="doc-permissions-toggle" id="doc-permissions-less">' . __( 'Summary', 'bp-docs' ) . '</a>';
	$html .= '</div>';

	echo $html;
}

function bp_docs_get_permissions_css_class( $level ) {
	return apply_filters( 'bp_docs_get_permissions_css_class', 'bp-docs-level-' . $level );
}

/**
 * Blasts any previous queries stashed in the BP global
 *
 * @since 1.2
 */
function bp_docs_reset_query() {
	global $bp;

	if ( isset( $bp->bp_docs->doc_query ) ) {
		unset( $bp->bp_docs->doc_query );
	}
}

/**
 * Get a total doc count, for a user, a group, or the whole site
 *
 * @since 1.2
 * @todo Total sitewide doc count
 *
 * @param int $item_id The id of the item (user or group)
 * @param str $item_type 'user' or 'group'
 * @return int
 */
function bp_docs_get_doc_count( $item_id = 0, $item_type = '' ) {
	$doc_count = 0;

	switch ( $item_type ) {
		case 'user' :
			$doc_count = get_user_meta( $item_id, 'bp_docs_count', true );

			if ( '' === $doc_count ) {
				$doc_count = bp_docs_update_doc_count( $item_id, 'user' );
			}

			break;
		case 'group' :
			$doc_count = groups_get_groupmeta( $item_id, 'bp-docs-count' );

			if ( '' === $doc_count ) {
				$doc_count = bp_docs_update_doc_count( $item_id, 'group' );
			}
			break;
	}

	return apply_filters( 'bp_docs_get_doc_count', (int)$doc_count, $item_id, $item_type );
}

/**
 * Is the current page a single Doc?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_single_doc() {
	global $wp_query;

	$is_single_doc = false;

	// There's an odd bug in WP_Query that causes errors when attempting to access
	// get_queried_object() too early. The check for $wp_query->post is a workaround
	if ( is_single() && ! empty( $wp_query->post ) ) {
		$post = get_queried_object();

		if ( isset( $post->post_type ) && bp_docs_get_post_type_name() == $post->post_type ) {
			$is_single_doc = true;
		}
	}

	return apply_filters( 'bp_docs_is_single_doc', $is_single_doc );
}

/**
 * Is the current page a single Doc 'read' view?
 *
 * By process of elimination.
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_doc_read() {
	$is_doc_read = false;

	if ( bp_docs_is_single_doc() &&
	     !bp_docs_is_doc_edit() &&
	     ( !function_exists( 'bp_docs_is_doc_history' ) || !bp_docs_is_doc_history() )
	   ) {
	 	$is_doc_read = true;
	}

	return apply_filters( 'bp_docs_is_doc_read', $is_doc_read );
}


/**
 * Is the current page a doc edit?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_doc_edit() {
	$is_doc_edit = false;

	if ( bp_docs_is_single_doc() && 1 == get_query_var( BP_DOCS_EDIT_SLUG ) ) {
		$is_doc_edit = true;
	}

	return apply_filters( 'bp_docs_is_doc_edit', $is_doc_edit );
}

/**
 * Is this the Docs create screen?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_doc_create() {
	$is_doc_create = false;

	if ( is_post_type_archive( bp_docs_get_post_type_name() ) && 1 == get_query_var( BP_DOCS_CREATE_SLUG ) ) {
		$is_doc_create = true;
	}

	return apply_filters( 'bp_docs_is_doc_create', $is_doc_create );
}

/**
 * Is this the My Groups tab of the Docs archive?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_mygroups_docs() {
	$is_mygroups_docs = false;

	if ( is_post_type_archive( bp_docs_get_post_type_name() ) && 1 == get_query_var( BP_DOCS_MY_GROUPS_SLUG ) ) {
		$is_mygroups_docs = true;
	}

	return apply_filters( 'bp_docs_is_mygroups_docs', $is_mygroups_docs );
}

/**
 * Is this the History tab?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_doc_history() {
	$is_doc_history = false;

	if ( bp_docs_is_single_doc() && 1 == get_query_var( BP_DOCS_HISTORY_SLUG ) ) {
		$is_doc_history = true;
	}

	return apply_filters( 'bp_docs_is_doc_history', $is_doc_history );
}

/**
 * Is this the Docs tab of a user profile?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_user_docs() {
	$is_user_docs = false;

	if ( bp_is_user() && bp_docs_is_docs_component() ) {
		$is_user_docs = true;
	}

	return apply_filters( 'bp_docs_is_user_docs', $is_user_docs );
}

/**
 * Is this the Started By tab of a user profile?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_started_by() {
	$is_started_by = false;

	if ( bp_docs_is_user_docs() && bp_is_current_action( BP_DOCS_STARTED_SLUG ) ) {
		$is_started_by = true;
	}

	return apply_filters( 'bp_docs_is_started_by', $is_started_by );
}

/**
 * Is this the Edited By tab of a user profile?
 *
 * @since 1.2
 * @return bool
 */
function bp_docs_is_edited_by() {
	$is_edited_by = false;

	if ( bp_docs_is_user_docs() && bp_is_current_action( BP_DOCS_EDITED_SLUG ) ) {
		$is_edited_by = true;
	}

	return apply_filters( 'bp_docs_is_edited_by', $is_edited_by );
}

/**
 * Is this the global Docs directory?
 */
function bp_docs_is_global_directory() {
	$is_global_directory = false;

	if ( is_post_type_archive( bp_docs_get_post_type_name() ) && ! get_query_var( BP_DOCS_MY_GROUPS_SLUG ) && ! get_query_var( BP_DOCS_CREATE_SLUG ) ) {
		$is_global_directory = true;
	}

	return apply_filters( 'bp_docs_is_global_directory', $is_global_directory );
}

?>

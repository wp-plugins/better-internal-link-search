<?php
/**
 * Filters that hook into the main Better Internal Link Search routine and
 * override its output by searching an external website or service.
 *
 * These modifiers are mainly meant to serve as examples. You can use them to
 * create your own snippets in your theme's functions.php file, or better yet,
 * your own plugin. If you'd like to disable these default modifiers, drop the
 * following filter into functions.php:
 *
 * <code>add_filter( 'better_internal_link_search_load_default_modifiers', '__return_false' );</code>
 *
 * Search modifiers start with a dash and end with a space, but aside from
 * those simple requirements, they can be any format, with any number of
 * arguments. The default argument separator is a colon.
 * 
 * Example: <code>-modifier:arg1:arg2:arg3 query</code>
 *
 * Using a modifier filter like the examples below, the example query would be
 * passed to the hook as an array like this:
 *
 * <code>array( 's' => $query, 'modifier' => array( $modifer, $arg1, $arg2 ) );</code>
 *
 * Snippets included for searching the WordPress Codex, WordPress plugin
 * respository, GitHub Repositories, iTunes, Spotify, WikiPedia, listing a
 * user's Gists, or returning various user links (archive URL, website, etc).
 */


/**
 * Search modifier help
 *
 * Hook into this filter to provide help documentation for any custom search
 * modifiers.
 *
 * @since 1.1
 */
add_filter( 'better_internal_link_search_modifier_help', 'bils_default_modifier_help', 10 );
function bils_default_modifier_help( $results ) {
	$modifiers = array(
		'codex' => array(
			'title' => '<strong>-codex {query}</strong></span><span class="item-description">Search the WordPress Codex</span>',
			'permalink' => 'http://codex.wordpress.org/',
			'info' => 'WordPress Codex'
		),
		'gists' => array(
			'title' => '<strong>-gists {username}</strong></span><span class="item-description">Lists gists for the specified user.</span>',
			'permalink' => 'https://gist.github.com/',
			'info' => 'GitHub'
		),
		'github' => array(
			'title' => '<strong>-github {query}</strong></span><span class="item-description">Searches GitHub for a respository matching the query.</span>',
			'permalink' => 'https://github.com/',
			'info' => 'GitHub'
		),
		'itunes' => array(
			'title' => '<strong>-itunes:{entity} {query}</strong></span><span class="item-description">Search iTunes for a particular entity. Entity can be \'album\', \'artist\', \'podcast\' or \'track\'; It is optional and defaults to \'album\'.</span>',
			'permalink' => 'http://www.apple.com/itunes/',
			'info' => 'iTunes'
		),
		'plugins' => array(
			'title' => '<strong>-plugins {query}</strong></span><span class="item-description">Search the WordPress plugin directory.</span>',
			'permalink' => 'http://wordpress.org/extend/plugins/',
			'info' => 'WordPress Plugins'
		),
		'spotify' => array(
			'title' => '<strong>-spotify:{entity} {query}</strong></span><span class="item-description">Search Spotify for a particular entity. Entity can be \'album\', \'artist\', or \'track\'; is optional and defaults to \'album\'.</span>',
			'permalink' => 'http://www.spotify.com/',
			'info' => 'Spotify'
		),
		'user' => array(
			'title' => '<strong>-user:{url_type}:all {user_login}</strong></span><span class="item-description">Search your local WordPress installation for the specified user and return the URL specified by \'<code>url_type</code>\'. The default URL returned is the user\'s archive listing their published posts. If set to \'<code>url</code>\', the returned link will be their website as entered in their profile. Setting the third argument to \'<code>all</code>\' will return all users and not just authors. The \'<code>user_login</code>\' parameter must be an exact match; if left blank, all users are returned.</span>',
			'permalink' => home_url( '/' ),
			'info' => 'Local'
		),
		'wikipedia' => array(
			'title' => '<strong>-wikipedia {query}</strong></span>',
			'permalink' => 'http://www.wikipedia.org/',
			'info' => 'Wikipedia'
		),
	);
	
	return array_merge( (array) $results, $modifiers );	
}


/**
 * WordPress Codex Search
 *
 * <code>-codex {query}</code>
 */
add_filter( 'better_internal_link_search_modifier-codex', 'bils_wpcodex_search', 10, 2 );
function bils_wpcodex_search( $results, $args ) {
	// don't want to hit the api for queries less than three characters
	if ( strlen( $args['s'] ) < 3 ) {
		wp_die( 0 );
	}
	
	$offset = ( $args['page'] - 1 ) * $args['per_page'];
	
	$search_args = array(
		'action' => 'query',
		'list' => 'search',
		'srsearch' => urlencode( $args['s'] ),
		'sroffset' => $offset,
		'srlimit' => $args['per_page'],
		'format' => 'json'
	);
	
	$request_uri = add_query_arg( $search_args, 'http://codex.wordpress.org/api.php' );
	
	$response = wp_remote_get( $request_uri );
	if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
		$json = json_decode( wp_remote_retrieve_body( $response ) );
		
		foreach( $json->query->search as $item ) {
			$results[] = array(
				'title' => trim( esc_html( strip_tags( $item->title ) ) ),
				'permalink' => 'http://codex.wordpress.org/' . $item->title,
				'info' => ''
			);
		}
	}
	
	return $results;
}


/**
 * GitHub Gists List
 *
 * <code>-gists (lists Gists from default user specified below)</code>
 * <code>-gists {username}</code>
 */
add_filter( 'better_internal_link_search_modifier-gists', 'bils_gists_search', 10, 2 );
function bils_gists_search( $results, $args ) {
	$username = ( ! empty( $args['s'] ) ) ? $args['s'] : 'bradyvercher';
	
	$search_args = array(
		'page' => $args['page'],
		'per_page' => $args['per_page']
	);
	
	$request_uri = add_query_arg( $search_args, sprintf( 'https://api.github.com/users/%s/gists', $username ) );
	
	$response = wp_remote_get( $request_uri, array( 'sslverify' => false ) );
	if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
		$data = json_decode( wp_remote_retrieve_body( $response ) );
		
		foreach( $data as $item ) {
			$results[] = array(
				'title' => trim( esc_html( strip_tags( $item->description ) ) ),
				'permalink' => esc_url( $item->html_url ),
				'info' => esc_html( $item->id )
			);
		}
	}
	
	return $results;
}


/**
 * GitHub Repo Search
 *
 * <code>-github {query}</code>
 */
add_filter( 'better_internal_link_search_modifier-github', 'bils_github_search', 10, 2 );
function bils_github_search( $results, $args ) {
	// don't want to hit the api for queries less than three characters
	if ( strlen( $args['s'] ) < 3 ) {
		wp_die( 0 );
	}
	
	$search_args = array(
		'start_page' => ceil( ( $args['page'] * $args['per_page'] ) / 100 ) // returns 100 results per page
	);
	
	$request_uri = add_query_arg( $search_args, sprintf( 'https://api.github.com/legacy/repos/search/%s', $args['s'] ) );
	
	$response = wp_remote_get( $request_uri, array( 'sslverify' => false ) );
	if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
		$json = json_decode( wp_remote_retrieve_body( $response ) );
		
		foreach( $json->repositories as $item ) {
			$results[] = array(
				'title' => sprintf( '<strong>%s</strong></span><span class="item-description">%s</span>',
					trim( esc_html( strip_tags( $item->name ) ) ),
					wp_trim_words( $item->description, 20, '...' )
				),
				'permalink' => esc_url( $item->url ),
				'info' => esc_html( $item->owner )
			);
		}
		
		if ( ! empty( $results ) ) {
			$start = ( $args['page'] - 1 ) * $args['per_page'] % 100;
			$results = array_slice( $results, $start, $args['per_page'] );
		}
	}
		
	return $results;
}


/**
 * iTunes Search
 *
 * Limited iTunes searching. The API has a bunch of capabilities, so this
 * could be customized to add or extend functionality.
 *
 * <code>-itunes {query}</code> (defauls to searching for albums)
 * <code>-itunes:{entity} {query}</code> (entities: album, artist, podcast, track)
 */
add_filter( 'better_internal_link_search_modifier-itunes', 'bils_itunes_search', 10, 2 );
function bils_itunes_search( $results, $args ) {
	// don't want to hit the api for queries less than three characters
	if ( strlen( $args['s'] ) < 3 ) {
		wp_die( 0 );
	}
	
	// iTunes doesn't support paging, so we'll request the smallest set of results necessary
	// then extract the results we want after processing
	$itunes_per_page = $args['page'] * $args['per_page'];
	
	// map search argument to actual API entities
	$api_entities = array(
		'album' => 'album',
		'artist' => 'musicArtist',
		'podcast' => 'podcast',
		'track' => 'song'
	);
	$entity = ( isset( $args['modifier'][1] ) && array_key_exists( $args['modifier'][1], $api_entities ) ) ? $api_entities[ $args['modifier'][1] ] : 'album';
	
	$search_args = array(
		'entity' => $entity,
		'limit' => $itunes_per_page,
		'media' => 'all',
		'term' => urlencode( $args['s'] )
	);
	
	$request_uri = add_query_arg( $search_args, 'http://itunes.apple.com/search' );
	
	$response = wp_remote_get( $request_uri );
	if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
		$json = json_decode( wp_remote_retrieve_body( $response ) );
		
		$start = ( $args['page'] - 1 ) * $args['per_page'] % $itunes_per_page;
		if ( isset( $json->resultCount ) && $start > $json->resultCount )
			wp_die( 0 ); // abort!
		
		
		$results = array();
		foreach( $json->results as $item ) {
			$result = array();
			
			if ( 'album' == $entity ) {
				$result = array(
					'title' => $item->collectionName,
					'permalink' => $item->collectionViewUrl,
					'info' => $item->artistName
				);
			}
			
			if ( 'musicArtist' == $entity ) {
				$result = array(
					'title' => $item->artistName,
					'permalink' => $item->artistLinkUrl,
					'info' => ''
				);
			}
			
			if ( 'podcast' == $entity ) {
				$result = array(
					'title' => $item->collectionName,
					'permalink' => $item->collectionViewUrl,
					'info' => $item->artistName
				);
			}
			
			if ( 'song' == $entity ) {
				$result = array(
					'title' => $item->trackName,
					'permalink' => $item->trackViewUrl,
					'info' => $item->artistName
				);
			}
			
			if ( ! empty( $result ) ) {
				$result['title'] = trim( esc_html( strip_tags( $result['title'] ) ) );
				$result['permalink'] = esc_url( $result['permalink'] );
				$result['info'] = trim( esc_html( strip_tags( $result['info'] ) ) );
				
				// not very exact, but oh well
				if ( strlen( $result['title'] ) > 30 ) {
					$result['info'] = substr( $result['info'], 0, 15 ) . '...';
				}
				
				$results[] = $result;
			}
		}
		
		if ( ! empty( $results ) ) {
			$results = array_slice( $results, $start, $args['per_page'] );
		}
	}
	
	return $results;
}


/**
 * WordPress Plugin Search
 *
 * Search the WordPress plugin repository.
 *
 * <code>-plugins {query}</code>
 */
add_filter( 'better_internal_link_search_modifier-plugins', 'bils_wpplugins_search', 10, 2 );
function bils_wpplugins_search( $results, $args ) {
	// don't want to hit the api for queries less than three characters
	if ( strlen( $args['s'] ) < 3 ) {
		wp_die( 0 );
	}
	
	$search_args = array(
		'search' => $args['s'],
		'page' => $args['page'],
		'per_page' => $args['per_page']
	);
	
	include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	$api = plugins_api( 'query_plugins', $search_args );
	
	if ( isset( $api->plugins ) ) {
		foreach( $api->plugins as $item ) {
			$results[] = array(
				'title' => sprintf( '<strong>%s</strong></span><span class="item-description">%s</span>',
					trim( esc_html( strip_tags( $item->name ) ) ),
					trim( esc_html( strip_tags( $item->short_description ) ) )
				),
				'permalink' => $item->homepage,
				'info' => trim( esc_html( strip_tags( $item->author ) ) )
			);
		}
	}
	
	return $results;
}


/**
 * Spotify Search
 *
 * <code>-spotify {query}</code>
 * <code>-spotify:{entity} {query}</code> (entities: album, artist, track)
 */
add_filter( 'better_internal_link_search_modifier-spotify', 'bils_spotify_search', 10, 2 );
function bils_spotify_search( $results, $args ) {
	// Spotify doesn't support paged requests, so if page arg is greater than one, abort
	// we also don't want to hit the api for queries less than three characters
	if ( intval( $args['page'] ) > 1 || strlen( $args['s'] ) < 3 ) {
		wp_die( 0 );
	}
	
	$api_entities = array( 'album', 'artist', 'track' );
	$entity = ( isset( $args['modifier'][1] ) && in_array( $args['modifier'][1], $api_entities ) ) ? $args['modifier'][1] : 'album';
	
	$search_args = array( 'q' => urlencode( $args['s'] ) );
	$request_uri = add_query_arg( $search_args, 'http://ws.spotify.com/search/1/' . $entity . '.json' );
	
	$response = wp_remote_get( $request_uri );
	if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
		$json = json_decode( wp_remote_retrieve_body( $response ) );
		
		$entity_plural = $entity . 's';
		$objects = $json->{$entity_plural};
		
		foreach( $objects as $item ) {
			$title = $item->name;
			$info = ( 'artist' != $entity ) ? $item->artists[0]->name : '';
			
			if ( strlen( $title > 30 ) ) {
				$info = substr( $info, 0, 15 ) . '...';
			}
			
			$results[] = array(
				'title' => trim( esc_html( strip_tags( $title ) ) ),
				'permalink' => sprintf( 'http://open.spotify.com/%s/%s',
					$entity,
					str_replace( 'spotify:' . $entity . ':', '', $item->href )
				),
				'info' => trim( esc_html( strip_tags( $info ) ) )
			);
		}
	}
	
	return $results;
}


/**
 * Search for a user
 *
 * Returns the user's archive link.
 *
 * The default url returned is a user's archive link. Setting url_type to
 * 'url' will return the user's website as entered on their profile and
 * setting it to 'twitter' will return a link to their Twitter profile if a
 * custom meta field as been added.
 *
 * <code>-user</code>
 * <code>-user {user_login}</code> (user_login needs to be exact)
 * <code>-user:{url_type}</code>
 * <code>-user:{url_type} {user_login}</code>
 * <code>-user:{url_type}:all</code> (returns all users, not just authors)
 * <code>-user:{url_type}:all {user_login}</code>
 */
add_filter( 'better_internal_link_search_modifier-user', 'bils_user_search', 10, 2 );
function bils_user_search( $results, $args ) {
	$arg1 = ( isset( $args['modifier'][1] ) && ! empty( $args['modifier'][1] ) ) ? $args['modifier'][1] : null;
	$arg2 = ( isset( $args['modifier'][2] ) && ! empty( $args['modifier'][2] ) ) ? $args['modifier'][2] : null;
	
	$search_args = array(
		'search' => $args['s'],
		'fields' => 'id',
		'offset' => ( $args['page'] - 1 ) * $args['per_page'],
		'number' => $args['per_page'],
		'who' => 'authors'
	);
	
	if ( 'all' == $arg2 ) {
		unset( $search_args['who'] );
	}
	
	$users = get_users( $search_args );
	if ( $users ) {
		foreach( $users as $user_id ) {
			$user = new WP_User( $user_id );
			
			$result = array(
				'title' => trim( esc_html( strip_tags( $user->display_name ) ) ),
				'permalink' => esc_url( get_author_posts_url( $user->ID ) ),
				'info' => sprintf( '%s (%s)',
					$user->user_login,
					join( ', ', $user->roles )
				)
			);
			
			
			if ( 'url' == $arg1 ) {
				$result['permalink'] = esc_url( $user->user_url );
			} elseif ( 'twitter' == $arg1 ) {
				$result['permalink'] = 'http://twitter.com/' . trim( esc_html( strip_tags( get_user_meta( $user->ID, 'twitter', true ) ) ) );
			}
			
			$results[] = $result;
		}
	}
	
	return $results;
}


/**
 * Wikipedia Search
 *
 * <code>-wikipedia {query}</code>
 */
add_filter( 'better_internal_link_search_modifier-wikipedia', 'bils_wikipedia_search', 10, 2 );
function bils_wikipedia_search( $results, $args ) {
	// don't want to hit the api for queries less than three characters
	if ( strlen( $args['s'] ) < 3 ) {
		wp_die( 0 );
	}
	
	$offset = ( $args['page'] - 1 ) * $args['per_page'];
	
	$search_args = array(
		'action' => 'query',
		'list' => 'search',
		'srsearch' => urlencode( $args['s'] ),
		'sroffset' => $offset,
		'srlimit' => $args['per_page'],
		'format' => 'json'
	);
	
	$request_uri = add_query_arg( $search_args, 'http://en.wikipedia.org/w/api.php' );
	
	$response = wp_remote_get( $request_uri );
	if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
		$json = json_decode( wp_remote_retrieve_body( $response ) );
		
		$total_items = $json->query->searchinfo->totalhits;
		if ( $total_items && $offset < $total_items ) {
			foreach( $json->query->search as $item ) {
				$results[] = array(
					'title' => trim( esc_html( strip_tags( $item->title ) ) ),
					'permalink' => 'http://www.wikipedia.org/wiki/' . $item->title,
					'info' => ''
				);
			}
		}
	}
	
	return $results;
}
?>
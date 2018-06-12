<?php

namespace WPS\Templates;

/**
 * Filter an array of objects.
 *
 * You can pass in one or more properties on which to filter.
 *
 * If the key of an array is an array, then it will filtered down to that
 * level of node.
 *
 * Example usages:
 * <code>
 * ofilter($items, 'size'); // filter anything that has value in the 'size' property
 * ofilter($items, ['size' => 3, 'name']); // filter anything that has the size property === 3 and a 'name' property
 * with value ofilter($items, ['size', ['user', 'forename' => 'Bob'], ['user', 'age' => 30]) // filter w/ size, have
 * the forename value of 'Bob' on the user object of and age of 30 ofilter($items, ['size' => function($prop) { return
 * ($prop > 18 && $prop < 50); }]);
 * </code>
 *
 * @param  array        $array
 * @param  string|array $properties
 *
 * @return array
 */
function ofilter( $array, $properties ) {
	if ( empty( $array ) ) {
		return;
	}
	if ( is_string( $properties ) ) {
		$properties = [ $properties ];
	}
	$isValid = function ( $obj, $propKey, $propVal ) {
		if ( is_int( $propKey ) ) {
			if ( ! property_exists( $obj, $propVal ) || empty( $obj->{$propVal} ) ) {
				return false;
			}
		} else {
			if ( ! property_exists( $obj, $propKey ) ) {
				return false;
			}
			if ( is_callable( $propVal ) ) {
				return call_user_func( $propVal, $obj->{$propKey} );
			}
			if ( strtolower( $obj->{$propKey} ) != strtolower( $propVal ) ) {
				return false;
			}
		}

		return true;
	};

	return array_filter( $array, function ( $v ) use ( $properties, $isValid ) {
		foreach ( $properties as $propKey => $propVal ) {
			if ( is_array( $propVal ) ) {
				$prop = array_shift( $propVal );
				if ( ! property_exists( $v, $prop ) ) {
					return false;
				}
				reset( $propVal );
				$key = key( $propVal );
				if ( ! $isValid( $v->{$prop}, $key, $propVal[ $key ] ) ) {
					return false;
				}
			} else {
				if ( ! $isValid( $v, $propKey, $propVal ) ) {
					return false;
				}
			}
		}

		return true;
	} );
}

remove_action( 'genesis_loop', 'genesis_do_loop' );
add_action( 'genesis_loop', __NAMESPACE__ . '\do_search_loop' );
/**
 * Outputs a custom loop.
 *
 * @global mixed $paged current page number if paginated.
 * @return void
 */
function do_search_loop() {
	global $wp_query;


//	\WPS\printr($wp_query, 'query');
	printf( '<div class="search-wrap">%s</div>', get_search_form( false ) );

	// create an array variable with specific post types in your desired order.
	$post_types = array( 'documentation', 'page', 'post', 'forum', );

	if ( count( $wp_query->posts ) > 0 ) {

		echo '<div class="search-results">';

		foreach ( $post_types as $post_type ) {
			$posts = ofilter( $wp_query->posts, array( 'post_type' => $post_type ) );
//		printr( $posts );

			if ( ! empty( $posts ) ) {

				printf( '<div class="post-type-search-results post-type-%s">', $post_type );

				$post_type_object = get_post_type_object( $post_type );
				printf( '<h2 class="post-type-heading">%s</h2>', $post_type_object->labels->name );

				foreach ( $posts as $post ) {

					printf( '<div class="search-result-entry search-result-%s">', $post_type );

					$output = apply_filters( 'the_excerpt', get_the_excerpt( $post ) );
					if ( '' === $output ) {
						$output = apply_filters( 'the_content', $post->post_content );
					}

					printf( '<div class="entry"><h6><a href="%s" title="%s">%s</a></h6><div class="search-result-content">%s</div></div>',
						get_permalink( $post->ID ),
						esc_attr( $post->post_title ),
						apply_filters( 'the_title', $post->post_title ),
						$output
					);

					echo '</div>';
				}

				echo '</div>'; // .post-type-content


			}

		}
		echo '</div>'; // .search-content

	} else {
		do_action( 'genesis_loop_else' );
	}
}


genesis();
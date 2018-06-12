<?php

namespace WPS\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WPS\Core\Search' ) ) {
	/**
	 * Class Search
	 *
	 * @package WPS\Core
	 */
	class Search extends Singleton {

		/**
		 * Array of post types to search.
		 *
		 * @var string[]
		 */
		public $post_types = array();

		/**
		 * Default array of post types to search.
		 *
		 * @var string[]
		 */
		private $_post_types = array( 'post', 'page', );

		/**
		 * Search constructor.
		 *
		 * @param string[] $post_types Array of post type names.
		 */
		protected function __construct( $post_types = array() ) {
			$this->post_types = $post_types;

			add_filter( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
			add_filter( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
			add_filter( 'core_acf_fields', array( $this, 'core_acf_fields' ) );
		}

		/**
		 * Add ACF fields to the Page.
		 *
		 * @param $fields
		 */
		public function core_acf_fields( $fields ) {

			$builder = \WPS\Core\Fields::get_instance();
			$content = $builder->new_fields_builder( 'search' );
			$content
				->addTrueFalse( 'exclude_from_search' );

			$post_types = $this->get_post_types();
			$location   = $content->setLocation( 'post_type', '==', '' );
			foreach ( $post_types as $post_type ) {
				$location->or( 'post_type', '==', $post_type );
			}

			$fields->add( $content );

		}

		/**
		 * Gets the supported post types
		 *
		 * Defaults self::$post_types to post, page, and any publicly queryable post types that
		 * are not excluded from search.
		 */
		private function get_post_types() {
			if ( ! empty( $this->post_types ) ) {
				return $this->post_types;
			}

			$this->post_types = array_values( array_unique( array_merge( get_post_types( array(
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
			) ), $this->_post_types ) ) );

			return $this->post_types;
		}

		/**
		 * Initialize plugin.
		 *
		 * Defaults self::$post_types to post, page, and any publicly queryable post types that
		 * are not excluded from search. Initializes ACF Fields on plugins_loaded hook.
		 */
		public function plugins_loaded() {

			$this->get_post_types();

			\WPS\Core\Fields::get_instance();

		}

		/**
		 * Adjust the search object.
		 *
		 * @param \WP_Query $query WP Query.
		 */
		public function pre_get_posts( $query ) {

			if ( ! $query->is_main_query() || is_admin() || ! $query->is_search() ) {
				return;
			}

			// Add Post Types
			$post_type = $query->get( 'post_type' );
			$query->set( 'post_type', array_unique( array_merge( (array)$post_type, $this->get_post_types() ) ) );

			//Get original meta query
			$meta_query = $query->get( 'meta_query' );

			$not_exclude_from_search = array(
				'key'     => 'exclude_from_search',
				'value'   => 1,
				'type' => 'NUMERIC',
				'compare' => '!=',
			);
			$exclude_from_search_not_exists = array(
				'key'     => 'exclude_from_search',
				'compare' => 'NOT EXISTS',
			);

			if ( ! is_array( $meta_query ) ) {
				$meta_query = array(
					$not_exclude_from_search,
					$exclude_from_search_not_exists,
					'relation' => 'OR',
				);
			} else {
				if ( ! isset( $meta_query['relation'] ) ) {
					$meta_query['relation'] = 'OR';
				}

				if ( 'OR' === $meta_query['relation'] ) {
					$meta_query[] = $not_exclude_from_search;
					$meta_query[] = $exclude_from_search_not_exists;
				} else {
					$meta_query[] = $not_exclude_from_search;
				}
			}

			$query->set( 'meta_query', $meta_query );

		}
	}
}
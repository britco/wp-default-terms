<?php
namespace WP_Default_Terms;

/**
 * Base class which adds callbacks for WP actions
 */

if ( class_exists( 'Actions' ) ) {
	return;
}

class Actions {
	public $option_prefix = 'brit_default_terms';
	public $upgrade_taxonomies = array();
	protected $logged_messages = array();
	public $is_wp_cli = false;

	function __construct() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->is_wp_cli = true;
		}

		add_action( 'registered_taxonomy', array( $this, 'registered_taxonomy' ), 20 );
		add_action( 'wp_insert_post', array( $this, 'wp_insert_post' ), 11, 3 );
		add_action( 'schema_upgrade', array( $this, 'schema_upgrade' ) );
	}

	/**
	 * Get the current defaults in the DB for all / a single taxonomy
	 *
	 * @param string Name of the taxonomy you want to get defaults for
	 *
	 * @return array Array of term names
	 */
	private function get_db_defaults( $name = '' ) {
		global $wp_taxonomies;

		$defaults = get_site_option(
			"{$this->option_prefix}", array_fill_keys(
			array_keys( $wp_taxonomies ),
			[ ]
		) );

		if ( ! empty( $name ) ) {
			if ( array_key_exists( $name, $defaults ) ) {
				return $defaults[ $name ];
			} else {
				return array();
			}
		}

		return $defaults;
	}

	/**
	 * Save new defaults for a taxonomy
	 *
	 * @param  string $name Name of the taxonomy to sync defaults for. I.E. post_tag.
	 *
	 * @return array All default terms currently set
	 */
	private function save_db_defaults( $taxonomy ) {
		if ( ! is_object( $taxonomy ) || ! property_exists( $taxonomy, 'defaults' ) ) {
			$taxonomy = get_taxonomy( $taxonomy );
		}

		// Get the existing option
		$defaults = get_site_option( "{$this->option_prefix}" );

		// Update the values for this key
		$defaults[ $taxonomy->name ] = $taxonomy->defaults->get();
		update_site_option( $this->option_prefix, $defaults );

		return $defaults;
	}

	/**
	 * Bulk insert rows into the term_relationships table
	 *
	 * @param array List of post IDs (or oother object IDs)
	 * @param array List of term IDs
	 * @param mixed WP taxonomy object
	 *
	 * @return bool Success of insert
	 */
	function assign_terms( $object_ids = array(), $term_taxonomy_ids = array(), $taxonomy = false ) {
		global $wpdb;

		if ( empty( $object_ids ) || empty( $term_taxonomy_ids ) || empty( $taxonomy ) ) {
			return false;
		}

		$wpdb->query( "SET unique_checks=0" );

		// Insert the the relationships from post_ids => $defaults
		$query = "INSERT INTO `{$wpdb->term_relationships}` (
      object_id, term_taxonomy_id
    ) VALUES";

		foreach ( $term_taxonomy_ids as $term_taxonomy_id ) {
			foreach ( $object_ids as $object_id ) {
				$query .= $wpdb->prepare( "(%d, %d),", $object_id, $term_taxonomy_id );
			}
		}

		$query = rtrim( $query, ',' );
		$a     = $wpdb->query( $query );

		// Commit the changes
		$wpdb->query( "SET unique_checks=1" );

		// Update the "total" count for each term that was affected
		wp_update_term_count_now( $term_taxonomy_ids, $taxonomy->name );

		return true;
	}

	/**
	 * When a new taxonomy is registered, check if the defaults are in sync,
	 * and if not, queue a schema upgrade.
	 */
	function registered_taxonomy( $name ) {
		global $wp_taxonomies;

		$_this = $this;

		if ( $this->is_wp_cli ) {
			$defaults = $this->get_db_defaults( $name );
		}

		$taxonomy = $wp_taxonomies[ $name ];

		if ( ! property_exists( $taxonomy, 'defaults' ) ) {
			// No defaults exist when taxonomy was registered, create an empty
			// DefaultsObject so some can be added latter
			$taxonomy->defaults = new DefaultsObject( $taxonomy, array() );

			return;
		}

		// Registered defaults exist, upgrade it to a DefaultsObject that can be
		// accessed like: $taxonomy->defaults->set(array('foo')) and
		// $taxonomy->defaults->get($post_type)
		if ( ! $taxonomy->defaults instanceof DefaultsObject ) {
			$taxonomy->defaults = new DefaultsObject( $taxonomy, $taxonomy->defaults );
		}

		// Queue a schema upgrade if in WP-CLI mode and defaults are not in sync
		if ( $this->is_wp_cli && $taxonomy->defaults->get() != $defaults || 1 === 1 ) {
			$this->upgrade_taxonomies[ $name ] = $taxonomy;
		}
	}

	/**
	 * Add default terms to new posts
	 */
	public function wp_insert_post( $post_ID, $post, $update ) {
		global $wp_taxonomies;

		if ( $update ) {
			return false;
		}

		// Get an array of taxonomy name => default terms for this post type
		$taxonomy_names = get_object_taxonomies( $post->post_type );
		$taxonomies     = array_map( 'get_taxonomy', (array) $taxonomy_names );
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! property_exists( $taxonomy, 'defaults' ) ||
			     empty( $taxonomy->defaults->get( $post->post_type ) )
			) {
				continue;
			}

			$defaults = array_filter( $taxonomy->defaults->get( $post->post_type ), 'strlen' );

			if ( ! empty( $defaults ) ) {
				// Map term names to IDs (creates the term if it doesn't exist)
				$terms = array_map( function ( $default ) use ( $taxonomy ) {
					// Get term
					$term = get_term_by( 'name', $default . '', $taxonomy->name );
					if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
						return $term->slug;
					} else {
						// Create term
						wp_insert_term( $default, $taxonomy->name );
						$term = get_term_by( 'name', $default . '', $taxonomy->name );

						if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
							return $term->slug;
						} else {
							return false;
						}
					}
				}, $defaults );

				// Remove empty terms
				$terms = array_filter( $terms, 'strlen' );

				wp_set_object_terms( $post_ID, $terms, $taxonomy->name, true );
			}
		}
	}

	/**
	 * Resync default terms for any taxonomies that are out of sync. I.E. if post_tag
	 * has a default term of "water", then any post without tags already set, will
	 * get the tag "water".
	 */
	function schema_upgrade() {
		global $wpdb;

		if ( empty( $this->upgrade_taxonomies ) ) {
			return false;
		}

		foreach ( $this->upgrade_taxonomies as $name => $taxonomy ) {
			unset( $this->upgrade_taxonomies[ $name ] );

			// First insert the term object if it doesn't exist
			$defaults = $taxonomy->defaults->get();

			$term_id = false;
			foreach( $defaults as $default ) {
				$term_id = term_exists( $default, $taxonomy->name );

				if ( empty( $term_id ) ) {
					$term = wp_insert_term( $default[0], $taxonomy->name );
					if( ! is_wp_error( $term ) ) {
						$term_id = $term['term_id'];
					}
				}
			}

			// @todo Appears edit flow changes taxonomy names for wp_count_term/get_terms queries. So direct SQL for now
			$term_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT count 
					FROM $wpdb->term_taxonomy 
					WHERE taxonomy = %s",
					$taxonomy->name
				)
			);

			if ( 0 < $term_count ) {
				foreach ( $taxonomy->object_type as $post_type ) {
					if ( empty( $taxonomy->defaults->get( $post_type ) ) ) {
						continue;
					}

					// Find all the posts (or other object type) that don't already have terms
					// for this taxonomy (a.k.a. They are still in a "default" state. So if
					// you added a default to post_tag, find all posts without any
					// post_tags.)

					$post_ids = array();
					for ( $i = 0; $i < $term_count; $i = $i + 500 ) {
						$raw_results = new \WP_Query( array(
							'fields'                 => 'ids',
							'post_type'              => $post_type,
							'post_parent'            => 0,
							'posts_per_page'         => 500,
							'offset'                 => $i,
							'no_found_rows'          => true,
							'update_post_meta_cache' => false,
							'tax_query'              => array(
								array(
									'taxonomy' => sanitize_key( $taxonomy->name ),
									'terms'    => (int) $term,
									'operator' => 'NOT IN'
								)
							)
						) );
						if ( is_array( $raw_results->posts ) && ! empty( $raw_results->posts ) ) {
							$post_ids = array_merge( $post_ids, $raw_results->posts );
						}
					}

					$term_ids = array();
					$defaults = $taxonomy->defaults->get( $post_type );
					foreach ( $defaults as $default ) {
						$term       = get_term_by( 'name', $default, $taxonomy->name );
						$term_ids[] = (int) $term->term_taxonomy_id;
					}

					$this->assign_terms( $post_ids, $term_ids, $taxonomy );

					// Save the state of the defaults to the DB
					$this->save_db_defaults( $taxonomy );
				}
			}
		}
	}
}

register_instance( new Actions() );

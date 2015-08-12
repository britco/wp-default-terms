<?php
namespace DefaultTerms;

/*
Plugin Name: Default Terms
Plugin URI:
Description: Adds support for default taxonomy terms
Author: Paul Dufour
Version: 0.1
Author URI: http://www.brit.co
*/

class DefaultTerms {
  public $option_prefix = 'brit_default_terms';
  public $upgrade_taxonomies = array();
  protected $logged_messages = array();
  
  function __construct() {
    add_action('registered_taxonomy', array($this, 'registered_taxonomy'), 20);
    add_action('schema_upgrade', array($this, 'schema_upgrade'));
  }
  
  /**
   * Set the defaults on a taxonomy. Used like $taxonomy->setDefaults(array('foo')).
   * Normalizes the data so it looks like:
   * [
   *  post => ['foo'],
   *  page => ['foo']
   * ]
   */
  private function set_defaults($taxonomy, $defaults=array()) {
    if(!is_array(array_values($defaults)[0])) {
      // Not a two level array yet
      sort($defaults);
            
      $new_defaults = array();
      $object_types = $taxonomy->object_type;
      
      sort($object_types);
      foreach($object_types as $object_type) {
        $new_defaults[$object_type] = $defaults;
      }
      $taxonomy->defaults = $new_defaults;
    } else {
      // Sort the the keys and values
      array_walk($defaults, function(&$value, $key) {
        sort($value);
      });
      ksort($defaults);
      $taxonomy->defaults = $defaults;
    }
  }
  
  /**
   * Get the current defaults in the DB for all / a single taxonomy
   *
   * @param string Name of the taxonomy you want to get defaults for
   * @return array Array of term names
   */
  private function get_db_defaults($name='') {
    global $wp_taxonomies;
    
    $defaults = get_site_option(
      "{$this->option_prefix}", array_fill_keys(
      array_keys($wp_taxonomies),
      []
    ));
    
    if(!empty($name)) {
      if(array_key_exists($name, $defaults)) {
        return $defaults[$name];
      } else {
        return array();
      }
    }
    
    return $defaults;
  }
  
  /**
   * Save new defaults for a taxonomy
   *
   * @param  string $name Name of the taxonomy
   * @return array Array that is saved to the DB
   */
  private function save_db_defaults($taxonomy) {
    if(!is_object($taxonomy)) {
      $taxonomy = get_taxonomy($taxonomy);
    }
    
    // (Re)normalize defaults
    $taxonomy->setDefaults->__invoke($taxonomy->defaults);
    
    // Get the existing option
    $defaults = get_site_option("{$this->option_prefix}");
    
    // Update the values for this key
    $defaults[$taxonomy->name] = $taxonomy->defaults;
    update_site_option($this->option_prefix, $defaults);
  }
  
  /**
   * Bulk insert rows into the term_relationships table
   * @return bool Success of insert
   */
  function assign_terms($object_ids=array(), $term_taxonomy_ids=array()) {
    global $wpdb;
    
    if(empty($object_ids) || empty($term_taxonomy_ids)) {
      return false;
    }
    
    $wpdb->query("START TRANSACTION");
    $wpdb->query("SET unique_checks=0");
    
    // Insert the the relationships from post_ids => $defaults
    $query = "INSERT INTO `{$wpdb->term_relationships}` (
      object_id, term_taxonomy_id
    ) VALUES";
    
    foreach($term_taxonomy_ids as $term_taxonomy_id) {
      foreach($object_ids as $object_id) {
        $query .= $wpdb->prepare("(%d, %d),", $object_id, $term_taxonomy_id);
      }
    }

    $query = rtrim($query, ',');
    $a = $wpdb->query($query);
    
    // Commit the changes
    $wpdb->query("SET unique_checks=1");
    $wpdb->query("COMMIT");
  }
  
  /**
   * When a new taxonomy is registered, check if the defaults are in sync,
   * and if not, run a schema upgrade.
   */
  function registered_taxonomy($name) {
    global $wp_taxonomies;
    
    $_this = $this;
    
    $defaults = $this->get_db_defaults($name);
    
    $taxonomy = $wp_taxonomies[$name];
    
    // Add a function set set the defaults property, so the data can be
    // normalized.
    $taxonomy->setDefaults = function() use ($taxonomy) {
      $arguments = func_get_args();
      array_unshift($arguments, $taxonomy);
      return call_user_func_array(__NAMESPACE__ .'\DefaultTerms::set_defaults', $arguments);
    };
        
    if(!property_exists($taxonomy, 'defaults')) {
      return;
    }
    
    $taxonomy->setDefaults->__invoke($taxonomy->defaults);
    
    if($taxonomy->defaults != $defaults) {
      $this->upgrade_taxonomies[$name] = $taxonomy;
    }
  }
  
  /**
   * When a schema_upgrade action happens, fill in default terms. I.E. if post_tag
   * has a default term of "water", then any post without tags already set, will
   * get the tag "water".
   */
  function schema_upgrade() {
    global $wpdb;
    
    if(empty($this->upgrade_taxonomies)) {
      return false;
    }
    
    foreach($this->upgrade_taxonomies as $name => $taxonomy) {
      unset($this->upgrade_taxonomies[$name]);
      
      // First insert the term if it doesn't exist
      array_walk_recursive($taxonomy->defaults, function($default) use ($taxonomy) {
        $term = term_exists($default, $taxonomy->name);
        if(empty($term)) {
          wp_insert_term($default, $taxonomy->name);
        }
      });

      foreach($taxonomy->object_type as $post_type) {
        // Find all the posts (or other post type) that don't already have terms
        // for this taxonomy (a.k.a. They are still in a "default" state. So if
        // you added a default to post_tag, find all posts without any
        // post_tags.)
        $post_ids = $wpdb->get_col(
          $wpdb->prepare(
            "SELECT *
            FROM {$wpdb->posts}
            WHERE
            NOT EXISTS (
              SELECT *
              FROM {$wpdb->term_relationships}
              RIGHT JOIN
                {$wpdb->term_taxonomy}
                  ON {$wpdb->term_taxonomy}.term_taxonomy_id = {$wpdb->term_relationships}.term_taxonomy_id
                  AND {$wpdb->term_taxonomy}.taxonomy = %s
              WHERE
                {$wpdb->term_relationships}.object_id = {$wpdb->posts}.id
            )
            AND {$wpdb->posts}.post_parent = 0
            AND {$wpdb->posts}.post_type = %s
            ORDER BY
              {$wpdb->posts}.id",
            $taxonomy->name,
            $post_type
          )
        );
        
        $defaults = $taxonomy->defaults[$post_type];
        foreach($defaults as $default) {
          $term = get_term_by('name', $default, $taxonomy->name);
          $term_ids[] = $term->term_taxonomy_id;
        }
        
        $this->assign_terms($post_ids, $term_ids);
        
        // Save the state of the defaults to the DB
        $this->save_db_defaults($taxonomy);
      }
    }
  }
}

$GLOBALS[__NAMESPACE__ . '_DefaultTerms'] = new DefaultTerms();

<?php
namespace WP_Default_Terms;

if(class_exists('DefaultsObject')) {
  return;
}

/**
 * Defaults object that is attached to WP taxonomy objects
 */

class DefaultsObject {
  protected $container = array();
  
  public function __construct($taxonomy, $defaults=array()) {
      $this->taxonomy = $taxonomy;
      
      $this->set((array)$defaults);
  }
  
  /**
   * Set the defaults on a taxonomy. Used like $taxonomy->defaults->set(array('foo')).
   * Will normalize the data so it looks like:
   * [
   *  post => ['foo'],
   *  page => ['foo']
   * ]
   */
  public function set($defaults=array()) {
    if(empty($defaults)) {
      return $defaults;
    }
    
    if(!is_array(array_values($defaults)[0])) {
      // Not a two level array yet
      sort($defaults);
            
      $new_defaults = array();
      $object_types = $this->taxonomy->object_type;
      
      sort($object_types);
      foreach($object_types as $object_type) {
        $new_defaults[$object_type] = $defaults;
      }
      $this->container = $new_defaults;
    } else {
      // Sort the the keys and values
      array_walk($defaults, function(&$value, $key) {
        sort($value);
      });
      ksort($defaults);
      $this->container = $defaults;
    }
  }
  
  /**
   * Get defaults array
   * @param  string $post_type Get defaults for this post type
   * @return array List of default terms
   */
  public function get($post_type='') {
    if(!empty($post_type)) {
      if(array_key_exists($post_type, $this->container)) {
        return $this->container[$post_type];
      } else {
        return false;
      }
    } else {
      return $this->container;
    }
  }
}

<?php
namespace WP_Default_Terms;

$wp_default_terms_instances = array();

/**
 * Get the instance of a class (that was already defined)
 */
function get_instance($name) {
  global $wp_default_terms_instances;
  
  return $wp_default_terms_instances[$name];
}

function register_instance($instance) {
  global $wp_default_terms_instances;
  
  $cls = substr(get_class($instance), strlen(__NAMESPACE__) + 1);
  
  $wp_default_terms_instances[$cls] = $instance;
  return $instance;
}

?>

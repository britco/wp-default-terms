<?php
namespace WP_Default_Terms;

use WP_UnitTestCase;

class UnitTestCase extends WP_UnitTestCase {
  public function setUp() {
    global $wpdb, $wp_taxonomies;
    
    call_user_func_array('parent::setUp', func_get_args());
    
    // Create new defaults object for taxonomies, so any defaults set from
    // previous tests aren't inherited
    foreach($wp_taxonomies as $taxonomy) {
      if(!property_exists($taxonomy, 'defaults')) {
        $taxonomy->defaults = new DefaultsObject($taxonomy, array());
      }
    }
    
    // Insert test terms so that we can double check that the correct ID field
    // is used (some functions call for term_id & some term_taxonomy_id) . The
    // auto increments for these two fields will be different now, so if the
    // wrong id is used, the test will fail).
    $wpdb->query("
      INSERT INTO {$wpdb->terms} (`name`, `slug`, `term_group`)
      VALUES
        ('Test tag', 'test-tag', 0),
        ('Test tag 2', 'test-tag-2', 0)
    ");

    // Mimic WP-CLI context
    get_instance('Actions')->is_wp_cli = true;
  }
  public function tearDown() {
    global $wp_taxonomies;
    
    call_user_func_array('parent::tearDown', func_get_args());
    
    // Reset any defaults set on taxomies
    foreach($wp_taxonomies as $taxonomy) {
      if(property_exists($taxonomy, 'defaults')) {
        unset($taxonomy->defaults);
      }
    }
  }
}

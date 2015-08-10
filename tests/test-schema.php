<?php

// @codingStandardsIgnoreStart
class SchemaTest extends WP_UnitTestCase {
  // @codingStandardsIgnoreEnd

  public function setUp() {
    global $wpdb;
  
    $_super = call_user_func_array(array($this, 'parent::setUp'), func_get_args());
    
    $wpdb->query("TRUNCATE table {$wpdb->term_relationships}");
    
    $wpdb->query("DELETE FROM {$wpdb->users} WHERE ID != 1");
    
    $this->author_id = $this->factory->user->create(array('role' => 'editor'));
    
    return $_super;
  }
  
  public function tearDown() {
    $_super = call_user_func_array(array($this, 'parent::tearDown'), func_get_args());
    
    return $_super;
  }

  // Set default tags and then test syncing them to the DB
  public function testSaveDbDefaults() {
    // Make method accessible outside of the class
    $methods = array_flip(array('save_db_defaults', 'normalize_defaults'));
    
    foreach($methods as $method => $empty) {
      $methods[$method] = new ReflectionMethod('DefaultTerms\DefaultTerms', 'save_db_defaults');
      $methods[$method]->setAccessible(true);
    }
    
    $instance = new DefaultTerms\DefaultTerms();
    
    // Add defaults to the post_tag taxonomy
    $post_tag = get_taxonomy('post_tag');
    $post_tag->setDefaults->__invoke(array('chair'));
    
    // Save to DB
    $methods['save_db_defaults']->invoke($instance, 'post_tag');
    
    $defaults = get_site_option($instance->option_prefix);
            
    $this->assertEqualSets($defaults, array(
      'post_tag' => array(
        'post' => array(
          'chair'
        )
      )
    ));
  }

  /**
   * Test that posts with no tags are added default tags during a schema upgrade
   */
  public function testSchemaUpgrade() {
    global $wpdb;
    
    // Insert test post
    $post_data = array(
      'post_author' => $this->author_id,
      'post_status' => 'publish',
      'post_content' => rand_str(),
      'post_title' => rand_str(),
      'post_type' => 'post'
    );
    
    $post_id = wp_insert_post($post_data);
    $post = get_post($post_id);
    
    // Insert test tag
    wp_insert_term('Water', 'post_tag');
    $tag = get_taxonomy('post_tag');
    
    // Set the defaults for the post_tag taxonomy
    $tag->defaults = array('Water');
    
    // Fake firing the tax registration again so that the schema upgrades can
    // get queued
    do_action('registered_taxonomy', 'post_tag');
    
    // See if the tags get added to posts when the schema upgrade action runs
    do_action('schema_upgrade');
    
    $post_tags = array_values(wp_list_pluck(wp_get_post_tags($post_id), 'name'));
    $this->assertEqualSets($post_tags, array('Water'));
  }
  
  /**
   * Test that posts with existing tags don't get added new tags during a schema upgrade
   */
  public function testSchemaUpgradeExistingTags() {
    global $wpdb;
    
    // Insert test post
    $post_data = array(
      'post_author' => $this->author_id,
      'post_status' => 'publish',
      'post_content' => rand_str(),
      'post_title' => rand_str(),
      'post_type' => 'post',
      'tags_input' => array('Juice')
    );
    
    // Insert test post (with tags already set)
    $post_id = wp_insert_post($post_data);
    $post = get_post($post_id);
    
    // Set the defaults for the post_tag taxonomy
    wp_insert_term('Water', 'post_tag');
    $tag = get_taxonomy('post_tag');
    $tag->defaults = array('Water');
    
    do_action('registered_taxonomy', 'post_tag');
    
    do_action('schema_upgrade');
    
    // Check that the tags for the post weren't edited
    $post_tags = array_values(wp_list_pluck(wp_get_post_tags($post_id), 'name'));
    $this->assertEqualSets($post_tags, array('Juice'));
  }
  
  public function testSkipSchemaUpgrade() {
    $tag = get_taxonomy('post_tag');
    $tag->setDefaults->__invoke(array('Keyboard'));
    
    // Run the first schema upgrade, which should add "Keyboard" to all posts
    do_action('registered_taxonomy', 'post_tag');
    $this->assertEquals(count($GLOBALS['DefaultTerms_DefaultTerms']->upgrade_taxonomies), 1);
    do_action('schema_upgrade');
    
    // Run the second schema upgrade (which shoudn't do anything since the
    // defaults are in sync)
    do_action('registered_taxonomy', 'post_tag');
    $this->assertEquals(count($GLOBALS['DefaultTerms_DefaultTerms']->upgrade_taxonomies), 0);
    do_action('schema_upgrade');
  }
}

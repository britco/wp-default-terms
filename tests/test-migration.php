<?php
// @codingStandardsIgnoreStart
class MigrationTest extends WP_UnitTestCase {
  // @codingStandardsIgnoreEnd

  public function setUp() {
    global $wpdb;
  
    $_super = call_user_func_array(array($this, 'parent::setUp'), func_get_args());
    
    $wpdb->query("TRUNCATE table {$wpdb->term_relationships}");
    
    $this->author_id = $this->factory->user->create(array('role' => 'editor'));
    
    return $_super;
  }
  
  public function tearDown() {
    return call_user_func_array(array($this, 'parent::tearDown'), func_get_args());
  }
  
  public function testPostTagDefaultTerm() {
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
    
    // Insert test post (with tags already set)
    $post_data2 = array_merge($post_data, array('tags_input' => array('Juice')));
    $post2_id = wp_insert_post($post_data2);
    $post2 = get_post($post2_id);
    
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
    
    // Check that the tags for the second post weren't edited
    $post_tags = array_values(wp_list_pluck(wp_get_post_tags($post2_id), 'name'));
    $this->assertEqualSets($post_tags, array('Juice'));
  }
}

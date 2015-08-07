<?php
// @codingStandardsIgnoreStart
class MigrationTest extends WP_UnitTestCase {
  // @codingStandardsIgnoreEnd

  public function setUp() {
    global $wpdb;
  
    call_user_func_array(array($this, 'parent::setUp'), func_get_args());
    
    $wpdb->query("TRUNCATE table {$wpdb->term_relationships}");
    
    $this->author_id = $this->factory->user->create(array('role' => 'editor'));
    
    $post_data = array(
      'post_author' => $this->author_id,
      'post_status' => 'publish',
      'post_content' => rand_str(),
      'post_title' => rand_str(),
      'post_type' => 'post'
    );
    
    $this->post_id = wp_insert_post($post_data);
    $this->post = get_post($this->post_id);
  }
  
  public function tearDown() {
    global $wpdb;
    
    call_user_func_array(array($this, 'parent::tearDown'), func_get_args());
  }
  
  public function testPostTagDefaultTerm() {
    global $wpdb;
    
    wp_insert_term('Water', 'post_tag');
    $tag = get_taxonomy('post_tag');
    $tag->defaults = array('Water');
    
    // Fake firing the tax registration again so that the schema upgrades can
    // get queued
    do_action('registered_taxonomy', 'post_tag');
    
    do_action('schema_upgrade');
    
    wp_set_post_terms($this->post_id, array(1));
    var_dump($wpdb->get_results("SELECT * FROM {$wpdb->term_taxonomy}"));
    var_dump($wpdb->get_results("SELECT * FROM {$wpdb->term_relationships}"));
    $post_tags = wp_get_post_tags($this->post_id);
    var_dump($post_tags);
    // die;
  }
}

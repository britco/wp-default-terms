<?php
namespace WP_Default_Terms;

use WP_UnitTestCase, ReflectionMethod;

// @codingStandardsIgnoreStart
class DBTest extends UnitTestCase {
  // @codingStandardsIgnoreEnd

  // Set default tags and then test syncing them to the DB
  public function testSaveDbDefaults() {
    // Make method accessible outside of the class
    $methods = array_flip(array('save_db_defaults', 'normalize_defaults'));

    foreach($methods as $method => $empty) {
      $methods[$method] = new ReflectionMethod('\\' . __NAMESPACE__ . '\Actions', 'save_db_defaults');
      $methods[$method]->setAccessible(true);
    }

    $instance = new Actions();

    // Add defaults to the post_tag taxonomy
    $post_tag = get_taxonomy('post_tag');
    $post_tag->defaults->set(array('chair'));

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

    $post_id = $this->factory->post->create(array());
    $post = $this->factory->post->get_object_by_id($post_id);

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

    wp_delete_post($post_id, true);

    $this->assertEqualSets($post_tags, array('Water'));
  }

  /**
   * Test that posts with existing tags don't get added new tags during a schema
   * upgrade
   */
  public function tesetSchemaUpgradeExistingTags() {
    global $wpdb;

    // Insert test post
    $post_data = array(
      'tags_input' => array('Juice')
    );

    // Insert test post (with tags already set)
    $post_id = $this->factory->post->create($post_data);
    $post = $this->factory->post->get_object_by_id($post_id);

    // Set the defaults for the post_tag taxonomy
    wp_insert_term('Water', 'post_tag');
    $tag = get_taxonomy('post_tag');
    $tag->defaults = array('Water');

    do_action('registered_taxonomy', 'post_tag');

    do_action('schema_upgrade');

    // Check that the tags for the post weren't edited
    $post_tags = array_values(wp_list_pluck(wp_get_post_tags($post_id), 'name'));

    wp_delete_post($post_id, true);

    $this->assertEqualSets($post_tags, array('Juice'));
  }

  public function testSkipSchemaUpgrade() {
    $tag = get_taxonomy('post_tag');
    $tag->defaults->set(array('Keyboard'));

    // Run the first schema upgrade, which should add "Keyboard" to all posts
    do_action('registered_taxonomy', 'post_tag');
    $this->assertEquals(count(get_instance('Actions')->upgrade_taxonomies), 1);
    do_action('schema_upgrade');

    // Run the second schema upgrade (which shoudn't do anything since the
    // defaults are in sync)
    do_action('registered_taxonomy', 'post_tag');
    $this->assertEquals(count(get_instance('Actions')->upgrade_taxonomies), 0);
    do_action('schema_upgrade');
  }

  /**
   * Test that the count column on the term_taxonomy table gets updated
   */
  public function testUpdateTermCount() {
    global $wpdb;

    $post_id1 = $this->factory->post->create(array());
    $post_id2 = $this->factory->post->create(array());

    $tag = get_taxonomy('post_tag');
    $tag->defaults->set(array('Fan'));

    do_action('registered_taxonomy', 'post_tag');
    $this->assertEquals(count(get_instance('Actions')->upgrade_taxonomies), 1);
    do_action('schema_upgrade');

    $term = get_term_by('name', 'Fan', 'post_tag');

    wp_delete_post($post_id1, true);
    wp_delete_post($post_id2, true);

    $this->assertEquals($term->count, 2);
  }
}

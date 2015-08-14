<?php
namespace WP_Default_Terms;

use WP_UnitTestCase;

// @codingStandardsIgnoreStart
class PostTest extends WP_UnitTestCase {
  // @codingStandardsIgnoreEnd
  
  public function testCreatePostWithDefaultTag() {
    wp_insert_term('I am a quot: "', 'post_tag');
    
    $tag = get_taxonomy('post_tag');
    $tag->defaults = array('I am a quot: "');

    do_action('registered_taxonomy', 'post_tag');
    
    $post_id = $this->factory->post->create();
    $post = $this->factory->post->get_object_by_id($post_id);
    
    $post_tags = array_values(wp_list_pluck(wp_get_post_tags($post_id), 'name'));
    
    wp_delete_post($post_id, true);
    
    $this->assertEqualSets($post_tags, array('I am a quot: "'));
  }
  
  public function testCreatePostWithCategory() {
    wp_insert_term('cool cat', 'category');
    $taxonomy = get_taxonomy('category');
    $taxonomy->defaults = array('cool cat');
    
    do_action('registered_taxonomy', 'category');
    
    $post_id = $this->factory->post->create();
    $post = $this->factory->post->get_object_by_id($post_id);
    
    $post_categories = wp_get_post_categories($post_id, array('fields' => 'names'));
    
    wp_delete_post($post_id, true);
    
    $this->assertEqualSets($post_categories, array('Uncategorized', 'cool cat'));
  }
  
  public function testCreatePostWithCategoryNotYetCreated() {
    $taxonomy = get_taxonomy('category');
    $taxonomy->defaults = array('not a cool cat');
    
    do_action('registered_taxonomy', 'category');
    
    $post_id = $this->factory->post->create();
    $post = $this->factory->post->get_object_by_id($post_id);
    
    $post_categories = array_values(wp_get_post_categories($post_id, array('fields' => 'names')));
    
    wp_delete_post($post_id, true);
    
    $this->assertEqualSets($post_categories, array('Uncategorized', 'not a cool cat'));
  }
}

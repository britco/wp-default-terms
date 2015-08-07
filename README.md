## wp-default-terms

A way to set default terms for taxonomies. Similar how to the "default
category" functionality works. Will backport the defaults you specify to old
posts as well if you install the wp-schema plugin.

## Usage

````
function custom_tax_init() {
  register_taxonomy('custom-tax', array('post'), array(
    'defaults' => array(
        'Foo'
      )
  ));
}
````

You can also specify different defaults for different post types

````
function custom_tax_init() {
  register_taxonomy('custom-tax', array('post', 'custom_post_type'), array(
    'defaults' => array(
        'post' => array(
          'Foo'
        ),
        'custom_post_type' => array(
          'Bar'
        )
      )
  ));
}
````

## License
Available under the MIT License.

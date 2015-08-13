## wp-default-terms

[![Circle CI](https://circleci.com/gh/britco/wp-default-terms.svg?style=svg)](https://circleci.com/gh/britco/wp-default-terms)

A way to set default terms for taxonomies. Similar how to the "default
category" functionality works. Will backport the defaults you specify to old
posts as well if you install the [wp-cli-schema plugin](https://github.com/britco/wp-cli-schema). So for example, if you add a default `Michael Jordan` for the taxonomy `People`, when you run `wp schema upgrade` it will add `Michael Jordan` to any post that doesn't have any existing `People` terms.

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

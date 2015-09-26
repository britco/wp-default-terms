## wp-default-terms

[![Circle CI](https://circleci.com/gh/britco/wp-default-terms.svg?style=svg)](https://circleci.com/gh/britco/wp-default-terms)

A way to set default terms for taxonomies. Similar how to the "default
category" functionality works, but for any taxonomy.

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

Also, you can set defaults for already registered taxonomies

```
$taxonomy = get_taxonomy('post_tag');
$taxonomy->defaults->set(array('paper'));
```

## License
Available under the MIT License.

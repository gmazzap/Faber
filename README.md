Faber
=====

A tiny dependency injection container and factory class for WordPress and PHP 5.4+

![Travis CI Status](https://api.travis-ci.org/Giuseppe-Mazzapica/Faber.svg?branch=master)

#What is this?#

A WordPress specific DI container that doesn't suck at factoring objects.

#Examples#

    $container = GM\Faber::instance('my_plugin');

    // registering services
    
    $container['fullpost'] = function( $container, $args ) {
      $wp_post = get_post( $args['id'] );
      $my_post = $container['post'];
      $my_post->setWpPost( $wp_post );
      return $my_post;
    };
    
    $container['post'] = function( $container, $args ) {
      return new My\Plugin\Post( $container['meta_manager'], $container['tax_manager'] );
    };
    
    // registering services using add() method (just an alternative)
    
    $container->add( 'meta_manager', = function( $container, $args ) {
      return new My\Plugin\MetaManager;
    };
    $container->add( 'tax_manager', = function( $container, $args ) {
      return new My\Plugin\TaxManager;
    };
    
    // registering properties
    
    $container['foo'] = 'bar';
    // alternative: $container->add( 'foo', 'bar' );
    
    // registering closures as properties
    
    $container->protect( 'a_closure', function() use($container) {
       return $container['foo'];
    } );
    
    
    // getting services that requires factory arguments 
    
    $post1 = GM\Faber::instance('my_plugin')->get( 'fullpost', [ 'id' => 1 ] );
    $post2 = GM\Faber::instance('my_plugin')->get( 'fullpost', [ 'id' => 2 ] );
    $post1_again = GM\Faber::instance('my_plugin')->get( 'fullpost', [ 'id' => 1 ] );
    var_dump( $post1 === $post1_again ); // true, same id and same args => same instance
    $post_1_new =  GM\Faber::instance('my_plugin')->make( 'fullpost', [ 'id' => 1 ] );
    var_dump( $post1 === $post_1_new ); // false, make() forces new instance
    
    
    // getting services that requires doesn't require factory arguments
    
    $container = GM\Faber::instance('my_plugin');
    $meta_manager = $container['meta_manager'];
    // alternative: $bar = $container->get('meta_manager');
    
    
    // getting properties
    
    $container = GM\Faber::instance('my_plugin');
    
    $closure = $container['a_closure'];
    // alternative: $bar = $container->get('a_closure');
    // alternative (only for properties and protected closures): $bar = $container->prop('a_closure');
    
    
    // prevent properties and services are updated / deleted
    
    $container->freeze( 'foo' );
    $container->freeze( 'fullpost' );
    
    // .. and unfreeze
    
    $container->unfreeze( 'foo' );
    $container->unfreeze( 'fullpost' );
    
    
   // remove roperties and services (fail when frozen)
   
   unset( $container[] );
   // alternative: $container->remove( 'foo' );
    
 
    
    

#Requirements#

 - PHP 5.4+
 - Composer
 - WordPress

#Installation#

    composer create-project giuseppe-mazzapica/faber --no-dev

#License#

Faber is released under MIT.

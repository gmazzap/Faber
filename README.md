![Faber](https://googledrive.com/host/0Bxo4bHbWEkMsdUFRVDRpQ0RsbG8/faber.png)

**A WordPress-specific dependency injection container that doesn't suck at *factoring* objects.**

----------

It is mainly inspired to [Pimple](http://pimple.sensiolabs.org/), but allows easier object instances creation.

It is **not** a full-working plugin, it is a library to be embedded in larger projects via [Composer](https://getcomposer.org/).

As Pimple (and other DI containers), Faber manages two different kind of data: **services** and **properties**: you can save a variables "as is", and just retrieve it when you need (properties) or you can register factory closures for services: they are [anonymous functions](http://php.net/manual/en/functions.anonymous.php) that return instances of objects: when same service is required again and again, same instance is returned.

Faber also implements factory pattern: you can use the registered factory closures to obtain always *fresh*, vanilla instances of objects.

----------

![Travis Status](https://api.travis-ci.org/Giuseppe-Mazzapica/Faber.svg)

#How it Works

 - [Create Container Instance](#create-container-instance)
   - [Static Instantiation: The `instance()` Method](#static-instantiation-the-instance-method)
   - [Dynamic Instantiation](#dynamic-instantiation)
   - [Init Hook](#init-hook)
 - [Registering and Getting Services](##registering-and-getting-services)
   - [Objects with Arguments](#objects-with-arguments)
   - [How To Force Fresh Instances](#how-to-force-fresh-instances)
   - [Get data using "Demeter chain" ids](#get-data-using-demeter-chain-ids)
 - [Registering and Getting Properties](#registering-and-getting-properties)
   - [Saving Closures as Properties](#saving-closures-as-properties) 
 - [Hooks](#hooks)
 - [Bulk Registering](#bulk-registering)
   - [Definitions to Constructor](#definitions-to-constructor) 
   - [Definitions to `load()` method](#definitions-to-load-method)
   - [Definitions to `loadFile()` method](#definitions-to-loadfile-method)
   - [Load On Init](#load-on-init)
 - [Updating, Removing, Freezing and Unfreezing](#updating-removing-freezing-and-unfreezing)
   - [Updating](#updating)
   - [Removing](#removing)
   - [Freezing and Unfreezing](#freezing-and-unfreezing)
 - [Fluent Interface](#fluent-interface)
 - [Issers and Info Getters](#issers-and-info-getters)
   - [Conditional Methods AKA Issers](#conditional-methods-aka-issers)
   - [Info Getters](#info-getters)
 - [Cached Objects](#cached-objects)
   - [Freezing and Unfreezing Cached Objects](#freezing-and-unfreezing-cached-objects) 
 - [About Serialization](#about-serialization)
 - [Error Handling](#error-handling)

#Requirements, Installation and License
 
 - [Requirements](#requirements)
 - [Installation](#Installation)
 - [License](#license)



-----

#Create Container Instance

Before being able to do anything, we need an instance of container. There are 2 ways: the *static* way and the *dynamic* one.

##Static Instantiation: The **`instance()`** Method

First way to create an instance of the container is to use the static `instance()` method:

    $container = GM\Faber::instance( 'my_plugin' );

The argument passed to instance is the id of the instance: you can create any number of instances passing different ids, they will be completely isolated one from another:

    $plugin_container = GM\Faber::instance( 'my_plugin' );
    $theme_container = GM\Faber::instance( 'my_theme' );

Benefit of this approach is simple: once having a container is useless if it isn't accessible from any part of application, using this method is possible to access container from anywhere in the app by calling the `instance()` method again and again.

Note that the `instance()` method has an alias: **`i()`**:

    class_alias( 'GM\Faber', 'MyApp' );
    $container = MyApp::i( 'my_app' );

##Dynamic Instantiation

You can create an instance of container also using *canonical* way:

    $container = new GM\Faber();

When instantiated like so, it's up to you how to access that instance from anywhere in the app. Maybe using a global var:

    global $myapp;
    $myapp = new GM\Faber();
   
   
##Init Hook

No matter the method used to instantiate the container, when an instance is created, an action hook is fired: **`"faber_{$id}_init"`**. The `$id` part is, as you can guess, the instance id.

When container is instantiated using dynamic method, passing an id is optional, but when no id is passed to constructor an unique id is automatically created, it isn't predictable, but can be retrieved using **`getId()`** method.

    $myapp = new GM\Faber();
    $id = $myapp->getId(); // will be something like 'faber_5400d85c5a18c'

However, if you plan to use the init hook and dynamic instantiation, is probably preferable to pass an instance id to constructor:

    $myapp = new GM\Faber( [], 'my_app' );
    $id = $myapp->getId(); // will be 'my_app'

The id is the second argument, because first is an array of properties / services to be registered on instance creation.

The hook can be used to add properties and services to container, because the it passes the just-created container instance as argument to hooking callbacks:

    add_action( 'faber_' . $container->getId() . '_init', function( $container ) {
       // do something with container
    });

#Registering and Getting Services

Having an instance of container, we can register services to be used in our app.

A service is an object that does *something* as part of a larger system. Almost any global object can be a service.

Services are defined by closures (anonymous functions) that return an instance of an object:

    // let's define some services
    
    $container['bar'] = function ( $cont, $args ) {
        return new My\App\Bar( $cont['foo'] );
    };
    
    $container['foo'] = function ( $cont, $args ) {
        return new My\App\Foo();
    };

Things to note in previous code:

 - array access interface is used to add services (container object is treated as an array)
 - factory closures have access to the container instance that can be used to inject dependencies in the object to be created (in the example above an instance of class `Foo` is injected into `Bar` class)
 - factory closures have access to the `$args` variable, it is an array of arguments used when the instance is retrieved (better explained below)
 - order used to define services doesn't matter: factories are executed if and when a service is required, not when it is defined.

To get registered services is possible to use array access:

    $bar = $container['bar']; // $bar is an instance of My\App\Bar

or, as alternative, the container **`get()`** method:

    $bar = $container->get( 'bar' ); // $bar is an instance of My\App\Bar

Services are cached, it means that when same service is required after first time, same instance is returned:

    $bar_1 = $container['bar'];
    $bar_2 = $container->get( 'bar' );
    var_dump( $bar_1 === $bar_2 ); // TRUE
    
    $foo = $container['foo'];
    // here getFoo() returns the instance of Foo injected in Bar constructor
    $bar_foo = $bar_1->getFoo();
    var_dump( $foo === $bar_foo ); // TRUE

##Objects with Arguments

Let's assume we have a class that requires some arguments to be instantiated, and they vary from instance to instance.

An example:

    class Post {

        private $wp_post;
        private $options;
      
        function __construct( $id, Options $options ) {
           $this->wp_post = get_post( $id );
           $this->options = $options;
        }
    }

Actually, this is not a *service*, however it's an object that needs a variable argument (`$id`) and a service (`$options`).

Whereas the service fits perfectly workflow explained above (an instance of `Options` can be saved in the container and passed to `Post`) isn't possible to do the same thing with `$id`.

This is the limit of a lot of DI containers, but luckily not of Faber.

See following definitions:
    
    $container['post'] = function ( $cont, $args ) {
        return new Post( $args['id'], $cont['options'] );
    };
    
    $container['options'] = function ( $cont, $args ) {
        return new Options;
    };

And let's play with some posts:

    $post_1 = $container->get( 'post', [ 'id' => 1 ] );
    $post_2 = $container->get( 'post', [ 'id' => 2 ] );
    $post_1_again = $container->get( 'post', [ 'id' => 1 ] );
    
    var_dump( $post_1 === $post_2 ); // FALSE: different args => different object
    var_dump( $post_1 === $post_1_again ); // TRUE: same args => same object

So, using `get()` method we can pass an array of arguments that can be used inside factory closures to generate objects.

When same id and same arguments are passed to `get()` method, the same instance is obtained; changing arguments different instances are returned.

Note that in code above the instance of `Options` passed to `Post` objects is always the same.

##How To Force Fresh Instances

Normally, services are cached: if same arguments are passed to `get()` method (or array access) then the same instance is returned.

What if a *fresh* instance of `Post` having ID 1 is required even if it has been required before?

The **`make()`** is there for the scope:

    $post_1 = $container->get( 'post', [ 'id' => 1 ] );
    
    $post_1_again = $container->get( 'post', [ 'id' => 1 ] );
    
    $post_1_fresh = $container->make( 'post', [ 'id' => 1 ] );
    
    var_dump( $post_1 === $post_1_again ); // TRUE
    var_dump( $post_1 === $post_1_fresh ); // FALSE: make() force fresh instances
    
##Get data using "Demeter chain" ids

Let's assume some classes are defined like so:

    class Foo {
      
      function getBar() {
        return new Bar;
      }
    }
    
    class Bar {
      
      function getBaz() {
        return new Baz;
      }
    }
    
    class Baz {
      
      function getResult() {
        return 'Result!';
      }
    }
    
And first class is registered in the container, like so:

    $container['foo'] = function() {
      return new Foo;
    }
    
To get call `getResult()` method of `Baz` class, is possible to do something like:

    $result = $container['foo']->getBar()->getBaz()->getResult(); // 'Result!'
    
Fine. But (since version 1.1) in Faber is also possible to do:

    $result = $container['foo->getBar->getBaz->getResult'] // 'Result!'
    
So we can access methods of objects in container, by using object assigment operator (`->`) to "glue" object id and methods to be called in chain.

#Registering and Getting Properties

Properties are usually non-object variables that need to be globally accessible in the app. They are registered and retrieved in same way of services:

    // define properties

    $container->add( 'version', '1.0.0' );
    $container['timeout'] = HOUR_IN_SECONDS;
    $container['app_path'] = plugin_dir_path( __FILE__ );
    $container['app_assets_url'] = plugins_url( 'assets/' , __FILE__ );

    // get properties

    $version = $container['version'];
    $timeout = $container->get( 'timeout' );

As you can see, for properties just like for services, is possible to use `add()` or array access to register properties and `get()` or array access to retrieve them. 

Additional **`prop()`** method can be used to get properties, it returns an error if used with a service id:

    $app_path = $container->prop( 'app_path' );

##Saving Closures as Properties

When a closure is added to container, by default it's considered a factory closure for a service.
To store a closure "as is", treating it as a property, is possible use the **`protect()`** method:

    $container->protect( 'greeting', function() {
      return date('a') === 'am' ? 'Good Morning' : 'Good Evening';
    });


#Hooks

There are only three hooks fired inside Faber class.

The first is `"faber_{$id}_init"` explained [here](#init-hook).

The other two are filters, and they are:

 - **`"faber_{$id}_get_{$which}"`**
 - **`"faber_{$id}_get_prop_{$which}"`**

These filters are fired every time, respectively, a service or a property are retrieved from the container.

The variable `$id` part is the container instance id.

The variable `$which` part is the id of service / property being retrieved.

First argument they pass to hooking callbacks is the value just retrieved from container.
Second argument is the instance of container itself.

    $container = GM\Faber::i( 'test' );
    
    $container['foo'] => 'foo';
    
    $container['bar'] => function() {
       new Bar;
    };

    $foo = $container['foo']; // result passes through 'faber_test_get_prop_foo' filter
    
    $bar = $container['bar']; // result passes through 'faber_test_get_bar' filter

 

#Bulk Registering

Instead of registering services one by one using array access or `add()` method, is possible to register more services (and properties) at once. That can be done in three ways:

 - passing an array of definitions to constructor (only when using dynamic instantiation method)
 - passing an array of definitions to `load()` method
 - passing the path of a PHP file that returns an array of definitions to `loadFile()` method

##Definitions to Constructor

    $def = [
       'timeout' => HOUR_IN_SECONDS,
       'version' => '1.0.0',
       'foo' => function( $container, $args ) {
          return new Foo;
       },
       'bar' => function( $container, $args ) {
          return new Bar( $container['foo'] );
       }
    ];
    
    $container = new GM\Faber( $def );

##Definitions to `load()` method

    $container = new GM\Faber;
    $container->load( $def ); // $def is same of above

##Definitions to `loadFile()` method

First a definitions file is needed, something like:
       
    <?php
    // file must return an array
    return [
       'timeout' => HOUR_IN_SECONDS,
       'version' => '1.0.0',
       'foo' => function( $container, $args ) {
          return new Foo;
       },
       'bar' => function( $container, $args ) {
          return new Bar( $container['foo'] );
       }
    ];

and then

    $container = new GM\Faber;
    $container->loadFile( 'full/path/to/definitions/file.php' );

##Load On Init

An intersting usage of `load()` and `loadFile()` methods is combined with the [Faber init hook](#init-hook):

    add_action( 'faber_' . $container->getId() . '_init', function( $container ) {
       $container->loadFile( 'full/path/to/definitions.php' );
    });

It's a simple way to register services keeping code clean and readable.

#Updating, Removing, Freezing and Unfreezing

##Updating

Once a service or a property is registered, it can be updated using array access or **`update()`** method:

    $container[ 'foo' ] = 'Foo';
    $container[ 'bar' ] = 'Bar';
    echo $container[ 'foo' ]; // Output "Foo"
    echo $container[ 'bar' ]; // Output "Bar"
    
    $container[ 'foo' ] = 'New Foo';
    $container->update( 'bar', 'New Bar' );
    
    echo $container[ 'foo' ]; // Output "New Foo"
    echo $container[ 'bar' ]; // Output "New Bar"

Note that a service can be updated only with another service and a protected closure with another closure:

    $container[ 'foo_service' ] = function() {
        return Foo;
    };
    
    $container->protect( 'closure', function() {
        return 'Hello World!';
    });
    
    $container[ 'foo_service' ] = 'a string'; // will fail
    
    $container->update( 'closure', [ 'a', 'b' ] ); // will fail

##Removing

Once a service or a property is registered, it can be removed using `unset()` and array access or via **`remove()`** method:

    $container[ 'foo' ] = 'Foo';
    $container[ 'bar' ] = 'Bar';
    
    unset( $container[ 'foo' ] );
    $container->remove( 'foo' );

##Freezing and Unfreezing

Updating and removing definitions can be avoided by *freezing* them via, guess it, **`freeze()`** method.

When frozen, a property or a service can't be updated or removed until unfrozen via **`unfreeze()`** method:

    $container[ 'foo' ] = 'Foo!';
    
    $container->freeze( 'foo' );
    
    unset( $container[ 'foo' ] ); // will fail
    echo $container[ 'foo' ]; // still output "Foo!"
    
    $container->update( 'foo', 'New Foo!' ); // will fail
    echo $container[ 'foo' ]; // still output "Foo!"
    
    $container->unfreeze( 'foo' );

    $container[ 'foo' ] = 'New Foo!'; // will success
    echo $container[ 'foo' ]; // output "New Foo!"
    
    $container->remove( 'foo' ); // will success
    echo $container[ 'foo' ]; // will output an error message

#Fluent Interface

Methods that set *things* on container return an instance of the container itself, allowing *fluent interface*, i.e. *chained* methods.

Among methods that support this interface there are:

 - `load()`
 - `loadFile()`
 - `add()`
 - `protect()`
 - `freeze()`
 - `unfreeze()`
 - `update()`
 - `remove()`

E. g. the following is valid code:

    $foo = GM\Faber::instance('myapp')
      ->load( $defs )
      ->loadFile('defs.php')
      ->add( 'foo', 'Foo!' )
      ->add( 'bar', 'Bar!' )
      ->protect( 'hello', function() { return 'Hello!'; } )
      ->freeze( 'foo' )
      ->freeze( 'hello' )
      ->unfreeze( 'foo' )
      ->update( 'foo', 'New Foo!' )
      ->remove( 'bar' )
      ->get('foo'); // return 'New Foo!'


#Issers and Info Getters

##Conditional Methods AKA Issers

For a better control of application flow and to avoid errors, is reasonable that one wants to check if a specific id is registered in the container and if it is associated to a property or a factory, if it is frozen, and so on.

To fulfill these needs there are a set of conditional methods ("issers"). They are:

 - `$c->isProp( $id )` returns true if given id is associated to a property (protected closures are properties too)
 - `$c->isFactory( $id )` returns true if given id is associated to a factory closure
 - `$c->isProtected( $id )` returns true if given id is associated to a protected closure
 - `$c->isFrozen( $id )` returns true if given id is associated to a frozen item (no matter if property or factory)
 - `$c->isCachedObject( $key )` returns true if given key is associated to a cached object ([more info](#cached-objects))

*(where `$c` is an instance of the container, of course)*

##Info Getters

Sometimes is also desirable to have informations on the current state of a container instance.

There are some getters that provide useful informations:

 - `$c->getID()` returns id of container instance
 - `$c->getHash()` returns an hash associated to the container instance, used in different places internally
 - `$c->getFactoryIds()` returns an array of all ids associated to factory closures
 - `$c->getPropIds()` returns an array of all ids associated to properties (including protected closures)
 - `$c->getFrozenIds()` returns an array of all ids of frozen items (both properties and factory closures)
 - `$c->getObjectsInfo()` returns an array containing informations about all cached objects ([more info](#cached-objects))
 - `$c->getInfo()` return a plain object (`stdClass`) where properties are informations on the current state of the instance, a sort of summary of all previous getters. Note that `Faber` implements [`JsonSerializable` interface ](http://php.net/manual/en/class.jsonserializable.php) and when [`json_encode`](http://php.net/manual/en/function.json-encode.php) is called with a container instance, what is encoded is just the object returned by this method

#Cached Objects

Thanks to the fact that factory closures accept arguments, for every factory closures can exist different cached objects. An example:
 
    // define a service
    $container['foo'] = function( $c, $args ) {
      return new Foo( $args );
    };

    // create some objects
    $foo_1 = $container->get( 'foo', [ 'id' => 'foo_1' ] );
    $foo_2 = $container->get( 'foo', [ 'id' => 'foo_2' ] );
    $foo_3 = $container->get( 'foo', [ 'id' => 'foo_3' ] );

So there are three cached objects, all related to the factory with id "foo".

Using following code is possible to prove that objects are cached:

    $foo_3_bis = $container->get( 'foo', [ 'id' => 'foo_3' ] );
    
    var_dump( $foo_3_bis === $foo_3 ); // TRUE: objects are cached

Worth to know that every cached object is stored in the container with an identifier (usually called `$key`).

This identifier (aka "key") is visible among informations retrieved via `$c->getObjectInfo()` or `$c->getInfo()` methods.

It is a long hash string, but predictable if the id of the factory closure and the array of used arguments are known. In facts, using those informations, object key can be retrieved via **`getObjectKey()`**  method:

    $foo_3_key = $container->getObjectKey( 'foo', [ 'id' => 'foo_3' ] );
    
    // Something like "foo_0000000022764fbf000000005edc5c64_3cb01b53c29fbca3070106d562fa42ce"
    echo $foo_3_key;
    

`isCachedObject()` method can be used with object key to know if a specific object has been cached or not

    $key = $container->getObjectKey( 'foo', [ 'id' => 'foo_3' ] );
    
    if ( $container->isCachedObject( $key ) ) {
       // Yes, objects is cached
    } else {
       // No, objects is not cached
    }

##Freezing and Unfreezing Cached Objects

[Up in this page](#updating-removing-freezing-and-unfreezing) is explained how to freeze and unfreeze factory closures. 
Faber also allows to freeze and unfreeze specific cached objects: that is possible using same methods with object key instead of factory id.

Note: when a factory closure is deleted or updated, all the cached objects it has generated are removed, unless specific cached object was frozen.

Example:

    // define a service
    $container['foo'] = function( $c, $args ) {
      return new Foo( $args );
    };

    // create some objects
    $foo_1 = $container->get( 'foo', [ 'id' => 'foo_1' ] );
    $foo_2 = $container->get( 'foo', [ 'id' => 'foo_2' ] );

    // get object keys
    $key_1 = $container->getObjectKey( 'foo', [ 'id' => 'foo_1' ] );
    $key_2 = $container->getObjectKey( 'foo', [ 'id' => 'foo_2' ] );
    
    // test
    var_dump( $container->isCachedObject( $key_1 ) ); // TRUE
    var_dump( $container->isCachedObject( $key_2 ) ); // TRUE
    
    // freeze first object
    $container->freeze( $key_1 );

    // delete factory closure
    unset( $container['foo'] );

    // test
    var_dump( $container->isCachedObject( $key_1 ) ); // TRUE: still exists because was frozen
    var_dump( $container->isCachedObject( $key_2 ) ); // FALSE: vanished

    // get cached object
    $cached_foo = $container->get( $key_1 );

Faber has helper method **`getAndFreeze()`** that gets an object and also freeze it:

    $foo = $container->getAndFreeze( 'foo', [ 'id' => 'foo_1' ] );
    $key = $container->getObjectKey( 'foo', [ 'id' => 'foo_1' ] );
    
    var_dump( $container->isFrozen( $key ) ); // TRUE
    

#About Serialization

Current versions of PHP have a limitation: can't serialize closures or any variable that contains closures, e. g. array containing closures or objects having closures in properties: trying to serialize them a catchable fatal error is thrown.

Faber is designed to have quite a lot closures as properties, so you may think that container instances can't be serialized: that's not true.

The reason is Faber implements [`__sleep()` and `__wakeUp()` methods](http://php.net/manual/it/oop4.magic-functions.php) to prevent this kind of errors.

When an instance of Faber is serialized and then unserialized, the serialized / unserialized object contains only the id and the hash of original objects, that being strings bring no problems.

A little *sugar*: serializing and then unserializing a Faber instance during same request will create an exact clone of original object, because on *wake up* container state is cloned from saved instance:

    $faber = GM\Faber::i( 'test' ); // or $faber = new GM\Faber()
    
    $faber['foo'] = function() {
      return new Foo;
    };
    
    $sleep = serialize( $faber );
    $wakeup = unserialize( $sleep );
    
    $foo = $wakeup['foo'];
    var_dump( $foo instanceof Foo ); // TRUE


#Error Handling

Things can go wrong. Every method of Faber can fail, for different reasons.

When it happens, Faber returns an Error object that is a subclass of [`WP_Error`](http://codex.wordpress.org/Class_Reference/WP_Error) and so can be checked using [`is_wp_error()`](http://codex.wordpress.org/Function_Reference/is_wp_error) function.

    $container[ 'foo' ] = 'Foo!';
    
    $foo =  $container[ 'foo' ];
    
    if ( ! is_wp_error( $foo ) ) {
       echo $foo; // Output "Foo!";
    }
    
    $meh =  $container[ 'meh' ];  // 'meh' is an unregistered id, $meh is a WP_Error object
    
    if ( ! is_wp_error( $meh ) ) {
       echo $meh; // not executed
    }

The extended `WP_Error` class used by Faber works well with fluent interface. If you look at [code here](#fluent-interface) everyone of the chained functions can fail and return a `WP_Error` object, so next method in the chain is called on error object instead of on `Faber` object.

That's not a problem: the error class will add an error to its errors storage and then returns itself, so that at the end of the chain an error object is obtained and it has track of every method called on it: debug happiness.


----------


#Requirements

 - PHP 5.4+
 - [Composer](https://getcomposer.org/)
 - WordPress 3.9+

#Installation

In your `composer.json` require Faber like so:

    {
        "require": {
            "php": ">=5.4",
            "giuseppe-mazzapica/faber": "dev-master"
        }
    }

#License

Faber is released under [MIT](http://opensource.org/licenses/MIT).






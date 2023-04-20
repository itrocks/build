PHP class builder 
-----------------

This librairy allows you to statically extend your PHP classes: add interface and traits or replace
them with a child class when they are instantiated. This is not simply mocking, its aim is to allow
you to create highly configurable applications, with class features activated or not, depending on
the deploy environment.

It is designed to be simple to apply to any PHP project, and fast to execute.

With it, you don't have to prepare your code for dependency injection. The goal is to keep your
coded applications as simple as possible, using any way php works, without embedding the complexity
of dependency injection design pattern implementations.

Limitations: this is about extending base class capabilities, not totally make dependency
implementations uncoupled. This tool is here to apply the Open/closed principle
(the O from [SOLID](https://en.wikipedia.org/wiki/SOLID))
to projects that need to be modular and deployed with or without some modules.

Pre-requisites
--------------

- This works with PHP 8.2 only. I wanted to make full use of the current PHP version features. \
  To install php 8.2 on a Debian/Ubuntu/Mint system:
  https://php.watch/articles/install-php82-ubuntu-debian.

Development progress
--------------------

This library is currently on a development phase. It will follow the it.rocks
[Builder](https://github.com/itrocks/framework/tree/master/builder) module specifications to
generalize the way it works:
- to be used in any PHP project, even if non-it.rocks,
- to be applied to vendor libraries.

Nothing work today, but it is coming soon. 

How it works
------------

- You write the class building table into a configuration array :
  class => child class or class => added interfaces and traits.
- Your traits can embed rules about inheritability : if you need some traits to be on another class
  level than others, because they extend their capabilities, you will use the #Extend attribute to
  describe this hierarchy rule.
- Your project must have an "update" phase: a procedure that will run everytime a php file is
  modified.
- During this phase, the builder uses itrocks/depend to scan all class dependencies.
- Using the class replacement table, the builder prepares the dynamic implementation of built
  replacement classes.
- Everywhere your application or vendors use a class, the class name is replaced by the built class
  reference. The modification is directly done into the tokens tree and the files marked for writting.
- The modified PHP files are written the cache directory.
- To use the modified PHP files instead of the original, a PHP file input filter is applied.
  It allows you to debug your applications and get error messages refering to the original PHP file,
  but the actual executed code comes from the cached modified PHP scripts.

This make it very fast on execution time : when you run your scripts, no more code than necessary
is executed to instantiate your built classes instead of the original ones. You can use PHP oriented
object programming the simpliest way : without interface when you do not need it, and you can inject
dependencies into any exiting class or trait using this library, without the original code being
prepared for this.

Example
-------

Considering this class:
```php
class Car
{
  int $engine_power = 100;

  function getMaxSpeed() : int
  {
    return round($this->engine_power * 1.5);
  }
}
```

You created another module, optional on your project deployment phase, to manage the weight of the
cas and its effect on its max speed:

```php
#[Extend(Car::class)]
trait Has_Weight
{
  int $weight = 500;

  function getMaxSpeed() : int
  {
    return round(parent::getMaxSpeed() / ($this->weight / 400));
  }
}
```

One of your deployment configuration files describes the way you "improve" your car, only for your
final applications that need this added code:

```php
$config['build'] = [
  Car::class => [Has_Weight::class]
];
```

Then, when your source code simply intantiate a car and gets its maximum speed information, you
don't have to change anything: itrocks/build will make the replacement into an internal cache file
and instantiate the new replacement class:

```php
$car = new Car;
echo "My car maximum speed is " . $car->getMaxSpeed() . "\n";
```

The code that will be really executed will be the creation of a new replacement class, and the
replacement of all references to the original class name by the new one:

The internally added code:
```php
class B\Car { use Has_Weight; }
```

The internally replaced code for your program, which will be executed:
```php
$car = new B\Car;
echo "My Car maximum speed is " . $car->getMaxSpeed() . "\n";
```

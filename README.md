# Lyra CMD
The command line interpretation of the Lyra, is done with the help of this library. The commandline options will
be parsed with the help internal regex. This command line library can be used in different configurations, to suite
the needs of the application. This library is based on the Nate Good's [Commando](https://github.com/nategood/commando) with more functionality like optional parameters, multiple parameters combination and command Dispatcher.

## Requirements
Lyra CMD needs `php-mbstring` to be installed.

## Installation
To Install CMD there are several options available:

This library is available in [packagist](https://packagist.org). So you need to only add this as requirement.

```lang=bash
composer require rzuw/lyra-cmd
```
you can also clone the library and add it using `autoload` or using the composer to add as filesystem component.

```lang=json
{
    "repositories": [
        {
            "type": "path",
            "url": "/path-to-git-clone"
        }
    ],
    "require": {
        "rzuw/lyra-cmd": "*"
    }
}
```

You can also add the repository and then require the `rzuw/lyra-cmd`.

```lang=json
{
    "require": {
        "rzuw/lyra-cmd": "*"
    },
    "repositories": [
        {
            "type": "vcs",
            "url":  "ssh://git@github.com/uniwue-rz/lyra-cmd.git"
        }
    ]
}
```

## Usage
To use the commandline just create a new instance.

```lang=php

use De\Uniwue\RZ\Lyra\CMD\Command;
$command = new Command();
```
Add your different options with parameters. You can also link different options together.
```lang=php
$command = new Command();
// Adds the command --bar with shortcut -b with boolean option.
// This means the command --bar will be on or off when called.
// The default value is set to false
$command->option("b")->aka("bar")->describedAs("The bar command")->boolean()->default(false);
// Adds the command --foo with shortcut with boolean option.
// This command will run the bar in the given class and needs always --bar to be set (on) and a username to be set.
// The command has also an optional part which can be there or not but the command will run nevertheless.
$command->option("f")->aka("foo")->describedAs("The foo command")->boolean()->run("bar")->needs(array("b","u"))
        ->optionals(array("n"));
$command->option("n")->aka("name")->describedAs("The name of the user")->default("");
// This will a required placeholder for the username option of the foo command,which will be filled by "" default.
$command->option("u")->aka("username")->describedAs("Username to foo")->default("");
```
Then call the commandline runner with the your class:
```lang=php
 // $class is the class that has the bar command. This will run the bar command with the optional 
 // parameter of "n" and needed parameters of "u" and "b".
$command->dispatchCommands($class);
```

# Tests and Development
The `phpunit` configurations are done for this component, so you can test most of the code. You can also add your changes to the code.

## License
SEE LICENSE and CREDIT files


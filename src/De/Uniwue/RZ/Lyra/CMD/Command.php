<?php
/**
 * This class is part of the cmd package of the Lyra. The code is free fork of the https://github.com/nategood/commando
 * by Nate Good. It supports all the functions that commando offers in addition to that, it allows to run methods in a given class. Also
 * optional and mandatory command parameters are now allowed.
 * 
 * @author Nate Good <me@nategood.com>
 * @author Pouyan Azari <pouyan.azari@uni-wuerzburg.de>
 * 
 * @license MIT
 */
namespace De\Uniwue\RZ\Lyra\CMD;

use De\Uniwue\RZ\Lyra\CMD\Util\Terminal;
use De\Uniwue\RZ\Lyra\Exceptions\CommandLineException;
use Colors\Color;

class Command implements \ArrayAccess, \Iterator
{
    const OPTION_TYPE_ARGUMENT = 1; // e.g. foo
    const OPTION_TYPE_SHORT = 2; // e.g. -u
    const OPTION_TYPE_VERBOSE = 4; // e.g. --username
    const OPTION_TYPE_VERBOSE_EQUALS = 5; // e.g. --username=

    private $current_option = null;
    private $name = null;
    private $options = array();
    private $arguments = array();
    private $flags = array();
    private $nameless_option_counter = 0;
    private $tokens = array();
    private $help = null;
    private $parsed = false;
    private $useDefaultHelp = true;
    private $trap_errors = true;
    private $beep_on_error = true;
    private $position = 0;
    private $sorted_keys = array();

    /**
     * @var array Valid "option" options, mapped to their aliases
     */
    public static $methods = array(

        'option' => 'option',
        'o' => 'option',

        'run' => 'run',
        'rn' => 'run',

        'flag' => 'flag',
        'argument' => 'argument',

        'boolean' => 'boolean',
        'bool' => 'boolean',
        'b' => 'boolean',
        // mustBeBoolean

        'require' => 'requires',
        'required' => 'requires',
        'r' => 'requires',

        'alias' => 'alias',
        'aka' => 'alias',
        'a' => 'alias',

        'title' => 'title',
        'referToAs' => 'title',
        'referredToAs' => 'title',

        'describe' => 'describes',
        'd' => 'describes',
        'describeAs' => 'describes',
        'description' => 'describes',
        'describedAs' => 'describes',

        'map' => 'map',
        'mapTo' => 'map',
        'cast' => 'map',
        'castWith' => 'map',

        'must' => 'must',
        // mustBeNumeric
        // mustBeInt
        // mustBeFloat
        'needs' => 'needs',

        'optionals' => 'optionals',

        'file' => 'file',
        'expectsFile' => 'file',
        // 'expectsFileGlob' => 'file',
        // 'mustBeAFile' => 'file',

        'default' => 'defaults',
        'defaultsTo' => 'defaults',
    );

    /**
     * @param array|null $tokens
     *                           Beware if tokens are manually supplied that the first element of the array
     *                           is array_shifted off the array and more or less discarded.
     *                           This is to substitute for the "executed filename" arg which is present as the
     *                           first element in the usually used $_SERVER['argv'] array.
     */
    public function __construct(array $tokens = null, $isTest = false)
    {
        if (empty($tokens)) {
            $tokens = $_SERVER['argv'];
        }
        $this->isTest = $isTest;
        $this->setTokens($tokens);
    }

    public function __destruct()
    {
        if (!$this->parsed) {
            $this->parse();
        }
    }

    /**
     * Factory style reads a little nicer.
     *
     * @param array $tokens defaults to $argv
     *
     * @return Command
     */
    public static function define($tokens = null)
    {
        return new self($tokens);
    }

    /**
     * This is the meat of Command.  Any time we are operating on
     * an individual option for command (e.g. $cmd->option()->require()...)
     * it relies on this magic method.  It allows us to handle some logic
     * that is applicable across the board and also allows easy aliasing of
     * methods (e.g. "o" for "option")... since it is a CLI library, such
     * minified aliases would only be fitting :-).
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return Command
     *
     * @throws \CommandLineException
     */
    public function __call($name, $arguments)
    {
        $this->internalMethodExists($name);
        // use the fully quantified name, e.g. "option" when "o"
        $method = self::$methods[$name];

        // set the option we'll be acting on
        $this->isMethodInternal($method);

        array_unshift($arguments, $this->current_option);

        $option = call_user_func_array(array($this, $method), $arguments);

        return $this;
    }

    /**
     * Checks if the list of current options is empty.
     *
     * @return bool
     */
    private function isCurrentOptionEmpty()
    {
        return empty($this->current_option);
    }

    /**
     * Check if the method is internal method.
     *
     * @throws CommandLineException
     */
    private function isMethodInternal($method)
    {
        if (!in_array($method, array('option', 'argument', 'flag')) && $this->isCurrentOptionEmpty()) {
            throw new CommandLineException(
                sprintf('Invalid Option Chain: Attempting to call %s before an "option" declaration', $method)
            );
        }
    }

    /**
     * Checks if the internal method exists.
     * throws CommandLineException.
     */
    private function internalMethodExists($method)
    {
        if (empty(self::$methods[$method])) {
            throw new CommandLineException(sprintf('Unknown function, %s, called', $method));
        }
    }

    /**
     * @param Option|null $option
     * @param string|int  $name
     *
     * @return Option
     */
    private function option($option, $name = null)
    {
        // Is this a previously declared option?
        if (isset($name) && !empty($this->options[$name])) {
            $this->current_option = $this->getOption($name);
        } else {
            if (!isset($name)) {
                $name = $this->nameless_option_counter++;
            }
            $this->current_option = $this->options[$name] = new Option($name);
        }

        return $this->current_option;
    }

    /**
     * @param Option|null $option
     * @param string      $name
     *
     * @return Option
     *
     * @throws CommandLineException
     *
     * Like _option but only for named flags
     */
    private function flag($option, $name)
    {
        if (isset($name) && is_numeric($name)) {
            throw new CommandLineException('Attempted to reference flag with a numeric index');
        }

        return $this->option($option, $name);
    }

    /**
     * @param Option|null $option
     * @param int         $index  [optional] only used when referencing an existing option
     *
     * @return Option
     *
     * @throws CommandLineException
     *
     * Like _option but only for anonymous arguments
     */
    private function argument($option, $index = null)
    {
        if (isset($index) && !is_numeric($index)) {
            throw new CommandLineException('Attempted to reference argument with a string name');
        }

        return $this->option($option, $index);
    }

    /**
     * @param Option $option
     * @param bool   $boolean [optional]
     *
     * @return Option
     */
    private function boolean(Option $option, $boolean = true)
    {
        return $option->setBoolean($boolean);
    }

    /**
     * @param Option $option
     * @param bool   $require [optional]
     *
     * @return Option
     */
    private function requires(Option $option, $require = true)
    {
        return $option->setRequired($require);
    }

    /**
     * Set a requirement on an option.
     *
     * @param De\Uniwue\RZ\Lyra\CMD\Option $option Current option
     * @param string           $name   Name of option
     *
     * @return De\Uniwue\RZ\Lyra\CMD\Option instance
     */
    private function needs(Option $option, $name)
    {
        return $option->setNeeds($name);
    }

    /**
    * Set an optional parameter for an option
    *
     * @param De\Uniwue\RZ\Lyra\CMD\Option $option Current option
     * @param string           $name   Name of option
     *
     * @return De\Uniwue\RZ\Lyra\CMD\Option instance
    */
    private function optionals(Option $option, $name){
        return $option->setOptionals($name);
    }

    /**
     * @param Option $option
     * @param string $alias
     *
     * @return Option
     */
    private function alias(Option $option, $alias)
    {
        $this->options[$alias] = $this->current_option;

        return $option->addAlias($alias);
    }

    /**
     * @param Option $option
     * @param string $description
     *
     * @return Option
     */
    private function describes(Option $option, $description)
    {
        return $option->setDescription($description);
    }

    /**
     * Runs the given function if true. valid for boolean functions.
     *
     * @param Option $option
     * @param string $run
     */
    private function run(Option $option, $run)
    {
        return $option->setRun($run);
    }

    /**
     * @param Option $option
     * @param string $title
     *
     * @return Option
     */
    private function title(Option $option, $title)
    {
        return $option->setTitle($title);
    }

    /**
     * @param Option   $option
     * @param \Closure $callback (string $value) -> boolean
     *
     * @return Option
     */
    private function must(Option $option, \Closure $callback)
    {
        return $option->setRule($callback);
    }

    /**
     * @param Option   $option
     * @param \Closure $callback
     *
     * @return Option
     */
    private function map(Option $option, \Closure $callback)
    {
        return $option->setMap($callback);
    }

    /**
     * @return Option
     *
     * @param $option Option
     * @param mixed $value
     */
    private function defaults(Option $option, $value)
    {
        return $option->setDefault($value);
    }

    private function file(Option $option, $require_exists = true, $allow_globbing = false)
    {
        return $option->setFileRequirements($require_exists, $allow_globbing);
    }

    public function useDefaultHelp($help = true)
    {
        $this->useDefaultHelp = $help;
    }

    /**
     * Rare that you would need to use this other than for testing,
     * allows defining the cli tokens, instead of using $argv.
     *
     * @param array $cli_tokens
     *
     * @return Command
     */
    public function setTokens(array $cli_tokens)
    {
        // todo also slice on "=" or other delimiters
        $this->tokens = $cli_tokens;

        return $this;
    }

    /**
     * @throws CommandLineException
     */
    private function parseIfNotParsed()
    {
        if ($this->isParsed()) {
            return;
        }
        $this->parse();
    }

    /**
     * Extracts the value of an equals option
     * E.g. The argument --option=value given to this method will return "value".
     *
     * @param type $cli_argument
     *
     * @return String or NULL
     *
     * @throws CommandLineException
     */
    private function extractEqualsOptionValue($cli_argument)
    {
        if (strpos($cli_argument, '=') === false) {
            throw new CommandLineException('Expected an equals character');
        }

        $value = trim(substr(strstr($cli_argument, '='), 1));
        if ($value != '') {
            return $value;
        }

        return;
    }

    /**
     * @throws CommandLineException
     */
    public function parse()
    {
        $this->parsed = true;
        // Get all the tokens
        $tokens = $this->tokens;
        // Set the first element of the tokens array to internal name
        $this->name = array_shift($tokens);
        $keyValues = array();
        $count = 0;

        try {
            while(!empty($tokens)){
                $token = array_shift($tokens);
                list($argName, $argType) = $this->parseOption($token);
                if ($this->isOptionArgument($argType)) {
                    list($count, $keyValues) = $this->handleArgument($argName, $count, $keyValues);
                }
                else {
                    $this->handleHelp($argName);
                    $option = $this->getOption($argName);
                    list($tokens, $keyValues) = $this->handleOption($option, $argName, $argType, $token, $tokens, $keyValues);
                }
            }
            $this->setOptionValues($keyValues);
            $this->checkForRequirementArguments();
            $this->checkForRequirementOptions($keyValues);
            $this->setOptionsFlags();
            $this->setSortedKeys();
        } catch (CommandLineException $e) {
            $this->error($e);
        }
    }

    /**
    * Sets the sorted keys
    */
    public function SetSortedKeys(){
            $this->sorted_keys = array_keys($this->options);
            natsort($this->sorted_keys);
    }

    /**
    * Set the flags for the options.
    */
    public function setOptionsFlags(){
        foreach ($this->options as $k => $v) {
            if (is_numeric($k)) {
                $this->arguments[$k] = $v;
            } else {
                $this->flags[$k] = $v;
            }
        }
    }

    /**
    * Checks if the command given meets the give requirements
    *
    * @throws CommandLineException
    */
    public function checkForRequirementArguments(){
        foreach ($this->options as $option) {
            if (is_null($option->getValue()) && $option->isRequired()) {
                    throw new CommandLineException(
                        sprintf('Required %s %s must be specified', $option->getType() & Option::TYPE_NAMED ?
                            'option' : 'argument', $option->getName())
                    );
                }
        }
    }

    /**
    * Checks the $keyValues for the required options.
    *
    * @param array $keyValues The key value array
    *
    * @throws InvalidArgumentException
    */
    public function checkForRequirementOptions($keyValues){
        foreach ($keyValues as $key => $value) {
            $option = $this->getOption($key);
            if (!is_null($option->getValue()) || $option->isRequired()) {
                $needs = $option->hasNeeds($this->options);
                if ($needs !== true) {
                    throw new \InvalidArgumentException(
                        'Option "'.$option->getName().'" does not have required option(s): '.implode(', ', $needs)
                    );
                }
            }
        }
    }

    /**
    * Checks if an element is argument.
    *
    * @param int $argType The type of argument
    *
    * @return bool
    */
    public function isOptionArgument($argType){
        return $argType === self::OPTION_TYPE_ARGUMENT;
    }

    /**
    * Set the option values from the keyValues array
    *
    * @param array $keyValues The key values array.
    */
    public function setOptionValues($keyValues){
        foreach ($keyValues as $key => $value) {
            $this->getOption($key)->setValue($value);
        }
    }

    /**
    * Handles the option arguments.
    *
    * @param Option $option     The created option
    * @param string $argName    The name of argument used.
    * @param int    $argType    The type of argument used.
    * @param string $token      The token which is used.
    * @param array  $tokens     The token parameters.
    * @param array  $keyValues  The processed values
    *
    * @return array
    *
    * @throws CommandLineException
    */
    public function handleOption($option, $argName, $argType, $token, $tokens, $keyValues){
        if ($option->isBoolean()) {
            $keyValues[$argName] = !$option->getDefault();
        }
        else {
            if ($argType === self::OPTION_TYPE_VERBOSE_EQUALS) {
                $argValue = $this->extractEqualsOptionValue($token);
            }else {
                $argValue = array_shift($tokens);
            }
            list($val, $valType) = $this->parseOption($argValue);
        
            if ($valType === self::OPTION_TYPE_ARGUMENT) {
                $keyValues[$argName] = $val;
            }
            else {
            throw new CommandLineException(
                sprintf('Unable to parse option %s: Expected an argument', $argValue));            
            }
        }
        return array($tokens, $keyValues);
    }

    /**
    * Checks it the element is a help argument
    *
    * @param string $argName The name of the given argument
    *
    * @return bool
    */
    public function isHelp($argName){
        return $this->useDefaultHelp === true && $argName === 'help';
    }

    /**
    * Handles the help
    *
    * @param string $argName The name of the given argument
    */
    public function handleHelp($argName){
        if($this->isHelp($argName)){
            $this->printHelp();
            if (!$this->isTest) {
                exit(0); //@codeCoverageIgnore
            }
        }
    }

    /**
    * Handles the terms that have argument type.
    * @param string $argName    The name of the argument to be handled
    * @param int    $count          Where the argument is in the parsed cmd string
    * @param array  $keyValues      The values for the keys
    * 
    * @return array
    */
    public function handleArgument($argName, $count, $keyValues){
        $keyValues[$count] = $argName;
        if (!$this->hasOption($count)) {
                $this->options[$count] = new Option($count);
        }
        $count = $count + 1;

        return array($count, $keyValues);
    }

    /**
     * Prints out the errors.
     *
     * @param CommandLineException $e The exception that should be thrown.
     *
     * @return int
     */
    public function error(CommandLineException $e)
    {
        if ($this->beep_on_error === true) {
            Terminal::beep();
        }
        if ($this->trap_errors === false) {
            throw $e;
        }
        $color = new Color();
        $error = sprintf('ERROR: %s ', $e->getMessage());
        echo $color($error)->bg('red')->bold()->white().PHP_EOL;
        if ($this->isTest) {
            throw $e;
        } else {
            exit(1); //@codeCoverageIgnore
        }
    }

    /**
     * Has this Command instance parsed its arguments?
     *
     * @return bool
     */
    public function isParsed()
    {
        return $this->parsed;
    }

    /**
     * @param string $token
     *
     * @return array [option name/value, OPTION_TYPE_*]
     *
     * @throws CommandLineException
     */
    private function parseOption($token)
    {
        $matches = array();

        if (substr($token, 0, 1) === '-' &&
            !preg_match('/(?P<hyphen>\-{1,2})(?P<name>[a-z][a-z0-9_-]*(?P<equals>\=){0,1})/i', $token, $matches)) {
            throw new CommandLineException(sprintf('Unable to parse option %s: Invalid syntax', $token));
        }

        $name = $this->getValue($matches, "name");
        $hyphenCount = $this->getValue($matches, "hyphen", "strlen", 0);
        $hasEqual = (isset($matches['equals']));
        $type = $this->getType($hyphenCount, $hasEqual);

        // Replace the = in the token with ''
        if($type === self::OPTION_TYPE_VERBOSE_EQUALS){
            $name = str_replace("=", '', $name);
        }
        // When the Hyphen Count is not zero change the token to name.
        if($hyphenCount !== 0){
            $token = $name;
        }

        return array($token,  $type);
    }

    /**
    * Checks if the given key is set and applies the given method on the existing
    * or returns the default value.
    *
    * @param array  $toSearch   The array that should be searched.
    * @param string $key        The key to the given array
    * @param string $callback   The callback function that should be done on the object.
    * @param mix    $default    The default value to be returned if the key is not set.
    *
    * @return mix
    */
    public function getValue($toSearch, $key, $callback = '', $default = ''){
        if(isset($toSearch[$key])){
            if($callback !== ""){
                return call_user_func($callback, $toSearch[$key]);
            }
            return $toSearch[$key];
        }
        return $default;
    }



    /**
    * Returns the type of the command line parameters from the hyphen counts.
    *
    * @param int  $hyphenCount The number of the hyphens.
    * @param bool $hasEqual    If the type has "="
    *
    * @return OPTION_TYPE
    */
    public function getType($hyphenCount, $hasEqual){
        switch ($hyphenCount) {
            case 1:
                return self::OPTION_TYPE_SHORT;    
            case 2:
                if ($hasEqual) {
                    return self::OPTION_TYPE_VERBOSE_EQUALS;
                } else {
                    return self::OPTION_TYPE_VERBOSE;
                }
            default:
                return self::OPTION_TYPE_ARGUMENT;
        }
    }

    /**
     * @param string $option
     *
     * @return Option
     *
     * @throws CommandLineException if $option does not exist
     */
    public function getOption($option)
    {
        if (!$this->hasOption($option)) {
            throw new CommandLineException(sprintf('Unknown option, %s, specified', $option));
        }

        return $this->options[$option];
    }

    /**
     * @return array of argument `Option` only
     */
    public function getArguments()
    {
        $this->parseIfNotParsed();

        return $this->arguments;
    }

    /**
     * @return array of flag `Option` only
     */
    public function getFlags()
    {
        $this->parseIfNotParsed();

        return $this->flags;
    }

    /**
     * @return array of argument values only
     *
     * If your command was `php filename -f flagvalue argument1 argument2`
     * `getArguments` would return array("argument1", "argument2");
     */
    public function getArgumentValues()
    {
        $this->parseIfNotParsed();

        return array_map(function (Option $argument) {
            return $argument->getValue();
        }, $this->arguments);
    }

    /**
     * @return array of flag values only
     *
     * If your command was `php filename -f flagvalue argument1 argument2`
     * `getFlags` would return array("-f" => "flagvalue");
     */
    public function getFlagValues()
    {
        $this->parseIfNotParsed();

        return array_map(function (Option $flag) {
            return $flag->getValue();
        }, $this->dedupeFlags());
    }

    /**
     * @return array of deduped flag Options.  Needed because of
     *               how the flags are mapped internally to make alias lookup
     *               simpler/faster.
     */
    private function dedupeFlags()
    {
        $seen = array();
        foreach ($this->flags as $flag) {
            if (empty($flags[$flag->getName()])) {
                $seen[$flag->getName()] = $flag;
            }
        }

        return $seen;
    }

    /**
     * @param string $option name (named option) or index (anonymous option)
     *
     * @return bool
     */
    public function hasOption($option)
    {
        return !empty($this->options[$option]);
    }

    /**
     * @return string dump values
     */
    public function __toString()
    {
        return serialize($this->getArguments());
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return count($this->options);
    }

    /**
     * @param string $help
     *
     * @return Command
     */
    public function setHelp($help)
    {
        $this->help = $help;

        return $this;
    }

    /**
     * @param bool $trap when true, exceptions will be caught by Commando and
     *                   printed cleanly to standard error.
     *
     * @return Command
     */
    public function trapErrors($trap = true)
    {
        $this->trap_errors = $trap;

        return $this;
    }

    /**
     * @return Command
     */
    public function doNotTrapErrors()
    {
        return $this->trapErrors(false);
    }

    /**
     * Terminal beep on error.
     *
     * @param bool $beep
     *
     * @return Command
     */
    public function beepOnError($beep = true)
    {
        $this->beep_on_error = $beep;

        return $this;
    }

    /**
     * Returns the help binary string which can be printed to the terminal.
     *
     * @return string help docs
     */
    public function getHelp()
    {
        $this->attachHelp();

        if (empty($this->name) && isset($this->tokens[0])) {
            $this->name = $this->tokens[0];
        }

        $color = new Color();

        $help = '';

        $help .= $color(Terminal::header(' '.$this->name))
            ->white()->bg('green')->bold().PHP_EOL;

        $help = $this->appendToString($help, $this->wrapToTerminal($this->help), true, true);
        $help = $this->appendToString($help, '', true);
        $keys = $this->getSortedOptionKeys();
        $help = $this->addOptionHelp($help, $keys);

        return $help;
    }

    /**
     * Wraps the given string to the Terminal mode.
     *
     * @param string $text The text that should be wrapped.
     *
     * @return string
     */
    public function wrapToTerminal($text)
    {
        if (!empty($text)) {
            return Terminal::wrap($text);
        }

        return '';
    }

    /**
     * Get the sorted options key list.
     *
     * @return array
     */
    public function getSortedOptionKeys()
    {
        $keys = array_keys($this->options);
        natsort($keys);

        return $keys;
    }

    /**
     * Adds the helps from the option to the command help.
     *
     * @return string
     */
    public function addOptionHelp($help, $options)
    {
        $seen = array();
        foreach ($options as $key) {
            $option = $this->getOption($key);
            if (!in_array($option, $seen)) {
                $help = $this->appendToString($help, $option->getHelp(), true);
                array_push($seen, $option);
            }
        }

        return $help;
    }

    /**
     * Adds the string to the end of another string, with the option of EOL.
     *
     * @param string $source               The string new string is appended to.
     * @param string $toBeAppended         The string that should be appended to the old string.
     * @param bool   $shouldEndLine        Flag to use the EOL at the end of newly created string.
     * @param bool   $shouldEndLineAtStart Flag to use the EOL at the start of newly created string.
     *
     * @return string
     */
    public function appendToString($source, $toBeAppended, $shouldEndLineAtEnd = false, $shouldEndLineAtStart = false)
    {
        $result = $source.$toBeAppended;
        if ($shouldEndLineAtEnd) {
            $result = $result.PHP_EOL;
        }
        if ($shouldEndLineAtStart) {
            $result = PHP_EOL.$result;
        }

        return $result;
    }

    /**
     * Prints the help to the terminal.
     */
    public function printHelp()
    {
        echo $this->getHelp();
    }

    private function attachHelp()
    {
        // Add in a default help method
        $this->o('help')
             ->describe('Show the help page for this command.')
             ->b();
    }

    /**
     * @param string $offset
     *
     * @see \ArrayAccess
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->options[$offset]);
    }

    /**
     * @param string $offset
     *
     * @see \ArrayAccess
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        // Support implicit/lazy parsing
        $this->parseIfNotParsed();
        if (!isset($this->options[$offset])) {
            return; // follows normal php convention
        }

        return $this->options[$offset]->getValue();
    }

    /**
     * @param string $offset
     * @param string $value
     *
     * @throws CommandLineException
     *
     * @see \ArrayAccess
     */
    public function offsetSet($offset, $value)
    {
        throw new CommandLineException('Setting an option value via array syntax is not permitted');
    }

    /**
     * @param string $offset
     *
     * @see \ArrayAccess
     */
    public function offsetUnset($offset)
    {
        $this->options[$offset]->setValue(null);
    }

    /**
     * @see \Iterator
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * @return mixed value of current option
     *
     * @see \Iterator
     */
    public function current()
    {
        if ($this->valid()) {
            return $this->options[$this->sorted_keys[$this->position]]->getValue();
        }

        return;
    }

    /**
     * @return int
     *
     * @see \Iterator
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * @see \Iterator
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * @return bool
     *
     * @see \Iterator
     */
    public function valid()
    {
        return isset($this->sorted_keys[$this->position]);
    }

    /**
     * Returns the run commands for a given.
     *
     * @return array
     */
    public function getRuns()
    {
        $this->parseIfNotParsed();

        return array_map(function (Option $argument) {
                    return $argument->getRun();
        }, $this->options);
    }

    /**
     * Returns the needs for the commands.
     *
     * @return array
     */
    public function getNeeds()
    {
        return array_map(function (Option $argument) {
                          return $argument->getNeeds();
        }, $this->options);
    }

    /**
    * Returns the list of parameters that are optional
    *
    * @return array
    */
    public function getOptionals(){
        return array_map(function (Option $argument){
            return $argument->getOptionals();
        }, $this->options);
    }

    /**
    * Dispatches the commands to the given class.
    *
    * @param mix $class The class that contains the methods that should be running
    *
    * @return mix
    */
    public function dispatchCommands($class){
        // Get the parsed parameters
        $cmdParams = $this->getFlagValues();
        $runs = $this->getRuns();
        $needs = $this->getNeeds();
        $optionals = $this->getOptionals();
        // Create an empty placeholder
        $commandToRun = $this->getRunCommands($cmdParams, $runs, $needs, $optionals);
        return $this->runCommand($class, $commandToRun);
    }

    /**
    * Runs the given method in the given class.
    * 
    * @param mix    $class     The class instance, it's method that should run
    * @param string $method    The method that should run
    *
    * @return mix
    */
    public function runCommand($class, $commandToRun){
        if (isset($commandToRun['run'])) {
            return call_user_func(array($class, $commandToRun['run']), $commandToRun['params']);
        } else {
            $this->writeInColor('The command not found, please read the help.', 'red'); //@codeCoverageIgnore
            $this->printHelp();//@codeCoverageIgnore
        }       //@codeCoverageIgnore
    }//@codeCoverageIgnore

   /**
     * Writes the given string in the given color in the terminal.
     *
     * @param string $toWrite The string that should be written.
     * @param string $bgColor The background color of the text.
     * @param string $fgColor The color of the text that should be written
     */
    public function writeInColor($toWrite, $bgColor, $fgColor = 'white')
    {
        $color = new \Colors\Color(); //@codeCoverageIgnore
        echo $color($toWrite)->bg($bgColor)->bold()->$fgColor().PHP_EOL;//@codeCoverageIgnore
    } //@codeCoverageIgnore

    /**
     * Checks if the command is in the rus list.
     *
     * @param array $cmdParams The command-line parameters from the user.
     * @param array $runs      The commands that can be ran.
     * @param array $needs     The list of elements the command needs.
     * @param array $optionals The list of optionals
     *
     * @return array
     */
    public function getRunCommands($cmdParams, $runs, $needs, $optionals)
    {
        // If you have multiple runs options the first in the list will be chosen.
        $commandToRun = array();
        $selected = false;
        foreach ($cmdParams as $c => $v) {
            if (isset($runs[$c]) && $runs[$c] !== '' && $v && $selected === false) {
                $commandToRun['val'] = $v;
                $commandToRun['run'] = $runs[$c];
                $commandToRun['params'] = $this->getParams($c, $cmdParams, $needs, $optionals);
                $selected = true;
            }
        }
        return $commandToRun;
    }

    /**
     * Get needs values.
     *
     * @param string $commandToRun   The name of the command that should be running.
     * @param array  $cmdParams      The array containing all the command line values.
     * @param array  $needs          The array containing the needs keys.
     * @param array  $optionals      The list of optional methods.
     *
     * @return array
     */
    public function getParams($commandToRun, $cmdParams, $needs, $optionals)
    {
        $result = array();

        if (isset($needs[$commandToRun])) {
            $result = array_intersect_key($cmdParams, array_flip($needs[$commandToRun]));
        }
        if(isset($optionals[$commandToRun])){
            $optionalMethods = array_intersect_key($cmdParams, array_flip($optionals[$commandToRun]));
            foreach($optionalMethods as $k => $v){
                $result[$k] = $v;
            }
        }
        return $result;
    }
}
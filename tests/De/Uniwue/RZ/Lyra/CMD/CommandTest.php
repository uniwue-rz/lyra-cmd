<?php

namespace De\Uniwue\RZ\Lyra\CMD;

/*
* TestCase for the Command.
*/

use De\Uniwue\RZ\Lyra\Exceptions\CommandLineException;

class CommandTest extends \PHPUnit_Framework_TestCase
{
    protected $config;

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    protected function setUp()
    {
        $this->command = new Command();
        $this->command->setHelp('Help');
        $this->command->option('t')->aka('test-report')
            ->describedAs('test-desc')
            ->boolean()
            ->default(false)
            ->needs(array('a'))
            ->run('testCommand');
    }

    protected function destroy()
    {
    }

    /**
     * Test the test constructor.
     */
    public function testConstructor()
    {
        $this->command = new Command();
    }

    /**
     * Tests the getRuns command.
     **/
    public function testGetRuns()
    {
        $this->assertEquals($this->command->getRuns(), array('t' => 'testCommand', 'test-report' => 'testCommand'));
    }

    /**
     * Tests the getNeeds function.
     */
    public function testGetNeeds()
    {
        $this->assertEquals($this->command->getNeeds(), array('t' => array('a'), 'test-report' => array('a')));
    }

    /**
     * Tests the the getOption command.
     */
    public function testGetOption()
    {
        $toTestOption = new Option('t');
        $toTestOption->setValue(false);
        $toTestOption->addAlias('test-report');
        $toTestOption->setDescription('test-desc');
        $toTestOption->setNeeds(array('a'));
        $toTestOption->setBoolean(true);
        $toTestOption->setRun('testCommand');
        $this->assertEquals($this->command->getOption('test-report'), $toTestOption);
    }

    /**
     * Tests the help command.
     **/
    public function testGetHelp()
    {
        $this->assertEquals(gettype($this->command->getHelp()), 'string');
        $this->command->printHelp();
    }

    public function testWrapToTerminal()
    {
        $text = '';
        $this->assertEquals($this->command->wrapToTerminal($text), '');
    }

    public function testOffsetExits()
    {
        $this->command->option('hu');
        $this->assertTrue($this->command->offsetExists('hu'));
    }

    public function testOffsetGet()
    {
        $this->command->option('Hi')->boolean()->default(true);
        $this->assertEquals($this->command->offsetGet('Hi'), true);
        $this->assertEquals($this->command->offsetGet('Hu'), null);
    }

    /**
     * Setting option value via array is not permitted.
     *
     * @expectedException \Exception
     */
    public function testOffsetSet()
    {
        $this->command->offsetSet('Hi', false);
    }

    public function testOffsetUnset()
    {
        $this->command->option('Hi')->boolean()->default(true);
        $this->command->offsetUnset('Hi');
        $this->assertEquals($this->command->offsetGet('Hi'), null);
    }

    public function testTitle()
    {
        $this->command->option('Hi')->title('TT');
        $this->assertEquals($this->command->getOption('Hi')->getTitle(), 'TT');
    }

    public function testTrapErrors()
    {
        $this->command->trapErrors(false);
        $this->command->option('A')->boolean()->option('A');
    }

    public function testCommandoAnon()
    {
        $tokens = array('filename', 'arg1', 'arg2', 'arg3');
        $cmd = new Command($tokens);
        $this->assertEquals($tokens[1], $cmd[0]);
    }

    /**
     * Test flag method for a given exception.
     *
     * @expectedException \Exception
     */
    public function testFlagException()
    {
        $toTestOption = new Option('t');
        $toTestOption->setValue(false);
        $toTestOption->addAlias('test-report');
        $toTestOption->setDescription('test-desc');
        $toTestOption->setNeeds(array('a'));
        $toTestOption->setBoolean(true);
        $toTestOption->setRun('testCommand');
        $this->invokeMethod($this->command, 'flag', array($toTestOption, '12'));
    }

    /**
     * Test the to string function.
     */
    public function testToString()
    {
        $command = new Command();
        ob_start();
        echo $command;
        $output = ob_get_clean();
        $this->assertEquals($output, 'a:0:{}');
    }

    /**
     * Tests the getFlags command!
     */
    public function testGetFalgs()
    {
        $this->command = new Command();
        $this->command->setHelp('Help');
        $this->command->option('t')->aka('test-report');
        $this->assertEquals(sizeof($this->command->getFlags()), 2);
    }

    /**
     * Tests the getOptions Exception.
     *
     * @expectedException \Exception
     */
    public function testGetOptionsException()
    {
        $this->command = new Command();
        $this->command->setHelp('Help');
        $this->command->getOption('HHHHHH');
    }
    /**
     * Tests call function exceptions.
     *
     * @expectedException \Exception
     */
    public function testCallExceptionFirst()
    {
        $this->command->hello();
    }

    /**
     * Tests call function exceptions.
     *
     * @expectedException \Exception
     */
    public function testCallExceptionSecond()
    {
        $command = new Command();
        $command->needs();
    }

    /**
     * Tests the extract equal value function.
     *
     * @expectedException \Exception
     */
    public function testExtractEqualsOptionValue()
    {
        $value = 'hello=world';
        $this->assertEquals($this->invokeMethod($this->command, 'extractEqualsOptionValue', array($value)), 'world');
        $value = 'hello=';
        $this->assertEquals($this->invokeMethod($this->command, 'extractEqualsOptionValue', array($value)), null);
        $value = 'hello,222';
        $this->assertEquals($this->invokeMethod($this->command, 'extractEqualsOptionValue', array($value)), null);
    }

    /**
     * Test the file options.
     */
    public function testFile()
    {
        $option = new Option('c');
        $this->assertEquals(
            get_class($this->invokeMethod($this->command, 'file', array($option))),
            'De\Uniwue\RZ\Lyra\CMD\CommandTest'
        );
    }

    /**
     * Test the default help.
     */
    public function testDefaultHelp()
    {
        $command = new Command();
        $command->useDefaultHelp();
    }

    /**
     * Test the must.
     */
    public function testMust()
    {
        $command = new Command();
        $command->option('HI')->must(
            function () {
                return true;
            }
        );
        $this->assertEquals(
            $command->getOption('HI')->getRule(),
            function () {
                return true;
            }
        );
    }

    /**
     * Test the map function.
     */
    public function testMap()
    {
        $command = new Command();
        $command->option('HI')->map(
            function () {
                return true;
            }
        );
        $this->assertEquals(
            $command->getOption('HI')->getMap(),
            function () {
                return true;
            }
        );
    }

    /**
     * Tests the argument for the given exception.
     *
     * @expectedException \Exception
     */
    public function testArgumentException()
    {
        $toTestOption = new Option('t');
        $toTestOption->setValue(false);
        $toTestOption->addAlias('test-report');
        $toTestOption->setDescription('test-desc');
        $toTestOption->setNeeds(array('a'));
        $toTestOption->setBoolean(true);
        $toTestOption->setRun('testCommand');
        $this->invokeMethod($this->command, 'argument', array($toTestOption, 'Hi'));
    }

    public function testCommandoFlag()
    {
        // Single flag
        $tokens = array('filename', '-f', 'val');
        $cmd = new Command($tokens);
        $cmd->option('f');
        $this->assertEquals($tokens[2], $cmd['f']);
        // Single alias
        $tokens = array('filename', '--foo', 'val');
        $cmd = new Command($tokens);
        $cmd->option('f')->alias('foo');
        $this->assertEquals($tokens[2], $cmd['f']);
        $this->assertEquals($tokens[2], $cmd['foo']);
        // Multiple flags
        $tokens = array('filename', '-f', 'val', '-g', 'val2');
        $cmd = new Command($tokens);
        $cmd->option('f')->option('g');
        $this->assertEquals($tokens[2], $cmd['f']);
        $this->assertEquals($tokens[4], $cmd['g']);
        // Single flag with anonnymous argument
        $tokens = array('filename', '-f', 'val', 'arg1');
        $cmd = new Command($tokens);
        $cmd->option('f')->option();
        $this->assertEquals($tokens[3], $cmd[0]);
        // Single flag with anonnymous argument
        $tokens = array('filename', '-f', 'val', 'arg1');
        $cmd = new Command($tokens);
        $cmd->option('f');
        $this->assertEquals($tokens[3], $cmd[0]);
        // Define flag with `flag` and a named argument
        $tokens = array('filename', '-f', 'val', 'arg1');
        $cmd = new Command($tokens);
        $cmd
            ->flag('f')
            ->argument();
        $this->assertEquals($tokens[3], $cmd[0]);
        $this->assertEquals($tokens[2], $cmd['f']);
    }
    public function testImplicitAndExplicitParse()
    {
        // Implicit
        $tokens = array('filename', 'arg1', 'arg2', 'arg3');
        $cmd = new Command($tokens);
        $this->assertFalse($cmd->isParsed());
        $val = $cmd[0];
        $this->assertTrue($cmd->isParsed());
        // Explicit
        $cmd = new Command($tokens);
        $this->assertFalse($cmd->isParsed());
        $cmd->parse();
        $this->assertTrue($cmd->isParsed());
    }
    // Test retrieving a previously defined option via option($name)
    public function testRetrievingOptionNamed()
    {
        // Short flag
        $tokens = array('filename', '-f', 'val');
        $cmd = new Command($tokens);
        $option = $cmd->option('f')->require();
        $this->assertTrue($cmd->getOption('f')->isRequired());
        $cmd->option('f')->require(false);
        $this->assertFalse($cmd->getOption('f')->isRequired());
        // Make sure there is still only one option
        $this->assertEquals(1, $cmd->getSize());
    }

    /**
     * Test retrieving a previously defined option via option($name).
     **/
    public function testRetrievingOptionAnon()
    {
        // Annonymous
        $tokens = array('filename', 'arg1', 'arg2', 'arg3');
        $cmd = new Command($tokens);
        $option = $cmd->option()->require();
        $this->assertTrue($cmd->getOption(0)->isRequired());
        $cmd->option(0)->require(false);
        $this->assertFalse($cmd->getOption(0)->isRequired());
        $this->assertEquals(1, $cmd->getSize());
    }
    /**
     * Test the boolean options.
     **/
    public function testBooleanOption()
    {
        // with bool flag
        $tokens = array('filename', 'arg1', '-b', 'arg2');
        $cmd = new Command($tokens);
        $cmd->option('b')
            ->boolean();
        $this->assertTrue($cmd['b']);
        // without
        $tokens = array('filename', 'arg1', 'arg2');
        $cmd = new Command($tokens);
        $cmd->option('b')
            ->boolean();
        $this->assertFalse($cmd['b']);
        // try inverse bool default operations...
        // with bool flag
        $tokens = array('filename', 'arg1', '-b', 'arg2');
        $cmd = new Command($tokens);
        $cmd->option('b')
            ->default(true)
            ->boolean();
        $this->assertFalse($cmd['b']);
        // without
        $tokens = array('filename', 'arg1', 'arg2');
        $cmd = new Command($tokens);
        $cmd->option('b')
            ->default(true)
            ->boolean();
        $this->assertTrue($cmd['b']);
    }
    public function testGetValues()
    {
        $tokens = array('filename', '-a', 'v1', '-b', 'v2', 'v3', 'v4', 'v5');
        $cmd = new Command($tokens);
        $cmd
            ->flag('a')
            ->flag('b')->aka('boo');
        $this->assertEquals(array('v3', 'v4', 'v5'), $cmd->getArgumentValues());
        $this->assertEquals(array('a' => 'v1', 'b' => 'v2'), $cmd->getFlagValues());
    }
    /**
     * Ensure that requirements are resolved correctly.
     */
    public function testRequirementsOnOptionsValid()
    {
        $tokens = array('filename', '-a', 'v1', '-b', 'v2');
        $cmd = new Command($tokens);
        $cmd->option('b');
        $cmd->option('a')
            ->needs('b');
        $this->assertEquals($cmd['a'], 'v1');
    }

    /**
     * Tests the parse function.
     */
    public function testParse1()
    {
        $tokens = array('filename', '-a', 'v1', '-b', 'v2', 'v3', 'v4', 'v5', '--boo=hi', '--help=true');
        $cmd = new Command($tokens, true);
        $cmd->flag('a')
            ->flag('b')->aka('boo');
        $cmd->useDefaultHelp();
    }

    /**
     * Tests the parse Exceptions.
     *
     * @expectedException \Exception
     */
    public function testParseException1()
    {
        $tokens = array('filename', '-a', 'v1', '-b', 'v2', 'v3', 'v4', 'v5', '--boo=-hi');
        $cmd = new Command($tokens, true);
        $cmd->flag('a')
                    ->flag('b')->aka('boo');
        $cmd->useDefaultHelp();
    }
    /**
     * Tests the parse Exceptions.
     *
     * @expectedException \Exception
     */
    public function testParseException2()
    {
        $tokens = array('filename', '-a', null, '-b', 'v2', 'v3', 'v4', 'v5', '--boo=hi');
        $cmd = new Command($tokens, true);
        $cmd->flag('a')->required()->flag('b')->aka('boo');
        $cmd->useDefaultHelp();
    }
    /**
     * Tests the parseOption Exception.
     *
     * @expectedException \Exception
     */
    public function testParseOptionsException()
    {
        $tokens = array('filename', '-!a', null, '-b', 'v2', 'v3', 'v4', 'v5', '--boo=hi');
        $cmd = new Command($tokens, true);
        $cmd->flag('a')->required()->flag('b')->aka('boo');
        $cmd->useDefaultHelp();
    }

    /**
     * Test that an exception is thrown when an option isn't set.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testRequirementsOnOptionsMissing()
    {
        $tokens = array('filename', '-a', 'v1');
        $cmd = new Command($tokens, true);
        $cmd->trapErrors(false)
            ->beepOnError(false);
        $cmd->option('a')
        ->needs('b');
    }

    public function testDefine()
    {
        $tokens = array('filename', 'v1', 'v2', 'v3', 'v4', 'v5');
        $this->command->trapErrors(true);
        $this->command->define($tokens);
    }
    /**
     *  Tests the do not trap error.
     *
     * @expectedException De\Uniwue\RZ\Lyra\Exceptions\CommandLineException
     */
    public function testDoNotTrapErrors()
    {
        $tokens = array('filename', '-a', 'v1');
        $cmd = new Command($tokens, true);
        $cmd->doNotTrapErrors();
        $cmd->beepOnError(false);
        $cmd->option('b')
            ->needs('b');
    }

    /**
     * @expectedException \Exception
     */
    public function testSetEnv()
    {
        $tokens = array('filename', '-a', 'v1');
        $cmd = new Command($tokens, true);
    }

    /**
     * Test the error function.
     *
     * @expectedException \Exception
     */
    public function testError()
    {
        $tokens = array('filename', '-a', 'v1');
        $cmd = new Command($tokens, true);
        $cmd->beepOnError();
        $exception = new \Exception('HHAAH');
        $cmd->error($exception);
    }

    /**
     * Tests the list movement options.
     */
    public function testMovementOption()
    {
        $cmd = new Command();
        $cmd->option('d')->aka('boo');
        $this->assertFalse($cmd->valid());
        $cmd->rewind();
        $this->assertEquals($cmd->key(), 0);
        $cmd->next();
        $this->assertEquals($cmd->key(), 1);
        $this->assertEquals($cmd->current(), null);
        $tokens = array('filename', '-a', 'v1', '-b', 'v2');
        $command = new Command();
        $cmd->setHelp('Help');
        $cmd->option('t')->aka('test-report')
            ->describedAs('test-desc')
            ->boolean()
            ->default(false)
            ->needs(array('a'))
            ->run('testCommand');
        $cmd->parse();
        $this->assertEquals($cmd->current(), null);
    }

    /**
    * Test all the terminal related functions.
    * 
    **/
    public function testTerminal(){
        $term = new Util\Terminal();
        $value = $term->getHeight(32);
        // The height can be set with hand, as a result it can not tested with
        // normal testing, this is only done to have the code logic covered.
        $this->assertTrue($value<500);
    }

    /**
    * Test the exception to string function.
    */
    public function testException(){
        $exp = new CommandLineException("Test");
        $this->assertEquals(strval($exp), "De\Uniwue\RZ\Lyra\Exceptions\CommandLineException: [0]: CMD Exception: Test\n");
    }

    /**
    *
    * Tests with the optional parameters
    */
    public function testWithOptionalParameters(){
        $cmd = new Command(array(), true);
        $cmd->option('b')->aka('boo')->optionals("d")->run("helloWorld")->default(true)->boolean();
        $cmd->option('d')->aka('dryrun')->boolean()->default(false);
        $cmdParams = $cmd->getFlagValues();
        $runs = $cmd->getRuns();
        $needs = $cmd->getNeeds();
        $optionals = $cmd->getOptionals();
        $runs = $cmd->getRunCommands($cmdParams, $runs, $needs, $optionals);
        $rightValue = array("val"=>true, "run"=>"helloWorld", "params"=>array("d"=>false));
        $this->assertEquals($runs, $rightValue);
        $example = new ExampleClassTest();
        $helloWorld = $cmd->dispatchCommands($example);
        $this->assertEquals($helloWorld, "helloWorld");
    }
}
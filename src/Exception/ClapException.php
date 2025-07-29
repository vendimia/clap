<?php

namespace Vendimia\Clap\Exception;

use Exception;

/**
 * Generic parsing exception
 */
class ClapException extends Exception
{
    private array $args = [];
    public function __construct(string $message = '', ...$args)
    {
        parent::__construct($message);
        $this->args = $args;
    }

    public function getArguments(): array
    {
        return $this->args;
    }
}

/**
 * Invalid command
 */
class InvalidCommandException extends ClapException {}

/**
 * Missing subcommand
 */
class MissingSubcommandException extends ClapException {}

/**
 * Invalid subcommand
 */
class InvalidSubcommandException extends ClapException {}

/**
 * There are no arguments to parse. A help should be printed.
 */
class NoArgumentsException extends ClapException {}

/**
 * Invalid argument
 */
class InvalidArgumentException extends ClapException {}

/**
 * Flags doesn't require a value
 */
class FlagWithValueException extends ClapException {}

/**
 * Parameter require a value
 */
class ParameterWithoutValueException extends ClapException {}

/**
 * Argument must be named only
 */
class NamedArgumentOnlyException extends ClapException {}

/**
 * An argument is needed for a required parameter
 */
class MissingArgumentException extends ClapException {};

<?php

namespace Vendimia\Clap\Option;

use Attribute;
use InvalidArgumentException;

/**
 * Adds a posix_alias for this parameter
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Short implements OptionInterface
{
    private array $character;

    public function __construct(...$character)
    {
        foreach ($character as $char) {
            if (strlen($char) > 1) {
                throw InvalidArgumentException('Short argument should be only one character');
            }
        }

        $this->character = $character;
    }

    public function processOption(array &$element, TargetEnum $target)
    {
        $element['posix_aliases'] = $this->character;
    }
}
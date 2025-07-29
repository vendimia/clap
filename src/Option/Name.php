<?php

namespace Vendimia\Clap\Option;

use Attribute;

/**
 * Allows changing a option name or setting an alias
 */
#[Attribute]
class Name
{
    public function __construct(
        private string $name = '',
        private string | array $alias = ''
    )
    {

    }

    public function processOption(array &$element, TargetEnum $target)
    {
        if($this->name) {
            $element['name'] = $this->name;
        }

        if($this->alias) {
            if (is_string($this->alias)) {
                $alias = [$this->alias];
            } else {
                $alias = $this->alias;
            }

            // Segun el target, guardamos en un elemento distinto
            $key = match($target) {
                TargetEnum::PARAMETER => 'param_aliases',
                TargetEnum::FUNCTION => 'aliases'
            };


            $element[$key] = [
                ...$element[$key],
                ...$alias,
            ];
        }
    }
}

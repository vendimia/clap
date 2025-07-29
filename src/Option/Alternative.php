<?php

namespace Vendimia\Clap\Option;

use Vendimia\Clap\Parser;

use Attribute;

/**
 * Allows changing this parameter with an alias and a default value
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Alternative
{
    private array $alternatives;

    public function __construct(...$alternatives)
    {
        foreach ($alternatives as $name => $value) {
            // El nombre lo convertimos a anticucho case
            $name = Parser::snakeToAnticuchoCase($name);

            $this->alternatives[$name] = $value;
        }
    }

    public function processOption(array &$element, TargetEnum $target)
    {
        // Cambiamos el valor de cada alternativa por un array con el nombre del
        // argumento, para poder aplicarlo fácilmente después
        $alternatives = [];
        foreach ($this->alternatives as $name => $value)
        {
            $alternatives[$name] = [$element['name'], $value];
        }

        $element['alternatives'] = [
            ...$element['alternatives'],
            ...$alternatives,
        ];
    }
}
<?php

namespace Vendimia\Clap;

use Closure;
use ReflectionFunction;

/**
 * Command definition, obtained from a function or method.
 */
class Command
{
    /**
     * Command name, in anticucho-case.
     *
     * This is the array key in the parent::$commands which holds this object
     **/
    private string $name;

    /**
     * Alternate names of this command.
     *
     * This should be passed to the parent object.
     */
    private array $aliases = [];

    /**
     * Closure parameter list (number-indexed array)
     */
    private array $params = [];

    /**
     * Closure parameters by name, pointing to &self::$params
     */
    private array $params_name = [];

    /**
     * Closure parameters aliases, pointing to &self::$params
     */
    private array $params_aliases = [];

    /**
     * Closure parameters as POSIX arguments, pointing to &self::$params
     */
    private array $params_posix = [];

    /**
     * Closure parameters aliases with predetermined value
     */
    private array $param_alternatives = [];

    public function __construct(
        /** Closure to the function or method */
        private Closure $closure,

        /** Parent of this command */
        private ?Command $parent,
    )
    {
        // Empezamos a analizar el closure
        $ref = new ReflectionFunction($closure);

        // Procesamos los atributos de la función
        foreach ($rf->getAttributes() as $attr) {
            $attribute = $attr->newInstance();
            $attribute->processOption(
                element: $this,
                target: Option\TargetEnum::FUNCTION
            );
        }

    }
}
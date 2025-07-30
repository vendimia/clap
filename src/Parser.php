<?php

namespace Vendimia\Clap;

use ReflectionFunctionAbstract;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use ReflectionClass;
use LogicException;
use Closure;

/**
 * Vendimia\Clap - Command-line argument parser 👏
 */
class Parser
{
    /** Storage of commands and its subcommands, parameters and options */
    private array $commands = [
        // Default command when there is not command
        '' => []
    ];

    /** Aliases for commands */
    private array $aliases = [];

    /**
     * Prepares a new instance of Clap
     */
    public function __construct()
    {
        // Cargamos las excepciones
        class_exists(Exception\ClapException::class);
    }

    /**
     * Converts camelCase or PascalCase to anticucho-case (or kebab-case)
     *
     * @see https://en.wikipedia.org/wiki/Anticucho
     */
    public static function camelToAnticuchoCase(string $camel_cased_name): string
    {
        // La primera letra siempre estará en minúsculas
        $camel_cased_name = mb_strtolower(mb_substr($camel_cased_name, 0, 1)) .
            mb_substr($camel_cased_name, 1);

        return mb_strtolower(
            preg_replace('/(?!^)([\p{Lu}])/u', '-\1', $camel_cased_name)
        );
    }

    /**
     * Converts snake_case to anticucho-case (or kebab-case)
     *
     * @see https://en.wikipedia.org/wiki/Anticucho
     */
    public static function snakeToAnticuchoCase(string $snake_cased_name): string
    {
        return mb_strtolower(strtr($snake_cased_name, '_', '-'));
    }

    /**
     * Creates a subcommand from a closure or function
     *
     * @param string $command Main command of this subcommand
     */
    private function registerClosure(
        callable $closure,
        $command = ''
    ): void
    {
        $rf = new ReflectionFunction($closure);

        // Preparamos el subcomando de este closure
        $subcommand = [
            // Nombre de este subcomando
            'name' => $this::camelToAnticuchoCase($rf->getName()),

            // Aliases de este subcomando
            'aliases' => [],

            // Closure a ser ejecutado
            'closure' => $closure,

            // Lista de parámetros del closure,
            'params' => [],

            // Índice de 'params', [nombre => &$param]
            'params_index' => [],

            // Alias de los parámetros
            'params_aliases' => [],

            // Alias cortos de los parámetros
            'posix_aliases' => [],

            // Alias de los parámetros, con valores predefinidos
            'alternatives' => [],
        ];

        // Procesamos los atributos de la función
        foreach ($rf->getAttributes() as $attr) {
            $attribute = $attr->newInstance();
            $attribute->processOption(
                element: $subcommand,
                target: Option\TargetEnum::FUNCTION
            );
        }

        $param_idx = 0;
        // Obtenemos los parámetros de la función
        foreach ($rf->getParameters() as $param) {
            $type = $param->getType()?->getName();

            $info = [
                // El nombre de los parámetros debe ser de snake case a anticucho
                'name' => $this::snakeToAnticuchoCase($param->getName()),
                // El nombre original del parámetro
                'param_name' => $param->getName(),
                'optional' => $param->isDefaultValueAvailable(),
                'flag' => $type == 'bool',
                'param_aliases' => [],
                'posix_aliases' => [],
                'alternatives' => [],

                // Los flags por defecto son named_only
                'named_only' => $type == 'bool',
            ];

            foreach ($param->getAttributes() as $attr) {
                $attribute = $attr->newInstance();
                $attribute->processOption(
                    element: $info,
                    target: Option\TargetEnum::PARAMETER
                );
            }

            $subcommand['params'][$param_idx] = $info;
            $subcommand['params_index'][$info['name']] = &$subcommand['params'][$param_idx];

            // Si hay aliases de los parámetros, apuntamos al mismo $info
            foreach ($info['param_aliases'] as $alias) {
                $subcommand['param_aliases'][$alias] = &$subcommand['params'][$param_idx];
            }

            // Si hay aliases posix, también apuntamos al mismo info
            foreach ($info['posix_aliases'] as $alias) {
                $subcommand['posix_aliases'][$alias] = &$subcommand['params'][$param_idx];
            }

            // Si hay alternatives, lo copiamos al subcommand
            $subcommand['alternatives'] = [
                ...$subcommand['alternatives'],
                ...$info['alternatives']
            ];

            $param_idx += 1;
        }

        // Si el closure es anónimo, requiere un nombre
        if($subcommand['name'] == '{closure}') {
            throw new LogicException("Anonymous closure requires a name");
        }

        $this->commands[$command][$subcommand['name']] = &$subcommand;

        // Registramos los alias para este subcomando
        foreach ($subcommand['aliases'] as $alias)  {
            $this->aliases[$command][$alias] = &$subcommand;
        }
    }

    /**
     * Process static methods in a class as subcommands
     */
    private function registerClass($class_name)
    {
        $rc = new ReflectionClass($class_name);
        $command = $this::camelToAnticuchoCase($rc->getShortName());

        $methods = $rc->getMethods(
            ReflectionMethod::IS_STATIC
        );

        foreach ($methods as $method) {
            // Solo procesamos los públicos
            if (!$method->isPublic()) {
                continue;
            }
            $this->registerClosure($method->getClosure(), $command);
        }
    }

    private function registerObject(object $object)
    {
        $rc = new ReflectionObject($object);
        $command = $this::camelToAnticuchoCase($rc->getName());

        $methods = $rc->getMethods(
            ReflectionMethod::IS_PUBLIC
        );

        foreach ($methods as $method) {
            $this->registerClosure($method->getClosure($object), $command);
        }
    }

    /**
     * Register a function or class as receptor of the command line expression
     */
    public function register($executable): void
    {
        // Procesamos el callable según su tipo
        if ($executable instanceof Closure || is_callable($executable)) {
            // Closure, función o callable
            $this->registerClosure($executable);
        } elseif (is_object($executable)) {
            // Objeto
            $this->registerObject($executable);
        } elseif (class_exists($executable)) {
            // Clase
            $this->registerClass($executable);
        } else {
            throw new LogicException("Can't register executable of type " . gettype($executable));
        }
    }

    /**
     * Process GNU long argument
     */
    private function processLongArgument(&$function_info, $arg): array
    {
        // Removemos los '--'
        $arg = substr($arg, 2);

        // Si hay un =, dividimos el nombre del valor
        if (str_contains($arg, '=')) {
            [$name, $value] = explode('=', $arg, 2);
        } else {
            // Si no hay, entonces el valor es True
            $name = $arg;
            $value = true;
        }

        // Verificamos si este argumento es una alternativa
        if (key_exists($name, $function_info['alternatives'])) {
            [$name, $value] = $function_info['alternatives'][$name];
        }

        // Validamos si el parámetro requiere o no un valor.

        // Extraño el 'or' de python :')
        ($info = $function_info['params_index'][$name] ?? null) ||
        ($info = $function_info['param_aliases'][$name] ?? null);

        if (!$info) {
            throw new Exception\InvalidArgumentException("Invalid argument '{$name}'",
                argument: $name,
            );
        }

        if ($info['flag']) {
            // Si el parametro es un flag, no debe tener un valor
            if (!is_bool($value)) {
                throw new Exception\FlagWithValueException("Flag '{$name}' requires no value",
                    argument: $name,
                );
            }
        } else {
            // Al reves, si no es un flag, _debe_ tener un valor
            if (is_bool($value)) {
                throw new Exception\ParameterWithoutValueException("Parameter '{$name}' requires a value",
                    argument: $name,
                );
            }
        }

        // Usamos el nombre de $info, por si este parámetro fue un alias
        $name = $info['name'];

        return [$name => $value];
    }

    /**
     * Process POSIX group of rarguments
     *
     * @param array $&function_info Information about the processor function
     * @param string $arg Arguments to process
     * @param string &$args All the arguments, for advance the internal pointer
     *  if needed
     */
    private function processShortArgument(&$function_info, $arg, &$args): array
    {
        // Removemos el '-'
        $arg = substr($arg, 1);

        $return_args = [];

        foreach (mb_str_split($arg) as $character) {
            // si no existe el caracter en $posix_aliases, fallamos
            if (!key_exists($character, $function_info['posix_aliases'])) {
                throw new Exception\InvalidArgumentException("Invalid short argument '{$character}'",
                    short_argument: $arg,
                );
            }

            $name = $function_info['posix_aliases'][$character]['name'];
            $value = True;

            $return_args[$name] = $value;
        }

        return $return_args;
    }

    /**
     * Process the arguments from the command line or other source
     */
    public function process(?array $args = null)
    {
        // Si no hay argumentos, usamos $argv menos el primer elemento
        if (is_null($args)) {
            // $argv no es global
            $args = array_slice($_SERVER['argv'], 1);
        }

        // Aquí estará la información de la función o método que será ejecutado
        $function_info = null;

        if (count($this->commands) == 1) {
            $command = current($this->commands);

            if (count($command) == 1) {
                $subcommand = current($command);
                $function_info = &$subcommand;
            } else {
                // El primer elemento de $arg debe ser un subcomando
                $command_name = array_shift($args);

                $function_info = $command[$command_name] ?? null;

                if (!$function_info) {
                    throw new Exception\InvalidCommandException("Invalid command '{$command_name}'",
                        command: $command_name
                    );
                }
            }
        } else {
            // El primer elemento del $arg debe ser un comando
            $command_name = array_shift($args);

            if (!key_exists($command_name, $this->commands)) {
                throw new Exception\InvalidCommandException("Invalid command '{$command_name}'",
                    command: $command_name
                );
            }
            $command = &$this->commands[$command_name];


            // El 2do es el subcomando
            $subcommand_name = array_shift($args);

            if (!$subcommand_name) {
                throw new Exception\MissingSubcommandException("Missing subcommand",
                    subcommand: $command_name
                );
            }

            ($function_info = &$command[$subcommand_name] ?? null) ||
            ($function_info = &$this->aliases[$command_name][$subcommand_name] ?? null);

            if (!$function_info) {
                throw new Exception\InvalidSubcommandException("Invalid subcommand '{$subcommand_name}'",
                    subcommand: $command_name
                );
            }

        }

        // Procesamos $args
        $positional_args = [];
        $named_args = [];
        while ($arg = current($args)) {
            // Es un parámetro con nombre?
            if (str_starts_with($arg, '--')) {
                $named_args = [
                    ...$named_args,
                    ...$this->processLongArgument($function_info, $arg),
                ];
            } elseif (str_starts_with($arg, '-')) {
                // Es un parámetro POSIX corto?
                $named_args = [
                    ...$named_args,
                    ...$this->processShortArgument($function_info, $arg, $args),
                ];
            } else {
                $positional_args[] = $arg;
            }

            $function_args = array_merge($positional_args, $named_args);

            next($args);
        }

        $processed_args = [
            ...$positional_args,
            ...$named_args,
        ];

        // Aquí guardamos los valores según vayan siendo obtenidos de $argv
        $function_args = [];

        $param_idx = 0;
        foreach ($processed_args as $name => $value) {
            if (is_string($name)) {
                // Argumento con nombre
                $function_args[$name] = $value;
            } else {
                // Argumento posicional, el nombre lo obtenemos de 'params'
                $name = $function_info['params'][$param_idx]['name'];
                $param_name = $function_info['params'][$param_idx]['param_name'];

                // Si es un flag, lo ignoramos
                if ($function_info['params'][$param_idx]['flag']) {
                    continue;
                }

                // Si este argumento es solo nombre, fallamos
                if ($function_info['params_index'][$name]['named_only']) {
                    throw new Exception\NamedArgumentOnlyException("Argument for '$name' must be named-only",
                        param: $name
                    );
                }

                $function_args[$param_name] = $value;
                $param_idx += 1;
            }
        }

        // Buscamos si algún parámetro requerido no tiene valor
        foreach ($function_info['params'] as $info) {
            if (!$info['optional'] && !key_exists($info['param_name'], $function_args)) {
                throw new Exception\MissingArgumentException("Missing argument for parameter '{$info['name']}'",
                    param: $info['name']
                );
            }
        }


        // Y llamamos a la función
        $closure = $function_info['closure'];
        $closure(...$function_args);
    }
}

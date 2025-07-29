<?php

namespace Vendimia\Clap\Option;

interface OptionInterface
{
    /**
     * Process an option, updating the $element array according to $target
     */
    public function processOption(array &$element, TargetEnum $target);
}
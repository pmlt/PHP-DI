<?php

declare(strict_types=1);

namespace DI\Definition\Source;

use DI\Definition\ArrayDefinition;
use DI\Definition\AutowireDefinition;
use DI\Definition\DecoratorDefinition;
use DI\Definition\Definition;
use DI\Definition\Exception\InvalidDefinition;
use DI\Definition\FactoryDefinition;
use DI\Definition\Helper\DefinitionHelper;
use DI\Definition\ValueDefinition;

/**
 * Turns raw definitions/definition helpers into definitions ready
 * to be resolved or compiled.
 *
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class DefinitionNormalizer
{
    /**
     * @var Autowiring
     */
    private $autowiring;

    public function __construct(Autowiring $autowiring)
    {
        $this->autowiring = $autowiring;
    }

    /**
     * Normalize a definition that is *not* nested in another one.
     *
     * This is usually a definition declared at the root of a definition array.
     *
     * @param mixed $definition
     * @param string $name The definition name.
     *
     * @throws InvalidDefinition
     */
    public function normalizeRootDefinition($definition, string $name) : Definition
    {
        if ($definition instanceof DefinitionHelper) {
            $definition = $definition->getDefinition($name);
        } elseif (is_array($definition)) {
            $definition = new ArrayDefinition($name, $definition);
        } elseif ($definition instanceof \Closure) {
            $definition = new FactoryDefinition($name, $definition);
        } elseif (! $definition instanceof Definition) {
            $definition = new ValueDefinition($name, $definition);
        }

        if ($definition instanceof AutowireDefinition) {
            $definition = $this->autowiring->autowire($name, $definition);
        }

        try {
            $definition->replaceNestedDefinitions([$this, 'normalizeNestedDefinition']);
        } catch (InvalidDefinition $e) {
            throw InvalidDefinition::create($definition, sprintf(
                'Definition "%s" contains an error: %s',
                $definition->getName(),
                $e->getMessage()
            ), $e);
        }

        return $definition;
    }

    /**
     * Normalize a definition that is nested in another one.
     *
     * @param mixed $definition
     * @return mixed
     *
     * @throws InvalidDefinition
     */
    public function normalizeNestedDefinition($definition)
    {
        $name = '<nested definition>';

        if ($definition instanceof DefinitionHelper) {
            $definition = $definition->getDefinition($name);
        } elseif (is_array($definition)) {
            $definition = new ArrayDefinition($name, $definition);
        } elseif ($definition instanceof \Closure) {
            $definition = new FactoryDefinition($name, $definition);
        }

        if ($definition instanceof DecoratorDefinition) {
            throw new InvalidDefinition('Decorators cannot be nested in another definition');
        }

        if ($definition instanceof AutowireDefinition) {
            $definition = $this->autowiring->autowire($name, $definition);
        }

        if ($definition instanceof Definition) {
            // Recursively traverse nested definitions
            $definition->replaceNestedDefinitions([$this, 'normalizeNestedDefinition']);
        }

        return $definition;
    }
}
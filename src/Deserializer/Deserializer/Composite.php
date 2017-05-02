<?php namespace EventSourced\ValueObject\Deserializer\Deserializer;

use EventSourced\ValueObject\Deserializer\Deserializer;
use EventSourced\ValueObject\Deserializer\Reflector;
use EventSourced\ValueObject\Deserializer\Exception;
use EventSourced\ValueObject\Assert\Exception as AssertException;
use ReflectionParameter;

class Composite
{
    private $deserializer;
    private $reflector;

    public function __construct(Deserializer $deserializer, Reflector $reflector)
    {
        $this->deserializer = $deserializer;
        $this->reflector = $reflector;
    }
    
    public function deserialize($class, $serialized)
    {
        if (is_array($serialized)) {
            $serialized = (object)$serialized;
        }

        $errors = [];
        $deserialized_parameters = [];

        $parameters = $this->reflector->get_constructor_parameters($class);
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            try {
                $deserialized_parameters[$name] = $this->make_parameter($parameter, $serialized);
            } catch (AssertException $e) {
                $errors[$name] = $e->error_messages();
            } catch (Exception $e) {
                $errors[$name] = $e->error_messages();
            }
        }

        if (count($errors) != 0) {
            throw new Exception($errors);
        }

        return $this->reflector->call_constructor($class, $deserialized_parameters);
    }

    private function is_nullable_parameter(ReflectionParameter $parameter)
    {
        return ($parameter->isOptional() && $parameter->getDefaultValue() == null);
    }


    private function make_parameter($parameter, $serialized)
    {
        if ($this->is_nullable_parameter($parameter)) {
            return null;
        }

        $name = $parameter->getName();
        $parameter_class = $parameter->getClass()->getName();

        if (!property_exists($serialized, $name)) {
            throw new Exception(["Property '$name' is missing"]);
        }

        return $this->deserializer->deserialize(
            $parameter_class, $serialized->$name
        );
    }
}

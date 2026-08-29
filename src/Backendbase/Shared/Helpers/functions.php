<?php

declare(strict_types=1);

function backendbaseEnv(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    $value = getenv($key);

    return $value === false ? $default : $value;
}

function backendbaseIntegerEnvironmentValue(string $key, int $default): int
{
    $value = backendbaseEnv($key, $default);
    if (is_int($value)) {
        return $value;
    }

    $parsedValue = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
    if ($parsedValue === false) {
        throw new UnexpectedValueException(sprintf('The %s environment value must be an integer.', $key));
    }

    return $parsedValue;
}

function backendbaseFloatEnvironmentValue(string $key, float $default): float
{
    $value = backendbaseEnv($key, $default);
    if (is_float($value) && is_finite($value)) {
        return $value;
    }

    if (is_int($value)) {
        return (float) $value;
    }

    $parsedValue = is_string($value) ? filter_var($value, FILTER_VALIDATE_FLOAT) : false;
    if ($parsedValue === false || ! is_finite($parsedValue)) {
        throw new UnexpectedValueException(sprintf('The %s environment value must be a finite number.', $key));
    }

    return $parsedValue;
}

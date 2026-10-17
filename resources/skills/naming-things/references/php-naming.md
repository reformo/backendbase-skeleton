# PHP naming patterns

Use these as examples, then follow the project's local conventions when they are materially different.

For this skill, variables are `camelCase` and classes are `PascalCase`. Methods and functions use `camelCase` by default.

## Core forms

| Kind | Preferred form | Example |
| --- | --- | --- |
| Class | `PascalCase` | `class OrderValidator {}` |
| Variable | `$camelCase` | `$orderTotal` |
| Method/function | `camelCase` | `calculateTax()` |
| Test class | `PascalCase` | `TaxCalculatorTest` |
| PHP class file | `PascalCase.php` | `TaxCalculator.php` |

## Context-aware example

```php
class PayrollCalculator
{
    public function calculateGrossPay(Employee $employee, PayPeriod $payPeriod): Money
    {
        $rate = $employee->hourlyRate;
        $hours = $payPeriod->hoursWorked;

        return Money::fromDecimal($rate * $hours);
    }
}
```

The class name carries the payroll context, so local variables can stay concise without becoming vague.

## Intention-revealing example

```php
if (isEligibleForExpressProcessing($order, $customer, $inventory)) {
    processExpress($order);
}
```

Prefer `isEligibleForExpressProcessing()` over an unnamed boolean expression and prefer `$validatedOrder` over `$data` when the value's state matters.

## Class cohesion example

If one class is difficult to name without combining responsibilities, consider separate PascalCase surfaces:

```php
class OrderValidator {}
class PricingCalculator {}
class PaymentProcessor {}
class CustomerNotifier {}
```

## Review prompts

- Does the name match the domain term used by the team?
- Is the useful word visible early enough for search and autocomplete?
- Is the name carrying context that the caller cannot see?
- Is the variable name unambiguous among its neighbors?
- Does the class have one reason to change, or is its name hiding multiple responsibilities?
- Does the name make the AI-assisted search → read → edit → test loop easier?
- Is the symbol public, reflected on, or documented outside the codebase?
- Can the change be made with semantic rename tooling and verified by tests?

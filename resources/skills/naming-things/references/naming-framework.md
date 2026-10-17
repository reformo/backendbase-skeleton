# Naming framework

Use this model to choose names that help people and AI agents find, understand, change, and verify code.

## Three jobs a good name makes easier

- **Find:** search lands on the intended concept instead of every vague use of `data` or `process`.
- **Understand:** the name and its neighborhood form a readable map of intent.
- **Change:** a refactor stays close to the requested surface and is easier to review.

## Context ladder

Meaning travels through a hierarchy. Read from broadest context to narrowest:

```text
system → module → file/class → method/function → local variable
```

The farther a name travels from where it was defined, the more context it must carry. The goal is not maximum length; it is enough information to be unambiguous beside its neighbors.

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

`PayrollCalculator` carries the payroll context, so `$rate` and `$hours` can stay concise within the method.

## From implementation to intention

Prefer the name that answers “why does this exist?” before the name that only describes its representation.

| Less useful | More useful |
| --- | --- |
| `frontGlass` | `windshield` |
| `$rawData` | `$validatedOrder` |
| `doThing()` | `calculateTax()` |
| `function doIt($x)` | `function firstItem(array $items)` |

## Domain vocabulary

Use the words that already connect code, documentation, tests, prompts, and user workflows. An acronym is not automatically bad when the domain uses it and learning it pays off.

```php
$medicalRecordNumber = $patient->medicalRecordNumber;
$routingNumber = $account->routingNumber;
$trackingId = $shipment->trackingId;
```

Do not use a domain term merely to sound sophisticated. If the team does not recognize it, add a glossary or choose a more familiar name.

## Things, actions, and questions

- Objects and values use nouns: `Customer`, `PhoneDialer`, `$preferredName`.
- Commands use verbs: `dial($phoneNumber)`, `awardPoints()`, `processOrder()`.
- Queries can use noun-like names when the result is obvious: `$primaryPhoneNumber`, `$eligibleOrders`.

Let PHP syntax and local framework idioms carry the remaining detail.

## Naming as safe iteration

Names often improve in stages:

1. **Missing:** no useful label yet.
2. **Honest / incomplete:** describes the current use.
3. **Honest / complete:** explains the whole job.
4. **Intention-revealing:** says why it exists.
5. **Domain abstraction:** fits the shared vocabulary.

Rename in small steps and run tests after each step. Better names often reveal the next design move.

## Extraction and cohesion

If a complex block needs a comment, extract a named operation:

```php
if (isEligibleForExpressProcessing($order, $customer, $inventory)) {
    processExpress($order);
}
```

The caller becomes a readable outline, and the named operation can have focused tests. If a class needs an “and” in its name, consider separate cohesive surfaces:

```php
class OrderValidator {}
class PricingCalculator {}
class PaymentProcessor {}
class CustomerNotifier {}
```

## Five-question review

Before accepting a name, ask:

1. What is the domain term?
2. What is it for?
3. How far does it travel?
4. Is it unique among its neighbors?
5. Can it be renamed safely with semantic tooling and tests?

For an AI handoff, include the target, intent, constraints, and verification:

```text
Update TaxCalculator::calculateTax() to use $taxableIncome.
Keep PascalCase classes and camelCase variables.
Run the existing tests.
```

# AGENTS.md - DevSquad Sidecar Laravel Development Guide

This document provides guidelines for agentic coding assistants working on the sidecar-laravel repository.

## Project Overview

**DevSquad Sidecar** is a Laravel library that enables developers and QA to test Laravel applications directly from the browser. It provides utilities for executing Tinker code, artisan commands, manipulating the fake clock, and impersonating users—all from a browser UI.

- **Language**: PHP 8.2+
- **Framework**: Laravel 11+, 12+
- **Type**: Composer library
- **Testing Framework**: Pest + PHPUnit
- **Code Quality**: PHPStan (Level 10), Pint (Laravel preset)

## Build, Lint, and Test Commands

### Quick Reference

```bash
# Fix code style issues
composer fix

# Run all tests
composer test

# Run individual test suites
composer test:unit      # Run unit/feature tests with 95% minimum coverage
composer test:lint      # Run Pint linter check
composer test:types     # Run PHPStan static analysis
composer test:debug     # Check for debugging code (dump, dd)

# Run a single test
./vendor/bin/pest tests/Feature/ExecuteTinkerControllerTest.php
./vendor/bin/pest tests/Feature/ExecuteTinkerControllerTest.php --filter="handles exception"

# Run tests with coverage report
./vendor/bin/pest --coverage --min=95
```

### Installing Dependencies

```bash
composer install
```

## Code Style Guidelines

### Formatting & Imports

- **Preset**: Laravel Pint preset with custom rules (see pint.json)
- **Line Length**: Follow PSR-12 standards
- **Imports**: 
  - Group imports by category (use statements)
  - Use a single import per statement disabled (multiple imports on one line is allowed)
  - Remove unused imports automatically (`no_unused_imports: true`)
  - Use namespace-grouped imports for classes in same namespace: `use Namespace\{Class1, Class2};`
  - Example: `use EliteDevSquad\SidecarLaravel\Http\Middleware\{FakeClockMiddleware, SidecarMiddleware};`

- **Parentheses**: Always use parentheses for new instances
  - ✅ `new ClassName()`
  - ✅ `new class() {}`
  - ❌ `new ClassName`

### Naming Conventions

- **Namespaces**: PSR-4 standard - `EliteDevSquad\SidecarLaravel\*`
- **Classes**: PascalCase (e.g., `SidecarServiceProvider`, `ExecuteTinkerController`)
- **Methods**: camelCase (e.g., `getUserMap()`, `getUserBuilder()`)
- **Properties**: camelCase for instance, $UPPERCASE for class constants
- **Files**: Match class name exactly (e.g., `class Sidecar` → `Sidecar.php`)

### Type Declarations

- **Minimum Level**: PHP 8.2 (use strict types)
- **Requirements**:
  - All functions must have return type declarations
  - Use union types where applicable: `string|int`
  - Use nullable types: `?string` instead of `string|null`
  - Array types must be documented: `array<string, string>` or `array<int, ClassName>`
  - Add `@var` phpdoc for class properties with complex types

- **PHPStan Level**: 10 (maximum strictness)
- **Configuration**: `phpstan.neon` includes Larastan extension for Laravel-specific rules

- **Ignore Comments**: Use only when necessary
  - Format: `// @phpstan-ignore-line` on the same line
  - Use: For dynamic Laravel calls or type inconsistencies beyond your control
  - Example: When accessing dynamic user properties set via `Sidecar::$userBuilder`

### Documentation

- **DocBlocks**: Include for:
  - Public methods and properties
  - Complex logic requires explanation
  - Return types, especially arrays with specific keys
  
- **Example**:
```php
/**
 * Retrieve the configured user mapping.
 *
 * @return array<string, string>
 */
public static function getUserMap(): array
{
    return self::$userMap; // @codeCoverageIgnore
}
```

### Error Handling

- **Exceptions**: Throw specific exceptions with meaningful messages
- **Validation**: Use Laravel's validation framework in Request classes
- **Coverage**: Mark untestable lines with `// @codeCoverageIgnore`
- **Try-Catch**: Only catch exceptions you plan to handle
- **Return Values**: Use type-safe returns (no mixed unless necessary)

### Testing

- **Framework**: Pest with Laravel plugin
- **Location**: `tests/Feature/` for feature tests
- **Minimum Coverage**: 95%
- **Test Format**: Pest's expressive syntax with `it()` and `expect()`
- **Setup**: Use `beforeEach()` for common setup
- **Example**:
```php
it('handles exception when executing tinker code', function () {
    postJson('__devsquad-sidecar/execute-tinker', ['code' => base64_encode('bad')])
        ->assertOk()
        ->assertJson(['output' => 'Error executing code: oops']);
});
```

## Directory Structure

```
src/
├── Http/
│   ├── Controllers/      # Request handlers
│   ├── Middleware/       # HTTP middleware
│   ├── Requests/         # Form requests with validation
│   └── Resources/        # API resources
├── Jobs/                 # Queued jobs
├── Providers/            # Service providers
├── Traits/               # Shared traits
└── Sidecar.php           # Main facade/configuration class
tests/
└── Feature/              # Feature tests
```

## Key Architecture Decisions

1. **Static Configuration**: `Sidecar` class uses static properties for application-level configuration
2. **Middleware-based Auth**: IP whitelisting via `SidecarMiddleware`
3. **Controller Actions**: One-action controllers for clear single responsibilities
4. **Request Validation**: Form request classes for input validation
5. **Resources**: API resource classes for response formatting

## Before Submitting Changes

1. Run `composer fix` to auto-format code
2. Run `composer test` to ensure all checks pass
3. Verify coverage is ≥95% with `composer test:unit`
4. Check no debug code remains with `composer test:debug`
5. Ensure types pass with `composer test:types`
6. Verify linting passes with `composer test:lint`

## Common Tasks

### Add a New Endpoint

1. Create Request class in `src/Http/Requests/` with validation
2. Create Controller in `src/Http/Controllers/` with single action
3. Register route in `resources/routes.php`
4. Add feature test in `tests/Feature/`
5. Add return types and docblocks
6. Run `composer test` to verify

### Add a Helper Method

1. Create in the appropriate namespace under `src/`
2. Add strict types: `declare(strict_types=1);`
3. Add return type and parameter types
4. Add comprehensive docblock
5. Test with minimum 95% coverage
6. Document in this file if it's a significant architectural addition

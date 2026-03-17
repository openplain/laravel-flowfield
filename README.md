# Laravel FlowField

[![Latest Version on Packagist](https://img.shields.io/packagist/v/openplain/laravel-flowfield.svg?style=flat-square)](https://packagist.org/packages/openplain/laravel-flowfield)
[![Total Downloads](https://img.shields.io/packagist/dt/openplain/laravel-flowfield.svg?style=flat-square)](https://packagist.org/packages/openplain/laravel-flowfield)

Cache-backed computed aggregate fields for Eloquent — inspired by Navision's FlowField concept.

## Why This Package?

When your `Customer` model needs to show a balance (sum of all ledger entries), or your `Item` needs `inventory_quantity` (sum of stock movements), you have two bad options: run the aggregate query every time (slow with thousands of entries), or store a denormalized total and keep it in sync manually (fragile — things drift, you build a "recalc" button).

In the late 1980s, three Danish engineers at PC&C (later Navision, now Microsoft Business Central) solved this exact problem. Their answer was **FlowFields** — virtual fields that compute aggregates on demand without storing the result in the database. Navision defined seven FlowField types: Sum, Count, Average, Min, Max, Exist, and Lookup. For Sum fields specifically, they built **SIFT** (Sum Index Field Technology) — pre-calculated indexes maintained on every write to make sum lookups instant. This concept has powered millions of ERP installations for over 35 years.

We brought the FlowField concept to Laravel. Where Navision uses SIFT indexes for sums and live queries for the rest, we use your cache layer (Redis/Memcached) as the performance layer for all aggregate types.

**Our Goal:** Declare aggregate fields as model attributes. Computed once, cached in Redis/Memcached, automatically invalidated when data changes. Instant reads. Zero maintenance. No stale data.

### Built on Proven Technology

- **Laravel Cache** - Uses your existing in-memory cache (Redis or Memcached) for instant lookups
- **PHP 8.1 Attributes** - Clean, declarative syntax for defining computed fields
- **Eloquent Events** - Automatic cache invalidation via model observers

## Features

- ⚡ **Instant Reads** - Aggregate values served from cache, not computed on every request
- 🔄 **Auto-Invalidation** - Cache busts automatically when related records change
- 🎯 **Declarative Syntax** - Define FlowFields with PHP attributes, no boilerplate
- 📊 **All Aggregates** - Supports `sum`, `count`, `avg`, `min`, `max`, and `exists`
- 🔧 **Redis & Memcached** - Designed for in-memory cache stores for true instant reads
- 🛡️ **Fault Tolerant** - Falls back to live queries if cache is unavailable
- 📦 **Zero Schema Changes** - No migrations, no database modifications
- 🔑 **Smart Invalidation** - Only invalidates when relevant columns change

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12

## Installation

Install via Composer:

```bash
composer require openplain/laravel-flowfield
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --tag=flowfield-config
```

## Quick Start

### 1. Define FlowFields on Your Parent Model

Add the `HasFlowFields` trait and use the `#[FlowField]` attribute on accessor methods:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Openplain\FlowField\Attributes\FlowField;
use Openplain\FlowField\Concerns\HasFlowFields;

class Customer extends Model
{
    use HasFlowFields;

    public function ledgerEntries()
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    #[FlowField(method: 'sum', relation: 'ledgerEntries', column: 'amount')]
    protected function balance(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'count', relation: 'ledgerEntries')]
    protected function entryCount(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }
}
```

### 2. Set Up Auto-Invalidation on Source Models

Add the `InvalidatesFlowFields` trait to models that contribute data:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Openplain\FlowField\Concerns\InvalidatesFlowFields;

class CustomerLedgerEntry extends Model
{
    use InvalidatesFlowFields;

    protected array $flowFieldTargets = [
        Customer::class => 'customer_id',
    ];
}
```

### 3. Use It

```php
$customer = Customer::find(1);

// First access: computes via SQL, caches the result
$customer->balance;    // 1250.75

// Second access: instant cache hit, no query
$customer->balance;    // 1250.75

// Create a new entry — cache is automatically invalidated
CustomerLedgerEntry::create([
    'customer_id' => 1,
    'amount' => 500,
]);

// Next access: recalculates transparently
$customer->balance;    // 1750.75
```

## Usage

### Defining FlowFields

The `#[FlowField]` attribute accepts the following parameters:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `method` | `string` | *(required)* | Aggregate function: `sum`, `count`, `avg`, `min`, `max`, `exists` |
| `relation` | `string` | *(required)* | Name of the Eloquent relationship method |
| `column` | `string` | `'*'` | Column to aggregate |
| `where` | `array` | `[]` | Static where conditions |
| `ttl` | `?int` | `null` | Cache TTL override in seconds |
| `cacheKey` | `?string` | `null` | Custom cache key suffix |

#### All Aggregate Methods

```php
#[FlowField(method: 'sum', relation: 'entries', column: 'amount')]
protected function balance(): Attribute { ... }

#[FlowField(method: 'count', relation: 'entries')]
protected function entryCount(): Attribute { ... }

#[FlowField(method: 'avg', relation: 'entries', column: 'amount')]
protected function averageAmount(): Attribute { ... }

#[FlowField(method: 'min', relation: 'entries', column: 'amount')]
protected function minAmount(): Attribute { ... }

#[FlowField(method: 'max', relation: 'entries', column: 'amount')]
protected function maxAmount(): Attribute { ... }

#[FlowField(method: 'exists', relation: 'entries')]
protected function hasEntries(): Attribute { ... }
```

#### Filtered Aggregates

Use the `where` parameter to aggregate a subset of records:

```php
#[FlowField(method: 'sum', relation: 'entries', column: 'amount', where: ['type' => 'invoice'])]
protected function totalInvoiced(): Attribute { ... }

#[FlowField(method: 'sum', relation: 'entries', column: 'amount', where: ['type' => 'credit'])]
protected function totalCredits(): Attribute { ... }

// Array values use whereIn
#[FlowField(method: 'count', relation: 'entries', where: ['status' => ['pending', 'processing']])]
protected function openEntryCount(): Attribute { ... }
```

### Automatic Invalidation

The `InvalidatesFlowFields` trait handles cache invalidation when source records are created, updated, deleted, or restored.

```php
class OrderLine extends Model
{
    use InvalidatesFlowFields;

    // Map parent models to their foreign key on this table
    protected array $flowFieldTargets = [
        Order::class => 'order_id',
        Customer::class => 'customer_id',
    ];
}
```

**Smart invalidation**: When a source record is updated, only FlowFields whose aggregated column or where-condition columns actually changed are invalidated. Updating an irrelevant column (like `notes`) won't bust the cache.

**Foreign key changes**: If a record moves from one parent to another (e.g., reassigning an order line), both the old and new parent's caches are invalidated.

### Manual Calculation

Force-recalculate specific fields (equivalent to NAV's `CALCFIELDS`):

```php
// Recalculate specific fields
$customer->calcFlowFields('balance', 'entry_count');

// Recalculate all FlowFields
$customer->calcFlowFields();
```

### Flushing Cache

```php
// Flush specific fields
$customer->flushFlowFields('balance');

// Flush all FlowFields for this record
$customer->flushFlowFields();
```

### Eager Computation with `withFlowFields`

FlowFields are **not** auto-appended to `toArray()`/`toJson()` to avoid N+1 queries when serializing collections. Use the `withFlowFields` scope to compute them eagerly:

```php
// Compute specific fields for all results
$customers = Customer::withFlowFields('balance', 'entry_count')->get();

// Compute all FlowFields
$customers = Customer::withFlowFields()->get();
```

### Sorting by FlowField

Sort query results by an aggregate value using a subquery:

```php
// Highest balance first
$customers = Customer::orderByFlowField('balance', 'desc')->get();

// Combine with other conditions
$customers = Customer::where('active', true)
    ->orderByFlowField('entry_count', 'desc')
    ->paginate(25);
```

### Inspecting Definitions

```php
$definitions = $customer->getFlowFieldDefinitions();
// Returns: ['balance' => FlowFieldDefinition, 'entry_count' => FlowFieldDefinition, ...]
```

## Configuration

All settings are in `config/flowfield.php`. All are optional with sensible defaults.

### Cache Store

Which cache store to use. Defaults to your application's default cache store.

```php
'cache' => [
    'store' => env('FLOWFIELD_CACHE_STORE', null),
],
```

### Cache TTL

Cache lifetime in seconds. Defaults to `null` (forever) since invalidation is event-driven. Set a value like `3600` as a safety net if you have processes that bypass Eloquent events (raw SQL, external systems).

```php
'cache' => [
    'ttl' => null,  // Forever — invalidation is event-driven
],
```

> **Note**: Individual FlowFields can override this via the `ttl` attribute parameter.

### Cache Key Prefix

Prefix for all FlowField cache keys. Useful to avoid collisions.

```php
'cache' => [
    'prefix' => 'flowfield',
],
```

### Auto-Warm

When enabled, the cache is immediately re-populated after invalidation instead of waiting for the next read.

```php
'auto_warm' => false,  // Set to true for always-hot caches
```

> **Note**: Enabling this adds a query on every write to source models. Only enable if read performance is critical.

### Tag-Based Invalidation

Uses cache tags when the driver supports them (Redis, Memcached) for efficient bulk invalidation. Falls back to prefix-based key management for simpler drivers.

```php
'tag_based' => true,
```

## Artisan Commands

### Warm Cache

Pre-populate FlowField caches:

```bash
# Warm all models with FlowFields (auto-discovers from app/Models)
php artisan flowfield:warm

# Warm a specific model
php artisan flowfield:warm "App\Models\Customer"

# Warm a specific record
php artisan flowfield:warm "App\Models\Customer" --id=42

# Warm a specific field only
php artisan flowfield:warm "App\Models\Customer" --field=balance
```

### Flush Cache

Clear cached FlowField values:

```bash
# Flush all FlowField caches
php artisan flowfield:flush

# Flush a specific model
php artisan flowfield:flush "App\Models\Customer"

# Flush a specific record
php artisan flowfield:flush "App\Models\Customer" --id=42
```

## Real-World Use Cases

### E-Commerce: Order Totals

```php
class Order extends Model
{
    use HasFlowFields;

    public function lines() { return $this->hasMany(OrderLine::class); }

    #[FlowField(method: 'sum', relation: 'lines', column: 'line_total')]
    protected function total(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'count', relation: 'lines')]
    protected function lineCount(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }
}
```

### SaaS: Subscription Metrics

```php
class Tenant extends Model
{
    use HasFlowFields;

    public function users() { return $this->hasMany(User::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }

    #[FlowField(method: 'count', relation: 'users')]
    protected function userCount(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'sum', relation: 'invoices', column: 'amount', where: ['status' => 'paid'])]
    protected function totalRevenue(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'exists', relation: 'invoices', where: ['status' => 'overdue'])]
    protected function hasOverdueInvoices(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }
}
```

### Inventory: Stock Levels

```php
class Product extends Model
{
    use HasFlowFields;

    public function stockMovements() { return $this->hasMany(StockMovement::class); }

    #[FlowField(method: 'sum', relation: 'stockMovements', column: 'quantity')]
    protected function inventoryQuantity(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }
}
```

## Limitations

- **Eventual consistency** - There's a brief window between a write and cache invalidation where stale data may be served. For most applications this is negligible.
- **Not for real-time dashboards** - If you need sub-second freshness on rapidly changing data, use live queries or database views instead.
- **In-memory cache recommended** - The package works with any Laravel cache driver, but Redis or Memcached are strongly recommended. Using file or database cache stores defeats the purpose — you'd just be trading one database query for another.
- **No cross-database relations** - FlowFields rely on standard Eloquent relationships within a single database connection.

## Inspiration

This package implements the **FlowField** concept from Microsoft Dynamics NAV/Business Central — virtual fields that display computed aggregates (Sum, Count, Average, Min, Max, Exist) without storing the result in the table.

The performance layer behind FlowFields has evolved over the decades:

- **SIFT** (Sum Index Field Technology) — the original optimization, specifically for Sum fields. On SQL Server, SIFT creates **indexed views** — materialized aggregates grouped by the key fields, maintained automatically by the database engine on every insert/update/delete. A Sum FlowField query hits the indexed view instead of scanning the base table.
- **NCCI** (Nonclustered Columnstore Indexes) — the modern successor to SIFT in Business Central. Instead of maintaining separate indexed views per SIFT key, a single columnstore index covers all aggregation scenarios with less write overhead.

We take a different approach: **cache as the performance layer**. Where Navision/Business Central relies on SQL Server features (indexed views, columnstore indexes), we use Redis or Memcached. The tradeoff is simplicity — no database schema changes, works with any database — at the cost of eventual consistency during the brief window between a write and cache invalidation.

For the curious:
- [FlowFields overview](https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/developer/devenv-flowfields)
- [SIFT technology](https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/developer/devenv-sift-technology)
- [SIFT and SQL Server](https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/developer/devenv-sift-and-sql-server)
- [Migrating from SIFT to NCCI](https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/developer/devenv-migrating-from-sift-to-ncci)

## Testing

```bash
composer test
```

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please email security@openplain.dev. All security vulnerabilities will be promptly addressed.

**Please do not** open public issues for security vulnerabilities.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

Built with ❤️ by [Openplain](https://openplain.dev)

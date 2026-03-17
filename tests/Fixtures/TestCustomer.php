<?php

namespace Openplain\FlowField\Tests\Fixtures;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Openplain\FlowField\Attributes\FlowField;
use Openplain\FlowField\Concerns\HasFlowFields;

class TestCustomer extends Model
{
    use HasFlowFields;

    protected $table = 'test_customers';

    protected $guarded = [];

    public function entries()
    {
        return $this->hasMany(TestEntry::class, 'customer_id');
    }

    #[FlowField(method: 'sum', relation: 'entries', column: 'amount')]
    protected function balance(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'sum', relation: 'entries', column: 'amount', where: ['type' => 'invoice'])]
    protected function totalInvoiced(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'count', relation: 'entries')]
    protected function entryCount(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'exists', relation: 'entries')]
    protected function hasEntries(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'avg', relation: 'entries', column: 'amount')]
    protected function averageAmount(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'min', relation: 'entries', column: 'amount')]
    protected function minAmount(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }

    #[FlowField(method: 'max', relation: 'entries', column: 'amount')]
    protected function maxAmount(): Attribute
    {
        return Attribute::make(get: fn () => null);
    }
}

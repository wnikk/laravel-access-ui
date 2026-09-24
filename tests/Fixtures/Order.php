<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Wnikk\LaravelAccessRules\Traits\HasAccessScope;

/**
 * The one model of the application the tests know: what conditions of the panel are about.
 */
class Order extends Model
{
    use HasAccessScope;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['locked' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}

<?php

namespace Weboldalnet\CommerceGls\Models;

use Illuminate\Database\Eloquent\Model;
use Weboldalnet\CommerceCore\Models\Concerns\UsesCommerceConnection;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string $group
 * @mixin \Eloquent
 */
class GlsSetting extends Model
{
    use UsesCommerceConnection;

    protected $table = 'commerce_gls_settings';

    protected $fillable = ['key', 'value', 'type', 'group'];

    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }
}

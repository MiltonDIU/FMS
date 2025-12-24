<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultType extends Model
{
    protected $fillable = ['type_name', 'is_active','sort_order', 'description'];
    public function education()
    {
        return $this->hasMany(Education::class, 'result_type_id');
    }
}

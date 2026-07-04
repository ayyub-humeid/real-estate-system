<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{

   public function featured()
{
    $units = Unit::with('property:id,name,address,description')
        ->with('images',function($query){
            $query->where('is_primary',true)->latest()->limit(1);
        })
        ->where('status', 'available')
        ->where('is_featured',1)
        ->latest()
        ->limit(3)
        ->get();


    return UnitResource::collection($units);

}
}
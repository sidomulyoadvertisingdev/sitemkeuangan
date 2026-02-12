<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:income,expense',
        ]);

        $category = Category::create([
            'user_id' => auth()->id(),
            'name'    => $request->name,
            'type'    => $request->type,
        ]);

        return response()->json($category);
    }
}

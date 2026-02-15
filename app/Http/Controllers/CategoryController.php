<?php

namespace App\Http\Controllers;

use App\Models\Categories; // Asegúrate de que tu modelo se llame así
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categorias = Categories::all();
        return response()->json($categorias);
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class CategoryController extends Controller
{
    private function sendResponse($data, $message, $code = 200)
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $code);
    }

    private function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = ['success' => false, 'message' => $error];
        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }
        return response()->json($response, $code);
    }

    // GET /admin/categorias
    public function index()
    {
        try {
            $categories = Categories::withCount('petitions')->get();
            return $this->sendResponse($categories, 'Categorías recuperadas con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar categorías', $e->getMessage(), 500);
        }
    }

    // GET /admin/categorias/{id}
    public function show($id)
    {
        try {
            $category = Categories::withCount('petitions')->find($id);
            if (!$category) {
                return $this->sendError('Categoría no encontrada');
            }
            return $this->sendResponse($category, 'Categoría encontrada');
        } catch (Exception $e) {
            return $this->sendError('Error en el servidor', $e->getMessage(), 500);
        }
    }

    // POST /admin/categorias
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors(), 422);
        }

        try {
            $category = Categories::create(['name' => $request->name]);
            return $this->sendResponse($category, 'Categoría creada con éxito', 201);
        } catch (Exception $e) {
            return $this->sendError('Error al crear categoría', $e->getMessage(), 500);
        }
    }

    // PUT /admin/categorias/{id}
    public function update(Request $request, $id)
    {
        try {
            $category = Categories::find($id);
            if (!$category) {
                return $this->sendError('Categoría no encontrada');
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:categories,name,' . $id,
            ]);

            if ($validator->fails()) {
                return $this->sendError('Error de validación', $validator->errors(), 422);
            }

            $category->name = $request->name;
            $category->save();

            return $this->sendResponse($category, 'Categoría actualizada con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al actualizar categoría', $e->getMessage(), 500);
        }
    }

    // DELETE /admin/categorias/{id}
    public function destroy($id)
    {
        try {
            $category = Categories::withCount('petitions')->find($id);
            if (!$category) {
                return $this->sendError('Categoría no encontrada');
            }

            if ($category->petitions_count > 0) {
                return $this->sendError(
                    'No se puede eliminar: la categoría tiene peticiones asociadas.',
                    [],
                    409
                );
            }

            $category->delete();

            return $this->sendResponse(null, 'Categoría eliminada con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al eliminar categoría', $e->getMessage(), 500);
        }
    }
}

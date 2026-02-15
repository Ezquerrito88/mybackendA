<?php

namespace App\Http\Controllers;

use App\Models\Petitions;
use App\Models\Categories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;

class PetitionController extends Controller
{
    // --- MÉTODOS AUXILIARES PARA RESPUESTAS JSON ---
    private function sendResponse($data, $message, $code = 200) {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message
        ], $code);
    }

    private function sendError($error, $errorMessages = [], $code = 404) {
        $response = [
            'success' => false,
            'message' => $error,
        ];
        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }
        return response()->json($response, $code);
    }

    // 1. LISTAR PETICIONES
    public function index(Request $request)
    {
        try {
            // Cargamos la categoría y el usuario.
            $petitions = Petitions::with(['user', 'category'])->get(); 
            return $this->sendResponse($petitions, 'Peticiones recuperadas con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar peticiones', $e->getMessage(), 500);
        }
    }

    // 2. MOSTRAR UNA PETICIÓN
    public function show($id)
    {
        try {
            $petition = Petitions::with(['user', 'category'])->find($id);
            if (is_null($petition)) {
                return $this->sendError('Petición no encontrada');
            }
            return $this->sendResponse($petition, 'Petición encontrada');
        } catch (Exception $e) {
            return $this->sendError('Error en el servidor', $e->getMessage(), 500);
        }
    }

    // 3. CREAR PETICIÓN
    public function store(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'titulo'       => 'required|max:255',
            'descripcion'  => 'required',
            'destinatario' => 'required',
            'categoria_id' => 'required|exists:categories,id',
            'file'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors(), 422);
        }

        try {
            $input['user_id'] = Auth::id();
            $input['firmantes'] = 0;
            $input['estado'] = 'pendiente';

            if ($request->hasFile('file')) {
                $path = $request->file('file')->store('peticiones', 'public');
                $input['file'] = $path;
            }

            $petition = Petitions::create($input);

            return $this->sendResponse($petition, 'Petición creada con éxito', 201);

        } catch (Exception $e) {
            return $this->sendError('Error al crear la petición', $e->getMessage(), 500);
        }
    }

    // 4. ACTUALIZAR PETICIÓN
    public function update(Request $request, $id)
    {
        try {
            $petition = Petitions::find($id);
            if (!$petition) return $this->sendError('Petición no encontrada');

            // Verificar si el usuario es el dueño
            if ($petition->user_id !== Auth::id()) {
                return $this->sendError('No autorizado', [], 403);
            }

            $input = $request->all();
            
            // Validación
            $validator = Validator::make($input, [
                'titulo'       => 'required|max:255',
                'descripcion'  => 'required',
                'destinatario' => 'required',
                'categoria_id' => 'required|exists:categories,id',
                'file'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:80240',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Error de validación', $validator->errors(), 422);
            }

            // Actualizamos campos de texto
            $petition->titulo = $input['titulo'];
            $petition->descripcion = $input['descripcion'];
            $petition->destinatario = $input['destinatario'];
            $petition->categoria_id = $input['categoria_id'];

            if ($request->hasFile('file')) {
                // 1. Borrar imagen vieja si existe
                if ($petition->file) {
                    Storage::disk('public')->delete($petition->file);
                }
                // 2. Subir nueva
                $path = $request->file('file')->store('peticiones', 'public');
                $petition->file = $path;
            }

            $petition->save();

            return $this->sendResponse($petition, 'Petición actualizada con éxito');

        } catch (Exception $e) {
            return $this->sendError('Error al actualizar', $e->getMessage(), 500);
        }
    }

    // 5. BORRAR PETICIÓN
    public function destroy(Request $request, $id)
    {
        try {
            $petition = Petitions::find($id);
            if (!$petition) return $this->sendError('Petición no encontrada');

            if ($petition->user_id !== Auth::id()) {
                return $this->sendError('No autorizado', [], 403);
            }

            // Borrar la imagen del disco para limpiar basura
            if ($petition->file) {
                Storage::disk('public')->delete($petition->file);
            }
            
            $petition->delete();

            return $this->sendResponse(null, 'Petición eliminada con éxito');

        } catch (Exception $e) {
            return $this->sendError('Error al eliminar', $e->getMessage(), 500);
        }
    }

    // 6. LISTAR MIS PETICIONES
    public function listMine()
    {
        try {
            $user = Auth::user();
            $petitions = Petitions::where('user_id', $user->id)
                ->with(['category'])
                ->get();
            return $this->sendResponse($petitions, 'Mis peticiones recuperadas');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar tus peticiones', $e->getMessage(), 500);
        }
    }

    // 7. FIRMAR PETICIÓN
    public function firmar(Request $request, $id)
    {
        try {
            $petition = Petitions::findOrFail($id);
            $user = Auth::user();

            // Verificar si ya firmó
            if ($petition->firmas()->where('user_id', $user->id)->exists()) {
                return $this->sendError('Ya has firmado esta petición', [], 403);
            }

            // Guardar firma y sumar contador
            $petition->firmas()->attach($user->id);
            $petition->increment('firmantes');
            
            return $this->sendResponse($petition, 'Petición firmada con éxito', 201);

        } catch (Exception $e) {
            return $this->sendError('No se pudo firmar', $e->getMessage(), 500);
        }
    }

    // 8. CAMBIAR ESTADO
    public function cambiarEstado(Request $request, $id)
    {
        try {
            $petition = Petitions::findOrFail($id);
            $petition->estado = 'aceptada';
            $petition->save();
            return $this->sendResponse($petition, 'Estado actualizado');
        } catch (Exception $e) {
            return $this->sendError('Error al cambiar estado', $e->getMessage(), 500);
        }
    }
}
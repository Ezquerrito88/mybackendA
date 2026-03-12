<?php

namespace App\Http\Controllers;

use App\Models\Petitions;
use App\Models\Categories;
use App\Models\Files;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;

class PetitionController extends Controller
{
    // --- MÉTODOS AUXILIARES PARA RESPUESTAS JSON ---
    private function sendResponse($data, $message, $code = 200)
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message
        ], $code);
    }

    private function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];
        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }
        return response()->json($response, $code);
    }

    // 1. Listar peticiones
    public function index(Request $request)
    {
        try {
            $query = Petitions::with(['user', 'category', 'files']);

            $estado = $request->query('estado');
            if ($estado && $estado !== 'todas') {
                $query->where('estado', $estado);
            }

            $categoriaId = $request->query('categoria_id');
            if ($categoriaId) {
                $query->where('categoria_id', $categoriaId);
            }

            $petitions = $query->latest()->paginate(6);

            return $this->sendResponse($petitions, 'Peticiones recuperadas con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar peticiones', $e->getMessage(), 500);
        }
    }


    // 2. Mostrar una petición
    public function show($id)
    {
        try {
            $petition = Petitions::with(['user', 'category', 'files'])->find($id);
            if (is_null($petition)) {
                return $this->sendError('Petición no encontrada');
            }
            return $this->sendResponse($petition, 'Petición encontrada');
        } catch (Exception $e) {
            return $this->sendError('Error en el servidor', $e->getMessage(), 500);
        }
    }

    // 3. Crear petición
    public function store(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'titulo'       => 'required|max:255',
            'descripcion'  => 'required',
            'destinatario' => 'required',
            'categoria_id' => 'required|exists:categories,id',
            'files'        => 'nullable|array',
            'files.*'      => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors(), 422);
        }

        try {
            $input['user_id']   = Auth::id();
            $input['firmantes'] = 0;
            $input['estado']    = 'pendiente';

            unset($input['files']);

            $petition = Petitions::create($input);


            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('peticiones', 'public');

                    $petition->files()->create([
                        'file_path' => $path,
                        'name' => $file->getClientOriginalName()
                    ]);
                }
            }

            return $this->sendResponse($petition->load('files'), 'Petición creada con éxito', 201);
        } catch (Exception $e) {
            return $this->sendError('Error al crear la petición', $e->getMessage(), 500);
        }
    }

    // 4. Actualizar petición
    public function update(Request $request, $id)
    {
        try {
            $petition = Petitions::find($id);
            if (!$petition) return $this->sendError('Petición no encontrada');

            if ($petition->user_id !== Auth::id()) {
                return $this->sendError('No autorizado', [], 403);
            }

            $validator = Validator::make($request->all(), [
                'titulo'       => 'sometimes|max:255',
                'descripcion'  => 'sometimes',
                'destinatario' => 'sometimes',
                'categoria_id' => 'sometimes|exists:categories,id',
                'file'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:80240',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Error de validación', $validator->errors(), 422);
            }

            $petition->titulo       = $request->input('titulo', $petition->titulo);
            $petition->descripcion  = $request->input('descripcion', $petition->descripcion);
            $petition->destinatario = $request->input('destinatario', $petition->destinatario);
            $petition->categoria_id = $request->input('categoria_id', $petition->categoria_id);

            if ($request->hasFile('file')) {
                if ($petition->file) {
                    Storage::disk('public')->delete($petition->file);
                }
                $path = $request->file('file')->store('peticiones', 'public');
                $petition->file = $path;
            }

            $petition->save();

            return $this->sendResponse($petition->load('files'), 'Petición actualizada con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al actualizar', $e->getMessage(), 500);
        }
    }

    // 5. Borrar petición
    public function destroy(Request $request, $id)
    {
        try {
            $petition = Petitions::with('files')->find($id);
            if (!$petition) return $this->sendError('Petición no encontrada');

            if ($petition->user_id !== Auth::id()) {
                return $this->sendError('No autorizado', [], 403);
            }

            if ($petition->file) {
                Storage::disk('public')->delete($petition->file);
            }

            if ($petition->files->count() > 0) {
                foreach ($petition->files as $file) {
                    Storage::disk('public')->delete($file->file_path);
                }
            }

            $petition->delete();

            return $this->sendResponse(null, 'Petición eliminada con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al eliminar', $e->getMessage(), 500);
        }
    }

    // 6. Listar mis peticiones
    public function listMine()
    {
        try {
            $user = Auth::user();
            $petitions = Petitions::where('user_id', $user->id)
                ->with(['category', 'files'])
                ->get();
            return $this->sendResponse($petitions, 'Mis peticiones recuperadas');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar tus peticiones', $e->getMessage(), 500);
        }
    }

    // 7. Firmar petición
    public function firmar(Request $request, $id)
    {
        try {
            $petition = Petitions::findOrFail($id);
            $user = Auth::user();

            if ($petition->firmas()->where('user_id', $user->id)->exists()) {
                return $this->sendError('Ya has firmado esta petición', [], 403);
            }

            $petition->firmas()->attach($user->id);
            $petition->increment('firmantes');

            return $this->sendResponse($petition, 'Petición firmada con éxito', 201);
        } catch (Exception $e) {
            return $this->sendError('No se pudo firmar', $e->getMessage(), 500);
        }
    }

    // 8. Cambiar estado
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

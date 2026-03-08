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

    // 1. LISTAR PETICIONES ✅ CORREGIDO
    public function index(Request $request)
    {
        try {
            // AÑADIDO 'files' a las relaciones
            $petitions = Petitions::with(['user', 'category', 'files'])->get();
            return $this->sendResponse($petitions, 'Peticiones recuperadas con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar peticiones', $e->getMessage(), 500);
        }
    }

    // 2. MOSTRAR UNA PETICIÓN
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

    // 3. CREAR PETICIÓN
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

            // ✅ PROCESAR MÚLTIPLES ARCHIVOS CON NOMBRE
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('peticiones', 'public');

                    $petition->files()->create([
                        'file_path' => $path,
                        'name' => $file->getClientOriginalName()  // ← FIJA EL ERROR SQL
                    ]);
                }
            }

            return $this->sendResponse($petition->load('files'), 'Petición creada con éxito', 201);
        } catch (Exception $e) {
            return $this->sendError('Error al crear la petición', $e->getMessage(), 500);
        }
    }

    // 4. ACTUALIZAR PETICIÓN (pendiente de migrar a múltiples archivos)
    public function update(Request $request, $id)
    {
        try {
            $petition = Petitions::find($id);
            if (!$petition) return $this->sendError('Petición no encontrada');

            if ($petition->user_id !== Auth::id()) {
                return $this->sendError('No autorizado', [], 403);
            }

            $input = $request->all();

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

            $petition->titulo = $input['titulo'];
            $petition->descripcion = $input['descripcion'];
            $petition->destinatario = $input['destinatario'];
            $petition->categoria_id = $input['categoria_id'];

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

    // 5. BORRAR PETICIÓN ✅ CORREGIDO (Borra archivos múltiples físicos)
    public function destroy(Request $request, $id)
    {
        try {
            // Cargamos la petición con sus archivos
            $petition = Petitions::with('files')->find($id);
            if (!$petition) return $this->sendError('Petición no encontrada');

            if ($petition->user_id !== Auth::id()) {
                return $this->sendError('No autorizado', [], 403);
            }

            // Borramos el archivo antiguo si lo tuviera (para compatibilidad)
            if ($petition->file) {
                Storage::disk('public')->delete($petition->file);
            }

            // Borramos los archivos múltiples del disco físico
            if ($petition->files->count() > 0) {
                foreach ($petition->files as $file) {
                    Storage::disk('public')->delete($file->file_path);
                }
            }

            // Eliminamos la petición (los registros de files se borrarán si hay onDelete('cascade') en la migración)
            $petition->delete();

            return $this->sendResponse(null, 'Petición eliminada con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al eliminar', $e->getMessage(), 500);
        }
    }

    // 6. LISTAR MIS PETICIONES ✅ CORREGIDO
    public function listMine()
    {
        try {
            $user = Auth::user();
            $petitions = Petitions::where('user_id', $user->id)
                ->with(['category', 'files']) // AÑADIDO 'files'
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
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Petitions;
use App\Models\Files;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Exception;

class PetitionController extends Controller
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

    // GET /admin/peticiones
    public function index(Request $request)
    {
        try {
            $query = Petitions::with(['user', 'category', 'files']);

            if ($request->filled('estado') && $request->estado !== 'todas') {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('categoria_id')) {
                $query->where('categoria_id', $request->categoria_id);
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $petitions = $query->latest()->paginate($request->get('per_page', 15));

            return $this->sendResponse($petitions, 'Peticiones recuperadas con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar peticiones', $e->getMessage(), 500);
        }
    }

    // GET /admin/peticiones/{id}
    public function show($id)
    {
        try {
            $petition = Petitions::with(['user', 'category', 'files', 'firmas'])->find($id);
            if (!$petition) {
                return $this->sendError('Petición no encontrada');
            }
            return $this->sendResponse($petition, 'Petición encontrada');
        } catch (Exception $e) {
            return $this->sendError('Error en el servidor', $e->getMessage(), 500);
        }
    }

    // POST /admin/peticiones
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo'       => 'required|max:255',
            'descripcion'  => 'required',
            'destinatario' => 'required',
            'categoria_id' => 'required|exists:categories,id',
            'user_id'      => 'required|exists:users,id',
            'estado'       => 'in:pendiente,activa,cerrada',
            'files'        => 'nullable|array',
            'files.*'      => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors(), 422);
        }

        try {
            $data = $request->only(['titulo', 'descripcion', 'destinatario', 'categoria_id', 'user_id']);
            $data['firmantes'] = 0;
            $data['estado']    = $request->input('estado', 'pendiente');

            $petition = Petitions::create($data);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('peticiones', 'public');
                    $petition->files()->create([
                        'file_path' => $path,
                        'name'      => $file->getClientOriginalName(),
                    ]);
                }
            }

            return $this->sendResponse($petition->load('files'), 'Petición creada con éxito', 201);
        } catch (Exception $e) {
            return $this->sendError('Error al crear la petición', $e->getMessage(), 500);
        }
    }

    // PUT /admin/peticiones/{id}
    public function update(Request $request, $id)
    {
        try {
            $petition = Petitions::find($id);
            if (!$petition) {
                return $this->sendError('Petición no encontrada');
            }

            $validator = Validator::make($request->all(), [
                'titulo'       => 'sometimes|max:255',
                'descripcion'  => 'sometimes',
                'destinatario' => 'sometimes',
                'categoria_id' => 'sometimes|exists:categories,id',
                'user_id'      => 'sometimes|exists:users,id',
                'estado'       => 'sometimes|in:pendiente,activa,cerrada',
                'files'        => 'nullable|array',
                'files.*'      => 'file|mimes:jpg,jpeg,png,pdf|max:10240',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Error de validación', $validator->errors(), 422);
            }

            $petition->fill($request->only([
                'titulo', 'descripcion', 'destinatario',
                'categoria_id', 'user_id', 'estado',
            ]));

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('peticiones', 'public');
                    $petition->files()->create([
                        'file_path' => $path,
                        'name'      => $file->getClientOriginalName(),
                    ]);
                }
            }

            $petition->save();

            return $this->sendResponse($petition->load('files'), 'Petición actualizada con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al actualizar', $e->getMessage(), 500);
        }
    }

    // PATCH /admin/peticiones/{id}/estado
    public function cambiarEstado(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:pendiente,activa,cerrada',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Estado inválido', $validator->errors(), 422);
        }

        try {
            $petition = Petitions::find($id);
            if (!$petition) {
                return $this->sendError('Petición no encontrada');
            }

            $petition->estado = $request->estado;
            $petition->save();

            return $this->sendResponse($petition, 'Estado actualizado con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al cambiar estado', $e->getMessage(), 500);
        }
    }

    // DELETE /admin/peticiones/{id}
    public function destroy($id)
    {
        try {
            $petition = Petitions::with('files')->find($id);
            if (!$petition) {
                return $this->sendError('Petición no encontrada');
            }

            foreach ($petition->files as $file) {
                Storage::disk('public')->delete($file->file_path);
            }

            if ($petition->file) {
                Storage::disk('public')->delete($petition->file);
            }

            $petition->delete();

            return $this->sendResponse(null, 'Petición eliminada con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al eliminar', $e->getMessage(), 500);
        }
    }
}

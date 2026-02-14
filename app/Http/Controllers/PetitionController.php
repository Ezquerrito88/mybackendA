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


    public function index(Request $request)
    {
        try {
            $petitions = Petitions::with(['user', 'categoria', 'files'])->get();
            return $this->sendResponse($petitions, 'Peticiones recuperadas con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar peticiones', $e->getMessage(), 500);
        }
    }

    public function show($id)
    {
        try {
            $petition = Petitions::with(['user', 'categoria', 'files'])->find($id);
            if (is_null($petition)) {
                return $this->sendError('Petición no encontrada');
            }
            return $this->sendResponse($petition, 'Petición encontrada');
        } catch (Exception $e) {
            return $this->sendError('Error en el servidor', $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'titulo'       => 'required|max:255',
            'descripcion'  => 'required',
            'destinatario' => 'required',
            'categoria_id' => 'required|exists:categories,id',
            'file'         => 'required|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors(), 422);
        }

        try {
            DB::beginTransaction();

            // 1. Crear Petición
            $petition = new Petitions($input);
            $petition->user_id = Auth::id();
            $petition->firmantes = 0;
            $petition->estado = 'pendiente';
            $petition->save();

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('peticiones', 'public');

                $fileModel = new Files();
                $fileModel->name = $file->getClientOriginalName();
                $fileModel->file_path = $path;
                $fileModel->petition_id = $petition->id;
                $fileModel->save();
            }

            DB::commit();
            
            return $this->sendResponse($petition->load('files'), 'Petición creada con éxito', 201);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError('Error al crear la petición', $e->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $petition = Petitions::find($id);
            if (!$petition) return $this->sendError('Petición no encontrada');

            if ($request->user()->cannot('update', $petition)) {
                return $this->sendError('No autorizado para editar esta petición', [], 403);
            }

            $input = $request->all();
            
            $validator = Validator::make($input, [
                'titulo'       => 'required|max:255',
                'descripcion'  => 'required',
                'destinatario' => 'required',
                'categoria_id' => 'required|exists:categories,id',
                'file'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Error de validación', $validator->errors(), 422);
            }

            $petition->titulo = $input['titulo'];
            $petition->descripcion = $input['descripcion'];
            $petition->destinatario = $input['destinatario'];
            $petition->categoria_id = $input['categoria_id'];
            $petition->save();

            if ($request->hasFile('file')) {
                foreach ($petition->files as $oldFile) {
                    Storage::disk('public')->delete($oldFile->file_path);
                    $oldFile->delete();
                }

                $file = $request->file('file');
                $path = $file->store('peticiones', 'public');

                $newFile = new Files();
                $newFile->name = $file->getClientOriginalName();
                $newFile->file_path = $path;
                $newFile->petition_id = $petition->id;
                $newFile->save();
            }

            return $this->sendResponse($petition->load('files'), 'Petición actualizada con éxito');

        } catch (Exception $e) {
            return $this->sendError('Error al actualizar', $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $petition = Petitions::find($id);
            if (!$petition) return $this->sendError('Petición no encontrada');

            // POLICY: Verificar dueño
            if ($request->user()->cannot('delete', $petition)) {
                return $this->sendError('No autorizado para eliminar', [], 403);
            }

            foreach ($petition->files as $file) {
                Storage::disk('public')->delete($file->file_path);
            }
            
            $petition->delete();

            return $this->sendResponse(null, 'Petición eliminada con éxito');

        } catch (Exception $e) {
            return $this->sendError('Error al eliminar', $e->getMessage(), 500);
        }
    }

    public function listMine()
    {
        try {
            $user = Auth::user();
            $petitions = Petitions::where('user_id', $user->id)
                ->with(['user', 'categoria', 'files'])
                ->get();
            return $this->sendResponse($petitions, 'Mis peticiones recuperadas');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar tus peticiones', $e->getMessage(), 500);
        }
    }

    public function firmar(Request $request, $id)
    {
        try {
            $petition = Petitions::findOrFail($id);
            $user = Auth::user();

            if ($petition->firmas()->where('user_id', $user->id)->exists()) {
                return $this->sendError('Ya has firmado esta petición', [], 403);
            }

            DB::transaction(function () use ($petition, $user) {
                $petition->firmas()->attach($user->id);
                $petition->increment('firmantes');
            });
            
            return $this->sendResponse($petition, 'Petición firmada con éxito', 201);

        } catch (Exception $e) {
            return $this->sendError('No se pudo firmar la petición', $e->getMessage(), 500);
        }
    }

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
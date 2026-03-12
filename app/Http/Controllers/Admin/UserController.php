<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Exception;

class UserController extends Controller
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

    // GET /admin/users
    public function index(Request $request)
    {
        try {
            $query = User::query();

            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%');
                });
            }

            $users = $query->latest()->paginate($request->get('per_page', 15));

            return $this->sendResponse($users, 'Usuarios recuperados con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al recuperar usuarios', $e->getMessage(), 500);
        }
    }

    // GET /admin/users/{id}
    public function show($id)
    {
        try {
            $user = User::with('peticiones')->find($id);
            if (!$user) {
                return $this->sendError('Usuario no encontrado');
            }
            return $this->sendResponse($user, 'Usuario encontrado');
        } catch (Exception $e) {
            return $this->sendError('Error en el servidor', $e->getMessage(), 500);
        }
    }

    // POST /admin/users
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role'     => 'in:user,admin',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error de validación', $validator->errors(), 422);
        }

        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => $request->input('role', 'user'),
            ]);

            return $this->sendResponse($user, 'Usuario creado con éxito', 201);
        } catch (Exception $e) {
            return $this->sendError('Error al crear usuario', $e->getMessage(), 500);
        }
    }

    // PUT /admin/users/{id}
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return $this->sendError('Usuario no encontrado');
            }

            $validator = Validator::make($request->all(), [
                'name'     => 'sometimes|string|max:255',
                'email'    => 'sometimes|email|max:255|unique:users,email,' . $id,
                'password' => 'sometimes|string|min:6',
                'role'     => 'sometimes|in:user,admin',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Error de validación', $validator->errors(), 422);
            }

            if ($request->filled('name')) {
                $user->name = $request->name;
            }
            if ($request->filled('email')) {
                $user->email = $request->email;
            }
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            if ($request->filled('role')) {
                $user->role = $request->role;
            }

            $user->save();

            return $this->sendResponse($user, 'Usuario actualizado con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al actualizar usuario', $e->getMessage(), 500);
        }
    }

    // DELETE /admin/users/{id}
    public function destroy($id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return $this->sendError('Usuario no encontrado');
            }

            // Prevent self-deletion
            if ($user->id === auth()->guard('api')->id()) {
                return $this->sendError('No puedes eliminar tu propio usuario', [], 403);
            }

            $user->delete();

            return $this->sendResponse(null, 'Usuario eliminado con éxito');
        } catch (Exception $e) {
            return $this->sendError('Error al eliminar usuario', $e->getMessage(), 500);
        }
    }
}

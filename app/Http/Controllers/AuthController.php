<?php

namespace App\Http\Controllers;

// Importaciones necesarias para manejar peticiones, seguridad y modelos
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * REGISTRO DE USUARIOS
     */
    public function register(Request $request)
    {
        // Validación de datos
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422); // Error de validación
        }

        // Creación del usuario con contraseña encriptada (Hash)
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'user' => $user
        ], 201);
    }

    /**
     * LOGIN JWT 
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Intento de autenticación mediante el guard de la API
        if (! $token = auth()->guard('api')->attempt($credentials)) {
            // Error 401: Credenciales incorrectas 
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * PERFIL DEL USUARIO (ME) 
     */
    public function me()
    {
        return response()->json(auth()->guard('api')->user());
    }

    /**
     * CIERRE DE SESIÓN
     */
    public function logout()
    {
        auth()->guard('api')->logout();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    /**
     * REFRESH TOKEN
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->guard('api')->refresh());
    }

    /**
     * ESTRUCTURA DE RESPUESTA DEL TOKEN
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->guard('api')->factory()->getTTL() * 60,
            'user' => auth()->guard('api')->user()
        ]);
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Petitions;
use App\Models\User;
use App\Models\Categories;
use Illuminate\Support\Facades\DB;
use Exception;

class DashboardController extends Controller
{
    public function stats()
    {
        try {
            $totalPeticiones   = Petitions::count();
            $totalUsers        = User::count();
            $totalCategorias   = Categories::count();

            $peticionesPorEstado = Petitions::select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->pluck('total', 'estado');

            $recentPeticiones = Petitions::with(['user', 'category'])
                ->latest()
                ->limit(5)
                ->get();

            $recentUsers = User::latest()->limit(5)->get(['id', 'name', 'email', 'role', 'created_at']);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_peticiones'    => $totalPeticiones,
                    'total_users'         => $totalUsers,
                    'total_categorias'    => $totalCategorias,
                    'peticiones_estado'   => $peticionesPorEstado,
                    'recent_peticiones'   => $recentPeticiones,
                    'recent_users'        => $recentUsers,
                ],
                'message' => 'Estadísticas del panel obtenidas con éxito',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }
}

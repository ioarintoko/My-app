<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    // GET /api/movies
    public function index(Request $request): JsonResponse
    {
        $query = Movie::query();

        if ($request->filled('genre')) {
            $query->where('genre', $request->genre);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $movies = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Daftar movie berhasil diambil',
            'data' => $movies,
        ]);
    }

    // GET /api/movies/{movie}
    public function show(Movie $movie): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail movie berhasil diambil',
            'data' => $movie,
        ]);
    }

    // DELETE /api/movies/{movie}
    public function destroy(Movie $movie): JsonResponse
    {
        $movie->delete();

        return response()->json([
            'success' => true,
            'message' => 'Movie berhasil dihapus',
        ]);
    }
}
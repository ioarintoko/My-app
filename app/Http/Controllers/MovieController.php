<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovieRequest;
use App\Models\Movie;
use Illuminate\Http\JsonResponse;

class MovieController extends Controller
{
    // GET /api/movies
    public function index(): JsonResponse
    {
        $movies = Movie::latest()->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Daftar movie berhasil diambil',
            'data' => $movies,
        ]);
    }

    // POST /api/movies
    public function store(MovieRequest $request): JsonResponse
    {
        $movie = Movie::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Movie berhasil ditambahkan',
            'data' => $movie,
        ], 201);
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

    // PUT/PATCH /api/movies/{movie}
    public function update(MovieRequest $request, Movie $movie): JsonResponse
    {
        $movie->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Movie berhasil diperbarui',
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
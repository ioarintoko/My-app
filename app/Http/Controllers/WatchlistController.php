<?php

namespace App\Http\Controllers;

use App\Http\Requests\WatchlistRequest;
use App\Models\Watchlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WatchlistController extends Controller
{
    // GET /api/watchlists
    public function index(): JsonResponse
    {
        $watchlists = Watchlist::with('movie')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Daftar watchlist berhasil diambil',
            'data' => $watchlists,
        ]);
    }

    // POST /api/watchlists
    public function store(WatchlistRequest $request): JsonResponse
    {
        $exists = Watchlist::where('user_id', Auth::id())
            ->where('movie_id', $request->movie_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Movie ini sudah ada di watchlist kamu',
            ], 409);
        }

        $watchlist = Watchlist::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        Log::channel('transactions')->info('Watchlist created', [
            'user_id' => Auth::id(),
            'watchlist_id' => $watchlist->id,
            'movie_id' => $watchlist->movie_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Watchlist berhasil ditambahkan',
            'data' => $watchlist->load('movie'),
        ], 201);
    }

    // GET /api/watchlists/{watchlist}
    public function show(Watchlist $watchlist): JsonResponse
    {
        if ($watchlist->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu tidak punya akses ke watchlist ini',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail watchlist berhasil diambil',
            'data' => $watchlist->load('movie'),
        ]);
    }

    // PUT/PATCH /api/watchlists/{watchlist}
    public function update(WatchlistRequest $request, Watchlist $watchlist): JsonResponse
    {
        if ($watchlist->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu tidak punya akses ke watchlist ini',
            ], 403);
        }

        $watchlist->update($request->validated());

        Log::channel('transactions')->info('Watchlist updated', [
            'user_id' => Auth::id(),
            'watchlist_id' => $watchlist->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Watchlist berhasil diperbarui',
            'data' => $watchlist->load('movie'),
        ]);
    }

    // DELETE /api/watchlists/{watchlist}
    public function destroy(Watchlist $watchlist): JsonResponse
    {
        if ($watchlist->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu tidak punya akses ke watchlist ini',
            ], 403);
        }

        $watchlist->delete();

        Log::channel('transactions')->info('Watchlist deleted', [
            'user_id' => Auth::id(),
            'watchlist_id' => $watchlist->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Watchlist berhasil dihapus',
        ]);
    }
}
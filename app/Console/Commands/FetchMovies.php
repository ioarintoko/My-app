<?php

namespace App\Console\Commands;

use App\Models\Movie;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchMovies extends Command
{
    protected $signature = 'movies:fetch {genre=comedy}';

    protected $description = 'Fetch movies from Sample API and store into local database';

    public function handle(): int
    {
        $genre = $this->argument('genre');
        $url = "https://api.sampleapis.com/movies/{$genre}";

        $this->info("Fetching movies from: {$url}");

        try {
            $response = Http::timeout(15)
                ->retry(3, 2000, function (\Throwable $exception, $request) {
                    // retry hanya untuk rate limit / server error, bukan client error biasa
                    if (!$exception instanceof RequestException) {
                        return false;
                    }

                    $status = $exception->response->status();

                    return in_array($status, [429, 500, 502, 503]);
                }, throw: false)
                ->get($url);

            if ($response->status() === 429) {
                $this->error('Rate limited by Sample API (429). Coba lagi beberapa saat lagi.');
                Log::warning('FetchMovies rate limited', [
                    'url' => $url,
                    'status' => 429,
                ]);
                return self::FAILURE;
            }

            if (!$response->successful()) {
                $this->error("Failed to fetch data. Status: {$response->status()}");
                Log::error('FetchMovies failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return self::FAILURE;
            }

            $movies = $response->json();

            if (empty($movies)) {
                $this->warn('No movies returned from API.');
                return self::SUCCESS;
            }

            $count = 0;

            foreach ($movies as $item) {
                Movie::updateOrCreate(
                    [
                        'external_id' => $item['id'] ?? null,
                        'genre' => $genre,
                    ],
                    [
                        'title' => $item['title'] ?? 'Untitled',
                        'poster_url' => $item['posterURL'] ?? null,
                        'imdb_id' => $item['imdbId'] ?? null,
                        'year' => $item['year'] ?? null,
                        'plot' => $item['plot'] ?? null,
                    ]
                );
                $count++;
            }

            $this->info("Successfully synced {$count} movies (genre: {$genre}).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error: {$e->getMessage()}");
            Log::error('FetchMovies exception', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);
            return self::FAILURE;
        }
    }
}
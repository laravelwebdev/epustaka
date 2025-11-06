<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class Booklist
{
    protected string $baseUrl = 'https://api2-ipusnas.perpusnas.go.id/api/webhook';
    protected string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Ambil daftar buku berdasarkan kategori
     *
     * @param int|string $categoryId
     * @param int $offset
     * @param int $limit
     * @return array
     * @throws \Exception
     */
    public function fetchBookList($categoryId, int $offset = 0, int $limit = 1): array
    {
        $url = "{$this->baseUrl}/book-list";

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Origin' => 'https://ipusnas2.perpusnas.go.id',
                'Referer' => 'https://ipusnas2.perpusnas.go.id/',
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) ' .
                                'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36',
            ])->get($url, [
                'limit' => $limit,
                'offset' => $offset,
                'sort' => 'created_at',
                'category_ids' => $categoryId,
            ]);

            $response->throw();

            return $response->json();
        } catch (RequestException $e) {
            if ($e->response) {
                throw new \Exception("Failed to fetch list: " .
                    $e->response->status() . ' ' . $e->response->reason());
            } else {
                throw new \Exception("Network error: " . $e->getMessage());
            }
        }
    }
}

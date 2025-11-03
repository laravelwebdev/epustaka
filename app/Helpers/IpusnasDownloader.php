<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class IpusnasDownloader
{
    protected $bookId;

    protected $apiLogin;

    protected $apiBookDetail;

    protected $apiCheckBorrowBook;

    protected $apiReturnBook;

    protected $apiBorrowBook;

    protected $apiPustakaId;

    protected $apiSaveBook;

    protected $apiUpdateBookPath;

    protected $apiUpdateBorrowedStatus;

    protected $baseHeaders;

    protected $tempDir;

    protected $booksDir;

    public function __construct($bookId = null)
    {
        $this->bookId = $bookId;

        $this->apiLogin = 'https://api2-ipusnas.perpusnas.go.id/api/auth/login';
        $this->apiBookDetail = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/book-detail?book_id=';
        $this->apiCheckBorrowBook = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/check-borrow-status?book_id=';
        $this->apiReturnBook = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/book-return';
        $this->apiBorrowBook = 'https://api2-ipusnas.perpusnas.go.id/agent/webhook/borrow';
        $this->apiPustakaId = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/epustaka-borrow';

        $this->baseHeaders = [
            'Origin' => 'https://ipusnas2.perpusnas.go.id',
            'Referer' => 'https://ipusnas2.perpusnas.go.id/',
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36',
        ];

        $this->tempDir = storage_path('app/temp');
        $this->booksDir = storage_path('app/books');

    }

    /* ---------------------------
       Login
    ----------------------------*/
    public function login(string $email, string $password)
    {
        $headers = array_merge($this->baseHeaders, [
            'Content-Type' => 'application/vnd.api+json',
            'Accept' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)
            ->post($this->apiLogin, [
                'email' => $email,
                'password' => $password,
            ]);

        if ($response->failed()) {
            return ['status' => false, 'data' => $response->json()];
        }

        return ['status' => true, 'data' => $response->json()];
    }

    /* ---------------------------
       Borrow
    ----------------------------*/
    public function borrow(string $token, $user_id, $book_id, $organization_id, $epustaka_id)
    {
        $payload = [
            'epustaka_id' => $epustaka_id,
            'user_id' => $user_id,
            'book_id' => $book_id,
            'organization_id' => $organization_id,
        ];

        $headers = array_merge($this->baseHeaders, [
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)->post($this->apiBorrowBook, $payload);

        if ($response->failed()) {
            return ['status' => false, 'data' => $response->body()];
        }

        return ['status' => true, 'data' => $response->json()];
    }

    /* ---------------------------
       Get Pustaka ID
    ----------------------------*/
    public function getPustakaId(string $token, $book_id, $organization_id)
    {
        $headers = array_merge($this->baseHeaders, [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)
            ->get($this->apiPustakaId, [
                'book_id' => $book_id,
                'organization_id' => $organization_id,
            ]);

        if ($response->failed()) {
            return ['status' => false, 'data' => $response->body()];
        }

        $json = $response->json();

        return ['status' => true, 'data' => $json['data']['id'] ?? null];
    }

    /* ---------------------------
       Book detail / borrow info / return
    ----------------------------*/
    public function getBookDetail(string $token, $bookId)
    {
        $headers = array_merge($this->baseHeaders, [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)->get($this->apiBookDetail.$bookId);

        if ($response->failed()) {
            return ['status' => false, 'data' => $response->body()];
        }

        return ['status' => true, 'data' => $response->json()];
    }

    public function returnBook(string $token, $borrowBookId)
    {
        $headers = array_merge($this->baseHeaders, [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)
            ->put($this->apiReturnBook, ['borrow_book_id' => $borrowBookId]);

        return ['status' => ! $response->failed(), 'data' => $response->body()];
    }

    public function getBorrowInfo(string $token, $bookId)
    {
        $headers = array_merge($this->baseHeaders, [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)->get($this->apiCheckBorrowBook.$bookId);

        if ($response->failed()) {
            return ['status' => false, 'data' => $response->body()];
        }

        return ['status' => true, 'data' => $response->json()];
    }

    /* ---------------------------
       Format bytes
    ----------------------------*/
    protected function formatBytes($bytes)
    {
        if ($bytes == 0) {
            return '0 Bytes';
        }
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));

        return number_format($bytes / pow(1024, $i), 2).' '.$sizes[$i];
    }
}

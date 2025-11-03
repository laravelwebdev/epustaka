<?php

namespace App\Helpers;

use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpusnasDownloader
{
    protected $bookId;

    protected $apiLogin = 'https://api2-ipusnas.perpusnas.go.id/api/auth/login';

    protected $apiBookDetail = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/book-detail?book_id=';

    protected $apiCheckBorrowBook = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/check-borrow-status?book_id=';

    protected $apiReturnBook = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/book-return';

    protected $apiBorrowBook = 'https://api2-ipusnas.perpusnas.go.id/agent/webhook/borrow';

    protected $apiPustakaId = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/epustaka-borrow';

    protected $baseHeaders = [
        'Origin' => 'https://ipusnas2.perpusnas.go.id',
        'Referer' => 'https://ipusnas2.perpusnas.go.id/',
        'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36',
    ];

    public function __construct($accountId = null)
    {
        if (isset($accountId)) {
            $this->getAccessToken($accountId);
        }
    }

    public function getAccessToken($accountId)
    {
        if (! Cache::has('ipusnas_token_'.$accountId)) {
            $account = Account::find($accountId);
            if ($account) {
                $result = $this->login($account->email, $account->password);
                if ($result['status'] === true && isset($result['data']['data']['access_token'])) {
                    Cache::put('ipusnas_token_'.$accountId, $result['data']['data']['access_token'], 300);
                }
            }
        }

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

        return ['status' => ! $response->failed(), 'data' => $response->json()];
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

        return ['status' => ! $response->failed(), 'data' => $response->json()];
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

        return ['status' => ! $response->failed(), 'data' => $response->json()];
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

        return ['status' => ! $response->failed(), 'data' => $response->json()];
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

        return ['status' => ! $response->failed(), 'data' => $response->json()];
    }

    public function getBorrowInfo(string $token, $bookId)
    {
        $headers = array_merge($this->baseHeaders, [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)->get($this->apiCheckBorrowBook.$bookId);

        return ['status' => ! $response->failed(), 'data' => $response->json()];
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

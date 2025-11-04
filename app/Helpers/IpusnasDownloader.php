<?php

namespace App\Helpers;

use App\Models\Book;
use App\Models\Account;
use App\Models\FailedBook;
use App\Jobs\DownloadBookFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IpusnasDownloader
{
    private $accountId = null;

    private $apiLogin = 'https://api2-ipusnas.perpusnas.go.id/api/auth/login';

    private $apiBookDetail = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/book-detail?book_id=';

    private $apiCheckBorrowBook = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/check-borrow-status?book_id=';

    private $apiReturnBook = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/book-return';

    private $apiBorrowBook = 'https://api2-ipusnas.perpusnas.go.id/agent/webhook/borrow';

    private $apiPustakaId = 'https://api2-ipusnas.perpusnas.go.id/api/webhook/epustaka-borrow';

    private $baseHeaders = [
        'Origin' => 'https://ipusnas2.perpusnas.go.id',
        'Referer' => 'https://ipusnas2.perpusnas.go.id/',
        'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36',
    ];

    public function __construct($accountId = null)
    {
        if (isset($accountId)) {
            $this->getAccessToken($accountId);
            $this->accountId = $accountId;
        }
    }

    private function getAccessToken($accountId)
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
    private function borrow(string $token, $user_id, $book_id, $organization_id, $epustaka_id)
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
    private function getPustakaId(string $token, $book_id, $organization_id)
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
    private function getBookDetail(string $token, $bookId)
    {
        $headers = array_merge($this->baseHeaders, [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)->get($this->apiBookDetail.$bookId);

        return ['status' => ! $response->failed(), 'data' => $response->json()];
    }

    private function returnBook(string $token, $borrowBookId)
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

    private function getBorrowInfo(string $token, $bookId)
    {
        $headers = array_merge($this->baseHeaders, [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response = Http::withHeaders($headers)->get($this->apiCheckBorrowBook.$bookId);

        return ['status' => ! $response->failed(), 'data' => $response->json()];
    }

    /* ---------------------------
       Get Book
    ----------------------------*/
    public function getBook($bookId)
    {
        $error = null;
        $bookDetail = null;
        $epustaka = null;
        $borrowInfo = null;

        // credential
        $token = Cache::get('ipusnas_token_'.$this->accountId);
        $account = Account::find($this->accountId);
        // book detail
        $bookDetailResponse = $this->getBookDetail($token, $bookId);
        if ($bookDetailResponse['status'] === true) {
            $bookDetail = $bookDetailResponse['data'];
        } else {
            $error = 'Failed to get book detail.';
        }
        // epustaka
        $epustakaResponse = $this->getPustakaId($token, $bookId, optional($account)->organization_id);
        if ($epustakaResponse['status'] === true) {
            $epustaka = $epustakaResponse['data'];
        } else {
            $error = 'Failed to get epustaka id.';
        }
        // borrow
        $borrowResponse = $this->borrow($token, optional($account)->ipusnas_id, $bookId, optional($account)->organization_id, optional($epustaka)['data']['id'] ?? null);
        if ($borrowResponse['data']['code'] === 'SUCCESS') {
            // borrow info
            $borrowInfoResponse = $this->getBorrowInfo($token, $bookId);
            if ($borrowInfoResponse['status'] === true) {
                $borrowInfo = $borrowInfoResponse['data'];
            } else {
                $error = 'Failed to get borrow info.';
            }
            $this->returnBook($token, optional($borrowInfo)['data']['id']);
        } else {
            $error = 'Failed to borrow book.';
            $failed = FailedBook::firstOrNew(['book_id' => $bookId]);
            $failed->failed_borrow = true;
            $failed->save();
        }
        if (! isset($error)) {
            $book = new Book;
            $book->ipusnas_book_id = $bookId;
            $book->book_title = optional($bookDetail)['data']['book_title'];
            $book->book_author = optional($bookDetail)['data']['book_author'];
            $book->book_description = optional($bookDetail)['data']['book_description'];
            $book->category_name = optional($bookDetail)['data']['category_name'];
            $book->publish_date = optional($bookDetail)['data']['publish_date'];
            $book->file_size_info = optional($bookDetail)['data']['file_size_info'];
            $book->file_ext = optional($bookDetail)['data']['file_ext'];
            $book->cover_url = optional($bookDetail)['data']['cover_url'];
            $book->using_drm = optional($bookDetail)['data']['using_drm'];
            $book->epustaka_id = optional($epustaka)['data']['id'];
            $book->user_id = optional($account)->ipusnas_id;
            $book->organization_id = optional($account)->organization_id;
            $book->borrow_key = optional($borrowInfo)['data']['borrow_key'];
            $book->book_url = optional($borrowInfo)['data']['url_file'];
            $book->language = optional($bookDetail)['data']['catalog_info']['language_name'] ?? null;
            $book->publisher = optional($bookDetail)['data']['catalog_info']['organization_group_name'] ?? null;
            $book->save();
            DownloadBookFile::dispatch($bookId,optional($borrowInfo)['data']['url_file']);
        }

        return $error;
    }
}

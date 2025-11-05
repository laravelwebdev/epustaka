<?php

namespace App\Helpers;

use App\Models\Account;
use App\Models\Book;
use App\Models\FailedBook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

    private function downloadBookFile(Book $book)
    {
        $headers = [
            'Origin' => 'https://ipusnas2.perpusnas.go.id',
            'Referer' => 'https://ipusnas2.perpusnas.go.id/',
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Mobile Safari/537.36',
            'Content-Type' => 'application/vnd.api+json',
        ];
        $url = $book->book_url;
        $safeName = md5($book->ipusnas_book_id);
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'pdf';
        $filename = "{$safeName}.{$extension}";
        $path = "temp/{$filename}";

        $response = Http::withHeaders($headers)->timeout(1200)->get($url);
        if ($response->failed()) {
            $failed = FailedBook::firstOrNew(['ipusnas_book_id' => $book->ipusnas_book_id]);
            $failed->failed_url = true;
            $failed->save();

            return;
        }
        // Simpan ke temporary storage
        Storage::put($path, $response->body());

        if ($book->using_drm) {
            $passwordZip = IpusnasDecryptor::generatePasswordZip(
                $book->ipusnas_user_id,
                $book->ipusnas_book_id,
                $book->epustaka_id,
                $book->borrow_key
            );

        } else {
            $passwordZip = '';
        }
        $extractedPath = (new ZipExtractor)->extract(storage_path('app/private/'.$path), $passwordZip);
        FailedBook::where('ipusnas_book_id', $book->ipusnas_book_id)->delete();

        return $extractedPath;
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
            $failed = FailedBook::firstOrNew(['ipusnas_book_id' => $bookId]);
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
            $book->ipusnas_user_id = optional($account)->ipusnas_id;
            $book->organization_id = optional($account)->organization_id;
            $book->borrow_key = optional($borrowInfo)['data']['borrow_key'];
            $book->book_url = optional($borrowInfo)['data']['url_file'];
            $book->language = optional($bookDetail)['data']['catalog_info']['language_name'] ?? null;
            $book->publisher = optional($bookDetail)['data']['catalog_info']['organization_group_name'] ?? null;
            $extractedPath = $this->downloadBookFile($book) ?? null;
            if (empty($extractedPath)) {
                $error = 'Failed to download book file.';
            } else {
                $book->path = $extractedPath;
                $book->save();
                $book->refresh();
                $userId = optional($account)->user_id;
                $book->users()->attach($userId);
            }

        }

        return $error;
    }
}

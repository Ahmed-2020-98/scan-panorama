<?php

namespace App\Services\Drive;

use App\Models\DriveConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DriveClient
{
    private ?string $token = null;

    private ?DriveConnection $connection = null;

    /** A client bound to a specific account (defaults to the active one). */
    public function using(DriveConnection $connection): self
    {
        $client = new self;
        $client->connection = $connection;

        return $client;
    }

    public function connection(): DriveConnection
    {
        return $this->connection ??= DriveConnection::activeOrFail();
    }

    public function request(): PendingRequest
    {
        if (! $this->token) {
            $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('drive.client_id'), 'client_secret' => config('drive.client_secret'),
                'refresh_token' => $this->connection()->refresh_token, 'grant_type' => 'refresh_token',
            ]);
            if (! $response->successful() || ! $response->json('access_token')) {
                throw new \RuntimeException('Drive authentication failed. Reconnect the center account.');
            }
            $this->token = (string) $response->json('access_token');
        }

        return Http::withToken($this->token)->timeout(120)->connectTimeout(15)->withOptions(['allow_redirects' => false]);
    }

    public function newId(): string
    {
        $id = $this->request()->get('https://www.googleapis.com/drive/v3/files/generateIds', ['count' => 1, 'space' => 'drive', 'type' => 'files'])->throw()->json('ids.0');
        if (! is_string($id) || $id === '') {
            throw new \RuntimeException('Drive did not allocate a file ID.');
        }

        return $id;
    }

    /** @param array<string, mixed> $metadata */
    public function create(array $metadata): void
    {
        $response = $this->request()->post('https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&ignoreDefaultVisibility=true', $metadata);
        if ($response->status() !== 409) {
            $response->throw();
        }
    }
}

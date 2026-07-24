<?php

namespace App\Services;

use Cloudinary\Asset\Image;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    /**
     * Uploads an image as a private ("authenticated") Cloudinary asset — it is
     * never publicly reachable by URL, only via fetchBytes() below using our
     * own API credentials. Returns the Cloudinary public_id to store.
     */
    public function uploadPrivateImage(UploadedFile $file, string $folder): string
    {
        $result = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => $folder,
            'resource_type' => 'image',
            'type' => 'authenticated',
            'overwrite' => false,
            'unique_filename' => true,
        ]);

        return $result['public_id'];
    }

    /**
     * Downloads a private asset via a freshly signed delivery URL, along with
     * its content type. Used by admin endpoints to stream KYC documents back
     * to the panel without ever exposing a public Cloudinary URL to the browser.
     *
     * @return array{body: string, content_type: string}
     */
    public function fetchImage(string $publicId): array
    {
        $url = (string) Image::authenticated($publicId, $this->cloudinary->configuration)
            ->signUrl(true)
            ->toUrl();

        $response = Http::get($url)->throw();

        return [
            'body' => $response->body(),
            'content_type' => $response->header('Content-Type') ?: 'image/jpeg',
        ];
    }
}

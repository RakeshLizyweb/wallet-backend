<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message];

        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            $response = $data->response()->getData(true);
            $payload['data'] = $response['data'] ?? null;

            if (isset($response['meta'])) {
                $payload['meta'] = $response['meta'];
            }

            if (isset($response['links'])) {
                $payload['links'] = $response['links'];
            }
        } elseif ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $code);
    }

    protected function error(string $message = 'Something went wrong', int $code = 400, mixed $errors = null): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }
}

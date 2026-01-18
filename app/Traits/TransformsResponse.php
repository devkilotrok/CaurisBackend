<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

trait TransformsResponse
{
    /**
     * Return a standardized JSON response with camelCase keys.
     *
     * @param bool $success
     * @param string $message
     * @param mixed $data
     * @param int $status
     * @return JsonResponse
     */
    protected function apiResponse(bool $success, string $message, $data = null, int $status = 200, bool $wrapInData = true): JsonResponse
    {
        $response = [
            'success' => $success,
            'message' => $message,
        ];

        if ($data !== null) {
            $transformedData = $this->transformToCamelCase($data);
            if ($wrapInData) {
                $response['data'] = $transformedData;
            } else {
                $response = array_merge($response, $transformedData);
            }
        }

        return response()->json($response, $status);
    }

    /**
     * Recursively transform array keys to camelCase.
     * Also maps primary keys like user_id to just id if present in the top level of items.
     *
     * @param mixed $data
     * @return mixed
     */
    protected function transformToCamelCase($data)
    {
        if ($data instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            $data = $data->resolve();
        } elseif ($data instanceof \Illuminate\Support\Collection) {
            $data = $data->toArray();
        } elseif ($data instanceof \Illuminate\Database\Eloquent\Model) {
            $data = $data->toArray();
        }

        if (!is_array($data)) {
            return $data;
        }

        $transformed = [];
        foreach ($data as $key => $value) {
            // Standardize ID: if key ends with _id and it's the specific ID for that model
            // or just generic user_id etc.
            // But we must be careful not to break foreign keys that frontend might need
            // as they are.
            // Flutter models expect 'id' for the main object.
            
            // Special rule for often used IDs in this project
            if ($key === 'user_id' || $key === 'room_id' || $key === 'game_id' || $key === 'transaction_id' || $key === 'request_id' || $key === 'friendship_id') {
                $transformed['id'] = $this->transformToCamelCase($value);
            }

            $newKey = Str::camel($key);
            $transformed[$newKey] = $this->transformToCamelCase($value);
        }

        return $transformed;
    }
}

<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\RegisterResponse;

class ApiRegisterResponse implements RegisterResponse
{
    public function toResponse($request)
    {
        return response()->json([
            'data' => [
                'user' => $request->user(),
            ],
        ], 201);
    }
}

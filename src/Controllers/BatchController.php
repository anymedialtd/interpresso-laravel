<?php

namespace AnyMedia\Interpresso\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use AnyMedia\Interpresso\Services\BatchService;

class BatchController extends BaseController
{
    public function progress(Request $request, BatchService $service): JsonResponse
    {
        $request->validate(['id' => 'nullable|string|max:255']);
        $id = $request->string('id')->toString();
        return response()->json($service->progress($id === '' ? null : $id));
    }
}

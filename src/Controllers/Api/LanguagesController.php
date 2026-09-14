<?php

namespace AnyMedia\Interpresso\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Resources\LanguageResource;
use AnyMedia\Interpresso\Services\BatchService;

class LanguagesController extends Controller
{
    public function getLanguages(): AnonymousResourceCollection
    {
        return LanguageResource::collection(Language::all());
    }

    public function cancelBatch(BatchService $batchService): JsonResponse
    {
        $batchService->deleteBatches();
        return response()->json([], 204);
    }
}

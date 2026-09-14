<?php

namespace AnyMedia\Interpresso\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use AnyMedia\Interpresso\Jobs\Batch\BatchProcessor;
use AnyMedia\Interpresso\Jobs\ForceExportTranslationJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\FlashMessage;
use AnyMedia\Interpresso\Resources\TranslationResource;

class TranslationsController extends Controller
{
    /**
     * @return AnonymousResourceCollection
     */
    public function getPaginated(): AnonymousResourceCollection
    {
        return TranslationResource::collection(Translation::query()->paginate(500));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function forceExport(Request $request): JsonResponse
    {
        $batchArray = [];
        $host = $request->getSchemeAndHttpHost();
        Language::query()->each(function(Language $language) use (&$batchArray) {
            $batchArray[] = new ForceExportTranslationJob($language);
        });

        $then = function () use ($host) {
            Translator::query()->admin()->each(function (Translator $translator) use ($host) {
                $translator->notify(new FlashMessage(__('interpresso::translations.export_on_other_host_success', ['host' => $host])));
            });
        };

        resolve(BatchProcessor::class)->execute($batchArray,$then, null, null)->dispatchAfterResponse();

        return response()->json(['message' => __('interpresso::translations.export_on_other_host_started', ['host' => $host])]);
    }
}

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
use AnyMedia\Interpresso\Services\ProcessLock;
use AnyMedia\Interpresso\Services\QueueConfiguration;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;

class TranslationsController extends Controller
{
    use ChecksForRunningJobs;
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
        if (!QueueConfiguration::defersWork()) {
            return response()->json(['message' => QueueConfiguration::refusalMessage('php artisan interpresso:export-translations-deployment')], 503);
        }
        // A peer invokes this while holding its own export lock. Check this DB only.
        $lock = $this->acquireProcessLock('force export on peer', true, false);
        if ($lock === null) {
            $current = resolve(ProcessLock::class);
            return response()->json(['message' => 'Another process is running: ' . $current->description() . '.',
                'lock' => $current->current()], 409);
        }
        try {
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

            if ($batchArray !== []) {
                resolve(BatchProcessor::class)->dispatch($batchArray, $then, $lock);
            }

            return response()->json(['message' => __('interpresso::translations.export_on_other_host_started', ['host' => $host])]);
        } finally {
            $lock->release();
        }
    }
}

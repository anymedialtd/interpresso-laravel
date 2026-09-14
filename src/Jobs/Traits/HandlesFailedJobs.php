<?php

namespace AnyMedia\Interpresso\Jobs\Traits;


use AnyMedia\Interpresso\Exceptions\ExportFileException;
use AnyMedia\Interpresso\Exceptions\ExportTranslationException;
use AnyMedia\Interpresso\Exceptions\ImportTranslationsException;
use AnyMedia\Interpresso\Exceptions\MassCreateTranslationsException;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\FlashMessage;

trait HandlesFailedJobs
{
    /**
     * Handle a job failure. Notifies all admin translators about failed job
     */
    public function failed(\Throwable $e): void
    {
        Translator::query()->admin()->each(function (Translator $translator) use ($e) {
            if (in_array($e::class, [ImportTranslationsException::class, ExportTranslationException::class, MassCreateTranslationsException::class, ExportFileException::class])) {
                $translator->notify(new FlashMessage($e->getPublicMessage()));
            } else {
                $translator->notify(new FlashMessage(__('interpresso::global.something_wrong')));
            }
        });
    }
}

<?php

namespace AnyMedia\Interpresso\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\QueueConfiguration;
use AnyMedia\Interpresso\Services\Toast;

abstract class BaseController extends Controller
{
    /**
     * @param Request $request
     * @return void
     */
    public function __construct(protected Request $request)
    {
    }

    /**
     * Return the translator resolved by EnsureTranslator.
     *
     * @return Translator
     */
    public function authUser(): Translator
    {
        $authUser = $this->request->attributes->get('authUser');

        if (!$authUser instanceof Translator) {
            abort(403);
        }

        return $authUser;
    }

    /**
     * @return bool
     */
    public function isAdministrator(): bool
    {
        return (bool) $this->authUser()->admin;
    }

    /**
     * Restrict a language query to the translator's assigned languages.
     *
     * @param Builder<Language> $query
     * @return Builder<Language>
     */
    protected function scopeLanguages(Builder $query): Builder
    {
        if (!$this->isAdministrator()) {
            /** @var string $table Configured language table name. */
            $table = config('interpresso.table_languages');
            $query->whereIn(
                $table . '.id',
                $this->authUser()->languages()->pluck($table . '.id')
            );
        }

        return $query;
    }

    protected function authorizeLanguage(Language $language): void
    {
        abort_unless($this->scopeLanguages(Language::query())->whereKey($language->id)->exists(), 403);
    }

    protected function canDispatchBatch(?string $command = null): bool
    {
        if (QueueConfiguration::defersWork()) {
            return true;
        }

        /** @var string $queue */
        $queue = config('interpresso.queue_name');
        Toast::flash($command === null
            ? __('interpresso::global.queue_worker_required', ['command' => 'php artisan queue:work --queue=' . escapeshellarg($queue)])
            : QueueConfiguration::refusalMessage($command), 'WARNING', 20000);

        return false;
    }

    /**
     * Resolve a translation only within a permitted language, otherwise deny access.
     *
     * @param int $id
     * @param Language $language
     * @return Translation
     */
    protected function resolveTranslation(int $id, Language $language): Translation
    {
        $this->authorizeLanguage($language);

        $translation = $language->translations()->find($id);

        if (!$translation) {
            abort(403);
        }

        return $translation;
    }
}

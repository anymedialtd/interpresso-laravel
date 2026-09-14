<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Services\ApproveLanguagesService;

class ApproveLanguagesJob extends BaseJob
{
    public function __construct(
        protected Language $language,
        protected int $authUserId
    )
    {
        parent::__construct();
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        resolve(ApproveLanguagesService::class)->approveLanguages($this->language, $this->authUserId);
    }

}

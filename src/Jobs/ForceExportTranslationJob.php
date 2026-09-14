<?php

namespace AnyMedia\Interpresso\Jobs;

class ForceExportTranslationJob extends ExportTranslationJob
{
    protected bool $forceExportAll = true;
}

<?php

namespace AnyMedia\Interpresso\Tests\e2e;

use Illuminate\Database\Eloquent\Model;

// Disposable export target, never registered in the host application.
class Article extends Model
{
    protected $table = 'e2e_articles';
}

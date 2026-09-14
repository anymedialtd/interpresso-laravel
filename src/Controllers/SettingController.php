<?php

namespace AnyMedia\Interpresso\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Requests\UpdateSettingFieldRequest;
use AnyMedia\Interpresso\Services\Toast;

class SettingController extends BaseController
{
    public function index(): View
    {
        return view('interpresso::settings', ['setting' => Setting::getCached()]);
    }

    public function update(UpdateSettingFieldRequest $request): RedirectResponse
    {
        $field = $request->field();
        $setting = Setting::query()->firstOrFail();
        $setting->setAttribute($field, $request->validated($field));
        $setting->save();
        Setting::getFreshCached();
        Toast::flash('Setting saved.');
        return redirect()->route('interpresso.settings');
    }
}

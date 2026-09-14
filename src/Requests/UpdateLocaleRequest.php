<?php

namespace AnyMedia\Interpresso\Requests;

use AnyMedia\Interpresso\Services\InterfaceLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class UpdateLocaleRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'interfaceLocale';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return ['locale' => ['required', 'string', Rule::in(resolve(InterfaceLocales::class)->codes())]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['locale.*' => __('interpresso::global.invalid_locale')];
    }
}

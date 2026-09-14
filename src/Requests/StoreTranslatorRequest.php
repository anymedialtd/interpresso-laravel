<?php

namespace AnyMedia\Interpresso\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\InterfaceLocales;

class StoreTranslatorRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        $authUser = $this->attributes->get('authUser');

        return $authUser instanceof Translator && $authUser->admin;
    }

    /**
     * @return array<string, string|list<string|Unique|Exists|In>>
     */
    public function rules(): array
    {
        /** @var string|null $connection Configured database connection name. */
        $connection = config('interpresso.db_connection');
        /** @var string $table Configured translator table name. */
        $table = config('interpresso.table_translators');
        return [
            'email' => ['required', 'email', Rule::unique($connection . '.' . $table, 'email')],
            'phone' => ['nullable', 'string', Rule::unique($connection . '.' . $table, 'phone')],
            'admin' => 'nullable|bool',
            'locale' => ['nullable', 'string', Rule::in(resolve(InterfaceLocales::class)->codes())],
            'first_name' => 'required|string|min:2',
            'last_name' => 'required|string|min:2',
            'languages' => $this->boolean('admin') ? 'nullable|array' : 'required|array|min:1',
            "languages.*" => ['integer', 'distinct', Rule::exists(Language::class, 'id')],
        ];
    }

    /**
     * Accept profile fields only. The account holder sets a password by email.
     *
     * @return array<string, mixed>
     */
    public function translatorAttributes(): array
    {
        /** @var array{email: string, phone?: string|null, locale?: string|null, first_name: string, last_name: string, admin?: bool|0|1|'0'|'1'|null} $attributes Validated profile fields. */
        $attributes = $this->safe()->only(['email', 'phone', 'locale', 'first_name', 'last_name', 'admin']);
        $attributes['admin'] = $this->boolean('admin');

        return $attributes;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['locale.*' => __('interpresso::global.invalid_locale')];
    }
}

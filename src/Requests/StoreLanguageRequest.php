<?php

namespace AnyMedia\Interpresso\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Unique;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translator;

class StoreLanguageRequest extends FormRequest
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
     * Validate language codes using the existing creation rules.
     *
     * @return array<string, list<string|In|NotIn|Unique>>
     */
    public function rules(): array
    {
        /** @var string|null $connection Configured database connection name. */
        $connection = config('interpresso.db_connection');
        /** @var string $table Configured language table name. */
        $table = config('interpresso.table_languages');
        return [
            'language' => [
                'required', 'string',
                Rule::in(array_column(Language::LANGUAGES, 'code')),
                Rule::notIn(Language::query()->pluck('code')->all()),
                Rule::unique($connection . '.' . $table, 'code'),
            ],
        ];
    }
}

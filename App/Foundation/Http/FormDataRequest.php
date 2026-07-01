<?php

namespace App\Foundation\Http;
use Dep;

#[Dep('App\\Foundation\\Http\\Request')]
abstract class FormDataRequest extends Request
{
    /**
     * FormDataRequest constructor.
     * Captures the request as usual, then authorizes and validates it
     * against the rules defined by the concrete subclass.
     */
    public function __construct()
    {
        parent::__construct();

        if (!$this->authorize()) {
            abort(403);
        }

        $this->validateForm($this->rules(), $this->messages(), $this->attributes());
    }

    /**
     * Determine if the current user is authorized to make this request.
     */
    abstract public function authorize(): bool;

    /**
     * Get the validation rules that apply to this request.
     */
    abstract public function rules(): array;

    /**
     * Custom validation error messages, keyed by "field" or "field.rule".
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Custom display names for fields, used in error messages.
     */
    public function attributes(): array
    {
        return [];
    }
}

<?php

namespace App\Http\Requests\Api;

class TestRequest extends BaseProblemJsonRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => 'required|min:4|in:Satu,Dua',
            'nama2' => 'required|min:4|in:Satu,Dua',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => "Lu butuh nama bego"
        ];
    }

    protected function additionalClues(): array
    {
        return [
            'payload' => $this->all()
        ];
    }
}

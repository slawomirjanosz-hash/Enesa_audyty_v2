<?php

namespace App\Http\Requests;

use App\Models\Cylinder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CylinderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('superadmin') || $this->user()->canAny(['system.full_access', 'cylinders.manage']);
    }

    protected function prepareForValidation(): void
    {
        foreach (['serial_number', 'manufacturer_mark'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::upper(trim($this->input($field)))]);
            }
        }
        foreach (Cylinder::DECIMAL_PARAMETERS as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => str_replace(',', '.', trim($this->input($field)))]);
            }
        }
    }

    public function rules(): array
    {
        $cylinder = $this->route('cylinder');
        $rules = [
            'company_id' => $cylinder ? ['prohibited'] : ['required', 'integer', Rule::exists('companies', 'id')->where('company_type', 'client')->where('status', 'active')->whereNull('archived_at')],
            'name' => ['required', 'string', 'max:160'],
            'manufacturer_mark' => ['required', 'string', 'max:160'],
            'serial_number' => ['required', 'string', 'max:100', Rule::unique('cylinders')->where('manufacturer_mark', $this->input('manufacturer_mark'))->ignore($cylinder?->id)],
            'device_type' => ['prohibited'],
            'type' => ['prohibited'],
            'manufacturer' => ['nullable', 'string', 'max:160'],
            'manufactured_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
        foreach (['inventory_number', 'working_medium', 'filling_mass_symbol', 'equipment_type', 'equipment_mark'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:160'];
        }
        foreach (Cylinder::DECIMAL_PARAMETERS as $field) {
            $rules[$field] = ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:9999999'];
        }
        foreach (['capacity_litres', 'working_pressure_bar', 'test_pressure_bar'] as $field) {
            $rules[$field][] = 'gt:0';
        }
        $rules['temperature_min_c'] = ['nullable', 'numeric', 'decimal:0,2', 'min:-273.15', 'max:99999.99'];
        $rules['temperature_max_c'] = ['nullable', 'numeric', 'decimal:0,2', 'min:-273.15', 'max:99999.99'];
        if ($this->filled('temperature_min_c')) {
            $rules['temperature_max_c'][] = 'gte:temperature_min_c';
        }

        return $rules;
    }

    public function attributes(): array
    {
        return Cylinder::PARAMETER_LABELS + ['company_id' => 'klient', 'notes' => 'uwagi'];
    }
}

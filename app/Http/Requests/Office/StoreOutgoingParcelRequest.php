<?php

namespace App\Http\Requests\Office;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutgoingParcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('office')?->hasFeature('outgoing_parcels') === true;
    }

    protected function prepareForValidation(): void
    {
        $normalizeDigits = static function (?string $value): ?string {
            if ($value === null) {
                return null;
            }
            $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

            return str_replace($eastern, $western, trim($value));
        };

        $normalizePhone = static function (?string $phone) use ($normalizeDigits): string {
            if ($phone === null || $phone === '') {
                return '';
            }

            $converted = $normalizeDigits($phone) ?? '';
            $digits = preg_replace('/\D+/', '', $converted) ?? '';

            if (strlen($digits) === 12 && str_starts_with($digits, '9677')) {
                $digits = substr($digits, 3);
            } elseif (strlen($digits) === 14 && str_starts_with($digits, '009677')) {
                $digits = substr($digits, 5);
            }

            return $digits;
        };

        $cod = $this->input('cod_amount');
        $normalizedCod = $normalizeDigits($cod !== null ? (string) $cod : null);

        $this->merge([
            'sender_phone'    => $normalizePhone((string) $this->input('sender_phone', '')),
            'recipient_phone' => $normalizePhone((string) $this->input('recipient_phone', '')),
            'cod_amount'      => ($normalizedCod !== null && $normalizedCod !== '') ? $normalizedCod : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $currentOfficeId = (int) $this->user('office')?->id;

        return [
            'destination_type'      => ['required', Rule::in(['office', 'external'])],
            'destination_office_id' => [
                'required_if:destination_type,office',
                'nullable',
                'integer',
                Rule::notIn([$currentOfficeId]),
                Rule::exists('offices', 'id')->where(fn ($query) => $query->where('is_active', true)),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('destination_type') !== 'office' || $value === null || $value === '') {
                        return;
                    }

                    $editingParcel = $this->route('outgoingParcel');
                    if (
                        $editingParcel instanceof \App\Models\OutgoingParcel
                        && $editingParcel->destination_office_id !== null
                        && (int) $editingParcel->destination_office_id === (int) $value
                    ) {
                        return;
                    }

                    $office = $this->user('office');
                    $isLinkedPartner = $office && $office->partnerOffices()
                        ->where('offices.id', (int) $value)
                        ->where('offices.is_active', true)
                        ->exists();

                    if (! $isLinkedPartner) {
                        $fail('المكتب المستلم غير مرتبط بهذا المكتب.');
                    }
                },
            ],
            'external_office_name'  => ['required_if:destination_type,external', 'nullable', 'string', 'max:255'],
            'sender_name'           => ['required', 'string', 'max:255'],
            'sender_phone'          => ['required', 'regex:/^7[01378]\d{7}$/'],
            'recipient_name'        => ['nullable', 'string', 'max:255'],
            'recipient_phone'       => ['required', 'regex:/^7[01378]\d{7}$/'],
            'package_type'          => ['required', 'string', 'max:255'],
            'cod_amount'            => ['required', 'numeric', 'min:0'],
            'payment_type'          => ['required', Rule::in(['paid', 'deferred'])],
            'notes'                 => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destination_type.required'         => 'يرجى تحديد نوع الوجهة (فرع في النظام أو مكتب خارجي).',
            'destination_type.in'               => 'نوع الوجهة المحدد غير صالح.',
            'destination_office_id.required_if' => 'يرجى اختيار الفرع المستلم من القائمة.',
            'destination_office_id.integer'     => 'معرّف الفرع المستلم غير صالح.',
            'destination_office_id.not_in'      => 'لا يمكن إرسال الطرد إلى نفس المكتب الحالي.',
            'destination_office_id.exists'      => 'الفرع المستلم المحدد غير موجود أو غير نشط.',
            'external_office_name.required_if'  => 'يرجى إدخال اسم المكتب الخارجي.',
            'external_office_name.max'          => 'اسم المكتب الخارجي يجب ألا يتجاوز 255 حرفاً.',
            'sender_name.required'              => 'يرجى إدخال اسم المرسل.',
            'sender_name.max'                   => 'اسم المرسل يجب ألا يتجاوز 255 حرفاً.',
            'sender_phone.required'             => 'يرجى إدخال رقم هاتف المرسل.',
            'sender_phone.regex'                => 'رقم هاتف المرسل غير صالح. يجب أن يتكون من 9 أرقام ويبدأ بـ 77، 78، 73، 71، أو 70.',
            'recipient_name.max'                => 'اسم المستلم يجب ألا يتجاوز 255 حرفاً.',
            'recipient_phone.required'          => 'يرجى إدخال رقم هاتف المستلم.',
            'recipient_phone.regex'             => 'رقم هاتف المستلم غير صالح. يجب أن يتكون من 9 أرقام ويبدأ بـ 77، 78، 73، 71، أو 70.',
            'package_type.required'             => 'يرجى إدخال نوع الطرد.',
            'package_type.max'                  => 'نوع الطرد يجب ألا يتجاوز 255 حرفاً.',
            'cod_amount.required'               => 'يرجى إدخال المبلغ.',
            'cod_amount.numeric'                => 'المبلغ يجب أن يكون رقماً صالحاً.',
            'cod_amount.min'                    => 'المبلغ يجب ألا يقل عن صفر.',
            'payment_type.required'             => 'يرجى تحديد حالة الدفع: محاسب أو آجل.',
            'payment_type.in'                   => 'يرجى تحديد حالة الدفع: محاسب أو آجل.',
            'notes.max'                         => 'الملاحظات يجب ألا تتجاوز 1000 حرف.',
        ];
    }
}

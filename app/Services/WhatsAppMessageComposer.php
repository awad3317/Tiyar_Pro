<?php

namespace App\Services;

use App\Models\Office;
use App\Models\Parcel;

/**
 * مولّد نصوص رسائل الواتساب المحمية من بصمة الـ Spam:
 * - ترحيب ومقدمة متغيرة (Spintax) دون فرض اسم العميل إلا إذا وُجد في القالب
 * - سطر تحفيز العميل على الرد (يرفع الـ Trust Score للرقم)
 * - حقن محارف غير مرئية بين الكلمات العربية فقط (دون المساس بالأرقام أو الروابط)
 * - رمز مرجعي عشوائي في النهاية بحيث يستحيل تطابق رسالتين بنفس الـ Hash
 */
class WhatsAppMessageComposer
{
    public const GREETINGS = [
        'مرحباً بك',
        'أهلاً بك عزيزي',
        'حياك الله',
        'السلام عليكم',
        'أسعد الله أوقاتك',
        'يا هلا',
    ];

    public const INTROS = [
        'وصل طردك:',
        'إشعار بوصول شحنتك:',
        'نود إبلاغك بوصول:',
        'لديك طرد جاهز للاستلام:',
    ];

    public const DELIVERY_LINES = [
        'يوجد لدينا خدمة توصيل لمكانك عبر الرقم:',
        'لطلب التوصيل لموقعك تواصل مع:',
        'متوفر توصيل لباب البيت، تواصل على:',
    ];

    public const REPLY_PROMPTS = [
        'يرجى الرد بكلمة (تم) لتأكيد علمك بالوصول',
        'أرسل (1) لتأكيد استلام الإشعار',
        'نرجو الرد بكلمة (وصل) للتأكيد',
        'فضلاً أرسل (تم) ليصلنا تأكيدك',
        'للتأكيد يرجى الرد برقم (1)',
    ];

    /** محارف عرض صفري آمنة بين الكلمات (لا نضعها داخل الكلمة حتى لا يتكسر اتصال الحروف العربية) */
    private const ZERO_WIDTH = ["\u{200B}", "\u{200C}"];

    /**
     * رسالة إشعار وصول الطرد (تُستخدم في الطابور وفي الإعادة اليدوية).
     */
    public function composeArrival(Office $office, Parcel $parcel, WhatsAppService $wa): string
    {
        $greeting = $this->pick(self::GREETINGS);

        if (! empty($office->whatsapp_template)) {
            // يلتزم بالقالب فقط: إذا وضع المكتب {recipient} في القالب سيتم تعويضه، وإلا فلن يظهر الاسم
            $body = $wa->parseTemplate($office->whatsapp_template, $this->templateVariables($parcel, $office));
            $text = $greeting . "\n" . trim($body);
        } else {
            // في حال ترك القالب فارغاً، يُستخدم النص الافتراضي
            $recipient = trim((string) $parcel->recipient_name);
            $greetingLine = $recipient !== '' ? "{$greeting} {$recipient}" : $greeting;
            $text = $this->buildDefaultBody($office, $parcel, $greetingLine);
        }

        return $this->protect($text, true);
    }

    /**
     * حماية نص جاهز: سطر الرد (اختياري) + الرمز المرجعي + المحارف المخفية.
     */
    public function protect(string $text, bool $withReplyPrompt = false): string
    {
        $text = trim($text);

        if ($withReplyPrompt) {
            $text .= "\n\n" . $this->pick(self::REPLY_PROMPTS);
        }

        $text = $this->injectZeroWidth($text);

        return $text . "\n[م: " . random_int(1000, 9999) . ']';
    }

    /**
     * متغيرات قالب المكتب (تدعم جميع المتغيرات الظاهرة في لوحة التحكم).
     */
    public function templateVariables(Parcel $parcel, ?Office $office = null): array
    {
        $office = $office ?? $parcel->office;

        $package = $parcel->package_type ?: 'طرد';
        $receipt = $parcel->receipt_number ?? '';
        $recipient = trim((string) $parcel->recipient_name);
        $cod = (float) ($parcel->cod_amount ?? 0) > 0 ? number_format((float) $parcel->cod_amount) : '';

        return [
            '{package}'         => $package,
            '{package_type}'    => $package,
            '{office}'          => $office?->name ?? '',
            '{branch}'          => $office?->branch_name ?? '',
            '{receipt}'         => $receipt,
            '{receipt_number}'  => $receipt,
            '{recipient}'       => $recipient,
            '{recipient_name}'  => $recipient,
            '{cod_amount}'      => $cod,
        ];
    }

    private function buildDefaultBody(Office $office, Parcel $parcel, string $greetingLine): string
    {
        $lines = [$greetingLine];

        $lines[] = $this->pick(self::INTROS) . ' ' . ($parcel->package_type ?: 'طرد');

        if (! empty($parcel->receipt_number)) {
            $lines[] = 'رقم السند: ' . $parcel->receipt_number;
        }

        $officeLine = 'مكتب: ' . $office->name;
        if (! empty($office->branch_name)) {
            $officeLine .= ' (' . $office->branch_name . ')';
        }
        $lines[] = $officeLine;

        if ((float) ($parcel->cod_amount ?? 0) > 0) {
            $lines[] = 'المبلغ المطلوب عند الاستلام: ' . number_format((float) $parcel->cod_amount) . ' ر.ي';
        }

        $deliveryPhone = trim((string) ($office->getAttribute('delivery_service_phone') ?? ''));
        if ($deliveryPhone !== '') {
            $lines[] = $this->pick(self::DELIVERY_LINES) . ' ' . $deliveryPhone;
        }

        return implode("\n", $lines);
    }

    /**
     * حقن محارف غير مرئية عشوائياً عند حدود الكلمات العربية فقط.
     */
    public function injectZeroWidth(string $text): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $text);
        $injected = 0;

        foreach ($lines as $li => $line) {
            $tokens = explode(' ', $line);
            $count = count($tokens);

            for ($i = 0; $i < $count - 1; $i++) {
                if (! $this->isSafeArabicWord($tokens[$i]) || ! $this->isSafeArabicWord($tokens[$i + 1])) {
                    continue;
                }

                if (random_int(1, 100) <= 35) {
                    $tokens[$i] .= $this->pick(self::ZERO_WIDTH);
                    $injected++;
                }
            }

            $lines[$li] = implode(' ', $tokens);
        }

        if ($injected === 0) {
            foreach ($lines as $li => $line) {
                $tokens = explode(' ', $line);
                foreach ($tokens as $ti => $token) {
                    if ($ti < count($tokens) - 1 && $this->isSafeArabicWord($token) && $this->isSafeArabicWord($tokens[$ti + 1])) {
                        $tokens[$ti] .= $this->pick(self::ZERO_WIDTH);
                        $lines[$li] = implode(' ', $tokens);
                        break 2;
                    }
                }
            }
        }

        return implode("\n", $lines);
    }

    private function isSafeArabicWord(string $token): bool
    {
        if ($token === '' || ! preg_match('/\p{Arabic}/u', $token)) {
            return false;
        }

        if (preg_match('/[0-9\x{0660}-\x{0669}\x{06F0}-\x{06F9}]|https?:|www\.|@|\/|\[|\]/u', $token)) {
            return false;
        }

        return true;
    }

    private function pick(array $items): string
    {
        return $items[array_rand($items)];
    }
}